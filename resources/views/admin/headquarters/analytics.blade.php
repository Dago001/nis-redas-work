@extends('admin.headquarters.layout')

@section('title', 'Analytics')

@section('content')
@php
    $exportParams = array_filter($filters ?? [], fn ($v) => $v !== '' && $v !== 'all');
    $hasActiveFilters = count($exportParams) > 0;
    $avgHours = $kpis['avg_turnaround_hours'] ?? null;
    $avgTurnaroundLabel = $avgHours === null
        ? 'N/A'
        : ($avgHours >= 24 ? round($avgHours / 24, 1) . ' days' : $avgHours . ' hrs');
@endphp

<style>
    .hq-analytics-grid-kpi {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .hq-kpi-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 13px 15px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 6px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }
    .hq-kpi-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .hq-kpi-label {
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #64748b;
    }
    .hq-kpi-icon {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .78rem;
        flex-shrink: 0;
    }
    .hq-kpi-val {
        font-size: 1.25rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.2;
    }
    .hq-kpi-sub {
        font-size: .72rem;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 5px;
        flex-wrap: wrap;
    }
    .hq-filter-bar {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 14px 16px;
        margin-bottom: 20px;
    }
    .hq-filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        align-items: end;
    }
    .hq-filter-field label {
        display: block;
        font-size: .7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #475569;
        margin-bottom: 4px;
    }
    .hq-filter-field select {
        width: 100%;
        padding: 7px 10px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: .82rem;
        color: #0f172a;
        background: #f8fafc;
    }
    .hq-chart-grid-2 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    .hq-progress-track {
        width: 100%;
        min-width: 70px;
        height: 7px;
        background: #e2e8f0;
        border-radius: 999px;
        overflow: hidden;
    }
    .hq-progress-fill {
        height: 100%;
        border-radius: 999px;
        background: #15803d;
    }
    .hq-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .hq-chart-wrap {
        position: relative;
        width: 100%;
    }
    @media print {
        .hq-no-print { display: none !important; }
        .redas-sidebar, .redas-topbar { display: none !important; }
        .redas-main { margin-left: 0 !important; }
    }
</style>

