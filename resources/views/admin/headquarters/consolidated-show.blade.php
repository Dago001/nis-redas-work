@extends('admin.headquarters.layout')

@section('title', $report->reference)

@section('content')
<div class="page-header" style="margin-bottom:20px;">
    <div>
        <h1 class="page-title">{{ $report->reference }} — {{ $report->title }}</h1>
        <p class="page-subtitle">{{ ucfirst($report->level) }} | {{ strtoupper($report->scope_code) }} | Period {{ $report->report_period }} | Status: {{ ucfirst($report->status) }}</p>
    </div>
    <a href="{{ route('admin.consolidated.index') }}" class="btn-nis btn-ghost">Back to Queue</a>
</div>

@if(session('status'))
<div role="status" style="background:#ecfdf5;border:1px solid #86efac;color:#166534;border-radius:10px;padding:12px 16px;margin-bottom:16px;">{{ session('status') }}</div>
@endif

@if($report->status === 'submitted')
<div class="redas-card" style="margin-bottom:20px;">
    <div class="card-head"><div class="card-head-title">HQ Review Decision</div></div>
    <div class="card-body" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;">
        <form method="POST" action="{{ route('admin.consolidated.approve', $report) }}">
            @csrf @method('PATCH')
            <button type="submit" class="btn-nis btn-primary-nis">Approve Consolidated Report</button>
        </form>
        <form method="POST" action="{{ route('admin.consolidated.return', $report) }}" style="display:flex;gap:8px;flex:1;min-width:260px;">
            @csrf @method('PATCH')
            <input type="text" name="reason" class="ni" placeholder="Reason for returning for revision" required>
            <button type="submit" class="btn-nis btn-outline-nis">Return for Revision</button>
        </form>
    </div>
</div>
@endif

@if($latest)
<div class="redas-card" style="margin-bottom:20px;">
    <div class="card-head"><div class="card-head-title">Revision {{ $latest->revision_no }} Sources ({{ $latest->sources->count() }})</div></div>
    <div class="card-body">
        <p style="margin-bottom:14px;">{{ $latest->narrative }}</p>
        <table class="nis-table">
            <thead><tr><th>Original Ref</th><th>Unit</th><th>Version Used</th><th>Disposition</th><th>Inspect</th></tr></thead>
            <tbody>
                @foreach($latest->sources as $src)
                <tr>
                    <td>RET-{{ str_pad((string) $src->application_id, 5, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $src->application?->section_code ?: $src->application?->scope_code }}</td>
                    <td>v{{ $src->reportVersion?->version_no ?? 1 }}</td>
                    <td>{{ $src->included ? 'Included' : 'Excluded: '.$src->exclusion_reason }}</td>
                    <td>@if($src->application)<a href="{{ route('admin.hq.returns.show', $src->application) }}">Open Original</a>@endif</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
