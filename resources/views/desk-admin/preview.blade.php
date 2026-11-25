@extends('desk-admin.layout')

@section('content')
@php
    $data = is_array($application->return_data) ? $application->return_data : [];
    $skipKeys = [
        'directorate_slug',
        'cgis_unit_slug',
        'data_consent',
        'report_period',
        'reporting_officer',
        'command_name',
        'period',
        'return_type',
        'supporting_documents',
        'attachments',
        '_idempotency_key',
        '_method',
        '_token',
    ];

    $directorateNames = [];
    foreach (\App\Http\Controllers\Web\DashboardController::directorates() as $s => $meta) {
        $directorateNames[$s] = $meta['name'];
    }

    $refCode = 'RET-' . str_pad((string) $application->id, 5, '0', STR_PAD_LEFT);
    $scopeCode = strtoupper((string) ($application->scope_code ?? ''));
    $resolvedScopeName = $scopeCode !== ''
        ? (\App\Services\GeolocationService::stateNameFromCode($scopeCode) ?? $scopeCode)
        : 'State Command';
    $commandDisplay = $resolvedScopeName !== $scopeCode && $scopeCode !== ''
        ? "{$resolvedScopeName} ({$scopeCode})"
        : $resolvedScopeName;

    $sourceTitle = $directorateNames[$data['directorate_slug'] ?? $data['cgis_unit_slug'] ?? null]
        ?? ($application->category === 'state'
            ? "{$commandDisplay} - Combined Return"
            : ucwords(str_replace(['_', '-'], ' ', (string) ($data['directorate_slug'] ?? $commandDisplay))));

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
    $statusStyle = match (true) {
        $status === 'approved' => 'background:#ecfdf5;color:#166534;border:1px solid #86efac;',
        in_array($status, ['rejected', 'queried', 'returned'], true) => 'background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;',
        default => 'background:#fef9c3;color:#854d0e;border:1px solid #fde047;',
    };

    $sections = \App\Services\PreviewRenderer::buildSections($data, [
        'skipKeys' => $skipKeys,
        'directorateNames' => $directorateNames,
        'groupByDirectorate' => $application->category === 'state',
    ]);
@endphp

