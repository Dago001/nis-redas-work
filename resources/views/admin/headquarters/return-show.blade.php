@extends($layout ?? 'admin.headquarters.layout')

@section('title', 'Return Detail')

@section('content')

@php
    $data = is_array($application->return_data) ? $application->return_data : [];
    $skipKeys = [
        'directorate_slug', 'cgis_unit_slug', 'data_consent', 'report_period',
        'reporting_officer', 'command_name', 'period', 'return_type',
        'supporting_documents', 'attachments', '_idempotency_key', '_method', '_token',
    ];
    $statusLower = strtolower((string) ($application->status ?? 'pending'));
    $badge = match ($statusLower) {
        'approved' => 'badge-approved',
        'returned', 'rejected' => 'badge-rejected',
        default => 'badge-pending',
    };
    $backRoute = $backRoute ?? 'admin.hq.returns';
    $documentRoute = $documentRoute ?? 'admin.hq.returns.document';
    $commentHistory = $application->relationLoaded('reviewComments') ? $application->reviewComments : collect();

    $directorateNames = [];
    foreach (\App\Http\Controllers\Web\DashboardController::directorates() as $s => $meta) {
        $directorateNames[$s] = $meta['name'];
    }

    $refCode = 'RET-' . str_pad((string) $application->id, 5, '0', STR_PAD_LEFT);
    $scopeCode = strtoupper((string) ($application->scope_code ?? ''));
    $resolvedScope = $scopeCode !== ''
        ? (\App\Services\GeolocationService::stateNameFromCode($scopeCode) ?? $scopeCode)
        : ($data['command_name'] ?? '-');
    $scopeDisplay = ($resolvedScope !== $scopeCode && $scopeCode !== '')
        ? "{$resolvedScope} ({$scopeCode})"
        : $resolvedScope;

    $periodRaw = $data['report_period'] ?? $data['period'] ?? $application->report_period ?? null;
    $periodLabel = $periodRaw ?: '-';
    if (is_string($periodRaw) && preg_match('/^\d{4}-\d{2}$/', $periodRaw)) {
        try {
            $periodLabel = \Carbon\Carbon::createFromFormat('Y-m', $periodRaw)->format('F Y');
        } catch (\Throwable $e) {
            $periodLabel = $periodRaw;
        }
    }

    $isZonalOfficerSubmitter = ($application->user?->user_category === 'zonal_officer')
        || (str_starts_with($scopeCode, 'ZONE-'));

    // Build canonical end-to-end Process Flow pipeline stages for this return
    $pipelineStages = match ($application->category) {
        'directorate' => [
            ['key' => 'submitted',          'label' => 'Officer Submitted',        'next' => 'directorate_review'],
            ['key' => 'directorate_review', 'label' => 'Directorate Admin Review', 'next' => 'hq_review'],
            ['key' => 'hq_review',          'label' => 'HQ Admin Approval',        'next' => 'approved'],
            ['key' => 'approved',           'label' => 'Final Approved',           'next' => null],
        ],
        'cgis' => [
            ['key' => 'submitted',        'label' => 'Unit Officer Submitted', 'next' => 'cgis_desk_review'],
            ['key' => 'cgis_desk_review', 'label' => 'CGIS Desk Review',       'next' => 'hq_review'],
            ['key' => 'hq_review',        'label' => 'HQ Admin Approval',      'next' => 'approved'],
            ['key' => 'approved',         'label' => 'Final Approved',         'next' => null],
        ],
        default => $isZonalOfficerSubmitter
            ? [
                ['key' => 'submitted',    'label' => 'Zonal Officer Submitted', 'next' => 'zonal_review'],
                ['key' => 'zonal_review', 'label' => 'Zonal Admin Review',      'next' => 'hq_review'],
                ['key' => 'hq_review',    'label' => 'HQ Admin Approval',       'next' => 'approved'],
                ['key' => 'approved',     'label' => 'Final Approved',          'next' => null],
            ]
            : [
                ['key' => 'submitted',    'label' => 'Command Officer Submitted', 'next' => 'desk_review'],
                ['key' => 'desk_review',  'label' => 'Desk Admin Review',         'next' => 'zonal_review'],
                ['key' => 'zonal_review', 'label' => 'Zonal Admin Review',        'next' => 'hq_review'],
                ['key' => 'hq_review',    'label' => 'HQ Admin Approval',         'next' => 'approved'],
                ['key' => 'approved',     'label' => 'Final Approved',            'next' => null],
            ],
    };

    // Map raw workflow_path entries to the stage that was ACTED ON
    $rawPath = is_array($application->workflow_path) ? $application->workflow_path : [];
    $actorIds = collect($rawPath)->pluck('by')->filter()->unique()->values()->all();
    $actorsById = $actorIds !== [] ? \App\Models\User::whereIn('id', $actorIds)->pluck('name', 'id')->all() : [];

    // Map destination stage -> the stage that approved & advanced into it
    $prevStageByNext = [];
    foreach ($pipelineStages as $st) {
        if ($st['next'] !== null) {
            $prevStageByNext[$st['next']] = $st['key'];
        }
    }

    $stageActivity = [];
    foreach ($rawPath as $entry) {
        $entryStage = (string) ($entry['stage'] ?? '');
        $entryAction = strtolower((string) ($entry['action'] ?? ($entryStage === 'submitted' ? 'submitted' : 'approved')));
        $actorName = isset($entry['by']) ? ($actorsById[$entry['by']] ?? 'Officer') : 'System';
        $at = $entry['at'] ?? null;

        if ($entryStage === 'submitted' || $entryAction === 'resubmitted') {
            $stageActivity['submitted'] = ['action' => $entryAction, 'by' => $actorName, 'at' => $at];
        } elseif ($entryAction === 'rejected' || $entryAction === 'returned') {
            $stageActivity[$entryStage] = ['action' => 'returned', 'by' => $actorName, 'at' => $at];
        } elseif (isset($prevStageByNext[$entryStage])) {
            $completedStage = $prevStageByNext[$entryStage];
            $stageActivity[$completedStage] = ['action' => 'approved', 'by' => $actorName, 'at' => $at];
            if ($entryStage === 'approved') {
                $stageActivity['approved'] = ['action' => 'approved', 'by' => $actorName, 'at' => $at];
            }
        }
    }

    $stageOrderKeys = array_column($pipelineStages, 'key');
    $currentStageKey = $statusLower === 'approved'
        ? 'approved'
        : (string) ($application->workflow_stage ?? 'desk_review');
    $currentIdx = array_search($currentStageKey, $stageOrderKeys, true);
    if ($currentIdx === false) {
        $currentIdx = 1;
    }

    $currentStageDisplay = match ($currentStageKey) {
        'desk_review' => 'Desk Admin Review',
        'zonal_review' => 'Zonal Admin Review',
        'directorate_review' => 'Directorate Admin Review',
        'cgis_desk_review' => 'CGIS Desk Review',
        'hq_review' => 'HQ Admin Approval',
        'approved' => 'Final Approved',
        default => ucwords(str_replace('_', ' ', $currentStageKey)),
    };

    $sections = \App\Services\PreviewRenderer::buildSections($data, [
        'skipKeys' => $skipKeys,
        'directorateNames' => $directorateNames,
        'groupByDirectorate' => in_array($application->category, ['state', 'zone'], true),
    ]);
