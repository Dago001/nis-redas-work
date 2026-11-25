@include('partials.header3')

<style>
    .iam-section { border: 1px solid #e2e8f0; border-radius: 14px; background: #ffffff; padding: 20px 22px; margin-bottom: 18px; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03); }
    .iam-section-head { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; }
    .iam-step-badge { width: 26px; height: 26px; border-radius: 8px; background: #ecfdf5; color: #006633; font-size: .78rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .iam-section-title { font-size: .95rem; font-weight: 700; color: #1e293b; margin: 0; }
    .iam-grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .iam-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
    .iam-hint { display: block; font-size: .75rem; color: #64748b; margin-top: 5px; }
    .pw-wrap { position: relative; }
    .pw-wrap .pw-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #94a3b8; padding: 4px; }
    .pw-wrap input { padding-right: 40px; }
    @media (max-width: 768px) { .iam-grid-2, .iam-grid-3 { grid-template-columns: 1fr; } }
</style>

@php
    $nameParts = preg_split('/\s+/', trim((string) $user->name)) ?: [];
    $defaultSurname = $user->surname ?? ($nameParts[0] ?? '');
    $defaultFirst = $user->first_name ?? ($nameParts[1] ?? '');
    $defaultMiddle = $user->middle_name ?? (count($nameParts) > 2 ? implode(' ', array_slice($nameParts, 2)) : '');
@endphp

<main class="redas-content" style="max-width:900px;margin:24px auto;padding:0 16px 32px;">
    <div class="page-header" style="margin-bottom:18px;">
        <div>
            <h1 class="page-title">Edit User</h1>
            <p class="page-subtitle">Update officer identity, rank, access scope, and security status for <strong>{{ $user->service_number }}</strong>.</p>
        </div>
        <a href="{{ route('admin.users') }}" class="btn-nis btn-ghost">
            <i class="fas fa-arrow-left"></i> Back to Users
        </a>
    </div>

    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px;border-radius:12px;">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom:16px;border-radius:12px;">
            <ul style="margin:0;padding-left:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.users.update', $user) }}">
        @csrf
        @method('PATCH')

        <div class="iam-section">
            <div class="iam-section-head">
                <span class="iam-step-badge">1</span>
                <h2 class="iam-section-title">Officer Identity</h2>
            </div>

            <div class="iam-grid-3" style="margin-bottom:14px;">
                <div class="auth-form-group">
                    <label class="form-label-nis" for="surname">Surname <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="surname" name="surname" value="{{ old('surname', $defaultSurname) }}" class="auth-input" required>
                </div>

                <div class="auth-form-group">
                    <label class="form-label-nis" for="first_name">First Name <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $defaultFirst) }}" class="auth-input" required>
                </div>

                <div class="auth-form-group">
                    <label class="form-label-nis" for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name', $defaultMiddle) }}" class="auth-input">
                </div>
            </div>

            <div class="iam-grid-3">
                <div class="auth-form-group">
                    <label class="form-label-nis" for="service_number">Service No (Username) <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="service_number" name="service_number" value="{{ old('service_number', $user->service_number) }}" class="auth-input" placeholder="Service No" inputmode="numeric" pattern="[0-9]{4,5}" minlength="4" maxlength="5" required>
                </div>

                <div class="auth-form-group">
                    <label class="form-label-nis" for="rank">NIS Rank</label>
                    <select id="rank" name="rank" class="auth-input">
                        <option value="">Select NIS rank</option>
                        @foreach($ranks as $code => $label)
                            <option value="{{ $code }}" {{ old('rank', $user->rank) === $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="auth-form-group">
                    <label class="form-label-nis" for="email">Email Address <span style="color:#dc2626;">*</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="auth-input" required>
                </div>
            </div>
        </div>

        <div class="iam-section">
            <div class="iam-section-head">
                <span class="iam-step-badge">2</span>
                <h2 class="iam-section-title">Role &amp; Organisational Scope</h2>
            </div>

            <div class="iam-grid-2">
                <div class="auth-form-group">
                    <label class="form-label-nis" for="user_category">Access Category / Role <span style="color:#dc2626;">*</span></label>
                    <select id="user_category" name="user_category" class="auth-input" required>
                        @foreach($categories as $value => $label)
                            <option value="{{ $value }}" {{ old('user_category', $user->user_category) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="auth-form-group scope-panel" id="scopeState" style="display:none;">
                    <label class="form-label-nis" for="assigned_state_code">Assigned State Command</label>
                    <select id="assigned_state_code" name="assigned_state_code" class="auth-input">
                        <option value="">Select state command</option>
                        @foreach($states as $code => $label)
                            <option value="{{ $code }}" {{ strtoupper((string) old('assigned_state_code', $user->assigned_state_code ?? $user->primary_location_code)) === $code ? 'selected' : '' }}>{{ $label }} ({{ $code }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="auth-form-group scope-panel" id="scopeZone" style="display:none;">
                    <label class="form-label-nis" for="assigned_zonal_command_code">Assigned Zonal Command</label>
                    <select id="assigned_zonal_command_code" name="assigned_zonal_command_code" class="auth-input">
                        <option value="">Select zonal command</option>
                        @foreach($zones as $code => $label)
                            <option value="{{ $code }}" {{ strtoupper((string) old('assigned_zonal_command_code', $user->assigned_zonal_command_code ?? $user->primary_location_code)) === $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="auth-form-group scope-panel" id="scopeDirectorate" style="display:none;">
                    <label class="form-label-nis" for="assigned_directorate_code">Assigned Directorate</label>
                    <select id="assigned_directorate_code" name="assigned_directorate_code" class="auth-input">
                        <option value="">Select directorate</option>
                        @foreach($directorates as $code => $label)
                            <option value="{{ $code }}" {{ strtolower((string) old('assigned_directorate_code', $user->assigned_directorate_code ?? $user->primary_location_code)) === $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="auth-form-group scope-panel" id="scopeCgis" style="display:none;">
                    <label class="form-label-nis" for="assigned_cgis_unit_code">Assigned CGIS Unit</label>
                    <select id="assigned_cgis_unit_code" name="assigned_cgis_unit_code" class="auth-input">
                        <option value="">Select CGIS unit</option>
                        @foreach($cgisUnits as $code => $label)
                            <option value="{{ $code }}" {{ old('assigned_cgis_unit_code', $user->assigned_cgis_unit_code) === $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="auth-form-group scope-panel" id="scopeHq" style="display:none;">
                    <label class="form-label-nis">Assigned Location</label>
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:11px 14px;font-size:.84rem;color:#334155;">
                        <i class="fas fa-landmark text-nis me-1"></i> <strong>NIS Service Headquarters (HQ)</strong>
                    </div>
                </div>
            </div>

            <div class="auth-form-group" style="margin-top:14px;margin-bottom:0;max-width:420px;">
                <label class="form-label-nis" for="geo_state">ABAC Login State Override (Optional)</label>
                <select id="geo_state" name="geo_state" class="auth-input">
                    <option value="">Default (use assigned state / HQ bypass)</option>
                    @foreach($states as $code => $label)
                        <option value="{{ $code }}" {{ strtoupper((string) old('geo_state', $user->geo_state)) === $code ? 'selected' : '' }}>{{ $label }} ({{ $code }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="iam-section">
            <div class="iam-section-head">
                <span class="iam-step-badge">3</span>
                <h2 class="iam-section-title">Password Reset &amp; Status</h2>
            </div>

            <div class="iam-grid-2" style="margin-bottom:16px;">
                <div class="auth-form-group">
                    <label class="form-label-nis" for="password">New Password (Optional)</label>
                    <div class="pw-wrap">
                        <input type="password" id="password" name="password" class="auth-input" placeholder="Leave blank to keep current password" minlength="6">
                        <button type="button" class="pw-toggle" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                    </div>
                    <span class="iam-hint">Minimum 6 characters: uppercase, lowercase, number, and special character.</span>
                </div>

                <div class="auth-form-group">
                    <label class="form-label-nis" for="password_confirmation">Confirm New Password</label>
                    <div class="pw-wrap">
                        <input type="password" id="password_confirmation" name="password_confirmation" class="auth-input" minlength="6">
                        <button type="button" class="pw-toggle" aria-label="Toggle confirm password visibility"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding-top:12px;border-top:1px solid #f1f5f9;">
                <label style="display:inline-flex;align-items:center;gap:10px;cursor:pointer;font-size:.86rem;font-weight:600;color:#1e293b;">
                    <input type="checkbox" name="is_enabled" value="1" {{ old('is_enabled', $user->is_enabled ? '1' : '0') === '1' ? 'checked' : '' }} style="accent-color:#006633;width:17px;height:17px;">
                    Account Enabled
                </label>

                <div style="display:flex;gap:10px;">
                    <a href="{{ route('admin.users') }}" class="btn-nis btn-ghost">Cancel</a>
                    <button type="submit" class="btn-nis btn-primary-nis">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </form>
</main>

<script>
    (function () {
        const snInput = document.getElementById('service_number');
        snInput?.addEventListener('input', () => { snInput.value = snInput.value.replace(/\D/g, '').slice(0, 5); });

        const catSel = document.getElementById('user_category');
        const panels = {
            state: document.getElementById('scopeState'),
            zone: document.getElementById('scopeZone'),
            directorate: document.getElementById('scopeDirectorate'),
            cgis: document.getElementById('scopeCgis'),
            hq: document.getElementById('scopeHq'),
        };
        const catToScope = {
            state_user: 'state', desk_admin: 'state',
            zonal_commander: 'zone',
            directorate_user: 'directorate', directorate_admin: 'directorate',
            cgis_unit_user: 'cgis', cgis_desk_admin: 'cgis',
            hq_admin: 'hq', admin: 'hq', super_admin: 'hq',
        };
        function updateScope() {
            const active = catToScope[catSel?.value] || null;
            Object.entries(panels).forEach(([k, el]) => { if (el) el.style.display = k === active ? 'block' : 'none'; });
        }
        catSel?.addEventListener('change', updateScope);
        updateScope();
    })();
</script>

@include('partials.footer')
