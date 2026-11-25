<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $directorate['name'] ?? $unit['name'] ?? 'State Command' }} Return - {{ $application->return_data['report_period'] ?? $application->return_data['period'] ?? '' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { background:#f3f4f6; color:#111827; font-family:'Inter',system-ui,sans-serif; margin:0; }
        .report-page { max-width:1020px; margin:16px auto 32px; background:#fff; padding:28px 32px; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.06); border:1px solid #e5e7eb; }

        /* Authoritative NIS Official Letterhead Banner */
        .nis-doc-banner {
            background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 60%, #f8fafc 100%);
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 22px 26px;
            margin-bottom: 20px;
        }
        .nis-doc-brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
            padding-bottom: 16px;
            border-bottom: 1px solid #dcfce7;
        }
        .nis-doc-brand-left {
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .nis-doc-brand img.logo {
            width: 74px;
            height: 74px;
            object-fit: contain;
            flex-shrink: 0;
        }
        .nis-doc-brand h1 {
            font-size: 1.65rem;
            font-weight: 800;
            margin: 0 0 4px;
            color: #006838;
            letter-spacing: .02em;
            text-transform: uppercase;
            line-height: 1.15;
        }
        .nis-doc-brand .subtitle {
            margin: 0;
            font-size: .82rem;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: #374151;
        }
        .nis-doc-meta-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 10px;
            margin-top: 16px;
        }
        @media (max-width: 820px) {
            .nis-doc-meta-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .nis-doc-brand h1 { font-size: 1.3rem; }
            .nis-doc-brand img.logo { width: 58px; height: 58px; }
        }
        .nis-doc-meta-item {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 9px 12px;
        }
        .nis-doc-meta-label {
            display: block;
            font-size: .66rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 3px;
        }
        .nis-doc-meta-value {
            display: block;
            font-size: .82rem;
            font-weight: 700;
            color: #111827;
            word-break: break-word;
        }

        .status-badge { display:inline-block; padding:5px 12px; border-radius:999px; font-size:.74rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
        .status-approved { background:#ecfdf5; color:#166534; border:1px solid #86efac; }
        .status-pending { background:#fef9c3; color:#854d0e; border:1px solid #fde047; }
        .status-returned { background:#fef2f2; color:#991b1b; border:1px solid #fca5a5; }

        .report-section {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 18px 20px 12px;
            margin: 0 0 18px;
            background: #fff;
            overflow-x: auto;
            scrollbar-width: thin;
            scrollbar-color: #94a3b8 #f1f5f9;
        }
        .report-section::-webkit-scrollbar { height: 7px; }
        .report-section::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
        .report-section::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 4px; }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: .98rem;
            font-weight: 700;
            color: #006838;
            margin: 0 0 14px;
            border-bottom: 2px solid #006838;
            padding-bottom: 8px;
            line-height: 1.3;
        }
        .section-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 24px;
            padding: 0 7px;
            border-radius: 6px;
            background: #006838;
            color: #fff;
            font-size: .74rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        .sub-section-title {
            font-size: .84rem;
            font-weight: 700;
            color: #1f2937;
            margin: 16px 0 8px;
            padding-left: 8px;
            border-left: 3px solid #006838;
        }

        table.report-table { width:100%; border-collapse:collapse; font-size:.8rem; margin-bottom:14px; background:#fff; }
        table.report-table th, table.report-table td { border:1px solid #d1d5db; padding:7px 10px; text-align:left; vertical-align:top; }
        table.report-table th { background:#f0fdf4; font-weight:700; color:#14532d; font-size:.73rem; text-transform:uppercase; letter-spacing:.02em; }
        table.report-table tbody tr:nth-child(even) td { background:#fafcfb; }
        table.report-table td.kv-key { width:34%; font-weight:600; background:#f9fafb !important; color:#374151; }
        table.report-table.kv-table td:last-child { white-space:pre-wrap; }
        table.report-table td.row-head { font-weight:600; background:#f9fafb !important; color:#1f2937; }

        .report-actions { display:flex; gap:10px; justify-content:flex-end; max-width:1020px; margin:16px auto 0; padding:0 4px; flex-wrap:wrap; }
        .report-actions a, .report-actions button {
            display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:8px;
            font-size:.84rem; font-weight:600; cursor:pointer; text-decoration:none; border:1px solid #d1d5db;
            background:#fff; color:#374151;
        }
        .report-actions a:hover, .report-actions button:hover { background:#f9fafb; }
        .report-actions button.print-btn { background:#006838; border-color:#006838; color:#fff; }
        .report-actions button.print-btn:hover { background:#00522c; }

        @page { size:A4; margin:14mm 12mm; }
        @media print {
            body { background:#fff; }
            .report-page { margin:0; max-width:none; box-shadow:none; border:none; border-radius:0; padding:0; }
            .report-actions { display:none !important; }
            .nis-doc-banner { background:#fff; border:1px solid #d1d5db; }
            .report-section { border:none; border-radius:0; padding:0; margin:0 0 16px; overflow:visible; }
            .section-title, .sub-section-title { break-after:avoid; }
            table.report-table thead { display:table-header-group; }
            table.report-table tr { break-inside:avoid; }
        }
    </style>
</head>

<body>
@php
    $data = is_array($application->return_data) ? $application->return_data : [];
    $skipKeys = [
        'directorate_slug', 'cgis_unit_slug', 'data_consent', 'report_period',
        'reporting_officer', 'command_name', 'period', 'return_type',
        'supporting_documents', 'attachments', '_idempotency_key', '_method', '_token',
    ];

    $isStateReturn = isset($data['command_name']) || $application->category === 'state';
    $directorateSlugForGrouping = $isStateReturn ? null : ($slug ?? null);

    $directorateNames = [];
    foreach (\App\Http\Controllers\Web\DashboardController::directorates() as $s => $meta) {
        $directorateNames[$s] = $meta['name'];
    }

    $refCode = 'RET-' . str_pad((string) $application->id, 5, '0', STR_PAD_LEFT);
    $scopeCode = strtoupper((string) ($application->scope_code ?? ''));
    $resolvedScopeName = $scopeCode !== ''
        ? (\App\Services\GeolocationService::stateNameFromCode($scopeCode) ?? $scopeCode)
        : ($data['command_name'] ?? 'Command');
    $commandDisplay = $resolvedScopeName !== $scopeCode && $scopeCode !== ''
        ? "{$resolvedScopeName} ({$scopeCode})"
        : $resolvedScopeName;

    $sourceTitle = $directorate['name']
        ?? $unit['name']
        ?? ($isStateReturn ? "{$commandDisplay} - Combined Return" : 'Monthly Return');

    $periodRaw = $data['report_period'] ?? $data['period'] ?? $application->report_period ?? null;
    $periodLabel = $periodRaw ?: '-';
    if (is_string($periodRaw) && preg_match('/^\d{4}-\d{2}$/', $periodRaw)) {
        try {
            $periodLabel = \Carbon\Carbon::createFromFormat('Y-m', $periodRaw)->format('F Y');
        } catch (\Throwable $e) {
            $periodLabel = $periodRaw;
        }
    }

    $typeLabels = [
        'monthly'   => 'Monthly Return',
        'quarterly' => 'Quarterly Return',
        'biannual'  => 'Bi-Annual Return',
        'annual'    => 'Annual Return',
        'special'   => 'Special Report',
    ];
    $returnTypeLabel = $typeLabels[strtolower((string) ($data['return_type'] ?? 'monthly'))]
        ?? ucfirst((string) ($data['return_type'] ?? 'Monthly Return'));

    $status = strtolower((string) ($application->status ?? 'pending'));
    $statusClass = match (true) {
        $status === 'approved' => 'status-approved',
        in_array($status, ['rejected', 'queried', 'returned'], true) => 'status-returned',
        default => 'status-pending',
    };

    $viewerCategory = auth()->user()?->user_category;
    $dashboardRoute = match ($viewerCategory) {
        'state_user', 'zonal_officer' => 'user.submissions',
        'cgis_unit_user' => 'user.cgis-units.dashboard',
        default => 'user.directorates.dashboard',
    };
    $documentRoute = match ($viewerCategory) {
        'state_user', 'zonal_officer' => 'user.returns.document',
        'cgis_unit_user' => 'user.cgis-units.submissions.document',
        default => 'user.directorates.submissions.document',
    };

    $sections = \App\Services\PreviewRenderer::buildSections($data, [
        'skipKeys' => $skipKeys,
        'directorateSlug' => $directorateSlugForGrouping,
        'directorateNames' => $directorateNames,
        'groupByDirectorate' => $isStateReturn,
    ]);
@endphp

<div class="report-actions">
    <a href="{{ route($dashboardRoute) }}">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
    <a href="{{ route('user.submissions.pdf', $application) }}">
        <i class="fas fa-file-pdf"></i> Download PDF
    </a>
    <button type="button" class="print-btn" onclick="window.print()">
        <i class="fas fa-print"></i> Print
    </button>
</div>

<div class="report-page" data-preview-paginator>
    @include('partials.preview-pagination')

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

    <div class="nis-doc-banner">
        <div class="nis-doc-brand">
            <div class="nis-doc-brand-left">
                <img class="logo" src="{{ asset('nis-logo.png') }}" alt="NIS Logo">
                <div>
                    <h1>Nigeria Immigration Service</h1>
                    <p class="subtitle">{{ $sourceTitle }} &bull; {{ $returnTypeLabel }}</p>
                </div>
            </div>
            <div>
                <span class="status-badge {{ $statusClass }}">{{ ucfirst($application->status ?? 'pending') }}</span>
            </div>
        </div>
        <div class="nis-doc-meta-grid">
            <div class="nis-doc-meta-item">
                <span class="nis-doc-meta-label">Reference</span>
                <span class="nis-doc-meta-value">{{ $refCode }}</span>
            </div>
            <div class="nis-doc-meta-item">
                <span class="nis-doc-meta-label">Command / Scope</span>
                <span class="nis-doc-meta-value">{{ $commandDisplay }}</span>
            </div>
            <div class="nis-doc-meta-item">
                <span class="nis-doc-meta-label">Report Period</span>
                <span class="nis-doc-meta-value">{{ $periodLabel }}</span>
            </div>
            <div class="nis-doc-meta-item">
                <span class="nis-doc-meta-label">Reporting Officer</span>
                <span class="nis-doc-meta-value">{{ $data['reporting_officer'] ?? optional($application->user)->name ?? '-' }}</span>
            </div>
            <div class="nis-doc-meta-item">
                <span class="nis-doc-meta-label">Submitted</span>
                <span class="nis-doc-meta-value">{{ $application->created_at?->format('d M Y, H:i') ?? '-' }}</span>
            </div>
        </div>
    </div>

    @foreach($sections as $index => $section)
    <section class="report-section preview-section {{ $loop->first ? 'active' : '' }}" data-label="{{ $section['label'] }}">
        <h2 class="section-title"><span class="section-num">{{ $index + 1 }}</span> {{ $section['label'] }}</h2>
        {!! $section['html'] ?: '<p class="preview-section-empty">No data entered for this section.</p>' !!}
    </section>
    @endforeach

    @include('partials.document-viewer', ['docRoute' => $documentRoute])

    @php
        $commentHistory = $application->relationLoaded('reviewComments') ? $application->reviewComments : collect();
    @endphp
    @if($commentHistory->isNotEmpty())
    <section class="report-section preview-section" data-label="Review History">
        <h2 class="section-title"><span class="section-num"><i class="fas fa-clock-rotate-left" style="font-size:.6rem;"></i></span> Review History</h2>
        <div style="display:flex;flex-direction:column;gap:10px;padding-bottom:8px;">
            @foreach($commentHistory as $entry)
                @php
                    [$badgeBg, $badgeColor, $badgeIcon] = match ($entry->action) {
                        'approved' => ['#ecfdf5', '#15803d', 'fa-check'],
                        'rejected' => ['#fef2f2', '#b91c1c', 'fa-undo'],
                        'resubmitted' => ['#eff6ff', '#1d4ed8', 'fa-paper-plane'],
                        'submitted' => ['#eff6ff', '#1d4ed8', 'fa-inbox'],
                        default => ['#f8fafc', '#475569', 'fa-comment'],
                    };
                @endphp
                <div style="display:flex;gap:10px;align-items:flex-start;padding:8px 10px;border-radius:8px;background:#f8fafc;border:1px solid #e5e7eb;">
                    <span style="flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:999px;background:{{ $badgeBg }};color:{{ $badgeColor }};font-size:.72rem;"><i class="fas {{ $badgeIcon }}"></i></span>
                    <div style="flex:1;">
                        <div style="font-size:.8rem;color:#1f2937;">
                            <strong>{{ optional($entry->user)->name ?? 'System' }}</strong>
                            <span style="color:#6b7280;">· {{ ucfirst($entry->action) }} at {{ str_replace('_', ' ', $entry->stage) }} · {{ optional($entry->created_at)->format('d M Y, H:i') }}</span>
                        </div>
                        @if($entry->comment)
                            <div style="font-size:.84rem;color:#374151;margin-top:3px;">{{ $entry->comment }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>
    @endif
</div>

@if($isPrint)
<script>
    window.addEventListener('load', function () {
        window.print();
    });
</script>
@endif
</body>
</html>
