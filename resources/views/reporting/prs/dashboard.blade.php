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

@php
    $t = $summary['totals'];
    $prsCards = [
        ['label' => 'Expected Originals',    'val' => number_format((int) $t['expected_originals']),    'icon' => 'fas fa-bullseye',         'bg' => '#f1f5f9', 'fg' => '#475569', 'valColor' => '#0f172a'],
        ['label' => 'Received Originals',    'val' => number_format((int) $t['received_originals']),    'icon' => 'fas fa-file-lines',       'bg' => '#e0f2fe', 'fg' => '#0369a1', 'valColor' => '#0f172a'],
        ['label' => 'Received Consolidated', 'val' => number_format((int) $t['received_consolidated']), 'icon' => 'fas fa-layer-group',      'bg' => '#ede9fe', 'fg' => '#6d28d9', 'valColor' => '#0f172a'],
        ['label' => 'Total PRS Records',     'val' => number_format((int) $t['prs_records_total']),     'icon' => 'fas fa-database',         'bg' => '#ccfbf1', 'fg' => '#0f766e', 'valColor' => '#0f172a'],
        ['label' => 'Outstanding',           'val' => number_format((int) $t['outstanding']),           'icon' => 'fas fa-hourglass-half',   'bg' => '#fef3c7', 'fg' => '#b45309', 'valColor' => '#b45309'],
        ['label' => 'Returned',              'val' => number_format((int) $t['returned']),              'icon' => 'fas fa-rotate-left',      'bg' => '#fee2e2', 'fg' => '#b91c1c', 'valColor' => '#b91c1c'],
        ['label' => 'Approved',              'val' => number_format((int) $t['approved']),              'icon' => 'fas fa-circle-check',     'bg' => '#dcfce7', 'fg' => '#15803d', 'valColor' => '#15803d'],
        ['label' => 'Pending Approval',      'val' => number_format((int) $t['pending_approval']),      'icon' => 'fas fa-clock',            'bg' => '#ffedd5', 'fg' => '#c2410c', 'valColor' => '#c2410c'],
        ['label' => 'Completion Rate',       'val' => $t['completion_pct'] !== null ? $t['completion_pct'].'%' : 'N/A', 'icon' => 'fas fa-chart-pie', 'bg' => '#ecfdf5', 'fg' => '#006838', 'valColor' => '#006838'],
    ];
@endphp
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:12px;margin-bottom:20px;">
    @foreach($prsCards as $card)
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:13px 15px;display:flex;flex-direction:column;justify-content:space-between;gap:8px;box-shadow:0 1px 2px rgba(15,23,42,.04);">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                <span style="font-size:.68rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#64748b;">{{ $card['label'] }}</span>
                <span style="width:28px;height:28px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;flex-shrink:0;background:{{ $card['bg'] }};color:{{ $card['fg'] }};">
                    <i class="{{ $card['icon'] }}"></i>
                </span>
            </div>
            <div style="font-size:1.25rem;font-weight:700;color:{{ $card['valColor'] }};line-height:1.2;">{{ $card['val'] }}</div>
        </div>
    @endforeach
</div>

<div class="redas-card" style="margin-bottom:20px;">
    <div class="card-head">
        <div class="card-head-title">
            <div class="card-head-icon" style="background:#ede9fe;color:#6d28d9;"><i class="fas fa-table-list"></i></div>
            PRS Received Records - Originals &amp; Consolidated ({{ $records->total() }})
        </div>
    </div>
    <div class="card-body no-pad">
        <div class="table-responsive">
            <table class="nis-table" style="min-width:760px;">
                <thead>
                    <tr><th>Type</th><th>Reference</th><th>Category</th><th>Scope</th><th>Author</th><th>Status</th><th>Version</th><th style="text-align:right;">Action</th></tr>
                </thead>
                <tbody>
                    @forelse($records as $rec)
                    <tr>
                        <td><strong>{{ ucfirst($rec['record_type']) }}</strong>@if($rec['is_stale']) (Stale)@endif</td>
                        <td style="font-weight:600;">{{ $rec['reference'] }}</td>
                        <td>{{ $rec['category'] }}</td>
                        <td style="font-weight:600;text-transform:uppercase;">{{ strtoupper((string) $rec['scope_code']) }}</td>
                        <td>{{ $rec['author'] }}</td>
                        <td>{{ ucfirst((string) $rec['status']) }}</td>
                        <td>{{ $rec['version'] }}</td>
                        <td style="text-align:right;"><a href="{{ $rec['url'] }}" class="btn-nis btn-outline-nis btn-sm">Inspect</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="8" style="text-align:center;color:#64748b;padding:20px;">No reports matched the selected filters for {{ $period }}.</td></tr>
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