<style>
    /* Official NIS Letterhead Banner */
    .nis-review-banner {
        background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 60%, #f8fafc 100%);
        border: 1px solid #bbf7d0;
        border-radius: 12px;
        padding: 20px 24px;
        margin-bottom: 18px;
    }
    .nis-review-brand {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        padding-bottom: 14px;
        border-bottom: 1px solid #dcfce7;
    }
    .nis-review-brand-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .nis-review-brand img.logo {
        width: 72px;
        height: 72px;
        object-fit: contain;
        flex-shrink: 0;
    }
    .nis-review-brand h2 {
        font-size: 1.6rem;
        font-weight: 800;
        margin: 0 0 4px;
        color: #006838;
        letter-spacing: .02em;
        text-transform: uppercase;
        line-height: 1.15;
    }
    .nis-review-brand .subtitle {
        margin: 0;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: #4b5563;
    }
    .nis-review-meta-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 10px;
        margin-top: 14px;
    }
    @media (max-width: 960px) {
        .nis-review-meta-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    .nis-review-meta-item {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 9px 12px;
    }
    .nis-review-meta-label {
        display: block;
        font-size: .66rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: #6b7280;
        margin-bottom: 3px;
    }
    .nis-review-meta-value {
        display: block;
        font-size: .82rem;
        font-weight: 700;
        color: #111827;
        word-break: break-word;
    }

    /* Section Cards & PreviewRenderer Tables */
    .nis-review-section-card {
        border: 1px solid var(--gray-200);
        border-radius: 12px;
        background: #fff;
        margin-bottom: 16px;
        overflow: hidden;
    }
    .nis-review-section-body {
        padding: 18px 20px;
        overflow-x: auto;
    }
    .nis-review-section-body .section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: .96rem;
        font-weight: 700;
        color: #006838;
        margin: 0 0 14px;
        border-bottom: 2px solid #006838;
        padding-bottom: 8px;
    }
    .nis-review-section-body .section-num {
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
    .nis-review-section-body .sub-section-title {
        font-size: .84rem;
        font-weight: 700;
        color: #1f2937;
        margin: 16px 0 8px;
        padding-left: 8px;
        border-left: 3px solid #006838;
    }
    .nis-review-section-body table.report-table {
        width: 100%;
        border-collapse: collapse;
        font-size: .8rem;
        margin-bottom: 14px;
        background: #fff;
    }
    .nis-review-section-body table.report-table th,
    .nis-review-section-body table.report-table td {
        border: 1px solid #d1d5db;
        padding: 7px 10px;
        text-align: left;
        vertical-align: top;
    }
    .nis-review-section-body table.report-table th {
        background: #f0fdf4;
        font-weight: 700;
        color: #14532d;
        font-size: .74rem;
        text-transform: uppercase;
        letter-spacing: .02em;
    }
    .nis-review-section-body table.report-table tbody tr:nth-child(even) td {
        background: #fafcfb;
    }
    .nis-review-section-body table.report-table td.kv-key {
        width: 34%;
        font-weight: 600;
        background: #f9fafb !important;
        color: #374151;
    }
    .nis-review-section-body table.report-table.kv-table td:last-child {
        white-space: pre-wrap;
    }
    .nis-review-section-body table.report-table td.row-head {
        font-weight: 600;
        background: #f9fafb !important;
        color: #1f2937;
    }

    /* Review Action Grid */
    .nis-decision-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }
    @media (max-width: 768px) {
        .nis-decision-grid { grid-template-columns: 1fr; }
    }
    .nis-decision-box {
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        padding: 14px 16px;
        background: #f9fafb;
    }
    .nis-decision-box.approve { border-color: #bbf7d0; background: #f0fdf4; }
    .nis-decision-box.reject { border-color: #fecaca; background: #fef2f2; }
    .nis-decision-box label {
        display: block;
        font-size: .78rem;
        font-weight: 700;
        margin-bottom: 6px;
        color: #1f2937;
    }
    .nis-decision-row {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .nis-decision-row input {
        flex: 1 1 auto;
        min-width: 0;
        padding: 8px 11px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: .82rem;
        background: #fff;
    }
</style>

<main class="redas-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Submission {{ $refCode }}</h1>
            <p class="page-subtitle">Official return review package for verification, approval, or correction.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('user.submissions.pdf', $application) }}" class="btn-nis btn-outline-nis">
                <i class="fas fa-file-pdf"></i> Download PDF
            </a>
            <a href="{{ route('desk.admin.submissions.download', $application) }}" class="btn-nis btn-outline-nis">
                <i class="fas fa-download"></i> Download CSV
            </a>
            <a href="{{ auth()->user()?->dashboardUrl() ?? route('user.desk.home') }}" class="btn-nis btn-ghost">
                <i class="fas fa-arrow-left"></i> Back to Review Dashboard
            </a>
        </div>
    </div>

    @if(session('status'))
        <div style="margin-bottom:16px;padding:12px 14px;border-radius:10px;border:1px solid #bbf7d0;background:#f0fdf4;color:#166534;font-size:.86rem;">
            <i class="fas fa-check-circle" style="margin-right:6px;"></i>{{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div style="margin-bottom:16px;padding:12px 14px;border-radius:10px;border:1px solid #fca5a5;background:#fef2f2;color:#991b1b;font-size:.86rem;">
            @foreach($errors->all() as $error)
                <div><i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    {{-- Official NIS Letterhead Banner --}}
    <div class="nis-review-banner">
        <div class="nis-review-brand">
            <div class="nis-review-brand-left">
                <img class="logo" src="{{ asset('nis-logo.png') }}" alt="NIS Logo">
                <div>
                    <h2>Nigeria Immigration Service</h2>
                    <p class="subtitle">{{ $sourceTitle }} &bull; {{ $returnTypeLabel }}</p>
                </div>
            </div>
            <div>
                <span style="display:inline-block;padding:5px 12px;border-radius:999px;font-size:.74rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;{{ $statusStyle }}">
                    {{ ucfirst($application->status ?? 'pending') }}
                </span>
            </div>
        </div>
        <div class="nis-review-meta-grid">
            <div class="nis-review-meta-item">
                <span class="nis-review-meta-label">Reference</span>
                <span class="nis-review-meta-value">{{ $refCode }}</span>
            </div>
            <div class="nis-review-meta-item">
                <span class="nis-review-meta-label">Command / Scope</span>
                <span class="nis-review-meta-value">{{ $commandDisplay }}</span>
            </div>
            <div class="nis-review-meta-item">
                <span class="nis-review-meta-label">Report Period</span>
                <span class="nis-review-meta-value">{{ $periodLabel }}</span>
            </div>
            <div class="nis-review-meta-item">
                <span class="nis-review-meta-label">Reporting Officer</span>
                <span class="nis-review-meta-value">{{ $data['reporting_officer'] ?? optional($application->user)->name ?? '-' }}</span>
            </div>
            <div class="nis-review-meta-item">
                <span class="nis-review-meta-label">Submitted</span>
                <span class="nis-review-meta-value">{{ optional($application->created_at)->format('d M Y, H:i') ?? '-' }}</span>
            </div>
        </div>
    </div>

    {{-- Sectional return data with pagination --}}
    <div data-preview-paginator>
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

        @forelse($sections as $index => $section)
        <div class="nis-review-section-card preview-section {{ $loop->first ? 'active' : '' }}" data-label="{{ $section['label'] }}">
            <div class="nis-review-section-body">
                <h2 class="section-title"><span class="section-num">{{ $index + 1 }}</span> {{ $section['label'] }}</h2>
                @if($section['html'])
                    {!! $section['html'] !!}
                @else
                    <p style="padding:12px 0;color:#64748b;margin:0;font-size:.84rem;">No data entered for this section.</p>
                @endif
            </div>
        </div>
        @empty
        <div class="nis-review-section-card preview-section active" data-label="No Data">
            <div class="nis-review-section-body">
                <p style="padding:12px 0;color:#64748b;margin:0;font-size:.84rem;">No return data is available to preview.</p>
            </div>
        </div>
        @endforelse
    </div>

    @include('partials.document-viewer', ['docRoute' => 'desk.admin.submissions.document'])

    {{-- Review comment history --}}
    @php
        $commentHistory = $application->relationLoaded('reviewComments') ? $application->reviewComments : collect();
    @endphp
    @if($commentHistory->isNotEmpty())
        <div class="redas-card" style="padding:18px 20px;margin-top:18px;">
            <h3 style="margin:0 0 12px;font-size:.95rem;font-weight:700;color:var(--gray-800);"><i class="fas fa-history" style="color:var(--nis-700);margin-right:6px;"></i>Review History</h3>
            <div style="display:flex;flex-direction:column;gap:10px;">
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
                            <div style="font-size:.8rem;color:var(--gray-800);">
                                <strong>{{ optional($entry->user)->name ?? 'System' }}</strong>
                                <span style="color:var(--gray-500);">· {{ ucfirst($entry->action) }} at {{ str_replace('_', ' ', $entry->stage) }} · {{ optional($entry->created_at)->format('d M Y, H:i') }}</span>
                            </div>
                            @if($entry->comment)
                                <div style="font-size:.84rem;color:var(--gray-700);margin-top:3px;">{{ $entry->comment }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Review actions --}}
    <div class="redas-card" style="padding:18px 20px;margin-top:18px;">
        @if($canReview ?? false)
            <h3 style="margin:0 0 12px;font-size:.95rem;font-weight:700;color:var(--gray-800);"><i class="fas fa-gavel" style="color:var(--nis-700);margin-right:6px;"></i>Review Decision</h3>
            <div class="nis-decision-grid">
                <div class="nis-decision-box approve">
                    <label for="approvalNote">Approve &amp; Forward Return</label>
                    <form method="POST" action="{{ route($approveRoute, $application) }}" class="nis-decision-row" style="margin:0;">
                        @csrf
                        @method('PATCH')
                        <input id="approvalNote" type="text" name="note" maxlength="1000" placeholder="Approval note (optional)">
                        <button type="submit" class="btn-nis btn-primary-nis" style="white-space:nowrap;"><i class="fas fa-check"></i> Approve</button>
                    </form>
                </div>
                <div class="nis-decision-box reject">
                    <label for="rejectionComment">Return for Correction</label>
                    <form method="POST" action="{{ route($rejectRoute, $application) }}" class="nis-decision-row" style="margin:0;">
                        @csrf
                        @method('PATCH')
                        <input id="rejectionComment" type="text" name="comment" maxlength="1000" required placeholder="Reason for returning (required)">
                        <button type="submit" class="btn-nis" style="background:#b91c1c;color:#fff;white-space:nowrap;"><i class="fas fa-undo"></i> Return</button>
                    </form>
                </div>
            </div>
        @else
            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;">
                <span style="font-size:.84rem;color:var(--gray-500);"><i class="fas fa-info-circle" style="margin-right:6px;"></i>This submission is not awaiting your action.</span>
                <a href="{{ auth()->user()?->user_category === 'hq_admin' ? route('admin.submissions') : (auth()->user()?->dashboardUrl() ?? route('user.desk.home')) }}" class="btn-nis btn-ghost">Back to Dashboard</a>
            </div>
        @endif
    </div>
</main>
@endsection
