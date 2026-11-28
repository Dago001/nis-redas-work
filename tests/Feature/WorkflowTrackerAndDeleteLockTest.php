<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Services\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTrackerAndDeleteLockTest extends TestCase
{
    use RefreshDatabase;

    private function makeOfficer(string $category, string $locationType, string $scopeCode, string $serviceNo): User
    {
        return User::create([
            'name' => 'Test Officer ' . $serviceNo,
            'username' => $serviceNo,
            'email' => "officer{$serviceNo}@immigration.gov.ng",
            'password' => 'StrongPass#1234',
            'role' => $category === 'cgis_unit_user' ? 'unit_officer' : 'user',
            'user_category' => $category,
            'primary_location_type' => $locationType,
            'primary_location_code' => $scopeCode,
            'assigned_cgis_unit_code' => $category === 'cgis_unit_user' ? $scopeCode : null,
            'access_level' => 0,
            'email_verified_at' => now(),
            'mfa_enabled' => true,
            'must_change_password' => false,
            'is_active' => true,
        ]);
    }

    public function test_cgis_dashboard_shows_workflow_tracker_above_unit_info_and_locks_delete_after_approval(): void
    {
        $officer = $this->makeOfficer('cgis_unit_user', 'unit', 'provost', '77001');
        $deskAdmin = User::create([
            'name' => 'Provost Desk Admin',
            'username' => '77002',
            'email' => 'provost.admin@immigration.gov.ng',
            'password' => 'StrongPass#1234',
            'role' => 'unit_admin',
            'user_category' => 'cgis_desk_admin',
            'primary_location_type' => 'unit',
            'primary_location_code' => 'provost',
            'assigned_cgis_unit_code' => 'provost',
            'access_level' => 2,
            'email_verified_at' => now(),
            'mfa_enabled' => true,
            'must_change_password' => false,
            'is_active' => true,
        ]);
        $hqAdmin = User::create([
            'name' => 'HQ Approver',
            'username' => '77003',
            'email' => 'hq.approver@immigration.gov.ng',
            'password' => 'StrongPass#1234',
            'role' => 'admin',
            'user_category' => 'hq_admin',
            'primary_location_type' => 'headquarters',
            'primary_location_code' => 'SHQ',
            'access_level' => 5,
            'email_verified_at' => now(),
            'mfa_enabled' => true,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        // 1. Freshly submitted return (still at cgis_desk_review, no approval yet)
        $app = SubmissionWorkflow::create($officer, ['report_period' => '2026-11', 'introduction' => 'Test']);

        $res = $this->actingAs($officer)->get(route('user.cgis-units.dashboard'));
        $res->assertOk();
        $html = $res->getContent();

        $this->assertStringContainsString('Provost Unit Dashboard', $html);
        $this->assertStringNotContainsString('Provost Unit Unit Dashboard', $html);
        $this->assertLessThan(
            strpos($html, 'Unit Information'),
            strpos($html, 'Return Workflow'),
            'Return Workflow card must appear above Unit Information.'
        );
        $this->assertStringContainsString('CGIS Unit Desk Admin', $html);
        $this->assertStringContainsString('HQ Admin Approval', $html);
        // Before any approval, officer can edit/delete
        $this->assertTrue($officer->can('update', $app));
        $this->assertTrue($officer->can('delete', $app));
        $this->assertStringContainsString(route('user.cgis-units.submissions.destroy', $app), $html);

        // 2. Desk Admin approves -> advances to hq_review (still pending final HQ sign-off)
        SubmissionWorkflow::approve($app, $deskAdmin, 'Looks good');
        $app->refresh();

        $this->assertFalse($officer->can('delete', $app));
        $this->assertFalse($officer->can('update', $app));
        $this->actingAs($officer)
            ->delete(route('user.cgis-units.submissions.destroy', $app))
            ->assertForbidden();

        $afterFirstApprovalHtml = $this->actingAs($officer)
            ->get(route('user.cgis-units.dashboard'))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('name="_method" value="DELETE"', $afterFirstApprovalHtml);
        $this->assertStringNotContainsString('title="Update"', $afterFirstApprovalHtml);

        // 3. HQ Admin gives final approval -> still locked from delete/edit
        SubmissionWorkflow::approve($app, $hqAdmin, 'Final approval');
        $app->refresh();

        $this->assertSame('approved', strtolower((string) $app->status));
        $this->assertFalse($officer->can('delete', $app));
        $this->actingAs($officer)
            ->delete(route('user.cgis-units.submissions.destroy', $app))
            ->assertForbidden();
    }
}
