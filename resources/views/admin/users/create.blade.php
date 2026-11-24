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
        gap: 10px;
        margin-bottom: 16px;
        padding-bottom: 10px;
        border-bottom: 1px solid #f1f5f9;
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
    .iam-hint {
        display: block;
        font-size: .76rem;
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
    @media (max-width: 720px) {
        .iam-grid-2 { grid-template-columns: 1fr; }
    }
</style>

<main class="redas-content" style="max-width:860px;margin:24px auto;padding:0 16px 32px;">
    <div class="page-header" style="margin-bottom:18px;">
        <div>
            <h1 class="page-title">Create New User</h1>
            <p class="page-subtitle">Provision a verified NIS officer account with role-based access, scope boundaries, and first-login security controls.</p>
        </div>
        <a href="{{ route('admin.users') }}" class="btn-nis btn-ghost">
            <i class="fas fa-arrow-left"></i> Back to Users
        </a>
    </div>

    <div class="iam-notice">
        <i class="fas fa-shield-halved" style="margin-top:2px;flex-shrink:0;"></i>
        <div>
            <strong>Zero-Trust Onboarding Enforced:</strong> An official email address is required to deliver the account verification link. On first sign-in with their <strong>4–5 digit Service No</strong>, the user will be required to scan the <strong>Google Authenticator QR code</strong> and <strong>change their initial password</strong>.
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
                <span class="iam-step-badge">1</span>
                <h2 class="iam-section-title">Officer Identity</h2>
            </div>

            <div class="iam-grid-2">
                <div class="auth-form-group">
                    <label class="form-label-nis" for="name">Full Name <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" class="auth-input" placeholder="e.g. CIS Ibrahim Musa" required>
                </div>

                <div class="auth-form-group">
                    <label class="form-label-nis" for="service_number">Service Number (Login Username) <span style="color:#dc2626;">*</span></label>
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
                        title="Service number must be 4 or 5 digits only (0-9)"
                        required>
                    <span class="iam-hint">4 or 5 digits only (e.g. <code>1234</code> or <code>35562</code>). Used to sign in.</span>
                </div>
            </div>

            <div class="auth-form-group" style="margin-top:14px;margin-bottom:0;">
                <label class="form-label-nis" for="email">Official Email Address <span style="color:#dc2626;">*</span></label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" class="auth-input" placeholder="officer@immigration.gov.ng" required>
                <span class="iam-hint">Required — verification link and password reset notices are sent to this address.</span>
            </div>
        </div>

        {{-- Section 2: Role & Organisational Scope --}}
        <div class="iam-section">
            <div class="iam-section-head">
                <span class="iam-step-badge">2</span>
                <h2 class="iam-section-title">Role &amp; Organisational Scope</h2>
            </div>

            <div class="iam-grid-2">
                <div class="auth-form-group">
                    <label class="form-label-nis" for="user_category">Access Category <span style="color:#dc2626;">*</span></label>
                    <select id="user_category" name="user_category" class="auth-input" required>
                        <option value="" disabled {{ old('user_category') ? '' : 'selected' }}>Select access category</option>
                        @foreach($categories as $value => $label)
                            <option value="{{ $value }}" {{ old('user_category') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="iam-hint">Selecting a category automatically pairs the matching Role and Location Type.</span>
                </div>

                <div class="auth-form-group">
                    <label class="form-label-nis" for="role">System Role <span style="color:#dc2626;">*</span></label>
                    <select id="role" name="role" class="auth-input" required>
                        <option value="" disabled {{ old('role') ? '' : 'selected' }}>Select role</option>
                        @foreach($roles as $value => $label)
                            <option value="{{ $value }}" {{ old('role') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="auth-form-group">
                    <label class="form-label-nis" for="primary_location_type">Primary Location Type <span style="color:#dc2626;">*</span></label>
                    <select id="primary_location_type" name="primary_location_type" class="auth-input" required>
                        <option value="" disabled {{ old('primary_location_type') ? '' : 'selected' }}>Select location type</option>
                        @foreach($locationTypes as $value => $label)
                            <option value="{{ $value }}" {{ old('primary_location_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="auth-form-group">
                    <label class="form-label-nis" for="primary_location_code">Primary Location Code</label>
                    <input type="text" id="primary_location_code" name="primary_location_code" value="{{ old('primary_location_code') }}" list="nisLocationCodes" class="auth-input" placeholder="e.g. LA, FC, ZONE-A, HRM, HQ">
                    <datalist id="nisLocationCodes">
                        <option value="HQ">NIS Headquarters</option>
                        <option value="LA">Lagos State Command</option>
                        <option value="FC">FCT Abuja Command</option>
                        <option value="KN">Kano State Command</option>
                        <option value="RV">Rivers State Command</option>
                        <option value="KD">Kaduna State Command</option>
                        <option value="OG">Ogun State Command</option>
                        <option value="OY">Oyo State Command</option>
                        <option value="EN">Enugu State Command</option>
                        <option value="ZONE-A">Zone A (Lagos)</option>
                        <option value="ZONE-B">Zone B (Kaduna)</option>
                        <option value="ZONE-C">Zone C (Bauchi)</option>
                        <option value="ZONE-D">Zone D (Minna)</option>
                        <option value="ZONE-E">Zone E (Owerri)</option>
                        <option value="ZONE-F">Zone F (Ibadan)</option>
                        <option value="ZONE-G">Zone G (Benin)</option>
                        <option value="ZONE-H">Zone H (Makurdi)</option>
                        <option value="HRM">HRM Directorate</option>
                        <option value="PRS">PRS Directorate</option>
                        <option value="FINANCE">Finance Directorate</option>
                        <option value="VISA">Visa &amp; Residency</option>
                        <option value="PASSPORT">Passport &amp; OTD</option>
                        <option value="MIGRATION">Migration Directorate</option>
                        <option value="BORDER">Border Management</option>
                        <option value="INVESTIGATION">Investigation &amp; Compliance</option>
                        <option value="ICT">ICT Directorate</option>
                        <option value="WORKS-LOGISTICS">Works &amp; Logistics</option>
                    </datalist>
                    <span class="iam-hint">State code (<code>LA</code>, <code>FC</code>), Zone (<code>ZONE-A</code>), Directorate (<code>PRS</code>, <code>HRM</code>), or <code>HQ</code>.</span>
                </div>

                <div class="auth-form-group" id="cgisUnitGroup">
                    <label class="form-label-nis" for="assigned_cgis_unit_code">CGIS Unit</label>
                    <select id="assigned_cgis_unit_code" name="assigned_cgis_unit_code" class="auth-input">
                        <option value="" {{ old('assigned_cgis_unit_code') === null ? 'selected' : '' }}>None</option>
                        @foreach($cgisUnits as $value => $label)
                            <option value="{{ $value }}" {{ old('assigned_cgis_unit_code') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="iam-hint">Required only for CGIS Unit User and CGIS Unit Desk Admin accounts.</span>
                </div>

                <div class="auth-form-group">
                    <label class="form-label-nis" for="geo_state">ABAC Login State Override (Optional)</label>
                    <input type="text" id="geo_state" name="geo_state" value="{{ old('geo_state') }}" list="nisLocationCodes" class="auth-input" placeholder="Optional 2-letter state code">
                    <span class="iam-hint">Restricts login geolocation to a specific state when ABAC enforcement is enabled.</span>
                </div>
            </div>
        </div>

        {{-- Section 3: Initial Credentials & Account Status --}}
        <div class="iam-section">
            <div class="iam-section-head" style="justify-content:space-between;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span class="iam-step-badge">3</span>
                    <h2 class="iam-section-title">Initial Password &amp; Status</h2>
                </div>
                <button type="button" id="generatePwBtn" class="btn-nis btn-outline-nis btn-sm">
                    <i class="fas fa-wand-magic-sparkles"></i> Generate Strong Password
                </button>
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
            <span class="iam-hint" style="margin-bottom:16px;">Minimum 6 characters: must include uppercase letter, lowercase letter, number, and special character.</span>

            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding-top:12px;border-top:1px solid #f1f5f9;">
                <label style="display:inline-flex;align-items:center;gap:10px;cursor:pointer;font-size:.88rem;font-weight:600;color:#1e293b;">
                    <input type="checkbox" name="is_enabled" value="1" {{ old('is_enabled', '1') === '1' ? 'checked' : '' }} style="accent-color:#006633;width:17px;height:17px;">
                    Account Enabled (Active immediately upon email verification)
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
        // Live numeric-only 4-5 digit guard for Service Number
        const snInput = document.getElementById('service_number');
        if (snInput) {
            snInput.addEventListener('input', () => {
                snInput.value = snInput.value.replace(/\D/g, '').slice(0, 5);
            });
        }

        // Auto-pair Role and Primary Location Type when Access Category changes
        const catMap = {
            state_user:        { role: 'officer',      loc: 'state' },
            desk_admin:        { role: 'state',        loc: 'state' },
            directorate_user:  { role: 'directorate',  loc: 'directorate' },
            directorate_admin: { role: 'admin',        loc: 'directorate' },
            zonal_commander:   { role: 'zonal',        loc: 'zonal' },
            cgis_unit_user:    { role: 'unit_officer', loc: 'unit' },
            cgis_desk_admin:   { role: 'unit_admin',   loc: 'unit' },
            hq_admin:          { role: 'admin',        loc: 'headquarters', code: 'HQ' },
            admin:             { role: 'admin',        loc: 'headquarters', code: 'HQ' },
            super_admin:       { role: 'super_admin',  loc: 'headquarters', code: 'HQ' },
        };

        const catSel = document.getElementById('user_category');
        const roleSel = document.getElementById('role');
        const locSel = document.getElementById('primary_location_type');
        const codeInput = document.getElementById('primary_location_code');
        const cgisSel = document.getElementById('assigned_cgis_unit_code');

        if (catSel && roleSel && locSel) {
            catSel.addEventListener('change', () => {
                const cfg = catMap[catSel.value];
                if (!cfg) return;
                roleSel.value = cfg.role;
                locSel.value = cfg.loc;
                if (cfg.code && codeInput && !codeInput.value) {
                    codeInput.value = cfg.code;
                }
                if (cgisSel) {
                    cgisSel.required = (cfg.loc === 'unit');
                }
            });
        }

        // One-click cryptographically random policy-compliant temporary password generator
        const genBtn = document.getElementById('generatePwBtn');
        const pw1 = document.getElementById('password');
        const pw2 = document.getElementById('password_confirmation');
        if (genBtn && pw1 && pw2) {
            genBtn.addEventListener('click', () => {
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
                let chars = [rand(upper), rand(lower), rand(nums), rand(syms)];
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
            });
        }
    })();
</script>

@include('partials.footer')
