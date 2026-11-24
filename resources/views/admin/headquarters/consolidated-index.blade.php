@extends('admin.headquarters.layout')

@section('title', 'Consolidated Reports Review')

@section('content')
<div class="page-header" style="margin-bottom:20px;">
    <div>
        <h1 class="page-title">Consolidated Reports (HQ Approval)</h1>
        <p class="page-subtitle">Review and approve separate Zonal and Directorate Consolidated Reports.</p>
    </div>
    <a href="{{ route('prs.dashboard') }}" class="btn-nis btn-outline-nis">Open PRS Reporting Dashboard</a>
</div>

@if(session('status'))
<div role="status" style="background:#ecfdf5;border:1px solid #86efac;color:#166534;border-radius:10px;padding:12px 16px;margin-bottom:16px;">{{ session('status') }}</div>
@endif

<div class="redas-card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="nis-table">
                <thead>
                    <tr><th>Reference</th><th>Level</th><th>Scope</th><th>Period</th><th>Status</th><th>Rev</th><th>Stale</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse($reports as $rep)
                    <tr>
                        <td><strong>{{ $rep->reference }}</strong></td>
                        <td>{{ ucfirst($rep->level) }}</td>
                        <td>{{ strtoupper($rep->scope_code) }}</td>
                        <td>{{ $rep->report_period }}</td>
                        <td>{{ ucfirst($rep->status) }}</td>
                        <td>Rev {{ $rep->current_revision_no }}</td>
                        <td>{{ $rep->is_stale ? 'Yes' : 'No' }}</td>
                        <td><a href="{{ route('admin.consolidated.show', $rep) }}" class="btn-nis btn-outline-nis btn-sm">Review</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="8">No consolidated reports found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:12px;">{{ $reports->links() }}</div>
    </div>
</div>
@endsection
