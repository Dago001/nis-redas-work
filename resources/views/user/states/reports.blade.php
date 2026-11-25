@include('partials.header')

    <main class="redas-content">

        <!-- Page Header -->
        <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div>
                <h1 class="page-title">{{ $builderTitle ?? 'Generate Report' }}</h1>
                <p class="page-subtitle">{{ $builderSubtitle ?? 'Generate PDF, Excel, or CSV reports from your submitted returns.' }}</p>
            </div>
            <span style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;font-size:.74rem;font-weight:700;letter-spacing:.03em;{{ ($isPrsSuper ?? false) ? 'background:#006633;color:#ffffff;' : 'background:#e8f8ef;color:#166534;border:1px solid #bbf7d0;' }}">
                <i class="fas {{ ($isPrsSuper ?? false) ? 'fa-crown' : 'fa-building-shield' }}"></i>
                {{ $scopeBadge ?? 'COMMAND SCOPED' }}
            </span>
        </div>

        @if(session('status'))
        <div style="background:#dcfce7;border:1px solid #86efac;border-radius:var(--radius-md);padding:12px 18px;display:flex;align-items:center;gap:12px;margin-bottom:20px;">
            <i class="fas fa-check-circle" style="color:#15803d;"></i>
            <span style="font-size:.88rem;color:#166534;font-weight:600;">{{ session('status') }}</span>
        </div>
        @endif

        @if(session('error'))
        <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:var(--radius-md);padding:12px 18px;display:flex;align-items:center;gap:12px;margin-bottom:20px;">
            <i class="fas fa-exclamation-circle" style="color:#dc2626;"></i>
            <span style="font-size:.88rem;color:#991b1b;font-weight:600;">{{ session('error') }}</span>
        </div>
        @endif

        <!-- Active Reporting Scope Notice -->
        <div class="redas-card" style="margin-bottom:20px;border-left:4px solid {{ ($isPrsSuper ?? false) ? '#006633' : '#1d4ed8' }};">
            <div class="card-body" style="font-size:.82rem;color:var(--gray-600);padding:14px 18px;">
                <div style="display:flex;align-items:flex-start;gap:12px;">
                    <i class="fas {{ ($isPrsSuper ?? false) ? 'fa-globe-africa' : 'fa-shield-alt' }}" style="color:{{ ($isPrsSuper ?? false) ? '#006633' : '#1d4ed8' }};font-size:1.1rem;margin-top:2px;"></i>
                    <div>
                        <strong style="color:#0f172a;display:block;margin-bottom:3px;">
                            Active Scope: {{ $scopeLabel ?? 'Assigned Command' }}
                        </strong>
                        @if($isPrsSuper ?? false)
                            You have <strong>PRS Super &amp; Comprehensive</strong> reporting privileges. Filter across any NIS Zone (A-H), any of the 52 Commands, any Directorate, any CGIS Unit, or generate a nationwide consolidated export.
                        @else
                            All reports generated from this builder are strictly locked to <strong>{{ $scopeLabel ?? 'your assigned command' }}</strong>. Only authorised recipients should receive exported files in accordance with the <a href="{{ route('privacy') }}" target="_blank" style="color:#1d4ed8;text-decoration:underline;">Privacy Policy</a>.
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="grid-2" style="margin-bottom:24px;">

            <!-- Report Builder -->
            <div class="redas-card animate-fade-up">
                <div class="card-head">
                    <div class="card-head-title">
                        <div class="card-head-icon" style="background:var(--nis-50);color:var(--nis-600);"><i class="fas fa-file-export"></i></div>
                        {{ ($isPrsSuper ?? false) ? 'PRS Super Report Builder' : 'Command Report Builder' }}
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('user.reports.generate') }}" id="reportForm">
                        @csrf

                        <div class="fg" style="margin-bottom:14px;">
                            <label>Report Type</label>
                            <select name="report_type" class="ni ni-select" id="reportType" required>
                                <option value="">Select Report Type</option>
                                <option value="submission_summary">Submission Summary</option>
                                <option value="monthly_return">Monthly Return</option>
                                <option value="quarterly_return">Quarterly Return</option>
                                <option value="annual_return">Annual Return</option>
                                <option value="compliance_report">Compliance Report</option>
                                @if($isPrsSuper ?? false)
                                <option value="consolidated_summary">National Consolidated Summary (PRS)</option>
                                @endif
                                <option value="full_export">Full Indicator Data Export</option>
                            </select>
                        </div>

                        <div class="form-grid-2" style="margin-bottom:14px;">
                            <div class="fg">
                                <label>Date From</label>
                                <input type="date" name="date_from" class="ni" value="{{ now()->startOfYear()->format('Y-m-d') }}" required>
                            </div>
                            <div class="fg">
                                <label>Date To</label>
                                <input type="date" name="date_to" class="ni" value="{{ now()->format('Y-m-d') }}" required>
                            </div>
                        </div>

                        @if($isPrsSuper ?? false)
                            {{-- PRS Super & Comprehensive Multi-Command Filters --}}
                            <div style="padding:12px 14px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;margin-bottom:14px;">
                                <div style="font-size:.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#006633;margin-bottom:10px;">
                                    <i class="fas fa-sliders-h me-1"></i> PRS National Multi-Command Filters
                                </div>
                                <div class="form-grid-2" style="margin-bottom:10px;">
                                    <div class="fg">
                                        <label>Filter by NIS Zone</label>
                                        <select name="zone_code" class="ni ni-select">
                                            <option value="">All Zones (National)</option>
                                            @foreach($zoneOptions ?? [] as $zCode => $zLabel)
                                                <option value="{{ $zCode }}">{{ $zLabel }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="fg">
                                        <label>Filter by Specific Command</label>
                                        <select name="command_code" class="ni ni-select">
                                            <option value="">All 52 Commands</option>
                                            @foreach($groupedCommands ?? [] as $groupLabel => $cmds)
                                                <optgroup label="{{ $groupLabel }}">
                                                    @foreach($cmds as $cCode => $cLabel)
                                                        <option value="{{ $cCode }}">{{ $cLabel }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="form-grid-2">
                                    <div class="fg">
                                        <label>Filter by Directorate</label>
                                        <select name="directorate_code" class="ni ni-select">
                                            <option value="">All Directorates</option>
                                            @foreach($directorateOptions ?? [] as $dCode => $dLabel)
                                                <option value="{{ $dCode }}">{{ $dLabel }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="fg">
                                        <label>Workflow Status</label>
                                        <select name="status_filter" class="ni ni-select">
                                            <option value="all">All Statuses</option>
                                            <option value="approved">Approved Only</option>
                                            <option value="pending">Pending Review</option>
                                            <option value="returned">Returned for Correction</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        @elseif(! empty($zonalCommandOptions))
                            {{-- Zonal Commander: Filter strictly within their own Zone's commands --}}
                            <div class="form-grid-2" style="margin-bottom:14px;">
                                <div class="fg">
                                    <label>Command in Your Zone</label>
                                    <select name="command_code" class="ni ni-select">
                                        <option value="">All Commands in {{ $scopeLabel }}</option>
                                        @foreach($zonalCommandOptions as $cCode => $cLabel)
                                            <option value="{{ $cCode }}">{{ $cLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="fg">
                                    <label>Workflow Status</label>
                                    <select name="status_filter" class="ni ni-select">
                                        <option value="all">All Statuses</option>
                                        <option value="approved">Approved Only</option>
                                        <option value="pending">Pending Review</option>
                                        <option value="returned">Returned</option>
                                    </select>
                                </div>
                            </div>
                        @else
                            <div class="fg" style="margin-bottom:14px;">
                                <label>Workflow Status</label>
                                <select name="status_filter" class="ni ni-select">
                                    <option value="all">All Statuses</option>
                                    <option value="approved">Approved Only</option>
                                    <option value="pending">Pending Review</option>
                                    <option value="returned">Returned for Correction</option>
                                </select>
                            </div>
                        @endif

                        <div class="fg" style="margin-bottom:14px;">
                            <label>{{ ($sectionsLocked ?? false) ? 'Included Section (Locked to Your Unit)' : 'Sections to Include' }}</label>
                            @if($sectionsLocked ?? false)
                                <div style="display:flex;align-items:center;gap:8px;padding:10px 12px;border-radius:8px;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;font-size:.84rem;font-weight:600;margin-top:4px;">
                                    <i class="fas fa-lock"></i>
                                    @foreach($availableSections ?? [] as $key => $label)
                                        <input type="hidden" name="sections[]" value="{{ $key }}">
                                        <span>{{ $label }}</span>
                                    @endforeach
                                </div>
                            @else
                                <div id="sectionChecks" style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:6px;">
                                    @foreach($availableSections ?? [] as $key => $label)
                                    <label style="display:flex;align-items:center;gap:8px;font-size:.82rem;font-weight:500;cursor:pointer;padding:4px 0;">
                                        <input type="checkbox" name="sections[]" value="{{ $key }}" checked style="accent-color:var(--nis-600);">
                                        {{ $label }}
                                    </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="fg" style="margin-bottom:14px;">
                            <label>Output Format</label>
                            <div style="display:flex;gap:12px;margin-top:6px;">
                                <label style="display:flex;align-items:center;gap:8px;font-size:.84rem;cursor:pointer;">
                                    <input type="radio" name="format" value="pdf" checked style="accent-color:var(--nis-600);">
                                    <i class="fas fa-file-pdf" style="color:#dc2626;"></i> PDF
                                </label>
                                <label style="display:flex;align-items:center;gap:8px;font-size:.84rem;cursor:pointer;">
                                    <input type="radio" name="format" value="excel" style="accent-color:var(--nis-600);">
                                    <i class="fas fa-file-excel" style="color:#16a34a;"></i> Excel
                                </label>
                                <label style="display:flex;align-items:center;gap:8px;font-size:.84rem;cursor:pointer;">
                                    <input type="radio" name="format" value="csv" style="accent-color:var(--nis-600);">
                                    <i class="fas fa-file-csv" style="color:#2563eb;"></i> CSV
                                </label>
                            </div>
                        </div>

                        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-top:4px;">
                            @if(! ($sectionsLocked ?? false))
                            <button type="button" class="btn-nis btn-ghost btn-sm" onclick="toggleAllSections(false)">
                                <i class="fas fa-times-circle"></i> Deselect All
                            </button>
                            <button type="button" class="btn-nis btn-ghost btn-sm" onclick="toggleAllSections(true)">
                                <i class="fas fa-check-circle"></i> Select All
                            </button>
                            @endif
                            <button type="submit" class="btn-nis btn-primary-nis btn-sm" style="margin-left:auto;">
                                <i class="fas fa-download"></i> Generate Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Generate & Recent Reports -->
            <div style="display:flex;flex-direction:column;gap:20px;">

                <!-- Quick Generate -->
                <div class="redas-card animate-fade-up delay-1">
                    <div class="card-head">
                        <div class="card-head-title">
                            <div class="card-head-icon" style="background:var(--gold-100);color:var(--gold-500);"><i class="fas fa-bolt"></i></div>
                            {{ ($isPrsSuper ?? false) ? 'PRS Executive Quick Exports' : 'Command Quick Generate' }}
                        </div>
                    </div>
                    <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
                        @if($isPrsSuper ?? false)
                            <button type="button" class="btn-nis btn-outline-nis full-width" onclick="quickGenerate('consolidated_summary', 'pdf')">
                                <i class="fas fa-layer-group"></i> National Consolidated Summary - PDF
                            </button>
                            <button type="button" class="btn-nis btn-outline-nis full-width" onclick="quickGenerate('compliance_report', 'pdf')">
                                <i class="fas fa-clipboard-check"></i> Nationwide Compliance Report - PDF
                            </button>
                            <button type="button" class="btn-nis btn-ghost full-width" onclick="quickGenerate('full_export', 'excel')">
                                <i class="fas fa-file-excel" style="color:#16a34a;"></i> Full National Indicator Export - Excel
                            </button>
                            <button type="button" class="btn-nis btn-ghost full-width" onclick="quickGenerate('submission_summary', 'csv')">
                                <i class="fas fa-file-csv" style="color:#2563eb;"></i> All-Command Submission Register - CSV
                            </button>
                        @else
                            <button type="button" class="btn-nis btn-outline-nis full-width" onclick="quickGenerate('monthly_return', 'pdf')">
                                <i class="fas fa-file-alt"></i> This Month's Command Return - PDF
                            </button>
                            <button type="button" class="btn-nis btn-outline-nis full-width" onclick="quickGenerate('quarterly_return', 'pdf')">
                                <i class="fas fa-file-alt"></i> This Quarter's Command Return - PDF
                            </button>
                            <button type="button" class="btn-nis btn-ghost full-width" onclick="quickGenerate('full_export', 'excel')">
                                <i class="fas fa-file-excel" style="color:#16a34a;"></i> Command Full Year Export - Excel
                            </button>
                            <button type="button" class="btn-nis btn-ghost full-width" onclick="quickGenerate('submission_summary', 'pdf')">
                                <i class="fas fa-chart-bar"></i> Command Submission Summary - PDF
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Recent Generated -->
                <div class="redas-card animate-fade-up delay-2">
                    <div class="card-head">
                        <div class="card-head-title">
                            <div class="card-head-icon" style="background:var(--nis-50);color:var(--nis-600);"><i class="fas fa-history"></i></div>
                            Recently Generated
                        </div>
                    </div>
                    <div class="card-body no-pad">
                        @forelse($recentReports ?? [] as $rep)
                        <div style="display:flex;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid var(--gray-100);">
                            <i class="fas fa-file-{{ $rep->format === 'pdf' ? 'pdf' : ($rep->format === 'excel' ? 'excel' : 'csv') }}" style="color:var(--nis-600);font-size:1.2rem;flex-shrink:0;"></i>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:.82rem;font-weight:600;color:var(--gray-800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ ucwords(str_replace('_', ' ', $rep->report_type)) }}</div>
                                <div style="font-size:.72rem;color:var(--gray-400);">{{ strtoupper($rep->format) }} &bull; {{ ucfirst($rep->status) }} &bull; {{ $rep->created_at?->format('d M Y H:i') }}</div>
                            </div>
                            @if($rep->status === 'completed')
                            <a href="{{ route('user.reports.download', $rep) }}" class="btn-nis btn-ghost btn-sm" title="Download">
                                <i class="fas fa-download"></i>
                            </a>
                            @endif
                        </div>
                        @empty
                        <div style="padding:16px;color:var(--gray-500);font-size:.84rem;">No generated reports yet. Use the Report Builder to generate a PDF, Excel, or CSV report.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

    </main>

<script>
function toggleAllSections(state) {
    document.querySelectorAll('#sectionChecks input[type=checkbox]').forEach(cb => cb.checked = state);
}

function quickGenerate(type, format) {
    const form = document.getElementById('reportForm');
    const select = document.getElementById('reportType');
    if (select) select.value = type;
    if (format) {
        const radio = form?.querySelector(`input[name="format"][value="${format}"]`);
        if (radio) radio.checked = true;
    }
    if (form) form.submit();
}
</script>

@include('partials.footer')
