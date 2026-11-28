@extends('super-admin.layout')

@section('title', 'Super Admin Dashboard')

@section('content')
<style>
    .sa-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }
    .sa-kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 16px 18px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
        display: flex;
        flex-direction: column;
        gap: 8px;
        color: inherit;
    }

    .sa-kpi-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }
    .sa-kpi-label {
        font-size: .74rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #64748b;
    }
    .sa-kpi-icon {
        width: 32px;
        height: 32px;
        border-radius: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: .85rem;
    }
    .sa-kpi-value {
        font-size: 1.75rem;
        font-weight: 500;
        color: #1e293b;
        line-height: 1.1;
    }
    .sa-kpi-sub {
        font-size: .76rem;
        color: #64748b;
    }
    .sa-section-title {
        font-size: .78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #475569;
        margin: 0 0 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .sa-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 600;
    }
    .sa-badge.ok { background: #dcfce7; color: #15803d; }
    .sa-badge.warn { background: #fef3c7; color: #b45309; }
    .sa-badge.danger { background: #fee2e2; color: #b91c1c; }
    .sa-badge.info { background: #e0f2fe; color: #0369a1; }
    .sa-badge.muted { background: #f1f5f9; color: #475569; }
</style>

<main class="redas-content">
    <div class="page-header" style="margin-bottom:20px;">
        <div>
            <h1 class="page-title">Super Admin</h1>
            <p class="page-subtitle">National governance, identity &amp; access management, security auditing, and consolidated reporting oversight.</p>
        </div>
    </div>

    {{-- Row 1: Identity, Security & Governance KPIs --}}
    <div class="sa-section-title"><i class="fas fa-shield-halved text-nis"></i> Identity &amp; Security Governance</div>
    <div class="sa-kpi-grid">
        <div class="sa-kpi-card blue">
            <div class="sa-kpi-top">
                <span class="sa-kpi-label">Total Users</span>
                <span class="sa-kpi-icon" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-users"></i></span>
            </div>
            <div class="sa-kpi-value">{{ number_format($governance['total_users']) }}</div>
            <div class="sa-kpi-sub">{{ number_format($governance['active_users']) }} active &bull; {{ number_format($governance['disabled_users']) }} disabled</div>
        </div>

        <div class="sa-kpi-card green">
            <div class="sa-kpi-top">
                <span class="sa-kpi-label">MFA Adoption</span>
                <span class="sa-kpi-icon" style="background:#dcfce7;color:#15803d;"><i class="fas fa-mobile-screen-button"></i></span>
            </div>
            <div class="sa-kpi-value">{{ $governance['mfa_rate'] }}%</div>
            <div class="sa-kpi-sub">{{ number_format($governance['mfa_enabled_users']) }} of {{ number_format($governance['total_users']) }} Google Authenticator enrolled</div>
        </div>

        <div class="sa-kpi-card amber">
            <div class="sa-kpi-top">
                <span class="sa-kpi-label">Pending Password Change</span>
                <span class="sa-kpi-icon" style="background:#fef3c7;color:#b45309;"><i class="fas fa-key"></i></span>
            </div>
            <div class="sa-kpi-value">{{ number_format($governance['pending_password_changes']) }}</div>
            <div class="sa-kpi-sub">First-login accounts awaiting password reset</div>
        </div>

        <div class="sa-kpi-card red">
            <div class="sa-kpi-top">
                <span class="sa-kpi-label">Security Events (24h)</span>
                <span class="sa-kpi-icon" style="background:#fee2e2;color:#b91c1c;"><i class="fas fa-triangle-exclamation"></i></span>
            </div>
            <div class="sa-kpi-value">{{ number_format($governance['security_alerts_24h']) }}</div>
            <div class="sa-kpi-sub">WAF blocks, lockouts &amp; failed attempts</div>
        </div>
    </div>

    {{-- Row 2: National Reporting & Consolidation KPIs --}}
    <div class="sa-section-title"><i class="fas fa-chart-bar text-nis"></i> National Reporting &amp; Consolidation Oversight</div>
    <div class="sa-kpi-grid">
        <div class="sa-kpi-card blue">
            <div class="sa-kpi-top">
                <span class="sa-kpi-label">Original Returns</span>
                <span class="sa-kpi-icon" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-file-lines"></i></span>
            </div>
            <div class="sa-kpi-value">{{ number_format($stats['total']) }}</div>
            <div class="sa-kpi-sub">All formations &amp; reporting periods</div>
        </div>

        <div class="sa-kpi-card green">
            <div class="sa-kpi-top">
                <span class="sa-kpi-label">Approved Returns</span>
                <span class="sa-kpi-icon" style="background:#dcfce7;color:#15803d;"><i class="fas fa-circle-check"></i></span>
            </div>
            <div class="sa-kpi-value">{{ number_format($stats['approved']) }}</div>
            <div class="sa-kpi-sub">Cleared full approval chain</div>
        </div>

        <div class="sa-kpi-card purple">
            <div class="sa-kpi-top">
                <span class="sa-kpi-label">In Flight</span>
                <span class="sa-kpi-icon" style="background:#f3e8ff;color:#7c3aed;"><i class="fas fa-clock"></i></span>
            </div>
            <div class="sa-kpi-value">{{ number_format($stats['pending']) }}</div>
            <div class="sa-kpi-sub">Moving through review stages</div>
        </div>

        <div class="sa-kpi-card teal">
            <div class="sa-kpi-top">
                <span class="sa-kpi-label">Consolidated Reports</span>
                <span class="sa-kpi-icon" style="background:#ccfbf1;color:#0f766e;"><i class="fas fa-layer-group"></i></span>
            </div>
            <div class="sa-kpi-value">{{ number_format($governance['consolidated_total']) }}</div>
            <div class="sa-kpi-sub">{{ $governance['consolidated_approved'] }} approved &bull; {{ $governance['consolidated_submitted'] }} submitted</div>
        </div>

        <div class="sa-kpi-card amber">
            <div class="sa-kpi-top">
                <span class="sa-kpi-label">Avg. Turnaround</span>
                <span class="sa-kpi-icon" style="background:#fef3c7;color:#b45309;"><i class="fas fa-stopwatch"></i></span>
            </div>
            <div class="sa-kpi-value">{{ $stats['avg_turnaround_days'] !== null ? $stats['avg_turnaround_days'] . 'd' : '-' }}</div>
            <div class="sa-kpi-sub">{{ number_format($stats['returned']) }} returned for correction</div>
        </div>
    </div>

    {{-- Row 3: User Governance & Live Audit Log Control Center --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:20px;margin-bottom:20px;">
        <div class="redas-card">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-users-cog"></i></div>
                    User Accounts &amp; Access Control
                </div>
                <div style="display:flex;gap:10px;align-items:center;">
                    <a href="{{ route('admin.users.create') }}" style="font-size:.78rem;color:var(--nis-600);font-weight:700;text-decoration:none;">+ New User</a>
                    <a href="{{ route('admin.users') }}" style="font-size:.78rem;color:#475569;font-weight:600;text-decoration:none;">Manage all <i class="fas fa-arrow-right" style="font-size:.65rem;"></i></a>
                </div>
            </div>
            <div class="card-body no-pad">
                <div class="table-responsive">
                    <table class="redas-table" style="min-width:480px;">
                        <thead>
                            <tr>
                                <th>Service No</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>MFA / Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentUsers as $u)
                                <tr>
                                    <td style="font-family:monospace;font-weight:700;">{{ $u->service_number ?? '-' }}</td>
                                    <td>
                                        <div style="font-weight:600;color:#0f172a;">{{ $u->name }}</div>
                                        <div style="font-size:.74rem;color:#64748b;">{{ $u->email }}</div>
                                    </td>
                                    <td><span class="sa-badge info">{{ str_replace('_', ' ', $u->user_category ?? $u->role) }}</span></td>
                                    <td>
                                        @if($u->mfa_enabled)
                                            <span class="sa-badge ok"><i class="fas fa-check"></i> MFA</span>
                                        @else
                                            <span class="sa-badge warn">No MFA</span>
                                        @endif
                                        @if(! $u->is_enabled)
                                            <span class="sa-badge danger">Disabled</span>
                                        @endif
                                    </td>
                                    <td style="text-align:right;">
                                        <a href="{{ route('admin.users.edit', $u) }}" class="btn-nis btn-ghost" style="padding:5px 10px;font-size:.74rem;">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" style="text-align:center;color:var(--gray-400);padding:20px;">No user accounts found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="redas-card">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:#fee2e2;color:#b91c1c;"><i class="fas fa-shield-halved"></i></div>
                    Recent Immutable Audit Logs
                </div>
                <a href="{{ route('admin.audit-log.index') }}" style="font-size:.78rem;color:var(--nis-600);font-weight:600;text-decoration:none;">Full Audit Trail <i class="fas fa-arrow-right" style="font-size:.65rem;"></i></a>
            </div>
            <div class="card-body no-pad">
                <div class="table-responsive">
                    <table class="redas-table" style="min-width:460px;">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Actor</th>
                                <th>Action</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentAuditLogs as $log)
                                <tr>
                                    <td style="font-size:.76rem;color:#64748b;white-space:nowrap;">{{ optional($log->created_at)->format('d M H:i') }}</td>
                                    <td style="font-size:.8rem;font-weight:600;">{{ $log->user->service_number ?? $log->user->name ?? 'System / Guest' }}</td>
                                    <td style="font-family:monospace;font-size:.76rem;">{{ $log->action }}</td>
                                    <td>
                                        @php
                                            $cls = match($log->status) {
                                                'success' => 'ok',
                                                'blocked', 'failure' => 'danger',
                                                default => 'muted',
                                            };
                                        @endphp
                                        <span class="sa-badge {{ $cls }}">{{ $log->status }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" style="text-align:center;color:var(--gray-400);padding:20px;">No audit events recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 4: Executive Analytics Charts --}}
    <div class="redas-card" style="margin-bottom:20px;">
        <div class="card-head">
            <div class="card-head-title">
                <div class="card-head-icon" style="background:#ede9fe;color:#6d28d9;">
                    <i class="fas fa-chart-line"></i>
                </div>
                Submissions vs Approvals (Last 12 Months)
            </div>
        </div>
        <div class="card-body">
            <div style="position:relative;height:250px;width:100%;">
                <canvas id="trendChart" data-chart-managed="page"></canvas>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin-bottom:20px;">
        <div class="redas-card">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:#dcfce7;color:#15803d;">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    Status Distribution
                </div>
            </div>
            <div class="card-body">
                @if(array_sum($statusChart['data']) > 0)
                    <div style="position:relative;height:250px;width:100%;">
                        <canvas id="statusChart" data-chart-managed="page"></canvas>
                    </div>
                @else
                    <div style="height:250px;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#94a3b8;gap:8px;">
                        <i class="fas fa-chart-pie" style="font-size:2rem;opacity:.45;"></i>
                        <span style="font-size:.84rem;">No return status data recorded yet.</span>
                    </div>
                @endif
            </div>
        </div>

        <div class="redas-card">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:#e0f2fe;color:#0369a1;">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    Category Split
                </div>
            </div>
            <div class="card-body">
                @if(array_sum($categoryChart['data']) > 0)
                    <div style="position:relative;height:250px;width:100%;">
                        <canvas id="categoryChart" data-chart-managed="page"></canvas>
                    </div>
                @else
                    <div style="height:250px;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#94a3b8;gap:8px;">
                        <i class="fas fa-layer-group" style="font-size:2rem;opacity:.45;"></i>
                        <span style="font-size:.84rem;">No category submissions recorded yet.</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin-bottom:20px;">
        <div class="redas-card">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:#fef3c7;color:#b45309;">
                        <i class="fas fa-building"></i>
                    </div>
                    Returns per Directorate
                </div>
            </div>
            <div class="card-body">
                <div style="position:relative;height:260px;width:100%;">
                    <canvas id="directorateChart" data-chart-managed="page"></canvas>
                </div>
            </div>
        </div>

        <div class="redas-card">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:#fee2e2;color:#b91c1c;">
                        <i class="fas fa-map"></i>
                    </div>
                    Returns per Zone
                </div>
            </div>
            <div class="card-body">
                <div style="position:relative;height:260px;width:100%;">
                    <canvas id="zoneChart" data-chart-managed="page"></canvas>
                </div>
            </div>
        </div>

        <div class="redas-card">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:#e0e7ff;color:#4338ca;">
                        <i class="fas fa-location-dot"></i>
                    </div>
                    Top 10 State Commands
                </div>
            </div>
            <div class="card-body">
                @if(count($stateChart['labels']) > 0)
                    <div style="position:relative;height:260px;width:100%;">
                        <canvas id="stateChart" data-chart-managed="page"></canvas>
                    </div>
                @else
                    <div style="height:260px;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#94a3b8;gap:8px;">
                        <i class="fas fa-location-dot" style="font-size:2rem;opacity:.45;"></i>
                        <span style="font-size:.84rem;">No state command returns submitted yet.</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="redas-card">
        <div class="card-head">
            <div class="card-head-title">
                <div class="card-head-icon" style="background:#dcfce7;color:#15803d;">
                    <i class="fas fa-circle-check"></i>
                </div>
                Latest Approved Returns
            </div>
            <a href="{{ route('superadmin.returns', ['status' => 'approved']) }}" style="font-size:.78rem;color:var(--nis-600);font-weight:600;text-decoration:none;">View all <i class="fas fa-arrow-right" style="font-size:.65rem;"></i></a>
        </div>
        <div class="card-body no-pad">
            <div class="table-responsive">
                <table class="redas-table" style="min-width:680px;">
                    <thead>
                        <tr>
                            <th>Return</th>
                            <th>Officer</th>
                            <th>Category</th>
                            <th>Period</th>
                            <th>Approved</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($latestApproved as $return)
                            <tr>
                                <td style="font-weight:600;">RET-{{ str_pad($return->id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td>{{ $return->user->name ?? '-' }}</td>
                                <td>{{ $return->displayCategory() }}</td>
                                <td>{{ $return->report_period ?? ($return->return_data['report_period'] ?? ($return->return_data['period'] ?? '-')) }}</td>
                                <td>{{ optional($return->updated_at)->format('d M Y') }}</td>
                                <td style="text-align:right;">
                                    <a href="{{ route('superadmin.returns.show', $return) }}" class="btn-nis btn-ghost" style="padding:6px 12px;font-size:.75rem;">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="text-align:center;color:var(--gray-400);padding:24px;">No approved returns yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (!window.Chart) return;

    const statusData = @json($statusChart);
    const categoryData = @json($categoryChart);
    const directorateData = @json($directorateChart);
    const zoneData = @json($zoneChart);
    const stateData = @json($stateChart);
    const trendData = @json($trendChart);

    const baseOpts = {
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
                        label: 'Submissions',
                        data: trendData.submissions,
                        borderColor: '#1a5632',
                        backgroundColor: 'rgba(26, 86, 50, 0.10)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                    },
                    {
                        label: 'Approvals',
                        data: trendData.approvals,
                        borderColor: '#b45309',
                        backgroundColor: 'rgba(180, 83, 9, 0.08)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                    },
                ],
            },
            options: {
                ...baseOpts,
                plugins: { ...baseOpts.plugins, legend: { position: 'bottom' } },
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
                    backgroundColor: ['#d97706', '#16a34a', '#dc2626'],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 6,
                }],
            },
            options: {
                ...baseOpts,
                cutout: '64%',
                plugins: { ...baseOpts.plugins, legend: { position: 'bottom' } },
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
                    backgroundColor: ['#0369a1', '#b45309', '#1a5632', '#6d28d9'],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 6,
                }],
            },
            options: {
                ...baseOpts,
                cutout: '56%',
                plugins: { ...baseOpts.plugins, legend: { position: 'bottom' } },
            },
        });
    }

    const dirEl = document.getElementById('directorateChart');
    if (dirEl) {
        new Chart(dirEl, {
            type: 'bar',
            data: {
                labels: directorateData.labels,
                datasets: [{
                    label: 'Returns',
                    data: directorateData.data,
                    backgroundColor: '#1a5632',
                    borderRadius: 6,
                }],
            },
            options: {
                ...baseOpts,
                indexAxis: 'y',
                plugins: { ...baseOpts.plugins, legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(15,23,42,0.05)' } },
                    y: { grid: { display: false } },
                },
            },
        });
    }

    const zoneEl = document.getElementById('zoneChart');
    if (zoneEl) {
        new Chart(zoneEl, {
            type: 'bar',
            data: {
                labels: zoneData.labels,
                datasets: [{
                    label: 'Returns',
                    data: zoneData.data,
                    backgroundColor: '#b45309',
                    borderRadius: 6,
                }],
            },
            options: {
                ...baseOpts,
                plugins: { ...baseOpts.plugins, legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(15,23,42,0.05)' } },
                    x: { grid: { display: false } },
                },
            },
        });
    }

    const stateEl = document.getElementById('stateChart');
    if (stateEl) {
        new Chart(stateEl, {
            type: 'bar',
            data: {
                labels: stateData.labels,
                datasets: [{
                    label: 'Returns',
                    data: stateData.data,
                    backgroundColor: '#4338ca',
                    borderRadius: 6,
                }],
            },
            options: {
                ...baseOpts,
                indexAxis: 'y',
                plugins: { ...baseOpts.plugins, legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(15,23,42,0.05)' } },
                    y: { grid: { display: false } },
                },
            },
        });
    }
});
</script>
@endpush

@endsection