@endphp

<style>
    .sa-return-grid {
        display: grid;
        grid-template-columns: minmax(0, 2.2fr) minmax(300px, 1fr);
        gap: 20px;
        align-items: start;
    }
    @media (max-width: 1100px) {
        .sa-return-grid { grid-template-columns: 1fr; }
    }

    /* Properly styled PreviewRenderer tables & subsections */
    .sa-section-body {
        padding: 18px 20px;
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: #94a3b8 #f1f5f9;
    }
    .sa-section-body::-webkit-scrollbar { height: 7px; }
    .sa-section-body::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
    .sa-section-body::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 4px; }
    .sa-section-body .sub-section-title {
        font-size: .84rem;
        font-weight: 700;
        color: #1f2937;
        margin: 16px 0 8px;
        padding-left: 9px;
        border-left: 3px solid #006838;
    }
    .sa-section-body .sub-section-title:first-child { margin-top: 0; }
    .sa-section-body table.report-table {
        width: 100%;
        border-collapse: collapse;
        font-size: .8rem;
        margin-bottom: 14px;
        background: #fff;
    }
    .sa-section-body table.report-table th,
    .sa-section-body table.report-table td {
        border: 1px solid #d1d5db;
        padding: 7px 10px;
        text-align: left;
        vertical-align: top;
    }
    .sa-section-body table.report-table th {
        background: #f0fdf4;
        font-weight: 700;
        color: #14532d;
        font-size: .73rem;
        text-transform: uppercase;
        letter-spacing: .02em;
    }
    .sa-section-body table.report-table tbody tr:nth-child(even) td {
        background: #fafcfb;
    }
    .sa-section-body table.report-table td.kv-key {
        width: 34%;
        font-weight: 600;
        background: #f9fafb !important;
        color: #374151;
    }
    .sa-section-body table.report-table.kv-table td:last-child { white-space: pre-wrap; }
    .sa-section-body table.report-table td.row-head {
        font-weight: 600;
        background: #f9fafb !important;
        color: #1f2937;
        white-space: nowrap;
    }

    /* Process Flow Pipeline */
    .pf-step {
        display: flex;
        gap: 12px;
        position: relative;
        padding-bottom: 18px;
    }
    .pf-step:last-child { padding-bottom: 0; }
    .pf-line {
        position: absolute;
        left: 11px;
        top: 24px;
        bottom: 0;
        width: 2px;
        background: #e2e8f0;
    }
    .pf-step.done .pf-line { background: #16a34a; }
    .pf-icon {
        width: 24px;
        height: 24px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .68rem;
        font-weight: 700;
        flex-shrink: 0;
        position: relative;
        z-index: 1;
        margin-top: 1px;
    }
    .pf-step.done .pf-icon { background: #16a34a; color: #fff; }
    .pf-step.current .pf-icon { background: #f59e0b; color: #fff; box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.2); }
    .pf-step.returned .pf-icon { background: #dc2626; color: #fff; box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.16); }
    .pf-step.upcoming .pf-icon { background: #e2e8f0; color: #64748b; }
    .pf-badge {
        display: inline-block;
        padding: 1px 7px;
        border-radius: 999px;
        font-size: .66rem;
        font-weight: 700;
        margin-left: 6px;
        vertical-align: middle;
    }
    .pf-badge-done { background: #dcfce7; color: #166534; }
    .pf-badge-current { background: #fef3c7; color: #92400e; }
    .pf-badge-returned { background: #fee2e2; color: #991b1b; }
    .pf-badge-upcoming { background: #f1f5f9; color: #64748b; }
</style>

<main class="redas-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Return {{ $refCode }}</h1>
            <p class="page-subtitle">{{ $scopeDisplay }} &bull; Submitted {{ optional($application->created_at)->format('d M Y, H:i') }} &bull; <strong>View-only</strong> governance record.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <span class="status-badge {{ $badge }}">{{ ucfirst($application->status ?? 'pending') }}</span>
            <a href="{{ route('user.submissions.pdf', $application) }}" class="btn-nis btn-outline-nis" target="_blank">
                <i class="fas fa-file-pdf"></i> PDF
            </a>
            <a href="{{ route($backRoute) }}" class="btn-nis btn-ghost">
                <i class="fas fa-arrow-left"></i> Back to Returns
            </a>
        </div>
    </div>

    <div class="sa-return-grid">
        <div data-preview-paginator>
            @include('partials.preview-pagination')

            <div class="redas-card" style="margin-bottom:18px;">
                <div class="card-head">
                    <div class="card-head-title">
                        <div class="card-head-icon" style="background:#e0f2fe;color:#0369a1;">
                            <i class="fas fa-circle-info"></i>
                        </div>
                        Return Summary
                    </div>
                </div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:10px;">
                        <div style="border:1px solid var(--gray-200);border-radius:10px;padding:10px 12px;">
                            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gray-500);margin-bottom:2px;">Reporting Officer</div>
                            <div style="font-weight:700;font-size:.86rem;color:var(--gray-900);">{{ $data['reporting_officer'] ?? $application->user->name ?? '-' }}</div>
                        </div>
                        <div style="border:1px solid var(--gray-200);border-radius:10px;padding:10px 12px;">
                            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gray-500);margin-bottom:2px;">Service Number</div>
                            <div style="font-weight:700;font-size:.86rem;color:var(--gray-900);">{{ $application->user->service_number ?? '-' }}</div>
                        </div>
                        <div style="border:1px solid var(--gray-200);border-radius:10px;padding:10px 12px;">
                            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gray-500);margin-bottom:2px;">Report Period</div>
                            <div style="font-weight:700;font-size:.86rem;color:var(--gray-900);">{{ $periodLabel }}</div>
                        </div>
                        <div style="border:1px solid var(--gray-200);border-radius:10px;padding:10px 12px;">
                            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gray-500);margin-bottom:2px;">Command / Scope</div>
                            <div style="font-weight:700;font-size:.86rem;color:var(--gray-900);">{{ $scopeDisplay }}</div>
                        </div>
                        <div style="border:1px solid var(--gray-200);border-radius:10px;padding:10px 12px;">
                            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gray-500);margin-bottom:2px;">Current Stage</div>
                            <div style="font-weight:700;font-size:.86rem;color:#006838;">{{ $currentStageDisplay }}</div>
                        </div>
                        <div style="border:1px solid var(--gray-200);border-radius:10px;padding:10px 12px;">
                            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gray-500);margin-bottom:2px;">{{ $application->category === 'cgis' ? 'CGIS Unit' : 'Category' }}</div>
                            <div style="font-weight:700;font-size:.86rem;color:var(--gray-900);">{{ $application->category === 'cgis' ? ($application->user->assigned_cgis_unit_code ?? '-') : $application->displayCategory() }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="preview-paginator">
                <div class="preview-paginator-top">
                    <div class="preview-paginator-title preview-counter">Section 1 of {{ count($sections) }}</div>
                    <div class="preview-paginator-controls">
                        <button type="button" class="preview-prev-btn" disabled><i class="fas fa-arrow-left"></i> Previous</button>
                        <button type="button" class="preview-next-btn">Next <i class="fas fa-arrow-right"></i></button>
                        <button type="button" class="preview-all-btn">Show all</button>
                    </div>
                </div>
                <div class="preview-section-tabs"></div>
            </div>

            @forelse($sections as $index => $section)
            <div class="redas-card preview-section {{ $loop->first ? 'active' : '' }}" style="margin-bottom:18px;" data-label="{{ $section['label'] }}">
                <div class="card-head">
                    <div class="card-head-title">
                        <div class="card-head-icon" style="background:#dcfce7;color:#15803d;">
                            <span style="font-weight:800;font-size:.78rem;">{{ $index + 1 }}</span>
                        </div>
                        {{ $section['label'] }}
                    </div>
                </div>
                <div class="card-body no-pad">
                    <div class="sa-section-body">
                        @if($section['html'])
                            {!! $section['html'] !!}
                        @else
                            <p style="margin:0;color:#64748b;font-size:.84rem;">No data entered for this section.</p>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <div class="redas-card preview-section active" style="margin-bottom:18px;" data-label="No Data">
                <div class="card-body">
                    <p style="padding:12px;color:#64748b;margin:0;">No return data is available to preview.</p>
                </div>
            </div>
            @endforelse

            @include('partials.document-viewer', ['docRoute' => $documentRoute])
        </div>

        <div>
            {{-- Canonical End-to-End Process Flow Tracker --}}
            <div class="redas-card" style="margin-bottom:18px;">
                <div class="card-head">
                    <div class="card-head-title">
                        <div class="card-head-icon" style="background:#dcfce7;color:#15803d;">
                            <i class="fas fa-diagram-project"></i>
                        </div>
                        Process Flow
                    </div>
                </div>
                <div class="card-body">
                    <div>
                        @foreach($pipelineStages as $sIdx => $stage)
                            @php
                                $sKey = $stage['key'];
                                $act = $stageActivity[$sKey] ?? null;

                                if ($statusLower === 'approved') {
                                    $state = 'done';
                                } elseif ($statusLower === 'returned' && $sIdx === $currentIdx) {
                                    $state = 'returned';
                                } elseif ($sIdx < $currentIdx) {
                                    $state = 'done';
                                } elseif ($sIdx === $currentIdx) {
                                    $state = 'current';
                                } else {
                                    $state = 'upcoming';
                                }

                                $iconHtml = match ($state) {
                                    'done' => '<i class="fas fa-check"></i>',
                                    'current' => '<i class="fas fa-clock"></i>',
                                    'returned' => '<i class="fas fa-undo"></i>',
                                    default => (string) ($sIdx + 1),
                                };
                                [$badgeCls, $badgeLabel] = match ($state) {
                                    'done' => ['pf-badge-done', 'Completed'],
                                    'current' => ['pf-badge-current', 'In Progress'],
                                    'returned' => ['pf-badge-returned', 'Returned'],
                                    default => ['pf-badge-upcoming', 'Pending'],
                                };
                            @endphp
                            <div class="pf-step {{ $state }}">
                                @unless($loop->last)
                                    <div class="pf-line"></div>
                                @endunless
                                <span class="pf-icon">{!! $iconHtml !!}</span>
                                <div style="min-width:0;flex:1;">
                                    <div style="font-size:.83rem;font-weight:700;color:var(--gray-900);line-height:1.3;">
                                        {{ $stage['label'] }}
                                        <span class="pf-badge {{ $badgeCls }}">{{ $badgeLabel }}</span>
                                    </div>
                                    @if($act)
                                        <div style="font-size:.75rem;color:var(--gray-600);margin-top:2px;">
                                            {{ ucfirst($act['action']) }} by <strong>{{ $act['by'] }}</strong>
                                        </div>
                                        @if(!empty($act['at']))
                                            <div style="font-size:.71rem;color:var(--gray-400);margin-top:1px;">
                                                {{ \Illuminate\Support\Carbon::parse($act['at'])->format('d M Y, H:i') }}
                                            </div>
                                        @endif
                                    @elseif($state === 'current')
                                        <div style="font-size:.74rem;color:#92400e;margin-top:2px;">
                                            Awaiting action at this stage
                                        </div>
                                    @elseif($state === 'upcoming')
                                        <div style="font-size:.74rem;color:var(--gray-400);margin-top:2px;">
                                            Not yet reached
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Reviewer Comments / Audit Trail --}}
            <div class="redas-card">
                <div class="card-head">
                    <div class="card-head-title">
                        <div class="card-head-icon" style="background:#fee2e2;color:#b91c1c;">
                            <i class="fas fa-comment-dots"></i>
                        </div>
                        Reviewer Comments &amp; Activity
                    </div>
                </div>
                <div class="card-body">
                    @if($commentHistory->isNotEmpty())
                        <div style="display:flex;flex-direction:column;gap:10px;">
                            @foreach($commentHistory as $entry)
                                @php
                                    [$badgeBg, $badgeColor, $badgeIcon] = match ($entry->action) {
                                        'approved' => ['#ecfdf5', '#15803d', 'fa-check'],
                                        'rejected' => ['#fef2f2', '#b91c1c', 'fa-undo'],
                                        'resubmitted', 'submitted' => ['#eff6ff', '#1d4ed8', 'fa-paper-plane'],
                                        default => ['#f8fafc', '#475569', 'fa-comment'],
                                    };
                                @endphp
                                <div style="display:flex;gap:10px;align-items:flex-start;padding:8px 10px;border-radius:8px;background:#f8fafc;border:1px solid #e5e7eb;">
                                    <span style="flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:999px;background:{{ $badgeBg }};color:{{ $badgeColor }};font-size:.7rem;"><i class="fas {{ $badgeIcon }}"></i></span>
                                    <div style="flex:1;min-width:0;">
                                        <div style="font-size:.78rem;color:var(--gray-800);line-height:1.35;">
                                            <strong>{{ optional($entry->user)->name ?? 'System' }}</strong>
                                            <span style="color:var(--gray-500);">· {{ ucfirst($entry->action) }} at {{ ucwords(str_replace('_', ' ', (string) $entry->stage)) }}</span>
                                        </div>
                                        <div style="font-size:.71rem;color:var(--gray-400);margin-top:1px;">{{ optional($entry->created_at)->format('d M Y, H:i') }}</div>
                                        @if($entry->comment)
                                            <div style="font-size:.82rem;color:var(--gray-700);margin-top:4px;">{{ $entry->comment }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @elseif(filled($application->comments))
                        <div style="padding:12px;border:1px solid #fde68a;background:#fffbeb;border-radius:10px;color:#92400e;font-size:.84rem;">
                            {{ $application->comments }}
                        </div>
                    @else
                        <p style="font-size:.84rem;color:var(--gray-500);margin:0;">No comments have been added to this return.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</main>

@endsection
