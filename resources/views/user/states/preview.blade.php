<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>State Command Return - Preview</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { background:#f3f4f6; color:#111827; font-family:'Inter',system-ui,sans-serif; margin:0; }
        .report-page { max-width:980px; margin:20px auto 32px; background:#fff; padding:28px 32px; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.06); border:1px solid #e5e7eb; }

        /* Official NIS Letterhead Banner (isolated from dashboard.css .report-header) */
        .nis-preview-banner {
            background:linear-gradient(135deg, #f0fdf4 0%, #ffffff 60%, #f8fafc 100%);
            border:1px solid #bbf7d0;
            border-radius:12px;
            padding:20px 24px;
            margin-bottom:20px;
        }
        .nis-preview-brand {
            display:flex;
            align-items:center;
            gap:16px;
            padding-bottom:16px;
            border-bottom:1px solid #dcfce7;
        }
        .nis-preview-brand img.logo {
            width:62px;
            height:62px;
            object-fit:contain;
            flex-shrink:0;
        }
        .nis-preview-brand-text h1 {
            font-size:1.35rem;
            font-weight:800;
            margin:0 0 3px;
            color:#006838;
            letter-spacing:-.01em;
            line-height:1.2;
        }
        .nis-preview-brand-text .subtitle {
            margin:0;
            font-size:.78rem;
            font-weight:700;
            letter-spacing:.08em;
            text-transform:uppercase;
            color:#4b5563;
        }
        .nis-preview-meta-grid {
            display:grid;
            grid-template-columns:repeat(5, minmax(0, 1fr));
            gap:10px;
            margin-top:16px;
        }
        @media (max-width: 768px) {
            .nis-preview-meta-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); }
        }
        .nis-preview-meta-item {
            background:#fff;
            border:1px solid #e5e7eb;
            border-radius:8px;
            padding:9px 12px;
        }
        .nis-preview-meta-label {
            display:block;
            font-size:.66rem;
            font-weight:700;
            letter-spacing:.06em;
            text-transform:uppercase;
            color:#6b7280;
            margin-bottom:3px;
        }
        .nis-preview-meta-value {
            display:block;
            font-size:.82rem;
            font-weight:700;
            color:#111827;
            word-break:break-word;
        }

        .report-section { border:1px solid #e5e7eb; border-radius:10px; padding:16px 18px 10px; margin:0 0 18px; background:#fff; overflow-x:auto; }
        .section-title { display:flex; align-items:center; gap:8px; font-size:.95rem; font-weight:700; color:#006838; margin:0 0 12px; border-bottom:2px solid #006838; padding-bottom:6px; }
        .section-num { display:inline-flex; align-items:center; justify-content:center; min-width:22px; height:22px; padding:0 6px; border-radius:6px; background:#006838; color:#fff; font-size:.72rem; font-weight:700; }
        .sub-section-title { font-size:.84rem; font-weight:700; color:#374151; margin:14px 0 6px; }

        table.report-table { width:100%; border-collapse:collapse; font-size:.8rem; margin-bottom:12px; }
        table.report-table th, table.report-table td { border:1px solid #d1d5db; padding:6px 10px; text-align:left; vertical-align:top; }
        table.report-table th { background:#f0fdf4; font-weight:700; color:#14532d; }
        table.report-table td.kv-key { width:34%; font-weight:600; background:#f9fafb; color:#374151; }
        table.report-table.kv-table td:last-child { white-space:pre-wrap; }
        table.report-table td.row-head { font-weight:600; background:#f9fafb; }

        .preview-note {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            flex-wrap:wrap;
            background:#fef9c3;
            border:1px solid #fde047;
            color:#854d0e;
            border-radius:10px;
            padding:10px 16px;
            font-size:.82rem;
            max-width:980px;
            margin:16px auto 0;
        }
        .preview-note-actions { display:flex; gap:8px; flex-shrink:0; }
        .preview-note-btn {
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:6px 12px;
            border-radius:7px;
            border:1px solid #d97706;
            background:#fff;
            color:#92400e;
            font-size:.76rem;
            font-weight:700;
            cursor:pointer;
        }
        .preview-note-btn:hover { background:#fffbeb; }

        @page { size:A4; margin:14mm 12mm; }
        @media print {
            body { background:#fff; }
            .report-page { margin:0; max-width:none; box-shadow:none; border:none; border-radius:0; padding:0; }
            .preview-note { display:none !important; }
            .nis-preview-banner { background:#fff; border:1px solid #d1d5db; }
            .report-section { border:none; border-radius:0; padding:0; margin:0 0 16px; overflow:visible; }
            .section-title, .sub-section-title { break-after:avoid; }
            table.report-table thead { display:table-header-group; }
            table.report-table tr { break-inside:avoid; }
        }
    </style>
</head>

<body>
@php
    $data = is_array($previewData ?? null) ? $previewData : [];
    $skipKeys = ['command_name', 'period', 'return_type', 'report_period', 'reporting_officer', 'data_consent', 'supporting_documents', 'attachments', '_idempotency_key', '_method', '_token'];

    $directorateNames = [];
    foreach (\App\Http\Controllers\Web\DashboardController::directorates() as $slug => $meta) {
        $directorateNames[$slug] = $meta['name'];
    }

    $typeLabels = [
        'monthly'   => 'Monthly Return',
        'quarterly' => 'Quarterly Return',
        'biannual'  => 'Bi-Annual Return',
        'annual'    => 'Annual Return',
        'special'   => 'Special Report',
    ];

    $periodRaw = $data['period'] ?? null;
    $periodLabel = $periodRaw ?: '-';
    if ($periodRaw) {
        try {
            $periodLabel = \Carbon\Carbon::createFromFormat('Y-m', $periodRaw)->format('F Y');
        } catch (\Throwable $e) {
            $periodLabel = $periodRaw;
        }
    }
    $typeLabel = $typeLabels[$data['return_type'] ?? ''] ?? ucfirst((string) ($data['return_type'] ?? '-'));
@endphp

@php
$sections = \App\Services\PreviewRenderer::buildSections($data, [
    'skipKeys' => $skipKeys,
    'directorateNames' => $directorateNames,
]);
@endphp

<div class="preview-note">
    <div>
        <i class="fas fa-eye" style="margin-right:6px;"></i>
        This is a read-only preview. Attachments are included only after submission from the form page. Close this tab to return to the form.
    </div>
    <div class="preview-note-actions">
        <button type="button" class="preview-note-btn" onclick="window.print()">
            <i class="fas fa-print"></i> Print Preview
        </button>
    </div>
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

    <div class="nis-preview-banner">
        <div class="nis-preview-brand">
            <img class="logo" src="{{ asset('nis-logo.png') }}" alt="NIS Logo">
            <div class="nis-preview-brand-text">
                <h1>Nigeria Immigration Service</h1>
                <p class="subtitle">State Command Return - Official Preview</p>
            </div>
        </div>
        <div class="nis-preview-meta-grid">
            <div class="nis-preview-meta-item">
                <span class="nis-preview-meta-label">Command</span>
                <span class="nis-preview-meta-value">{{ $commandName ?? '-' }}</span>
            </div>
            <div class="nis-preview-meta-item">
                <span class="nis-preview-meta-label">Report Period</span>
                <span class="nis-preview-meta-value">{{ $periodLabel }}</span>
            </div>
            <div class="nis-preview-meta-item">
                <span class="nis-preview-meta-label">Return Type</span>
                <span class="nis-preview-meta-value">{{ $typeLabel }}</span>
            </div>
            <div class="nis-preview-meta-item">
                <span class="nis-preview-meta-label">Reporting Officer</span>
                <span class="nis-preview-meta-value">{{ $data['reporting_officer'] ?? $user?->name ?? '-' }}</span>
            </div>
            <div class="nis-preview-meta-item">
                <span class="nis-preview-meta-label">Generated</span>
                <span class="nis-preview-meta-value">{{ now()->format('d M Y, H:i') }}</span>
            </div>
        </div>
    </div>

    @forelse($sections as $index => $section)
    <section class="report-section preview-section {{ $loop->first ? 'active' : '' }}" data-label="{{ $section['label'] }}">
        <h2 class="section-title"><span class="section-num">{{ $index + 1 }}</span> {{ $section['label'] }}</h2>
        {!! $section['html'] ?: '<p class="preview-section-empty">No data entered for this section.</p>' !!}
    </section>
    @empty
    <section class="report-section preview-section active" data-label="No Data">
        <p style="color:#6b7280;font-size:.88rem;margin:0 0 8px;">No data entered yet. Return to the form to fill in the sections.</p>
    </section>
    @endforelse
</div>

</body>
</html>
