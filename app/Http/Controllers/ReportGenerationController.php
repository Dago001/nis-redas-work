<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportJob;
use App\Models\GeneratedReport;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Files\SecureFileStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportGenerationController extends Controller
{
    public function index(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $recentReports = GeneratedReport::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(20)
            ->get();

        return view('user.states.reports', [
            'recentReports' => $recentReports,
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'report_type' => ['required', 'in:submission_summary,monthly_return,quarterly_return,annual_return,compliance_report,full_export'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'sections' => ['nullable', 'array'],
            'sections.*' => ['string', 'max:50'],
            'format' => ['required', 'in:pdf,excel,csv'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $report = GeneratedReport::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'report_type' => $validated['report_type'],
            'format' => $validated['format'],
            'filters' => [
                'date_from' => $validated['date_from'],
                'date_to' => $validated['date_to'],
                'sections' => array_values($validated['sections'] ?? []),
            ],
            'status' => 'queued',
        ]);

        GenerateReportJob::dispatch($report->id);

        $report->refresh();

        if ($report->status === 'completed') {
            return redirect()->route('user.reports.download', $report);
        }

        if ($report->status === 'failed') {
            return redirect()
                ->route('user.reports')
                ->with('error', 'Report generation failed: '.($report->error ?: 'Unknown error.'));
        }

        return redirect()
            ->route('user.reports')
            ->with('status', 'Your report has been queued for generation. You will be notified once it is ready for download.');
    }

    public function download(GeneratedReport $generatedReport): StreamedResponse
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($generatedReport->user_id === $user->id, 403, 'You are not authorized to download this report.');
        abort_unless($generatedReport->status === 'completed' && is_string($generatedReport->stored_path), 404, 'Report file is not ready.');

        $safePath = SecureFileStorage::safePath($generatedReport->stored_path);
        abort_if($safePath === null, 404);

        AuditLogger::log(
            user: $user,
            action: 'report.downloaded',
            status: 'success',
            entityType: 'generated_report',
            entityId: $generatedReport->id,
            details: [
                'uuid' => $generatedReport->uuid,
                'report_type' => $generatedReport->report_type,
                'format' => $generatedReport->format,
            ]
        );

        $ext = match ($generatedReport->format) {
            'excel' => 'xlsx',
            'csv' => 'csv',
            default => 'pdf',
        };
        $filename = sprintf('nis-redas-%s-%s.%s', $generatedReport->report_type, $generatedReport->created_at->format('Ymd-His'), $ext);

        return SecureFileStorage::streamResponse($safePath, $filename, forceDownload: true);
    }
}
