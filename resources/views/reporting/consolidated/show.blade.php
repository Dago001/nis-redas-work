@extends('desk-admin.layout')

@section('content')
<main class="redas-content">
<div class="page-header" style="margin-bottom:20px;">
    <div>
        <h1 class="page-title">{{ $report->reference }} — {{ $report->title }}</h1>
        <p class="page-subtitle">Period: {{ $report->report_period }} | Status: {{ ucfirst($report->status) }} | Revision {{ $report->current_revision_no }}</p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('consolidated.index', ['period' => $report->report_period]) }}" class="btn-nis btn-ghost">Back</a>
        @if($canEdit)
            <a href="{{ route('consolidated.edit', $report) }}" class="btn-nis btn-outline-nis">
                {{ $report->is_stale ? 'Revise with Latest Source Versions' : 'Revise Draft' }}
            </a>
        @endif
        @if($canSubmit)
            <form method="POST" action="{{ route('consolidated.submit', $report) }}">
                @csrf
                <button type="submit" class="btn-nis btn-primary-nis">Submit to HQ / PRS</button>
            </form>
        @endif
    </div>
</div>

@if(session('status'))
<div role="status" style="background:#ecfdf5;border:1px solid #86efac;color:#166534;border-radius:10px;padding:12px 16px;margin-bottom:16px;">{{ session('status') }}</div>
@endif

@if($report->is_stale)
<div role="alert" style="background:#fffbeb;border:1px solid #f59e0b;color:#92400e;border-radius:10px;padding:12px 16px;margin-bottom:16px;">
    <strong>Stale Source Warning:</strong> {{ $report->stale_reason }}
    You must create a new revision before submitting this consolidated report.
</div>
@endif

@if($report->returned_reason)
<div role="alert" style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;border-radius:10px;padding:12px 16px;margin-bottom:16px;">
    <strong>Returned by HQ:</strong> {{ $report->returned_reason }}
</div>
@endif

@if($latest)
<div class="redas-card" style="margin-bottom:20px;">
    <div class="card-head"><div class="card-head-title">Revision {{ $latest->revision_no }} Narrative &amp; Aggregated Indicators</div></div>
    <div class="card-body">
        <p style="margin-bottom:16px;white-space:pre-line;">{{ $latest->narrative ?: 'No narrative provided.' }}</p>
        @if(!empty($latest->aggregated_data))
        <div class="table-responsive">
            <table class="nis-table">
                <thead><tr><th>Indicator</th><th>Section</th><th>Total</th><th>Sources</th></tr></thead>
                <tbody>
                    @foreach($latest->aggregated_data as $code => $item)
                    <tr>
                        <td>{{ $item['name'] ?? $code }}</td>
                        <td>{{ $item['section'] ?? '—' }}</td>
                        <td><strong>{{ number_format((float) ($item['total'] ?? 0), 2) }}</strong></td>
                        <td>{{ $item['sources_count'] ?? 0 }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

<div class="redas-card" style="margin-bottom:20px;">
    <div class="card-head"><div class="card-head-title">Source Report Lineage (Revision {{ $latest->revision_no }})</div></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="nis-table">
                <thead><tr><th>Original Ref</th><th>Unit</th><th>Version Used</th><th>Current Version</th><th>Disposition</th><th>Action</th></tr></thead>
                <tbody>
                    @foreach($latest->sources as $src)
                    <tr>
                        <td>RET-{{ str_pad((string) $src->application_id, 5, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ $src->application?->section_code ?: $src->application?->scope_code }}</td>
                        <td>v{{ $src->reportVersion?->version_no ?? 1 }}</td>
                        <td>v{{ $src->application?->current_version ?? 1 }}</td>
                        <td>{{ $src->included ? 'Included' : 'Excluded: '.$src->exclusion_reason }}</td>
                        <td>@if($src->application)<a href="{{ route('consolidated.original', $src->application) }}" class="btn-nis btn-ghost btn-sm">View Original</a>@endif</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="redas-card">
    <div class="card-head"><div class="card-head-title">Revision History</div></div>
    <div class="card-body">
        <ul>
            @foreach($report->revisions as $rev)
                <li><strong>Rev {{ $rev->revision_no }}</strong> — {{ $rev->created_at?->toDateTimeString() }} by {{ $rev->creator?->name ?? 'System' }} ({{ $rev->change_note ?? 'Revision' }})</li>
            @endforeach
        </ul>
    </div>
</div>
</main>
@endsection
