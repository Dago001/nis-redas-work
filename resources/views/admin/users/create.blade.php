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
                    <span class="iam-hint">Required for verification &amp; onboarding email.</span>
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

                {{-- Dynamic Scope Selector: State --}}
                <div class="auth-form-group scope-panel" id="scopeState" style="display:none;">
                    <label class="form-label-nis" for="assigned_state_code">Assigned State Command <span style="color:#dc2626;">*</span></label>
                    <select id="assigned_state_code" name="assigned_state_code" class="auth-input">
                        <option value="">Select state command</option>
                        @foreach($states as $code => $label)
                            <option value="{{ $code }}" {{ old('assigned_state_code') === $code ? 'selected' : '' }}>{{ $label }} ({{ $code }})</option>
                        @endforeach
                    </select>
                    <span class="iam-hint">Parent Zonal Command is linked automatically.</span>
                </div>

                {{-- Dynamic Scope Selector: Zone --}}
                <div class="auth-form-group scope-panel" id="scopeZone" style="display:none;">
                    <label class="form-label-nis" for="assigned_zonal_command_code">Assigned Zonal Command <span style="color:#dc2626;">*</span></label>
                    <select id="assigned_zonal_command_code" name="assigned_zonal_command_code" class="auth-input">
                        <option value="">Select zonal command</option>
                        @foreach($zones as $code => $label)
                            <option value="{{ $code }}" {{ old('assigned_zonal_command_code') === $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
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
                        <span><strong>NIS Service Headquarters (HQ)</strong> &mdash; National Scope</span>
                    </div>
                </div>
            </div>

            <div class="auth-form-group" style="margin-top:14px;margin-bottom:0;max-width:420px;">
                <label class="form-label-nis" for="geo_state">ABAC Login State Override (Optional)</label>
                <select id="geo_state" name="geo_state" class="auth-input">
                    <option value="">Default (use officer's assigned state / HQ bypass)</option>
                    @foreach($states as $code => $label)
                        <option value="{{ $code }}" {{ old('geo_state') === $code ? 'selected' : '' }}>{{ $label }} ({{ $code }})</option>
                    @endforeach
                </select>
                <span class="iam-hint">Optional: restrict login geolocation to a specific state when ABAC is enabled.</span>
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

        // 2. Dynamic scope panel switching based on Access Category
        const catSel = document.getElementById('user_category');
        const panels = {
            state:       { el: document.getElementById('scopeState'),       input: document.getElementById('assigned_state_code') },
            zone:        { el: document.getElementById('scopeZone'),        input: document.getElementById('assigned_zonal_command_code') },
            directorate: { el: document.getElementById('scopeDirectorate'), input: document.getElementById('assigned_directorate_code') },
            cgis:        { el: document.getElementById('scopeCgis'),        input: document.getElementById('assigned_cgis_unit_code') },
            hq:          { el: document.getElementById('scopeHq'),          input: null },
        };

        const catToScope = {
            state_user: 'state',
            desk_admin: 'state',
            zonal_commander: 'zone',
            directorate_user: 'directorate',
            directorate_admin: 'directorate',
            cgis_unit_user: 'cgis',
            cgis_desk_admin: 'cgis',
            hq_admin: 'hq',
            admin: 'hq',
            super_admin: 'hq',
        };

        function updateScopePanel() {
            const active = catToScope[catSel?.value] || null;
            Object.entries(panels).forEach(([key, cfg]) => {
                const show = key === active;
                if (cfg.el) cfg.el.style.display = show ? 'block' : 'none';
                if (cfg.input) cfg.input.required = show;
            });
        }

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
    })();
</script>

@include('partials.footer')
