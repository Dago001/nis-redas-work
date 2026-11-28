@include('partials.header')

    <main class="redas-content">
        @php
            $scopeLevel = $scopeLevel ?? 'state';
            $isNational = $scopeLevel === 'national';
            $f = $activeFilters ?? [];
            $kpis = $kpiCounts ?? ['total' => 0, 'approved' => 0, 'pending' => 0, 'returned' => 0];
            $user = auth()->user();
            $canViewAdminShow = in_array($user?->user_category, ['super_admin', 'hq_admin', 'admin'], true);
            $canViewDeskShow = in_array($user?->user_category, ['desk_admin', 'zonal_commander', 'directorate_admin', 'cgis_desk_admin'], true);
        @endphp

        <div class="page-header" style="margin-bottom:18px;">
            <div>
                <h1 class="page-title">{{ $builderTitle ?? 'Report Builder' }}</h1>
                <p class="page-subtitle">{{ $builderSubtitle ?? 'Generate and export official NIS-REDAS reports within your assigned scope.' }}</p>
            </div>
        </div>

        @if(session('status'))
        <div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;margin-bottom:16px;">
            <i class="fas fa-check-circle" style="color:#15803d;"></i>
            <span style="font-size:.86rem;color:#166534;font-weight:600;">{{ session('status') }}</span>
        </div>
        @endif

        @if(session('error'))
        <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;margin-bottom:16px;">
            <i class="fas fa-exclamation-circle" style="color:#dc2626;"></i>
            <span style="font-size:.86rem;color:#991b1b;font-weight:600;">{{ session('error') }}</span>
        </div>
        @endif

        {{-- Scoped Summary KPIs --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:12px;margin-bottom:20px;">
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:13px 15px;display:flex;flex-direction:column;gap:6px;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b;">Matching Returns</span>
                    <span style="width:28px;height:28px;border-radius:8px;background:#e0f2fe;color:#0369a1;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;"><i class="fas fa-file-lines"></i></span>
                </div>
                <div style="font-size:1.25rem;font-weight:700;color:#0f172a;">{{ number_format($kpis['total']) }}</div>
            </div>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:13px 15px;display:flex;flex-direction:column;gap:6px;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b;">Approved</span>
                    <span style="width:28px;height:28px;border-radius:8px;background:#dcfce7;color:#15803d;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;"><i class="fas fa-circle-check"></i></span>
                </div>
                <div style="font-size:1.25rem;font-weight:700;color:#15803d;">{{ number_format($kpis['approved']) }}</div>
            </div>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:13px 15px;display:flex;flex-direction:column;gap:6px;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b;">Pending Review</span>
                    <span style="width:28px;height:28px;border-radius:8px;background:#fef3c7;color:#b45309;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;"><i class="fas fa-clock"></i></span>
                </div>
                <div style="font-size:1.25rem;font-weight:700;color:#b45309;">{{ number_format($kpis['pending']) }}</div>
            </div>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:13px 15px;display:flex;flex-direction:column;gap:6px;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b;">Returned</span>
                    <span style="width:28px;height:28px;border-radius:8px;background:#fee2e2;color:#b91c1c;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;"><i class="fas fa-rotate-left"></i></span>
                </div>
                <div style="font-size:1.25rem;font-weight:700;color:#b91c1c;">{{ number_format($kpis['returned']) }}</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:20px;margin-bottom:20px;align-items:start;">
            {{-- Left: Report Builder & Filter Form --}}
            <div class="redas-card" style="min-width:0;">
                <div class="card-head">
                    <div class="card-head-title">
                        <div class="card-head-icon" style="background:var(--nis-50);color:var(--nis-600);"><i class="fas fa-file-export"></i></div>
                        Build Report ({{ $scopeLabel ?? 'Assigned Scope' }})
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('user.reports.generate') }}" id="reportForm">
                        @csrf

                        <div class="form-grid-2" style="margin-bottom:14px;">
                            <div class="fg">
                                <label for="reportType">Report Type</label>
                                <select name="report_type" class="ni ni-select" id="reportType" required>
                                    <option value="submission_summary" selected>Submission Register / Summary</option>
                                    <option value="monthly_return">Monthly Return</option>
                                    <option value="quarterly_return">Quarterly Return</option>
                                    <option value="annual_return">Annual Return</option>
                                    <option value="compliance_report">Compliance Report</option>
                                    @if($isNational)
                                        <option value="consolidated_summary">National Consolidated Summary</option>
                                    @endif
                                    <option value="full_export">Full Indicator Data Export</option>
                                </select>
                            </div>
                            <div class="fg">
                                <label for="statusFilter">Workflow Status</label>
                                <select name="status_filter" id="statusFilter" class="ni ni-select">
                                    <option value="all" @selected(($f['status_filter'] ?? 'all') === 'all')>All Statuses</option>
                                    <option value="approved" @selected(($f['status_filter'] ?? '') === 'approved')>Approved Only</option>
                                    <option value="pending" @selected(($f['status_filter'] ?? '') === 'pending')>Pending Review</option>
                                    <option value="returned" @selected(($f['status_filter'] ?? '') === 'returned')>Returned for Correction</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid-2" style="margin-bottom:14px;">
                            <div class="fg">
                                <label for="dateFrom">Date From</label>
                                <input type="date" id="dateFrom" name="date_from" class="ni" value="{{ $f['date_from'] ?? now()->startOfYear()->format('Y-m-d') }}" required>
                            </div>
                            <div class="fg">
                                <label for="dateTo">Date To</label>
                                <input type="date" id="dateTo" name="date_to" class="ni" value="{{ $f['date_to'] ?? now()->format('Y-m-d') }}" required>
                            </div>
                        </div>

                        @if($isNational)
                            <div style="padding:12px 14px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;margin-bottom:14px;">
                                <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#475569;margin-bottom:10px;">
                                    Scope Filters (National Access)
                                </div>
                                <div class="form-grid-2" style="margin-bottom:10px;">
                                    <div class="fg">
                                        <label for="catFilter">Formation Category</label>
                                        <select name="category_filter" id="catFilter" class="ni ni-select">
                                            <option value="">All Categories</option>
                                            <option value="state" @selected(($f['category_filter'] ?? '') === 'state')>State Commands</option>
                                            <option value="zone" @selected(($f['category_filter'] ?? '') === 'zone')>Zonal Commands</option>
                                            <option value="directorate" @selected(($f['category_filter'] ?? '') === 'directorate')>Directorates</option>
                                            <option value="cgis" @selected(($f['category_filter'] ?? '') === 'cgis')>CGIS Office / Units</option>
                                        </select>
                                    </div>
                                    <div class="fg">
                                        <label for="zoneCode">NIS Zone</label>
                                        <select name="zone_code" id="zoneCode" class="ni ni-select">
                                            <option value="">All Zones (Zone A – H)</option>
                                            @foreach($zoneOptions ?? [] as $zCode => $zLabel)
                                                <option value="{{ $zCode }}" @selected(($f['zone_code'] ?? '') === $zCode)>{{ $zLabel }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="form-grid-2" style="margin-bottom:10px;">
                                    <div class="fg">
                                        <label for="cmdCode">State Command / Formation</label>
                                        <select name="command_code" id="cmdCode" class="ni ni-select">
                                            <option value="">All Formations</option>
                                            @foreach($groupedCommands ?? [] as $groupLabel => $cmds)
                                                <optgroup label="{{ $groupLabel }}">
                                                    @foreach($cmds as $cCode => $cLabel)
                                                        <option value="{{ $cCode }}" @selected(($f['command_code'] ?? '') === $cCode)>{{ $cLabel }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="fg">
                                        <label for="dirCode">Directorate</label>
                                        <select name="directorate_code" id="dirCode" class="ni ni-select">
                                            <option value="">All Directorates</option>
                                            @foreach($directorateOptions ?? [] as $dCode => $dLabel)
                                                <option value="{{ $dCode }}" @selected(($f['directorate_code'] ?? '') === $dCode)>{{ $dLabel }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="fg" style="margin-bottom:0;">
                                    <label for="cgisCode">CGIS Office / Unit</label>
                                    <select name="cgis_unit_code" id="cgisCode" class="ni ni-select">
                                        <option value="">All CGIS Units</option>
                                        @foreach($cgisUnitOptions ?? [] as $uCode => $uLabel)
                                            <option value="{{ $uCode }}" @selected(($f['cgis_unit_code'] ?? '') === $uCode)>{{ $uLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @elseif($scopeLevel === 'zone' && ! empty($zonalCommandOptions))
                            <div class="fg" style="margin-bottom:14px;">
                                <label for="zonalCmdCode">Command in Your Zone</label>
                                <select name="command_code" id="zonalCmdCode" class="ni ni-select">
                                    <option value="">All Commands in {{ $scopeLabel }}</option>
                                    @foreach($zonalCommandOptions as $cCode => $cLabel)
                                        <option value="{{ $cCode }}" @selected(($f['command_code'] ?? '') === $cCode)>{{ $cLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @elseif($scopeLevel === 'directorate' && ! empty($directorateSectionOptions))
                            <div class="fg" style="margin-bottom:14px;">
                                <label for="dirSectionCode">Section Under {{ $scopeLabel }}</label>
                                <select name="section_code" id="dirSectionCode" class="ni ni-select">
                                    <option value="">All Sections</option>
                                    @foreach($directorateSectionOptions as $secCode)
                                        <option value="{{ $secCode }}" @selected(($f['section_code'] ?? '') === $secCode)>{{ strtoupper($secCode) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @if(! ($sectionsLocked ?? false))
                            <div class="fg" style="margin-bottom:14px;">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                                    <label style="margin:0;">Sections to Include</label>
                                    <div style="display:flex;gap:8px;font-size:.74rem;">
                                        <button type="button" onclick="toggleAllSections(true)" style="background:none;border:none;color:var(--nis-600);font-weight:600;cursor:pointer;padding:0;">Select all</button>
                                        <span style="color:#cbd5e1;">|</span>
                                        <button type="button" onclick="toggleAllSections(false)" style="background:none;border:none;color:#64748b;font-weight:600;cursor:pointer;padding:0;">Clear</button>
                                    </div>
                                </div>
                                <div id="sectionChecks" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:6px;">
                                    @foreach($availableSections ?? [] as $key => $label)
                                    <label style="display:flex;align-items:center;gap:8px;font-size:.82rem;font-weight:500;cursor:pointer;padding:4px 0;text-transform:none;letter-spacing:0;">
                                        <input type="checkbox" name="sections[]" value="{{ $key }}" checked style="accent-color:var(--nis-600);">
                                        {{ $label }}
                                    </label>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            @foreach($availableSections ?? [] as $key => $label)
                                <input type="hidden" name="sections[]" value="{{ $key }}">
                            @endforeach
                        @endif

                        <div class="fg" style="margin-bottom:16px;">
                            <label>Export Format</label>
                            <div style="display:flex;gap:16px;margin-top:6px;flex-wrap:wrap;">
                                <label style="display:flex;align-items:center;gap:7px;font-size:.84rem;cursor:pointer;text-transform:none;letter-spacing:0;">
                                    <input type="radio" name="format" value="pdf" checked style="accent-color:var(--nis-600);">
                                    <i class="fas fa-file-pdf" style="color:#dc2626;"></i> PDF Document
                                </label>
                                <label style="display:flex;align-items:center;gap:7px;font-size:.84rem;cursor:pointer;text-transform:none;letter-spacing:0;">
                                    <input type="radio" name="format" value="excel" style="accent-color:var(--nis-600);">
                                    <i class="fas fa-file-excel" style="color:#16a34a;"></i> Excel (.xlsx)
                                </label>
                                <label style="display:flex;align-items:center;gap:7px;font-size:.84rem;cursor:pointer;text-transform:none;letter-spacing:0;">
                                    <input type="radio" name="format" value="csv" style="accent-color:var(--nis-600);">
                                    <i class="fas fa-file-csv" style="color:#2563eb;"></i> CSV Spreadsheet
                                </label>
                            </div>
                        </div>

                        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;padding-top:8px;border-top:1px solid #f1f5f9;">
                            <button type="button" class="btn-nis btn-outline-nis btn-sm" onclick="previewMatchingRecords()">
                                <i class="fas fa-filter"></i> Preview Matching Records
                            </button>
                            <a href="{{ route('user.reports') }}" class="btn-nis btn-ghost btn-sm">Reset</a>
                            <button type="submit" class="btn-nis btn-primary-nis btn-sm" style="margin-left:auto;">
                                <i class="fas fa-download"></i> Generate &amp; Download
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Right: Quick Exports & Recent Reports --}}
            <div style="display:flex;flex-direction:column;gap:20px;min-width:0;">
                <div class="redas-card">
                    <div class="card-head">
                        <div class="card-head-title">
                            <div class="card-head-icon" style="background:#f1f5f9;color:#334155;"><i class="fas fa-download"></i></div>
                            Quick Exports
                        </div>
                    </div>
                    <div class="card-body" style="display:flex;flex-direction:column;gap:8px;">
                        @if($isNational)
                            <button type="button" class="btn-nis btn-outline-nis btn-sm" style="justify-content:flex-start;" onclick="quickGenerate('consolidated_summary', 'pdf')">
                                <i class="fas fa-layer-group"></i> National Consolidated Summary (PDF)
                            </button>
                            <button type="button" class="btn-nis btn-outline-nis btn-sm" style="justify-content:flex-start;" onclick="quickGenerate('compliance_report', 'pdf')">
                                <i class="fas fa-clipboard-check"></i> Nationwide Compliance Report (PDF)
                            </button>
                            <button type="button" class="btn-nis btn-ghost btn-sm" style="justify-content:flex-start;" onclick="quickGenerate('full_export', 'excel')">
                                <i class="fas fa-file-excel" style="color:#16a34a;"></i> Full Indicator Export (Excel)
                            </button>
                            <button type="button" class="btn-nis btn-ghost btn-sm" style="justify-content:flex-start;" onclick="quickGenerate('submission_summary', 'csv')">
                                <i class="fas fa-file-csv" style="color:#2563eb;"></i> Submission Register (CSV)
                            </button>
                        @else
                            <button type="button" class="btn-nis btn-outline-nis btn-sm" style="justify-content:flex-start;" onclick="quickGenerate('monthly_return', 'pdf')">
                                <i class="fas fa-file-pdf" style="color:#dc2626;"></i> Monthly Return Report (PDF)
                            </button>
                            <button type="button" class="btn-nis btn-outline-nis btn-sm" style="justify-content:flex-start;" onclick="quickGenerate('compliance_report', 'pdf')">
                                <i class="fas fa-clipboard-check"></i> Compliance Summary (PDF)
                            </button>
                            <button type="button" class="btn-nis btn-ghost btn-sm" style="justify-content:flex-start;" onclick="quickGenerate('full_export', 'excel')">
                                <i class="fas fa-file-excel" style="color:#16a34a;"></i> Full Data Export (Excel)
                            </button>
                            <button type="button" class="btn-nis btn-ghost btn-sm" style="justify-content:flex-start;" onclick="quickGenerate('submission_summary', 'csv')">
                                <i class="fas fa-file-csv" style="color:#2563eb;"></i> Submission Register (CSV)
                            </button>
                        @endif
                    </div>
                </div>

                <div class="redas-card">
                    <div class="card-head">
                        <div class="card-head-title">
                            <div class="card-head-icon" style="background:var(--nis-50);color:var(--nis-600);"><i class="fas fa-history"></i></div>
                            Recently Generated
                        </div>
                    </div>
                    <div class="card-body no-pad">
                        @forelse($recentReports ?? [] as $rep)
                        <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid #f1f5f9;">
                            <i class="fas fa-file-{{ $rep->format === 'pdf' ? 'pdf' : ($rep->format === 'excel' ? 'excel' : 'csv') }}" style="color:var(--nis-600);font-size:1.1rem;flex-shrink:0;"></i>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:.82rem;font-weight:600;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ ucwords(str_replace('_', ' ', $rep->report_type)) }}</div>
                                <div style="font-size:.72rem;color:#64748b;">{{ strtoupper($rep->format) }} &bull; {{ ucfirst($rep->status) }} &bull; {{ $rep->created_at?->format('d M Y, H:i') }}</div>
                            </div>
                            @if($rep->status === 'completed')
                            <a href="{{ route('user.reports.download', $rep) }}" class="btn-nis btn-ghost btn-sm" title="Download">
                                <i class="fas fa-download"></i>
                            </a>
                            @endif
                        </div>
                        @empty
                        <div style="padding:16px;color:#64748b;font-size:.82rem;">No reports generated yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Live Scoped Returns Preview Table --}}
        <div class="redas-card">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:#ede9fe;color:#6d28d9;"><i class="fas fa-table-list"></i></div>
                    Scoped Returns Included in Report ({{ number_format($previewReturns->total()) }})
                </div>
                <span style="font-size:.76rem;color:#64748b;">Showing returns strictly within {{ $scopeLabel ?? 'your scope' }}</span>
            </div>
            <div class="card-body no-pad">
                <div class="table-responsive">
                    <table class="nis-table" style="min-width:760px;">
                        <thead>
                            <tr>
                                <th>Ref</th>
                                <th>Formation</th>
                                <th>Category</th>
                                <th>Officer</th>
                                <th>Period</th>
                                <th>Stage</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($previewReturns as $return)
                                @php
                                    $rData = is_array($return->return_data) ? $return->return_data : [];
                                    $badge = match ($return->status) {
                                        'approved' => 'badge-approved',
                                        'returned', 'rejected' => 'badge-rejected',
                                        default => 'badge-pending',
                                    };
                                    $viewUrl = match (true) {
                                        $user?->user_category === 'super_admin' => route('superadmin.returns.show', $return),
                                        in_array($user?->user_category, ['hq_admin', 'admin'], true) => route('admin.hq.returns.show', $return),
                                        $canViewDeskShow => route('desk.admin.submissions.show', $return),
                                        $user?->user_category === 'directorate_user' => route('user.directorates.submissions.show', $return),
                                        $user?->user_category === 'cgis_unit_user' => route('user.cgis-units.submissions.show', $return),
                                        default => route('user.submissions.pdf', $return),
                                    };
                                @endphp
                                <tr>
                                    <td style="font-weight:700;color:#0f172a;">RET-{{ str_pad($return->id, 5, '0', STR_PAD_LEFT) }}</td>
                                    <td style="font-weight:600;text-transform:uppercase;">{{ strtoupper((string) ($rData['command_name'] ?? $return->scope_code ?? '-')) }}</td>
                                    <td>{{ $return->displayCategory() }}</td>
                                    <td>{{ $rData['reporting_officer'] ?? $return->user?->name ?? '-' }}</td>
                                    <td>{{ $return->report_period ?? ($rData['report_period'] ?? ($rData['period'] ?? '-')) }}</td>
                                    <td style="text-transform:capitalize;">{{ str_replace('_', ' ', (string) $return->workflow_stage) }}</td>
                                    <td><span class="status-badge {{ $badge }}">{{ ucfirst($return->status) }}</span></td>
                                    <td style="white-space:nowrap;">{{ optional($return->created_at)->format('d M Y') }}</td>
                                    <td style="text-align:right;">
                                        <a href="{{ $viewUrl }}" class="btn-nis btn-ghost btn-sm" style="padding:5px 10px;font-size:.74rem;">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" style="text-align:center;color:#64748b;padding:24px;">No returns found within your scope for the selected filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($previewReturns->hasPages())
                    <div style="padding:12px 16px;border-top:1px solid #f1f5f9;">{{ $previewReturns->links() }}</div>
                @endif
            </div>
        </div>
    </main>

<script>
function toggleAllSections(state) {
    document.querySelectorAll('#sectionChecks input[type=checkbox]').forEach(cb => cb.checked = state);
}

function previewMatchingRecords() {
    const form = document.getElementById('reportForm');
    if (!form) return;
    const params = new URLSearchParams();
    ['date_from', 'date_to', 'status_filter', 'category_filter', 'zone_code', 'command_code', 'directorate_code', 'section_code', 'cgis_unit_code'].forEach(name => {
        const el = form.querySelector(`[name="${name}"]`);
        if (el && el.value) params.set(name, el.value);
    });
    window.location.href = @json(route('user.reports')) + (params.toString() ? '?' + params.toString() : '');
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
