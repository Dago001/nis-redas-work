@extends($layout)

@section('title', 'PRS Structured Statistical Indicators')

@section('content')
<div class="page-header" style="margin-bottom:20px;">
    <div>
        <h1 class="page-title">National Statistical Indicators ({{ $period }})</h1>
        <p class="page-subtitle">Aggregated from versioned structured indicator records (current versions only).</p>
    </div>
    <a href="{{ route('prs.dashboard', ['period' => $period]) }}" class="btn-nis btn-ghost">Back to PRS Dashboard</a>
</div>

<div class="redas-card">
    <div class="card-body">
        <table class="nis-table">
            <thead><tr><th>Code</th><th>Indicator</th><th>Section</th><th>Category</th><th>Status</th><th>Reports</th><th>Total Value</th></tr></thead>
            <tbody>
                @forelse($rows as $row)
                <tr>
                    <td><code>{{ $row->indicator?->code }}</code></td>
                    <td>{{ $row->indicator?->name }}</td>
                    <td>{{ $row->indicator?->section ?? '—' }}</td>
                    <td>{{ ucfirst((string) $row->category) }}</td>
                    <td>{{ ucfirst((string) ($row->indicator?->status ?? 'provisional')) }}</td>
                    <td>{{ $row->report_count }}</td>
                    <td><strong>{{ number_format((float) $row->total_value, 2) }}</strong></td>
                </tr>
                @empty
                <tr><td colspan="7">No structured indicator values recorded for {{ $period }}.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div style="margin-top:12px;">{{ $rows->links() }}</div>
    </div>
</div>
@endsection
