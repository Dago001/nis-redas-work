@extends($layout)

@section('title', 'PRS Consolidated Report Inspection')

@section('content')
<main class="redas-content">
<div class="page-header" style="margin-bottom:20px;">
    <div>
        <h1 class="page-title">{{ $report->reference }} — {{ $report->title }}</h1>
        <p class="page-subtitle">Level: {{ ucfirst($report->level) }} | Scope: {{ strtoupper($report->scope_code) }} | Period: {{ $report->report_period }} | Status: {{ ucfirst($report->status) }} | Revision {{ $report->current_revision_no }}</p>
    </div>
    <a href="{{ route('prs.dashboard', ['period' => $report->report_period]) }}" class="btn-nis btn-ghost">Back to PRS Dashboard</a>
</div>

@if($report->is_stale)
<div role="alert" style="background:#fffbeb;border:1px solid #f59e0b;color:#92400e;border-radius:10px;padding:12px 16px;margin-bottom:16px;">
    <strong>Stale Warning:</strong> {{ $report->stale_reason }}
</div>
@endif

@if($latest)
<div class="redas-card" style="margin-bottom:20px;">
    <div class="card-head"><div class="card-head-title">Source Original Reports Referenced (Rev {{ $latest->revision_no }})</div></div>
    <div class="card-body">
        <p style="margin-bottom:14px;">{{ $latest->narrative }}</p>
        <table class="nis-table">
            <thead><tr><th>Original Ref</th><th>Unit</th><th>Version Used</th><th>Current Version</th><th>Disposition</th><th>Inspect</th></tr></thead>
            <tbody>
                @foreach($latest->sources as $src)
                <tr>
                    <td>RET-{{ str_pad((string) $src->application_id, 5, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $src->application?->section_code ?: $src->application?->scope_code }}</td>
                    <td>v{{ $src->reportVersion?->version_no ?? 1 }}</td>
                    <td>v{{ $src->application?->current_version ?? 1 }}</td>
                    <td>{{ $src->included ? 'Included' : 'Excluded: '.$src->exclusion_reason }}</td>
                    <td>@if($src->application)<a href="{{ route('prs.originals.show', $src->application) }}">Inspect Original</a>@endif</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
</main>
@endsection
