@extends($layout)

@section('title', 'PRS Central Reporting Dashboard')

@section('content')
<main class="redas-content">
<div class="page-header" style="margin-bottom:20px;">
    <div>
        <h1 class="page-title">PRS Central Reporting Dashboard</h1>
        <p class="page-subtitle">Period: {{ $period }}. Original formation/section reports and Zonal/Directorate consolidated reports are tracked separately.</p>
    </div>
    <a href="{{ route('prs.indicators', ['period' => $period]) }}" class="btn-nis btn-outline-nis">Structured Indicators</a>
</div>

<div class="redas-card" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" action="{{ route('prs.dashboard') }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;align-items:end;">
            <div class="fg"><label for="prsPeriod">Period</label><input id="prsPeriod" type="month" name="period" class="ni" value="{{ $period }}"></div>
            <div class="fg">
                <label for="prsZone">Zone</label>
                <select id="prsZone" name="zone" class="ni ni-select">
                    <option value="">All Zones</option>
                    @foreach($zoneOptions as $z)<option value="{{ $z->code }}" @selected($filters['zone'] === $z->code)>{{ $z->code }} - {{ $z->name }}</option>@endforeach
                </select>
            </div>
            <div class="fg">
                <label for="prsDir">Directorate</label>
                <select id="prsDir" name="directorate" class="ni ni-select">
                    <option value="">All Directorates</option>
                    @foreach($directorateOptions as $d)<option value="{{ $d->code }}" @selected($filters['directorate'] === $d->code)>{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <div class="fg">
                <label for="prsForm">Formation</label>
                <select id="prsForm" name="formation" class="ni ni-select">
                    <option value="">All Formations</option>
                    @foreach($formationOptions as $f)<option value="{{ $f->code }}" @selected($filters['formation'] === $f->code)>{{ $f->code }} - {{ $f->name }}</option>@endforeach
                </select>
            </div>
            <div class="fg">
                <label for="prsType">Report Type</label>
                <select id="prsType" name="report_type" class="ni ni-select">
                    <option value="all" @selected($filters['report_type'] === 'all')>All (Originals + Consolidated)</option>
                    <option value="original" @selected($filters['report_type'] === 'original')>Original Reports Only</option>
                    <option value="consolidated" @selected($filters['report_type'] === 'consolidated')>Consolidated Reports Only</option>
                </select>
            </div>
            <div class="fg">
                <label for="prsStatus">Status</label>
                <select id="prsStatus" name="status" class="ni ni-select">
                    <option value="">Any Status</option>
                    @foreach(['pending','draft','submitted','returned','approved'] as $st)<option value="{{ $st }}" @selected($filters['status'] === $st)>{{ ucfirst($st) }}</option>@endforeach
                </select>
            </div>
            <button type="submit" class="btn-nis btn-primary-nis">Apply Filters</button>
        </form>
    </div>
</div>

@php $t = $summary['totals']; @endphp
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px;">
    <div class="redas-card"><div class="card-body"><div style="font-size:.76rem;color:var(--gray-500);">Expected Originals</div><div style="font-size:1.5rem;font-weight:700;">{{ $t['expected_originals'] }}</div></div></div>
    <div class="redas-card"><div class="card-body"><div style="font-size:.76rem;color:var(--gray-500);">Received Originals</div><div style="font-size:1.5rem;font-weight:700;">{{ $t['received_originals'] }}</div></div></div>
    <div class="redas-card"><div class="card-body"><div style="font-size:.76rem;color:var(--gray-500);">Received Consolidated</div><div style="font-size:1.5rem;font-weight:700;">{{ $t['received_consolidated'] }}</div></div></div>
    <div class="redas-card"><div class="card-body"><div style="font-size:.76rem;color:var(--gray-500);">Total PRS Records</div><div style="font-size:1.5rem;font-weight:700;">{{ $t['prs_records_total'] }}</div></div></div>
    <div class="redas-card"><div class="card-body"><div style="font-size:.76rem;color:var(--gray-500);">Outstanding</div><div style="font-size:1.5rem;font-weight:700;">{{ $t['outstanding'] }}</div></div></div>
    <div class="redas-card"><div class="card-body"><div style="font-size:.76rem;color:var(--gray-500);">Returned</div><div style="font-size:1.5rem;font-weight:700;">{{ $t['returned'] }}</div></div></div>
    <div class="redas-card"><div class="card-body"><div style="font-size:.76rem;color:var(--gray-500);">Approved</div><div style="font-size:1.5rem;font-weight:700;">{{ $t['approved'] }}</div></div></div>
    <div class="redas-card"><div class="card-body"><div style="font-size:.76rem;color:var(--gray-500);">Pending Approval</div><div style="font-size:1.5rem;font-weight:700;">{{ $t['pending_approval'] }}</div></div></div>
    <div class="redas-card"><div class="card-body"><div style="font-size:.76rem;color:var(--gray-500);">Completion %</div><div style="font-size:1.5rem;font-weight:700;">{{ $t['completion_pct'] !== null ? $t['completion_pct'].'%' : 'N/A' }}</div></div></div>
</div>

<div class="redas-card" style="margin-bottom:20px;">
    <div class="card-head"><div class="card-head-title">PRS Received Records - Originals &amp; Consolidated ({{ $records->total() }})</div></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="nis-table">
                <thead>
                    <tr><th>Type</th><th>Reference</th><th>Category</th><th>Scope</th><th>Author</th><th>Status</th><th>Version</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse($records as $rec)
                    <tr>
                        <td><strong>{{ ucfirst($rec['record_type']) }}</strong>@if($rec['is_stale']) (Stale)@endif</td>
                        <td>{{ $rec['reference'] }}</td>
                        <td>{{ $rec['category'] }}</td>
                        <td>{{ $rec['scope_code'] }}</td>
                        <td>{{ $rec['author'] }}</td>
                        <td>{{ ucfirst((string) $rec['status']) }}</td>
                        <td>{{ $rec['version'] }}</td>
                        <td><a href="{{ $rec['url'] }}" class="btn-nis btn-outline-nis btn-sm">Inspect</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="8">No reports matched the selected filters for {{ $period }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:12px;">{{ $records->links() }}</div>
    </div>
</div>

<div class="redas-card" style="margin-bottom:20px;">
    <div class="card-head">
        <div class="card-head-title">
            <div class="card-head-icon" style="background:#fef3c7;color:#b45309;"><i class="fas fa-map"></i></div>
            Zone-Level Status
        </div>
    </div>
    <div class="card-body no-pad">
        <div class="table-responsive">
            <table class="nis-table" style="min-width:680px;">
                <thead>
                    <tr>
                        <th>Zone</th>
                        <th>Expected</th>
                        <th>Received</th>
                        <th>Outstanding</th>
                        <th>Completion</th>
                        <th>Consolidated</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($summary['zones'] as $z)
                    <tr>
                        <td style="font-weight:700;white-space:nowrap;">{{ $z['code'] }}</td>
                        <td>{{ $z['expected_formations'] }}</td>
                        <td>{{ $z['received_originals'] }}</td>
                        <td>{{ $z['outstanding'] }}</td>
                        <td style="font-weight:600;">{{ $z['completion_pct'] !== null ? $z['completion_pct'].'%' : '-' }}</td>
                        <td>{{ ucfirst($z['consolidated_status']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="redas-card" style="margin-bottom:20px;">
    <div class="card-head">
        <div class="card-head-title">
            <div class="card-head-icon" style="background:#e0f2fe;color:#0369a1;"><i class="fas fa-building-columns"></i></div>
            Directorate-Level Status
        </div>
    </div>
    <div class="card-body no-pad">
        <div class="table-responsive">
            <table class="nis-table" style="min-width:620px;">
                <thead>
                    <tr>
                        <th>Directorate</th>
                        <th>Expected Sections</th>
                        <th>Originals</th>
                        <th>Consolidated</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($summary['directorates'] as $d)
                    <tr>
                        <td style="font-weight:600;">{{ $d['name'] }}</td>
                        <td>{{ $d['expected_sections'] !== null ? $d['expected_sections'] : 'Not configured' }}</td>
                        <td>{{ $d['received_originals'] }}</td>
                        <td>{{ ucfirst($d['consolidated_status']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
</main>
@endsection
