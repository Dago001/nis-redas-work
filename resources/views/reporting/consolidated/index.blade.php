@extends('desk-admin.layout')

@section('content')
<div class="page-header" style="margin-bottom:20px;">
    <div>
        <h1 class="page-title">{{ ucfirst($level) }} Consolidated Reports ({{ strtoupper($scope) }})</h1>
        <p class="page-subtitle">Prepare separate consolidated reports while preserving every original submission.</p>
    </div>
    <a href="{{ route('consolidated.create', ['period' => $period]) }}" class="btn-nis btn-primary-nis">
        <i class="fas fa-plus"></i> Prepare Consolidated Report ({{ $period }})
    </a>
</div>

@if(session('status'))
<div role="status" style="background:#ecfdf5;border:1px solid #86efac;color:#166534;border-radius:10px;padding:12px 16px;margin-bottom:16px;">
    {{ session('status') }}
</div>
@endif

<div class="redas-card" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" action="{{ route('consolidated.index') }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
            <div class="fg">
                <label for="periodFilter">Reporting Period</label>
                <input id="periodFilter" type="month" name="period" class="ni" value="{{ $period }}">
            </div>
            <button type="submit" class="btn-nis btn-outline-nis">Filter Period</button>
        </form>
    </div>
</div>

<div class="grid-3" style="margin-bottom:20px;">
    <div class="redas-card"><div class="card-body">
        <div style="font-size:.78rem;color:var(--gray-500);">Expected Subordinate Units</div>
        <div style="font-size:1.6rem;font-weight:700;">{{ $expectedUnits->isNotEmpty() ? $expectedUnits->count() : 'Not Configured' }}</div>
    </div></div>
    <div class="redas-card"><div class="card-body">
        <div style="font-size:.78rem;color:var(--gray-500);">Received Original Reports ({{ $period }})</div>
        <div style="font-size:1.6rem;font-weight:700;">{{ $candidates->count() }}</div>
    </div></div>
    <div class="redas-card"><div class="card-body">
        <div style="font-size:.78rem;color:var(--gray-500);">Returned Originals ({{ $period }})</div>
        <div style="font-size:1.6rem;font-weight:700;">{{ $candidates->where('status', 'returned')->count() }}</div>
    </div></div>
</div>

<div class="redas-card" style="margin-bottom:20px;">
    <div class="card-head"><div class="card-head-title">Original Reports in Scope ({{ $period }})</div></div>
    <div class="card-body">
        @if($candidates->isEmpty())
            <p style="color:var(--gray-500);">No original reports received for {{ $period }}.</p>
        @else
            <div class="table-responsive">
                <table class="nis-table">
                    <thead>
                        <tr><th>Ref</th><th>Unit / Scope</th><th>Officer</th><th>Status</th><th>Version</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        @foreach($candidates as $app)
                        <tr>
                            <td>RET-{{ str_pad((string) $app->id, 5, '0', STR_PAD_LEFT) }}</td>
                            <td>{{ $app->section_code ?: $app->scope_code }}</td>
                            <td>{{ $app->user?->name ?? 'N/A' }}</td>
                            <td>{{ ucfirst($app->status) }}</td>
                            <td>v{{ max(1, (int) $app->current_version) }}</td>
                            <td><a href="{{ route('consolidated.original', $app) }}" class="btn-nis btn-ghost btn-sm">Inspect Original</a></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="redas-card">
    <div class="card-head"><div class="card-head-title">Consolidated Reports</div></div>
    <div class="card-body">
        @if($reports->isEmpty())
            <p style="color:var(--gray-500);">No consolidated reports created yet for this scope.</p>
        @else
            <div class="table-responsive">
                <table class="nis-table">
                    <thead>
                        <tr><th>Reference</th><th>Period</th><th>Title</th><th>Status</th><th>Revision</th><th>Stale?</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        @foreach($reports as $rep)
                        <tr>
                            <td><strong>{{ $rep->reference }}</strong></td>
                            <td>{{ $rep->report_period }}</td>
                            <td>{{ $rep->title }}</td>
                            <td>{{ ucfirst($rep->status) }}</td>
                            <td>Rev {{ $rep->current_revision_no }}</td>
                            <td>@if($rep->is_stale)<span style="color:#b45309;font-weight:700;">Stale</span>@else Current @endif</td>
                            <td><a href="{{ route('consolidated.show', $rep) }}" class="btn-nis btn-outline-nis btn-sm">Open</a></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="margin-top:12px;">{{ $reports->links() }}</div>
        @endif
    </div>
</div>
@endsection
