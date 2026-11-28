@include('partials.header3')

<style>
    .sys-grid-4 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .sys-kpi {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 13px 15px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .sys-kpi-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .sys-kpi-label {
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #64748b;
    }
    .sys-kpi-icon {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .78rem;
    }
    .sys-kpi-val {
        font-size: 1.15rem;
        font-weight: 700;
        color: #0f172a;
    }
    .sys-kpi-sub {
        font-size: .72rem;
        color: #64748b;
    }
    .sys-field-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 16px;
    }
    .sys-label {
        display: block;
        font-size: .78rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 5px;
    }
    .sys-input {
        width: 100%;
        padding: 8px 11px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        font-size: .84rem;
        color: #0f172a;
        background: #fff;
    }
    .sys-hint {
        display: block;
        margin-top: 4px;
        font-size: .73rem;
        color: #64748b;
    }
</style>

<main class="redas-content">
    <div class="page-header" style="margin-bottom:18px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 class="page-title">System Settings &amp; Platform Control</h1>
            <p class="page-subtitle">Control maintenance mode, reporting windows, authentication security, geofencing, and full database backups.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <form action="{{ route('admin.settings.clear-cache') }}" method="POST" style="margin:0;">
                @csrf
                <button type="submit" class="btn-nis btn-ghost btn-sm">
                    <i class="fas fa-broom"></i> Clear System Cache
                </button>
            </form>
            <a href="{{ route('admin.audit-log.index') }}" class="btn-nis btn-outline-nis btn-sm">
                <i class="fas fa-shield-alt"></i> Audit Log
            </a>
        </div>
    </div>

    @if(session('status'))
        <div style="margin-bottom:16px;padding:12px 16px;border-radius:10px;background:#dcfce7;color:#166534;border:1px solid #86efac;font-size:.86rem;font-weight:600;display:flex;align-items:center;gap:10px;">
            <i class="fas fa-check-circle"></i> {{ session('status') }}
        </div>
    @endif

    @if(session('error'))
        <div style="margin-bottom:16px;padding:12px 16px;border-radius:10px;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;font-size:.86rem;font-weight:600;display:flex;align-items:center;gap:10px;">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div style="margin-bottom:16px;padding:12px 16px;border-radius:10px;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;font-size:.84rem;">
            <ul style="margin:0;padding-left:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Live Platform Status Strip --}}
    <div class="sys-grid-4">
        <div class="sys-kpi">
            <div class="sys-kpi-top">
                <span class="sys-kpi-label">Platform Availability</span>
                <span class="sys-kpi-icon" style="{{ $settings['maintenance_mode'] ? 'background:#fef3c7;color:#b45309;' : 'background:#dcfce7;color:#15803d;' }}">
                    <i class="fas {{ $settings['maintenance_mode'] ? 'fa-wrench' : 'fa-check-circle' }}"></i>
                </span>
            </div>
            <div class="sys-kpi-val" style="color:{{ $settings['maintenance_mode'] ? '#b45309' : '#15803d' }};">
                {{ $settings['maintenance_mode'] ? 'Maintenance Mode' : 'Online' }}
            </div>
            <div class="sys-kpi-sub">{{ $settings['maintenance_mode'] ? 'Only Super Admins can access' : 'Open to all active officers' }}</div>
        </div>

        <div class="sys-kpi">
            <div class="sys-kpi-top">
                <span class="sys-kpi-label">Return Submissions</span>
                <span class="sys-kpi-icon" style="{{ $settings['submissions_open'] ? 'background:#e0f2fe;color:#0369a1;' : 'background:#fee2e2;color:#b91c1c;' }}">
                    <i class="fas {{ $settings['submissions_open'] ? 'fa-lock-open' : 'fa-lock' }}"></i>
                </span>
            </div>
            <div class="sys-kpi-val" style="color:{{ $settings['submissions_open'] ? '#0369a1' : '#b91c1c' }};">
                {{ $settings['submissions_open'] ? 'Open' : 'Closed' }}
            </div>
            <div class="sys-kpi-sub">Monthly deadline: Day {{ $settings['submission_deadline_day'] }}</div>
        </div>

        <div class="sys-kpi">
            <div class="sys-kpi-top">
                <span class="sys-kpi-label">Database Engine</span>
                <span class="sys-kpi-icon" style="background:#ede9fe;color:#6d28d9;"><i class="fas fa-database"></i></span>
            </div>
            <div class="sys-kpi-val">{{ $dbInfo['driver'] }} &bull; {{ $dbInfo['size_human'] }}</div>
            <div class="sys-kpi-sub">{{ $dbInfo['table_count'] }} tables ({{ $dbInfo['database'] }})</div>
        </div>

        <div class="sys-kpi">
            <div class="sys-kpi-top">
                <span class="sys-kpi-label">Saved Backups</span>
                <span class="sys-kpi-icon" style="background:#f0fdf4;color:#006838;"><i class="fas fa-hard-drive"></i></span>
            </div>
            <div class="sys-kpi-val">{{ count($backups) }}</div>
            <div class="sys-kpi-sub">{{ count($backups) > 0 ? 'Latest: '.$backups[0]['created_at'] : 'No backups created yet' }}</div>
        </div>
    </div>

    {{-- Unified Settings Form --}}
    <form action="{{ route('admin.settings.update') }}" method="POST">
        @csrf
        @method('PUT')

        {{-- 1. Maintenance Mode & Platform Availability --}}
        <div class="redas-card" style="margin-bottom:20px;{{ $settings['maintenance_mode'] ? 'border:1px solid #f59e0b;' : '' }}">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:#fef3c7;color:#b45309;"><i class="fas fa-wrench"></i></div>
                    Maintenance Mode &amp; Platform Availability
                </div>
                <button type="submit" class="btn-nis btn-primary-nis btn-sm">
                    <i class="fas fa-save"></i> Save All Settings
                </button>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">
                <div class="sys-field-grid">
                    <div>
                        <label class="sys-label" for="maintenance_mode">Maintenance Mode</label>
                        <select id="maintenance_mode" name="maintenance_mode" class="sys-input">
                            <option value="0" @selected(! $settings['maintenance_mode'])>Off — Portal Online</option>
                            <option value="1" @selected($settings['maintenance_mode'])>On — Maintenance Active (Super Admin Only)</option>
                        </select>
                        <span class="sys-hint">Blocks non-Super Admin users with an official maintenance page while keeping Super Admin access open.</span>
                    </div>

                    <div>
                        <label class="sys-label" for="maintenance_eta">Estimated Return Time (Optional)</label>
                        <input type="text" id="maintenance_eta" name="maintenance_eta" value="{{ old('maintenance_eta', $settings['maintenance_eta']) }}" class="sys-input" placeholder="e.g. Today at 18:00 WAT">
                        <span class="sys-hint">Displayed on the maintenance screen when set.</span>
                    </div>

                    <div>
                        <label class="sys-label" for="system_banner_enabled">Global Notice Banner</label>
                        <select id="system_banner_enabled" name="system_banner_enabled" class="sys-input">
                            <option value="0" @selected(! $settings['system_banner_enabled'])>Hidden</option>
                            <option value="1" @selected($settings['system_banner_enabled'])>Visible Across Portal</option>
                        </select>
                        <span class="sys-hint">Display an official announcement banner to logged-in officers.</span>
                    </div>
                </div>

                <div class="sys-field-grid">
                    <div>
                        <label class="sys-label" for="maintenance_message">Maintenance Notice Message</label>
                        <textarea id="maintenance_message" name="maintenance_message" rows="2" class="sys-input" required>{{ old('maintenance_message', $settings['maintenance_message']) }}</textarea>
                    </div>
                    <div>
                        <label class="sys-label" for="system_banner_message">Global Banner Text</label>
                        <textarea id="system_banner_message" name="system_banner_message" rows="2" class="sys-input" placeholder="e.g. All November returns must be submitted before 25th November.">{{ old('system_banner_message', $settings['system_banner_message']) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. Reporting & Submission Controls --}}
        <div class="redas-card" style="margin-bottom:20px;">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:#e0f2fe;color:#0369a1;"><i class="fas fa-calendar-check"></i></div>
                    Reporting &amp; Submission Controls
                </div>
            </div>
            <div class="card-body">
                <div class="sys-field-grid">
                    <div>
                        <label class="sys-label" for="submissions_open">Return Submission Window</label>
                        <select id="submissions_open" name="submissions_open" class="sys-input">
                            <option value="1" @selected($settings['submissions_open'])>Open — Officers Can Submit Returns</option>
                            <option value="0" @selected(! $settings['submissions_open'])>Closed — Freeze New Submissions</option>
                        </select>
                        <span class="sys-hint">Pause or resume monthly return submissions nationwide.</span>
                    </div>

                    <div>
                        <label class="sys-label" for="submission_deadline_day">Monthly Submission Deadline (Day of Month)</label>
                        <input type="number" id="submission_deadline_day" name="submission_deadline_day" min="1" max="31" value="{{ old('submission_deadline_day', $settings['submission_deadline_day']) }}" class="sys-input" required>
                        <span class="sys-hint">Day of each month by which commands must submit returns (1–31).</span>
                    </div>

                    <div>
                        <label class="sys-label" for="allow_return_resubmission">Returned Report Corrections</label>
                        <select id="allow_return_resubmission" name="allow_return_resubmission" class="sys-input">
                            <option value="1" @selected($settings['allow_return_resubmission'])>Allowed</option>
                            <option value="0" @selected(! $settings['allow_return_resubmission'])>Locked</option>
                        </select>
                        <span class="sys-hint">Allow officers to edit and resubmit returns sent back by reviewers.</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Security & Authentication Policy --}}
        <div class="redas-card" style="margin-bottom:20px;">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:#fee2e2;color:#b91c1c;"><i class="fas fa-user-lock"></i></div>
                    Security &amp; Authentication Policy
                </div>
            </div>
            <div class="card-body">
                <div class="sys-field-grid">
                    <div>
                        <label class="sys-label" for="mfa_required">Google Authenticator (MFA)</label>
                        <select id="mfa_required" name="mfa_required" class="sys-input">
                            <option value="1" @selected($settings['mfa_required'])>Required for All Officers</option>
                            <option value="0" @selected(! $settings['mfa_required'])>Optional</option>
                        </select>
                        <span class="sys-hint">Enforce two-factor authenticator verification at sign-in.</span>
                    </div>

                    <div>
                        <label class="sys-label" for="session_timeout_minutes">Idle Session Timeout (Minutes)</label>
                        <input type="number" id="session_timeout_minutes" name="session_timeout_minutes" min="15" max="480" value="{{ old('session_timeout_minutes', $settings['session_timeout_minutes']) }}" class="sys-input" required>
                        <span class="sys-hint">Auto-expire inactive sessions (15–480 minutes).</span>
                    </div>

                    <div>
                        <label class="sys-label" for="max_login_attempts">Max Failed Login Attempts</label>
                        <input type="number" id="max_login_attempts" name="max_login_attempts" min="3" max="20" value="{{ old('max_login_attempts', $settings['max_login_attempts']) }}" class="sys-input" required>
                        <span class="sys-hint">Lock out accounts temporarily after this many consecutive failures.</span>
                    </div>

                    <div>
                        <label class="sys-label" for="force_password_change_on_create">First-Login Password Reset</label>
                        <select id="force_password_change_on_create" name="force_password_change_on_create" class="sys-input">
                            <option value="1" @selected($settings['force_password_change_on_create'])>Required on First Sign-In</option>
                            <option value="0" @selected(! $settings['force_password_change_on_create'])>Not Required</option>
                        </select>
                        <span class="sys-hint">Require newly provisioned officers to change their initial password.</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- 4. Geofencing & ABAC Access Control --}}
        <div class="redas-card" style="margin-bottom:20px;">
            <div class="card-head">
                <div class="card-head-title">
                    <div class="card-head-icon" style="background:var(--nis-50);color:var(--nis-600);"><i class="fas fa-location-dot"></i></div>
                    Geofencing &amp; ABAC Access Control
                </div>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">
                <div class="sys-field-grid">
                    <div>
                        <label class="sys-label" for="abac_enabled">ABAC Master Switch</label>
                        <select id="abac_enabled" name="abac_enabled" class="sys-input">
                            <option value="1" @selected($settings['abac_enabled'])>Enabled</option>
                            <option value="0" @selected(! $settings['abac_enabled'])>Disabled</option>
                        </select>
                        <span class="sys-hint">Enable attribute-based location verification across the portal.</span>
                    </div>

                    <div>
                        <label class="sys-label" for="abac_location_enforcement">Geofence Enforcement</label>
                        <select id="abac_location_enforcement" name="abac_location_enforcement" class="sys-input">
                            <option value="1" @selected($settings['abac_location_enforcement'])>Enforced</option>
                            <option value="0" @selected(! $settings['abac_location_enforcement'])>Not Enforced</option>
                        </select>
                        <span class="sys-hint">Block sign-ins outside an officer's pinned command/state boundary.</span>
                    </div>

                    <div>
                        <label class="sys-label" for="abac_hq_bypass">HQ / Super Admin Bypass</label>
                        <select id="abac_hq_bypass" name="abac_hq_bypass" class="sys-input">
                            <option value="1" @selected($settings['abac_hq_bypass'])>Allowed</option>
                            <option value="0" @selected(! $settings['abac_hq_bypass'])>Not Allowed</option>
                        </select>
                        <span class="sys-hint">Allow HQ &amp; Super Admins to authenticate from any location.</span>
                    </div>

                    <div>
                        <label class="sys-label" for="default_geofence_radius_km">Default Geofence Radius (km)</label>
                        <input type="number" id="default_geofence_radius_km" name="default_geofence_radius_km" min="1" max="500" value="{{ old('default_geofence_radius_km', $settings['default_geofence_radius_km']) }}" class="sys-input" required>
                        <span class="sys-hint">Default boundary radius applied when provisioning new accounts.</span>
                    </div>

                    <div>
                        <label class="sys-label" for="abac_allowed_countries">Allowed Countries</label>
                        <input type="text" id="abac_allowed_countries" name="abac_allowed_countries" value="{{ old('abac_allowed_countries', $settings['abac_allowed_countries']) }}" class="sys-input" required>
                        <span class="sys-hint">Comma-separated list (e.g. Nigeria).</span>
                    </div>

                    <div>
                        <label class="sys-label" for="geolocation_provider">IP Geolocation Provider</label>
                        <select id="geolocation_provider" name="geolocation_provider" class="sys-input">
                            <option value="ipapi_co" @selected(in_array($settings['geolocation_provider'], ['ipapi_co', 'ipapi'], true))>ipapi.co (HTTPS)</option>
                            <option value="ip_api_pro" @selected(in_array($settings['geolocation_provider'], ['ip_api_pro', 'ip-api'], true))>ip-api.com</option>
                            <option value="ipinfo" @selected($settings['geolocation_provider'] === 'ipinfo')>ipinfo.io</option>
                        </select>
                    </div>

                    <div>
                        <label class="sys-label" for="geolocation_api_token">Provider API Token (Optional)</label>
                        <input type="password" id="geolocation_api_token" name="geolocation_api_token" value="{{ old('geolocation_api_token', $settings['geolocation_api_token']) }}" class="sys-input" placeholder="Optional paid provider token" autocomplete="off">
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:10px;border-top:1px solid #f1f5f9;">
                    <button type="submit" class="btn-nis btn-primary-nis">
                        <i class="fas fa-save"></i> Save All Settings
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- 5. Database Backup & Recovery --}}
    <div class="redas-card">
        <div class="card-head">
            <div class="card-head-title">
                <div class="card-head-icon" style="background:#ede9fe;color:#6d28d9;"><i class="fas fa-database"></i></div>
                Database Backup &amp; Recovery
            </div>
            <form action="{{ route('admin.settings.backup.create') }}" method="POST" style="margin:0;">
                @csrf
                <button type="submit" class="btn-nis btn-primary-nis btn-sm">
                    <i class="fas fa-cloud-arrow-down"></i> Backup Entire Database Now
                </button>
            </form>
        </div>
        <div class="card-body no-pad">
            <div style="padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:.8rem;color:#475569;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                <span>
                    <strong>Active Database:</strong> {{ $dbInfo['driver'] }} ({{ $dbInfo['database'] }}) &bull; {{ $dbInfo['table_count'] }} tables &bull; {{ $dbInfo['size_human'] }}
                </span>
                <span>Backups are stored privately outside the public web root and logged in the Audit Trail.</span>
            </div>

            <div class="table-responsive">
                <table class="nis-table" style="min-width:680px;">
                    <thead>
                        <tr>
                            <th>Backup File</th>
                            <th>Engine</th>
                            <th>Size</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($backups as $b)
                            <tr>
                                <td style="font-family:monospace;font-size:.8rem;font-weight:600;color:#0f172a;">{{ $b['filename'] }}</td>
                                <td>{{ $b['driver'] }}</td>
                                <td>{{ $b['size_human'] }}</td>
                                <td>{{ $b['created_by'] }}</td>
                                <td style="white-space:nowrap;">{{ $b['created_at'] }}</td>
                                <td style="text-align:right;white-space:nowrap;">
                                    <div style="display:inline-flex;gap:6px;align-items:center;">
                                        <a href="{{ route('admin.settings.backup.download', ['filename' => $b['filename']]) }}" class="btn-nis btn-outline-nis btn-sm" style="padding:5px 10px;font-size:.74rem;">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                        <form action="{{ route('admin.settings.backup.delete', ['filename' => $b['filename']]) }}" method="POST" style="margin:0;" onsubmit="return confirm('Delete backup {{ $b['filename'] }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-nis btn-ghost btn-sm" style="padding:5px 10px;font-size:.74rem;color:#b91c1c;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align:center;color:#64748b;padding:24px;">
                                    No database backups created yet. Click <strong>Backup Entire Database Now</strong> above to create your first full backup.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

@include('partials.footer3')