<main class="redas-content">
    <div class="page-header" style="margin-bottom:18px;">
        <div>
            <h1 class="page-title">National Analytics &amp; Performance Command Center</h1>
            <p class="page-subtitle">Live operational intelligence across all 8 Zonal Commands, State Commands, 10 Directorates, CGIS Units, and Consolidated Approvals.</p>
        </div>
        <div class="hq-no-print" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <a href="{{ route('admin.hq.analytics.export', $exportParams) }}" class="btn-nis btn-outline-nis btn-sm">
                <i class="fas fa-file-csv"></i> Export CSV
            </a>
            <button type="button" onclick="window.print()" class="btn-nis btn-ghost btn-sm">
                <i class="fas fa-print"></i> Print / PDF
            </button>
            <a href="{{ route('admin.hq.reports') }}" class="btn-nis btn-primary-nis btn-sm">
                <i class="fas fa-file-excel"></i> Generate Excel Report
            </a>
        </div>
    </div>

    {{-- Interactive Filter Bar --}}
    <form method="GET" action="{{ route('admin.hq.analytics') }}" class="hq-filter-bar hq-no-print">
        <div class="hq-filter-grid">
            <div class="hq-filter-field">
                <label for="f-range">Time Window</label>
                <select id="f-range" name="range">
                    <option value="all" {{ ($filters['range'] ?? 'all') === 'all' ? 'selected' : '' }}>All Time</option>
                    <option value="current_month" {{ ($filters['range'] ?? '') === 'current_month' ? 'selected' : '' }}>Current Month</option>
                    <option value="3m" {{ ($filters['range'] ?? '') === '3m' ? 'selected' : '' }}>Last 3 Months</option>
                    <option value="6m" {{ ($filters['range'] ?? '') === '6m' ? 'selected' : '' }}>Last 6 Months</option>
                    <option value="12m" {{ ($filters['range'] ?? '') === '12m' ? 'selected' : '' }}>Last 12 Months</option>
                </select>
            </div>

            <div class="hq-filter-field">
                <label for="f-period">Specific Period</label>
                <select id="f-period" name="period">
                    <option value="">All Periods</option>
                    @foreach($availablePeriods as $periodOpt)
                        @php
                            $periodNice = $periodOpt;
                            try {
                                $periodNice = \Carbon\Carbon::createFromFormat('Y-m', $periodOpt)->format('F Y');
                            } catch (\Throwable $e) {}
                        @endphp
                        <option value="{{ $periodOpt }}" {{ ($filters['period'] ?? '') === $periodOpt ? 'selected' : '' }}>
                            {{ $periodNice }} ({{ $periodOpt }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="hq-filter-field">
                <label for="f-category">Formation Category</label>
                <select id="f-category" name="category">
                    <option value="">All Categories</option>
                    <option value="state" {{ ($filters['category'] ?? '') === 'state' ? 'selected' : '' }}>State &amp; Zonal Commands</option>
                    <option value="directorate" {{ ($filters['category'] ?? '') === 'directorate' ? 'selected' : '' }}>Directorates</option>
                    <option value="cgis_unit" {{ ($filters['category'] ?? '') === 'cgis_unit' ? 'selected' : '' }}>CGIS Units</option>
                </select>
            </div>

            <div class="hq-filter-field">
                <label for="f-zone">Zonal Command</label>
                <select id="f-zone" name="zone">
                    <option value="">All 8 Zones</option>
                    @foreach($zones as $zCode => $zName)
                        <option value="{{ $zCode }}" {{ ($filters['zone'] ?? '') === $zCode ? 'selected' : '' }}>{{ $zName }}</option>
                    @endforeach
                </select>
            </div>

            <div class="hq-filter-field">
                <label for="f-status">Return Status</label>
                <select id="f-status" name="status">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending / In Flight</option>
                    <option value="approved" {{ ($filters['status'] ?? '') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="returned" {{ ($filters['status'] ?? '') === 'returned' ? 'selected' : '' }}>Returned / Queried</option>
                </select>
            </div>

            <div style="display:flex;gap:8px;align-items:center;">
                <button type="submit" class="btn-nis btn-primary-nis btn-sm" style="flex:1;justify-content:center;">
                    <i class="fas fa-filter"></i> Apply
                </button>
                @if($hasActiveFilters)
                    <a href="{{ route('admin.hq.analytics') }}" class="btn-nis btn-ghost btn-sm" title="Reset all filters">
                        <i class="fas fa-rotate-left"></i> Reset
                    </a>
                @endif
            </div>
        </div>
    </form>

    {{-- Executive KPI Strip --}}
    <div class="hq-analytics-grid-kpi">
        <div class="hq-kpi-card">
            <div class="hq-kpi-top">
                <span class="hq-kpi-label">Total Returns</span>
                <span class="hq-kpi-icon" style="background:#e0f2fe;color:#0369a1;"><i class="fas fa-file-lines"></i></span>
            </div>
            <div class="hq-kpi-val">{{ number_format($kpis['total_returns']) }}</div>
            <div class="hq-kpi-sub">
                <span>{{ $kpis['current_month_returns'] }} this month</span>
                @php $mom = $kpis['mom_delta_pct']; @endphp
                <span class="hq-pill" style="{{ $mom >= 0 ? 'background:#ecfdf5;color:#15803d;' : 'background:#fef2f2;color:#b91c1c;' }}">
                    {{ $mom >= 0 ? '+' : '' }}{{ $mom }}% MoM
                </span>
            </div>
        </div>

        <div class="hq-kpi-card">
            <div class="hq-kpi-top">
                <span class="hq-kpi-label">Approval Rate</span>
                <span class="hq-kpi-icon" style="background:#dcfce7;color:#15803d;"><i class="fas fa-circle-check"></i></span>
            </div>
            <div class="hq-kpi-val" style="color:#15803d;">{{ $kpis['approval_rate'] }}%</div>
            <div class="hq-kpi-sub">{{ number_format($kpis['approved_returns']) }} cleared full chain</div>
        </div>

        <div class="hq-kpi-card">
            <div class="hq-kpi-top">
                <span class="hq-kpi-label">In Flight / Pending</span>
                <span class="hq-kpi-icon" style="background:#fef3c7;color:#b45309;"><i class="fas fa-clock"></i></span>
            </div>
            <div class="hq-kpi-val" style="color:#b45309;">{{ number_format($kpis['pending_returns']) }}</div>
            <div class="hq-kpi-sub">
                <span><strong>{{ $kpis['awaiting_hq'] }}</strong> awaiting HQ review</span>
            </div>
        </div>

        <div class="hq-kpi-card">
            <div class="hq-kpi-top">
                <span class="hq-kpi-label">Returned / Queried</span>
                <span class="hq-kpi-icon" style="background:#fee2e2;color:#b91c1c;"><i class="fas fa-rotate-left"></i></span>
            </div>
            <div class="hq-kpi-val" style="color:#b91c1c;">{{ number_format($kpis['returned_returns']) }}</div>
            <div class="hq-kpi-sub">{{ $kpis['return_rate'] }}% return rate</div>
        </div>

        <div class="hq-kpi-card">
            <div class="hq-kpi-top">
                <span class="hq-kpi-label">Consolidated Reports</span>
                <span class="hq-kpi-icon" style="background:#ede9fe;color:#6d28d9;"><i class="fas fa-layer-group"></i></span>
            </div>
            <div class="hq-kpi-val">{{ number_format($kpis['consolidated_total']) }}</div>
            <div class="hq-kpi-sub">
                <span>{{ $kpis['consolidated_approved'] }} approved · {{ $kpis['consolidated_pending'] }} submitted</span>
                @if($kpis['consolidated_stale'] > 0)
                    <span class="hq-pill" style="background:#fef3c7;color:#92400e;">{{ $kpis['consolidated_stale'] }} stale</span>
                @endif
            </div>
        </div>

        <div class="hq-kpi-card">
            <div class="hq-kpi-top">
                <span class="hq-kpi-label">Avg. Stage Turnaround</span>
                <span class="hq-kpi-icon" style="background:#f1f5f9;color:#334155;"><i class="fas fa-stopwatch"></i></span>
            </div>
            <div class="hq-kpi-val">{{ $avgTurnaroundLabel }}</div>
            <div class="hq-kpi-sub">{{ $kpis['active_formations'] }} active reporting formations</div>
        </div>
    </div>

    {{-- Row 1: 12-Month Multi-Series Trend --}}
    <div class="redas-card" style="margin-bottom:20px;">
        <div class="card-head">
            <div class="card-head-title">
                <div class="card-head-icon" style="background:#ede9fe;color:#6d28d9;">
                    <i class="fas fa-chart-line"></i>
                </div>
                Submission, Approval &amp; Return Trend (Last 12 Months)
            </div>
            <span style="font-size:.76rem;color:#64748b;">Live monthly volume across selected scope</span>
        </div>
        <div class="card-body">
            <div class="hq-chart-wrap" style="height:260px;">
                <canvas id="trendChart" data-chart-managed="page"></canvas>
            </div>
        </div>
    </div>

    {{-- Row 2: Status Distribution & Workflow Pipeline Funnel --}}
    <div class="hq-chart-grid-2">
        <div class="redas-card">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:#dcfce7;color:#15803d;">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    Status Distribution
                </div>
                <span style="font-size:.75rem;color:#64748b;">{{ $kpis['total_returns'] }} total</span>
            </div>
            <div class="card-body">
                @if($kpis['total_returns'] > 0)
                    <div class="hq-chart-wrap" style="height:260px;">
                        <canvas id="statusChart" data-chart-managed="page"></canvas>
                    </div>
                @else
                    <div style="height:260px;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#94a3b8;gap:8px;">
                        <i class="fas fa-chart-pie" style="font-size:2rem;opacity:.45;"></i>
                        <span style="font-size:.85rem;">No returns match the current filter selection.</span>
                    </div>
                @endif
            </div>
        </div>

        <div class="redas-card">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:#dbeafe;color:#1d4ed8;">
                        <i class="fas fa-diagram-project"></i>
                    </div>
                    Workflow Pipeline by Current Stage
                </div>
                <span style="font-size:.75rem;color:#64748b;">Live stage distribution</span>
            </div>
            <div class="card-body">
                <div class="hq-chart-wrap" style="height:260px;">
                    <canvas id="stageChart" data-chart-managed="page"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 3: Zonal Command Performance & Category Mix --}}
    <div class="hq-chart-grid-2">
        <div class="redas-card">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:#fef3c7;color:#b45309;">
                        <i class="fas fa-map-location-dot"></i>
                    </div>
                    Zonal Command Performance (Zone A – Zone H)
                </div>
            </div>
            <div class="card-body">
                <div class="hq-chart-wrap" style="height:270px;">
                    <canvas id="zonalChart" data-chart-managed="page"></canvas>
                </div>
            </div>
        </div>

        <div class="redas-card">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:#e0f2fe;color:#0369a1;">
                        <i class="fas fa-building-columns"></i>
                    </div>
                    Returns &amp; Section Coverage by Directorate
                </div>
                <span style="font-size:.74rem;color:#64748b;">Direct + State/Zonal section data</span>
            </div>
            <div class="card-body">
                <div class="hq-chart-wrap" style="height:270px;">
                    <canvas id="directorateChart" data-chart-managed="page"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 4: Zonal Command Compliance & Consolidation Matrix --}}
    <div class="redas-card" style="margin-bottom:20px;">
        <div class="card-head">
            <div class="card-head-title">
                <div class="card-head-icon" style="background:#ecfdf5;color:#15803d;">
                    <i class="fas fa-globe-africa"></i>
                </div>
                Zonal Command Reporting &amp; Consolidation Matrix (Zone A – Zone H)
            </div>
            <a href="{{ route('admin.consolidated.index') }}" class="btn-nis btn-ghost btn-sm hq-no-print">
                <i class="fas fa-file-signature"></i> Consolidated Approvals
            </a>
        </div>
        <div class="card-body no-pad">
            <div class="table-responsive">
                <table class="nis-table" style="width:100%;min-width:780px;">
                    <thead>
                        <tr>
                            <th>Zone</th>
                            <th style="text-align:center;">Reporting Units</th>
                            <th style="text-align:center;">Total Returns</th>
                            <th style="text-align:center;">Approved</th>
                            <th style="text-align:center;">In Flight</th>
                            <th style="text-align:center;">Returned</th>
                            <th style="min-width:150px;">Approval Rate</th>
                            <th>Consolidated Report</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($zonalMatrix as $z)
                            @php
                                $cStatus = $z['consolidated_status'];
                                $cBadge = match ($cStatus) {
                                    'approved' => 'background:#ecfdf5;color:#15803d;',
                                    'submitted' => 'background:#eff6ff;color:#1d4ed8;',
                                    'returned' => 'background:#fef2f2;color:#b91c1c;',
                                    'draft' => 'background:#f1f5f9;color:#475569;',
                                    default => 'background:#f8fafc;color:#94a3b8;',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <div style="font-weight:700;color:#0f172a;">{{ $z['code'] }}</div>
                                    <div style="font-size:.74rem;color:#64748b;">{{ $z['name'] }}</div>
                                </td>
                                <td style="text-align:center;font-weight:600;">{{ $z['reporting_units'] }}</td>
                                <td style="text-align:center;font-weight:700;">{{ $z['total'] }}</td>
                                <td style="text-align:center;color:#15803d;font-weight:700;">{{ $z['approved'] }}</td>
                                <td style="text-align:center;color:#b45309;font-weight:600;">{{ $z['pending'] }}</td>
                                <td style="text-align:center;color:#b91c1c;font-weight:600;">{{ $z['returned'] }}</td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div class="hq-progress-track">
                                            <div class="hq-progress-fill" style="width:{{ min(100, $z['approval_rate']) }}%;"></div>
                                        </div>
                                        <span style="font-size:.76rem;font-weight:700;color:#334155;min-width:42px;">{{ $z['approval_rate'] }}%</span>
                                    </div>
                                </td>
                                <td>
                                    @if($cStatus)
                                        <span class="hq-pill" style="{{ $cBadge }}">
                                            {{ ucfirst($cStatus) }}
                                        </span>
                                        <div style="font-size:.71rem;color:#64748b;margin-top:2px;">{{ $z['consolidated_ref'] }}</div>
                                    @else
                                        <span style="font-size:.76rem;color:#94a3b8;">Not submitted</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Row 5: Formation Leaderboard & Workflow Turnaround / Bottlenecks --}}
    <div class="hq-chart-grid-2">
        <div class="redas-card">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:#e0f2fe;color:#0369a1;">
                        <i class="fas fa-ranking-star"></i>
                    </div>
                    Top Reporting Formations
                </div>
                <a href="{{ route('admin.hq.returns') }}" class="btn-nis btn-ghost btn-sm hq-no-print">All Returns</a>
            </div>
            <div class="card-body no-pad">
                <div class="table-responsive">
                    <table class="nis-table" style="width:100%;min-width:520px;">
                        <thead>
                            <tr>
                                <th>Formation / Command</th>
                                <th>Zone</th>
                                <th style="text-align:center;">Total</th>
                                <th style="text-align:center;">Approved</th>
                                <th>Approval %</th>
                                <th>Latest</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($formationLeaderboard as $row)
                                <tr>
                                    <td>
                                        <div style="font-weight:700;color:#0f172a;">{{ $row['name'] }}</div>
                                        <div style="font-size:.72rem;color:#64748b;">{{ $row['scope_code'] }} · {{ $row['category'] }}</div>
                                    </td>
                                    <td style="font-size:.78rem;color:#475569;">{{ $row['zone'] }}</td>
                                    <td style="text-align:center;font-weight:700;">{{ $row['total'] }}</td>
                                    <td style="text-align:center;color:#15803d;font-weight:700;">{{ $row['approved'] }}</td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:6px;">
                                            <div class="hq-progress-track" style="max-width:70px;">
                                                <div class="hq-progress-fill" style="width:{{ min(100, $row['approval_rate']) }}%;"></div>
                                            </div>
                                            <span style="font-size:.74rem;font-weight:700;">{{ $row['approval_rate'] }}%</span>
                                        </div>
                                    </td>
                                    <td style="font-size:.75rem;color:#64748b;white-space:nowrap;">{{ $row['last_submitted'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="text-align:center;color:#94a3b8;padding:24px;">No formation submissions recorded for this filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="redas-card">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:#fef3c7;color:#b45309;">
                        <i class="fas fa-stopwatch"></i>
                    </div>
                    Workflow Stage Turnaround &amp; Bottlenecks
                </div>
            </div>
            <div class="card-body no-pad">
                <div class="table-responsive">
                    <table class="nis-table" style="width:100%;min-width:480px;">
                        <thead>
                            <tr>
                                <th>Stage Reached</th>
                                <th style="text-align:center;">Samples</th>
                                <th style="text-align:right;">Avg. Time</th>
                                <th style="text-align:right;">Min / Max</th>
                                <th style="text-align:center;">SLA Health</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($turnaround as $row)
                                @php
                                    $fmt = fn (float $h) => $h >= 24 ? round($h / 24, 1) . 'd' : $h . 'h';
                                    $healthStyle = match ($row['health']) {
                                        'Fast' => 'background:#ecfdf5;color:#15803d;',
                                        'Moderate' => 'background:#fef3c7;color:#92400e;',
                                        default => 'background:#fef2f2;color:#b91c1c;',
                                    };
                                @endphp
                                <tr>
                                    <td style="font-weight:700;color:#0f172a;">{{ $row['stage'] }}</td>
                                    <td style="text-align:center;color:#475569;">{{ $row['samples'] }}</td>
                                    <td style="text-align:right;font-weight:700;">{{ $fmt((float) $row['avg_hours']) }}</td>
                                    <td style="text-align:right;font-size:.76rem;color:#64748b;">
                                        {{ $fmt((float) $row['min_hours']) }} / {{ $fmt((float) $row['max_hours']) }}
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="hq-pill" style="{{ $healthStyle }}">{{ $row['health'] }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="text-align:center;color:#94a3b8;padding:24px;">Not enough workflow transitions to compute turnaround times yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 6: Recent Consolidated Reports Oversight & Category Mix --}}
    <div class="hq-chart-grid-2">
        <div class="redas-card">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:#ede9fe;color:#6d28d9;">
                        <i class="fas fa-file-signature"></i>
                    </div>
                    Consolidated Reports Oversight
                </div>
                <a href="{{ route('admin.consolidated.index') }}" class="btn-nis btn-ghost btn-sm hq-no-print">Manage All</a>
            </div>
            <div class="card-body no-pad">
                <div class="table-responsive">
                    <table class="nis-table" style="width:100%;min-width:500px;">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Scope</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th class="hq-no-print">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentConsolidated as $rep)
                                @php
                                    $rStyle = match ($rep->status) {
                                        'approved' => 'background:#ecfdf5;color:#15803d;',
                                        'submitted' => 'background:#eff6ff;color:#1d4ed8;',
                                        'returned' => 'background:#fef2f2;color:#b91c1c;',
                                        default => 'background:#f1f5f9;color:#475569;',
                                    };
                                @endphp
                                <tr>
                                    <td style="font-weight:700;">{{ $rep->reference }}</td>
                                    <td>{{ strtoupper($rep->scope_code) }} <span style="font-size:.72rem;color:#64748b;">({{ ucfirst($rep->level) }})</span></td>
                                    <td>{{ $rep->report_period }}</td>
                                    <td>
                                        <span class="hq-pill" style="{{ $rStyle }}">{{ ucfirst($rep->status) }}</span>
                                        @if($rep->is_stale)
                                            <span class="hq-pill" style="background:#fef3c7;color:#92400e;margin-left:4px;">Stale</span>
                                        @endif
                                    </td>
                                    <td class="hq-no-print">
                                        <a href="{{ route('admin.consolidated.show', $rep) }}" class="btn-nis btn-outline-nis btn-sm">Review</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="text-align:center;color:#94a3b8;padding:24px;">No consolidated reports found for this filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="redas-card">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:#f0fdf4;color:#15803d;">
                        <i class="fas fa-sitemap"></i>
                    </div>
                    Submission Mix by Formation Tier
                </div>
            </div>
            <div class="card-body">
                @if($kpis['total_returns'] > 0)
                    <div class="hq-chart-wrap" style="height:240px;">
                        <canvas id="categoryChart" data-chart-managed="page"></canvas>
                    </div>
                @else
                    <div style="height:240px;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#94a3b8;gap:8px;">
                        <i class="fas fa-sitemap" style="font-size:2rem;opacity:.45;"></i>
                        <span style="font-size:.85rem;">No formation category data available for this filter.</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</main>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (!window.Chart) return;

    const trendData = @json($trendChart);
    const statusData = @json($statusChart);
    const stageData = @json($stageChart);
    const zonalData = @json($zonalChart);
    const directorateData = @json($directorateChart);
    const categoryData = @json($categoryChart);

    const commonOpts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            tooltip: {
                backgroundColor: '#0f172a',
                padding: 10,
                cornerRadius: 8,
            },
        },
    };

    const trendEl = document.getElementById('trendChart');
    if (trendEl) {
        new Chart(trendEl, {
            type: 'line',
            data: {
                labels: trendData.labels,
                datasets: [
                    {
                        label: 'Total Submitted',
                        data: trendData.submitted,
                        borderColor: '#006838',
                        backgroundColor: 'rgba(0, 104, 56, 0.10)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                    },
                    {
                        label: 'Approved',
                        data: trendData.approved,
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22, 163, 74, 0.06)',
                        fill: false,
                        tension: 0.35,
                        pointRadius: 3,
                    },
                    {
                        label: 'Returned / Queried',
                        data: trendData.returned,
                        borderColor: '#dc2626',
                        backgroundColor: 'rgba(220, 38, 38, 0.06)',
                        borderDash: [4, 4],
                        fill: false,
                        tension: 0.35,
                        pointRadius: 3,
                    },
                ],
            },
            options: {
                ...commonOpts,
                plugins: { ...commonOpts.plugins, legend: { position: 'top', align: 'end' } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(15,23,42,0.05)' } },
                    x: { grid: { display: false } },
                },
            },
        });
    }

    const statusEl = document.getElementById('statusChart');
    if (statusEl) {
        new Chart(statusEl, {
            type: 'doughnut',
            data: {
                labels: statusData.labels,
                datasets: [{
                    data: statusData.data,
                    backgroundColor: ['#15803d', '#d97706', '#dc2626'],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 6,
                }],
            },
            options: {
                ...commonOpts,
                cutout: '64%',
                plugins: {
                    ...commonOpts.plugins,
                    legend: { position: 'bottom' },
                },
            },
        });
    }

    const stageEl = document.getElementById('stageChart');
    if (stageEl) {
        new Chart(stageEl, {
            type: 'bar',
            data: {
                labels: stageData.labels,
                datasets: [{
                    label: 'Returns at Stage',
                    data: stageData.data,
                    backgroundColor: ['#64748b', '#0284c7', '#7c3aed', '#0891b2', '#d97706', '#15803d'],
                    borderRadius: 6,
                }],
            },
            options: {
                ...commonOpts,
                indexAxis: 'y',
                plugins: { ...commonOpts.plugins, legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(15,23,42,0.05)' } },
                    y: { grid: { display: false } },
                },
            },
        });
    }

    const zonalEl = document.getElementById('zonalChart');
    if (zonalEl) {
        new Chart(zonalEl, {
            type: 'bar',
            data: {
                labels: zonalData.labels,
                datasets: [
                    { label: 'Approved', data: zonalData.approved, backgroundColor: '#15803d', borderRadius: 4 },
                    { label: 'In Flight', data: zonalData.pending, backgroundColor: '#d97706', borderRadius: 4 },
                    { label: 'Returned', data: zonalData.returned, backgroundColor: '#dc2626', borderRadius: 4 },
                ],
            },
            options: {
                ...commonOpts,
                plugins: { ...commonOpts.plugins, legend: { position: 'bottom' } },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(15,23,42,0.05)' } },
                },
            },
        });
    }

    const dirEl = document.getElementById('directorateChart');
    if (dirEl) {
        new Chart(dirEl, {
            type: 'bar',
            data: {
                labels: directorateData.labels,
                datasets: [
                    { label: 'Direct Directorate Returns', data: directorateData.direct, backgroundColor: '#006838', borderRadius: 4 },
                    { label: 'State/Zonal Section Coverage', data: directorateData.state_coverage, backgroundColor: '#0ea5e9', borderRadius: 4 },
                ],
            },
            options: {
                ...commonOpts,
                indexAxis: 'y',
                plugins: { ...commonOpts.plugins, legend: { position: 'bottom' } },
                scales: {
                    x: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(15,23,42,0.05)' } },
                    y: { stacked: true, grid: { display: false } },
                },
            },
        });
    }

    const catEl = document.getElementById('categoryChart');
    if (catEl) {
        new Chart(catEl, {
            type: 'doughnut',
            data: {
                labels: categoryData.labels,
                datasets: [{
                    data: categoryData.data,
                    backgroundColor: ['#006838', '#0284c7', '#7c3aed'],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 6,
                }],
            },
            options: {
                ...commonOpts,
                cutout: '62%',
                plugins: { ...commonOpts.plugins, legend: { position: 'bottom' } },
            },
        });
    }
});
</script>
@endpush

@endsection
