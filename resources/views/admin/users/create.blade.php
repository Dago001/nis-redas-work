@include('partials.header3')

<style>
    .iam-section {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #ffffff;
        padding: 20px 22px;
        margin-bottom: 18px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
    }
    .iam-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 16px;
        padding-bottom: 10px;
        border-bottom: 1px solid #f1f5f9;
    }
    .iam-section-head-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .iam-step-badge {
        width: 26px;
        height: 26px;
        border-radius: 8px;
        background: #ecfdf5;
        color: #006633;
        font-size: .78rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .iam-section-title {
        font-size: .95rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }
    .iam-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }
    .iam-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }
    .iam-hint {
        display: block;
        font-size: .75rem;
        color: #64748b;
        margin-top: 5px;
        line-height: 1.4;
    }
    .iam-notice {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
        border-radius: 10px;
        padding: 12px 14px;
        font-size: .82rem;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 18px;
    }
    .iam-hq-pill {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 11px 14px;
        font-size: .84rem;
        color: #334155;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .pw-wrap { position: relative; }
    .pw-wrap .pw-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: #94a3b8;
        padding: 4px;
    }
    .pw-wrap .pw-toggle:hover { color: #475569; }
    .pw-wrap input { padding-right: 40px; }
    .pw-checklist {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 14px;
        margin: 10px 0 16px;
        padding: 10px 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: .76rem;
    }
    .pw-rule {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: #64748b;
        font-weight: 500;
    }
    .pw-rule.pass {
        color: #15803d;
        font-weight: 600;
    }
    @media (max-width: 768px) {
        .iam-grid-2, .iam-grid-3 { grid-template-columns: 1fr; }
    }
</style>

<main class="redas-content" style="max-width:900px;margin:24px auto;padding:0 16px 32px;">
    <div class="page-header" style="margin-bottom:18px;">
        <div>
            <h1 class="page-title">Create New User</h1>
            <p class="page-subtitle">Provision a verified NIS officer account with official rank, role scope, and zero-trust first-login onboarding.</p>
        </div>
        <a href="{{ route('admin.users') }}" class="btn-nis btn-ghost">
            <i class="fas fa-arrow-left"></i> Back to Users
        </a>
    </div>

    <div class="iam-notice">
        <i class="fas fa-shield-halved" style="margin-top:2px;flex-shrink:0;"></i>
        <div>
            <strong>Zero-Trust Onboarding Enforced:</strong> The officer's <strong>Service No</strong> is their login username. A verification link is sent to their official email, and on first sign-in they must enroll in <strong>Google Authenticator</strong> and <strong>change their initial password</strong>.
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom:16px;border-radius:12px;">
            <strong style="display:block;margin-bottom:4px;"><i class="fas fa-exclamation-triangle me-1"></i> Please correct the following:</strong>
            <ul style="margin:0;padding-left:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.users.store') }}" id="createUserForm">
        @csrf

        {{-- Section 1: Officer Identity --}}
        <div class="iam-section">
            <div class="iam-section-head">
                <div class="iam-section-head-left">
                    <span class="iam-step-badge">1</span>
                    <h2 class="iam-section-title">Officer Identity</h2>
                </div>
            </div>

            <div class="iam-grid-3" style="margin-bottom:14px;">
                <div class="auth-form-group">
                    <label class="form-label-nis" for="surname">Surname <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="surname" name="surname" value="{{ old('surname') }}" class="auth-input" placeholder="Surname" required>
                </div>

                <div class="auth-form-group">
                    <label class="form-label-nis" for="first_name">First Name <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" class="auth-input" placeholder="First Name" required>
                </div>

                <div class="auth-form-group">
                    <label class="form-label-nis" for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name') }}" class="auth-input" placeholder="Middle Name (optional)">
                </div>
            </div>

            <div class="iam-grid-3">
                <div class="auth-form-group">
                    <label class="form-label-nis" for="service_number">Service No (Username) <span style="color:#dc2626;">*</span></label>
                    <input type="text"
                        id="service_number"
                        name="service_number"
                        value="{{ old('service_number') }}"
                        class="auth-input"
                        placeholder="Service No"
                        inputmode="numeric"
                        pattern="[0-9]{4,5}"
                        minlength="4"
                        maxlength="5"
                        title="Service No must be 4 or 5 digits only (0-9)"
                        required>
                    <span class="iam-hint">4–5 digits only (0–9). Used as login username.</span>
                </div>

                <div class="auth-form-group">
                    <label class="form-label-nis" for="rank">NIS Rank <span style="color:#dc2626;">*</span></label>
                    <select id="rank" name="rank" class="auth-input" required>
                        <option value="" disabled {{ old('rank') ? '' : 'selected' }}>Select NIS rank</option>
                        @foreach($ranks as $code => $label)
                            <option value="{{ $code }}" {{ old('rank') === $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="auth-form-group">
                    <label class="form-label-nis" for="email">Email Address <span style="color:#dc2626;">*</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" class="auth-input" placeholder="officer@immigration.gov.ng" required>
                    <span class="iam-hint">Default password &amp; onboarding notifications will be sent to this address.</span>
                </div>
            </div>
        </div>

        {{-- Section 2: Role & Organisational Scope --}}
        <div class="iam-section">
            <div class="iam-section-head">
                <div class="iam-section-head-left">
                    <span class="iam-step-badge">2</span>
                    <h2 class="iam-section-title">Role &amp; Organisational Scope</h2>
                </div>
            </div>

            <div class="iam-grid-2">
                <div class="auth-form-group">
                    <label class="form-label-nis" for="user_category">Access Category / Role <span style="color:#dc2626;">*</span></label>
                    <select id="user_category" name="user_category" class="auth-input" required>
                        <option value="" disabled {{ old('user_category') ? '' : 'selected' }}>Select officer role</option>
                        @foreach($categories as $value => $label)
                            <option value="{{ $value }}" {{ old('user_category') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="iam-hint">System role and location type are assigned automatically based on this selection.</span>
                </div>

                {{-- Dynamic Scope Selector: All NIS Formations (from /directory) --}}
                <div class="auth-form-group scope-panel" id="scopeState" style="display:none;">
                    <label class="form-label-nis" for="assigned_state_code">Assigned NIS Formation <span style="color:#dc2626;">*</span></label>
                    <select id="assigned_state_code" name="assigned_state_code" class="auth-input">
                        <option value="">Select formation (State, Airport, Border, Marine, Passport, Training, Mission)</option>
                        @foreach($formationGroups as $groupLabel => $options)
                            <optgroup label="{{ $groupLabel }}">
                                @foreach($options as $code => $label)
                                    <option value="{{ $code }}" {{ old('assigned_state_code') === $code ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <span class="iam-hint">Synced with the official NIS Directory. Reporting Zone auto-links below.</span>
                </div>

                {{-- Dynamic Scope Selector: Zone (for Zonal Commander OR Formation parent zone) --}}
                <div class="auth-form-group scope-panel" id="scopeZone" style="display:none;">
                    <label class="form-label-nis" for="assigned_zonal_command_code" id="zoneFieldLabel">Assigned Zonal Command <span style="color:#dc2626;">*</span></label>
                    <select id="assigned_zonal_command_code" name="assigned_zonal_command_code" class="auth-input">
                        <option value="">Select zonal command</option>
                        @foreach($zones as $code => $label)
                            <option value="{{ $code }}" {{ old('assigned_zonal_command_code') === $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="iam-hint" id="zoneFieldHint">Determines which Zonal Commander reviews &amp; consolidates this formation's returns.</span>
                </div>

                {{-- Dynamic Scope Selector: Directorate --}}
                <div class="auth-form-group scope-panel" id="scopeDirectorate" style="display:none;">
                    <label class="form-label-nis" for="assigned_directorate_code">Assigned Directorate <span style="color:#dc2626;">*</span></label>
                    <select id="assigned_directorate_code" name="assigned_directorate_code" class="auth-input">
                        <option value="">Select directorate</option>
                        @foreach($directorates as $code => $label)
                            <option value="{{ $code }}" {{ old('assigned_directorate_code') === $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Dynamic Scope Selector: Directorate Section (for Directorate Officer) --}}
                <div class="auth-form-group scope-panel" id="scopeSection" style="display:none;">
                    <label class="form-label-nis" for="assigned_section_code">Assigned Section / Sub-Directorate</label>
                    <input type="text" id="assigned_section_code" name="assigned_section_code" value="{{ old('assigned_section_code') }}" class="auth-input" placeholder="e.g. RECRUITMENT, STATS, PASSPORT-OPS">
                    <span class="iam-hint">Identifies the officer's section for Directorate completeness tracking &amp; consolidation.</span>
                </div>

                {{-- Dynamic Scope Selector: CGIS Unit --}}
                <div class="auth-form-group scope-panel" id="scopeCgis" style="display:none;">
                    <label class="form-label-nis" for="assigned_cgis_unit_code">Assigned CGIS Unit <span style="color:#dc2626;">*</span></label>
                    <select id="assigned_cgis_unit_code" name="assigned_cgis_unit_code" class="auth-input">
                        <option value="">Select CGIS unit</option>
                        @foreach($cgisUnits as $code => $label)
                            <option value="{{ $code }}" {{ old('assigned_cgis_unit_code') === $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Dynamic Scope Display: Headquarters --}}
                <div class="auth-form-group scope-panel" id="scopeHq" style="display:none;">
                    <label class="form-label-nis">Assigned Location</label>
                    <div class="iam-hq-pill">
                        <i class="fas fa-landmark text-nis"></i>
                        <span><strong>NIS Service Headquarters (HQ)</strong> - National Scope</span>
                    </div>
                </div>
            </div>

            <div id="workflowChainPreview" style="display:none;margin-top:14px;padding:10px 14px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;font-size:.8rem;color:#334155;">
                <i class="fas fa-route text-nis me-1"></i>
                <strong>Reporting Workflow:</strong> <span id="workflowChainText"></span>
            </div>

            @php $geoActive = (string) old('geolocation_enabled', '1') === '1'; @endphp
            <div style="margin-top:16px;padding:14px 16px;border-radius:10px;border:1px solid #e2e8f0;background:#f8fafc;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:8px;">
                    <label class="form-label-nis" for="geolocation_enabled" style="margin:0;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-location-dot text-nis"></i> Geolocation Activation
                    </label>
                    <span id="geoStatusBadge" style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:.74rem;font-weight:600;{{ $geoActive ? 'background:#e8f8ef;color:#166534;border:1px solid #bbf7d0;' : 'background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;' }}">
                        <i class="fas {{ $geoActive ? 'fa-check-circle' : 'fa-minus-circle' }}"></i>
                        <span id="geoStatusText">{{ $geoActive ? 'Activated' : 'Not Activated' }}</span>
                    </span>
                </div>
                <input type="hidden" name="geolocation_enabled" value="0">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:.84rem;font-weight:500;color:#1e293b;margin:0;">
                    <input type="checkbox" id="geolocation_enabled" name="geolocation_enabled" value="1" {{ $geoActive ? 'checked' : '' }} style="width:16px;height:16px;accent-color:#006633;">
                    <span>Activate geolocation verification for this officer's sign-in and active sessions</span>
                </label>
                <span class="iam-hint" style="margin-top:6px;">When activated, the officer must authenticate from their assigned location. Uncheck to exempt this account from geolocation checks.</span>
            </div>
        </div>

        {{-- Section 3: Initial Credentials & Status --}}
        <div class="iam-section">
            <div class="iam-section-head">
                <div class="iam-section-head-left">
                    <span class="iam-step-badge">3</span>
                    <h2 class="iam-section-title">Initial Password &amp; Account Status</h2>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" id="generatePwBtn" class="btn-nis btn-outline-nis btn-sm">
                        <i class="fas fa-wand-magic-sparkles"></i> Generate Strong Password
                    </button>
                    <button type="button" id="copyPwBtn" class="btn-nis btn-ghost btn-sm" style="display:none;">
                        <i class="fas fa-copy"></i> Copy Password
                    </button>
                </div>
            </div>

            <div class="iam-grid-2">
                <div class="auth-form-group">
                    <label class="form-label-nis" for="password">Initial Password <span style="color:#dc2626;">*</span></label>
                    <div class="pw-wrap">
                        <input type="password" id="password" name="password" class="auth-input" placeholder="Enter initial password" minlength="6" required>
                        <button type="button" class="pw-toggle" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                    </div>
                </div>

                <div class="auth-form-group">
                    <label class="form-label-nis" for="password_confirmation">Confirm Initial Password <span style="color:#dc2626;">*</span></label>
                    <div class="pw-wrap">
                        <input type="password" id="password_confirmation" name="password_confirmation" class="auth-input" placeholder="Confirm initial password" minlength="6" required>
                        <button type="button" class="pw-toggle" aria-label="Toggle confirm password visibility"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
            </div>

            <div class="pw-checklist" id="pwChecklist">
                <span class="pw-rule" data-rule="len"><i class="fas fa-circle-dot"></i> 6+ characters</span>
                <span class="pw-rule" data-rule="upper"><i class="fas fa-circle-dot"></i> Uppercase (A–Z)</span>
                <span class="pw-rule" data-rule="lower"><i class="fas fa-circle-dot"></i> Lowercase (a–z)</span>
                <span class="pw-rule" data-rule="num"><i class="fas fa-circle-dot"></i> Number (0–9)</span>
                <span class="pw-rule" data-rule="sym"><i class="fas fa-circle-dot"></i> Special character</span>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding-top:12px;border-top:1px solid #f1f5f9;">
                <label style="display:inline-flex;align-items:center;gap:10px;cursor:pointer;font-size:.86rem;font-weight:600;color:#1e293b;">
                    <input type="checkbox" name="is_enabled" value="1" {{ old('is_enabled', '1') === '1' ? 'checked' : '' }} style="accent-color:#006633;width:17px;height:17px;">
                    Account Enabled (Active upon email verification)
                </label>

                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="{{ route('admin.users') }}" class="btn-nis btn-ghost">Cancel</a>
                    <button type="submit" class="btn-nis btn-primary-nis">
                        <i class="fas fa-user-plus"></i> Provision User Account
                    </button>
                </div>
            </div>
        </div>
    </form>
</main>

<script>
    (function () {
        // 1. Live numeric-only 4-5 digit guard for Service No
        const snInput = document.getElementById('service_number');
        if (snInput) {
            snInput.addEventListener('input', () => {
                snInput.value = snInput.value.replace(/\D/g, '').slice(0, 5);
            });
        }

        // 2. Dynamic scope panel switching + auto-zone linking + workflow chain preview
        const catSel = document.getElementById('user_category');
        const formationZoneMap = @json($formationZoneMap);
        const stateSel = document.getElementById('assigned_state_code');
        const zoneSel = document.getElementById('assigned_zonal_command_code');
        const zoneLabel = document.getElementById('zoneFieldLabel');
        const wfBox = document.getElementById('workflowChainPreview');
        const wfText = document.getElementById('workflowChainText');

        const panels = {
            state:       { el: document.getElementById('scopeState'),       input: stateSel },
            zone:        { el: document.getElementById('scopeZone'),        input: zoneSel },
            directorate: { el: document.getElementById('scopeDirectorate'), input: document.getElementById('assigned_directorate_code') },
            section:     { el: document.getElementById('scopeSection'),     input: document.getElementById('assigned_section_code') },
            cgis:        { el: document.getElementById('scopeCgis'),        input: document.getElementById('assigned_cgis_unit_code') },
            hq:          { el: document.getElementById('scopeHq'),          input: null },
        };

        const catConfig = {
            state_user:        { show: ['state', 'zone'], req: ['state'], wf: 'Formation Officer submits original return → Formation Desk Admin reviews → Zonal Commander reviews & consolidates → HQ Admin final approval (visible to PRS & Super Admin).' },
            desk_admin:        { show: ['state', 'zone'], req: ['state'], wf: 'Reviews & approves/returns submissions for their assigned Formation before forwarding to the Zonal Commander.' },
            zonal_commander:   { show: ['zone'], req: ['zone'], wf: 'Reviews approved Formation returns in their Zone and prepares separate Zonal Consolidated Reports for HQ Admin & PRS.' },
            directorate_user:  { show: ['directorate', 'section'], req: ['directorate'], wf: 'Directorate Section Officer submits original section return → Directorate Admin reviews & consolidates → HQ Admin final approval (visible to PRS).' },
            directorate_admin: { show: ['directorate'], req: ['directorate'], wf: 'Reviews section returns in their Directorate and prepares separate Directorate Consolidated Reports for HQ Admin & PRS (PRS Admin also views national dashboard).' },
            cgis_unit_user:    { show: ['cgis'], req: ['cgis'], wf: 'CGIS Unit Officer submits unit return → CGIS Desk Admin reviews → HQ Admin final approval.' },
            cgis_desk_admin:   { show: ['cgis'], req: ['cgis'], wf: 'Reviews & approves/returns submissions for their assigned CGIS Unit before forwarding to HQ Admin.' },
            hq_admin:          { show: ['hq'], req: [], wf: 'Final approval authority for all Formation, Directorate, CGIS Unit, and Consolidated reports nationwide.' },
            admin:             { show: ['hq'], req: [], wf: 'Read-only national executive visibility across all original and consolidated returns.' },
            super_admin:       { show: ['hq'], req: [], wf: 'Full platform governance, user & role administration, immutable audit oversight, ABAC settings, and national reporting visibility.' },
        };

        function updateScopePanel() {
            const cfg = catConfig[catSel?.value] || { show: [], req: [], wf: '' };
            Object.entries(panels).forEach(([key, p]) => {
                const visible = cfg.show.includes(key);
                if (p.el) p.el.style.display = visible ? 'block' : 'none';
                if (p.input) p.input.required = cfg.req.includes(key);
            });
            if (zoneLabel) {
                zoneLabel.innerHTML = catSel?.value === 'zonal_commander'
                    ? 'Assigned Zonal Command <span style="color:#dc2626;">*</span>'
                    : 'Reporting Zonal Command (Auto-linked)';
            }
            if (wfBox && wfText) {
                wfBox.style.display = cfg.wf ? 'block' : 'none';
                wfText.textContent = cfg.wf;
            }
        }

        stateSel?.addEventListener('change', () => {
            const mappedZone = formationZoneMap[stateSel.value];
            if (mappedZone && zoneSel) {
                zoneSel.value = mappedZone;
            }
        });

        catSel?.addEventListener('change', updateScopePanel);
        updateScopePanel();

        // 3. Live 5-rule password checklist + strong password generator + copy
        const pw1 = document.getElementById('password');
        const pw2 = document.getElementById('password_confirmation');
        const genBtn = document.getElementById('generatePwBtn');
        const copyBtn = document.getElementById('copyPwBtn');

        function updateChecklist() {
            const val = pw1?.value || '';
            const checks = {
                len: val.length >= 6,
                upper: /[A-Z]/.test(val),
                lower: /[a-z]/.test(val),
                num: /[0-9]/.test(val),
                sym: /[^A-Za-z0-9]/.test(val),
            };
            Object.entries(checks).forEach(([rule, ok]) => {
                const el = document.querySelector(`.pw-rule[data-rule="${rule}"]`);
                if (!el) return;
                el.classList.toggle('pass', ok);
                const icon = el.querySelector('i');
                if (icon) icon.className = ok ? 'fas fa-circle-check' : 'fas fa-circle-dot';
            });
        }

        pw1?.addEventListener('input', updateChecklist);
        updateChecklist();

        genBtn?.addEventListener('click', () => {
            const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
            const lower = 'abcdefghijkmnopqrstuvwxyz';
            const nums = '23456789';
            const syms = '@#$%!&*';
            const all = upper + lower + nums + syms;
            const rand = (str) => {
                const arr = new Uint32Array(1);
                window.crypto.getRandomValues(arr);
                return str[arr[0] % str.length];
            };
            const chars = [rand(upper), rand(lower), rand(nums), rand(syms)];
            for (let i = 0; i < 8; i++) chars.push(rand(all));
            chars.sort(() => {
                const arr = new Uint32Array(1);
                window.crypto.getRandomValues(arr);
                return (arr[0] % 3) - 1;
            });
            const generated = chars.join('');
            pw1.value = generated;
            pw2.value = generated;
            pw1.type = 'text';
            pw2.type = 'text';
            updateChecklist();
            if (copyBtn) copyBtn.style.display = 'inline-flex';
        });

        copyBtn?.addEventListener('click', () => {
            if (!pw1?.value) return;
            navigator.clipboard?.writeText(pw1.value).then(() => {
                copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied';
                setTimeout(() => { copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy Password'; }, 2000);
            }).catch(() => {});
        });

        const geoCheckbox = document.getElementById('geolocation_enabled');
        const geoBadge = document.getElementById('geoStatusBadge');
        const geoText = document.getElementById('geoStatusText');
        function updateGeoBadge() {
            if (!geoCheckbox || !geoBadge || !geoText) return;
            const on = geoCheckbox.checked;
            geoText.textContent = on ? 'Activated' : 'Not Activated';
            geoBadge.style.background = on ? '#e8f8ef' : '#f1f5f9';
            geoBadge.style.color = on ? '#166534' : '#475569';
            geoBadge.style.borderColor = on ? '#bbf7d0' : '#cbd5e1';
            const icon = geoBadge.querySelector('i');
            if (icon) icon.className = on ? 'fas fa-check-circle' : 'fas fa-minus-circle';
        }
        geoCheckbox?.addEventListener('change', updateGeoBadge);
        updateGeoBadge();
    })();
</script>

@include('partials.footer')
