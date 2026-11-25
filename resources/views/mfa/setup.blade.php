<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Set up Google Authenticator multi-factor authentication for NIS-REDAS.">
    <title>NIS-REDAS | Set Up Authenticator</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/images/nis.png') }}">
    @include('partials.head-meta')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @laravelPWA

    <style>
        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            overflow-x: hidden;
            background:
                linear-gradient(135deg, rgba(6, 36, 20, 0.56) 0%, rgba(10, 25, 47, 0.64) 100%),
                url("{{ asset('assets/images/nis-hq-bg.jpg') }}") center center / cover no-repeat fixed;
        }

        .mfa-split {
            min-height: 100vh;
            width: 100%;
            display: flex;
            align-items: stretch;
        }

        @media (min-width: 901px) {
            html, body, .mfa-split {
                height: 100vh;
                overflow: hidden;
            }
        }

        /* Left translucent glass side widget */
        .mfa-side {
            width: 390px;
            flex-shrink: 0;
            background: linear-gradient(165deg, rgba(5, 36, 20, 0.84) 0%, rgba(9, 28, 44, 0.88) 100%);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-right: 1px solid rgba(255, 255, 255, 0.12);
            color: #ffffff;
            padding: 28px 28px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 20px;
        }

        .mfa-side-brand {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .mfa-side-brand img {
            width: 52px;
            height: 52px;
            object-fit: contain;
            margin-bottom: 12px;
        }

        .mfa-side-name {
            font-size: 1.45rem;
            font-weight: 800;
            letter-spacing: .04em;
            color: #ffffff;
            line-height: 1.1;
        }

        .mfa-side-sub {
            font-size: .85rem;
            color: rgba(255, 255, 255, 0.78);
            margin-top: 6px;
        }

        .mfa-side-bar {
            width: 44px;
            height: 3px;
            background: var(--gold-500, #d4a843);
            border-radius: 2px;
            margin-top: 14px;
        }

        .mfa-features {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .mfa-feature {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .mfa-feature-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(212, 168, 67, 0.16);
            border: 1px solid rgba(212, 168, 67, 0.35);
            color: #facc15;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: .95rem;
        }

        .mfa-feature-title {
            font-size: .9rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 2px;
        }

        .mfa-feature-desc {
            font-size: .8rem;
            color: rgba(255, 255, 255, 0.75);
            line-height: 1.4;
        }

        .mfa-side-foot {
            font-size: .74rem;
            color: rgba(255, 255, 255, 0.6);
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            padding-top: 14px;
            line-height: 1.5;
        }

        /* Right panel */
        .mfa-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            overflow: hidden;
        }

        .mfa-card {
            width: 100%;
            max-width: 440px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.45);
            border-radius: 18px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
            padding: 18px 22px;
        }

        .mfa-title {
            margin: 0;
            text-align: center;
            font-size: 1.3rem;
            line-height: 1.2;
            font-weight: 600;
            color: #334155;
        }

        .mfa-subtitle {
            margin: 4px 0 10px;
            text-align: center;
            color: #475569;
            font-size: .83rem;
            line-height: 1.35;
        }

        .qr-panel {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            margin-bottom: 10px;
        }

        .qr-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 8px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .qr-box svg {
            width: 132px !important;
            height: 132px !important;
            display: block;
        }

        .secret-wrap { width: 100%; text-align: center; }

        .secret-label {
            font-size: .68rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .secret-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: .8rem;
            font-weight: 600;
            color: #0f172a;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 6px 10px;
            word-break: break-all;
            margin-top: 3px;
            user-select: all;
        }

        .secret-hint {
            font-size: .71rem;
            color: #64748b;
            margin: 3px 0 0;
        }

        .auth-form-group { margin-bottom: 10px; }

        .btn-cancel-mfa {
            width: 100%;
            margin-top: 6px;
            padding: 8px 14px;
            border-radius: 10px;
            background: transparent;
            color: #475569;
            border: 1px solid #cbd5e1;
            font-size: .85rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-cancel-mfa:hover { background: #f8fafc; color: #0f172a; }

        /* Tablet & Phone: stack cleanly so nothing is ever hidden */
        @media (max-width: 900px) {
            .mfa-split { flex-direction: column; }
            .mfa-side {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.14);
                padding: 20px 18px;
                gap: 14px;
            }
            .mfa-side-brand { flex-direction: row; align-items: center; gap: 12px; }
            .mfa-side-brand img { width: 40px; height: 40px; margin-bottom: 0; }
            .mfa-side-bar, .mfa-side-foot { display: none; }
            .mfa-features { gap: 10px; }
            .mfa-feature-icon { width: 32px; height: 32px; font-size: .85rem; }
            .mfa-main { padding: 16px 12px 24px; }
            .mfa-card { border-radius: 16px; padding: 20px 16px; }
        }
    </style>
</head>
<body>

@include('partials.preloader')

<div class="mfa-split">
    <aside class="mfa-side">
        <div class="mfa-side-brand">
            <img src="{{ asset('assets/images/nis.png') }}" alt="NIS Logo">
            <div>
                <div class="mfa-side-name">NIS REDAS</div>
                <div class="mfa-side-sub">Reporting Dashboard &amp; Archiving System</div>
                <div class="mfa-side-bar"></div>
            </div>
        </div>

        <div class="mfa-features">
            <div class="mfa-feature">
                <div class="mfa-feature-icon"><i class="fas fa-shield-alt"></i></div>
                <div>
                    <div class="mfa-feature-title">Enhanced Security</div>
                    <div class="mfa-feature-desc">MFA protects your NIS account even if your password is compromised.</div>
                </div>
            </div>
            <div class="mfa-feature">
                <div class="mfa-feature-icon"><i class="fas fa-mobile-alt"></i></div>
                <div>
                    <div class="mfa-feature-title">Google Authenticator</div>
                    <div class="mfa-feature-desc">Scan the QR code once with Google Authenticator or any TOTP app.</div>
                </div>
            </div>
            <div class="mfa-feature">
                <div class="mfa-feature-icon"><i class="fas fa-key"></i></div>
                <div>
                    <div class="mfa-feature-title">Backup Codes</div>
                    <div class="mfa-feature-desc">Single-use backup codes keep you covered when your phone is unavailable.</div>
                </div>
            </div>
        </div>

        <div class="mfa-side-foot">
            <i class="fas fa-lock me-1"></i> RESTRICTED - For Official NIS Use Only<br>
            &copy; {{ date('Y') }} Nigeria Immigration Service
        </div>
    </aside>

    <main class="mfa-main">
        <div class="mfa-card">
            <h1 class="mfa-title">Set Up Google Authenticator</h1>
            <p class="mfa-subtitle">Scan the QR code with Google Authenticator, then enter the 6-digit code below.</p>

            @if($errors->any())
            <div style="background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;border-radius:var(--radius-md);padding:10px 14px;font-size:.84rem;margin-bottom:12px;display:flex;gap:8px;align-items:flex-start;">
                <i class="fas fa-exclamation-circle" style="margin-top:2px;flex-shrink:0;"></i>
                <span>{{ $errors->first() }}</span>
            </div>
            @endif

            <div class="qr-panel">
                <div class="qr-box">
                    {!! $qrSvg !!}
                </div>

                <div class="secret-wrap">
                    <div class="secret-label">Manual Setup Key</div>
                    <div class="secret-code">{{ trim(chunk_split($secret, 4, ' ')) }}</div>
                    <p class="secret-hint">If you cannot scan the QR code, enter this key manually in your authenticator app.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('mfa.setup.verify') }}">
                @csrf

                <div class="auth-form-group">
                    <label class="form-label-nis" for="mfaCode">
                        <i class="fas fa-shield-alt me-1 text-nis"></i> 6-Digit Authenticator Code
                    </label>
                    <div class="auth-input-wrap">
                        <input type="text"
                            class="auth-input @error('code') is-invalid @enderror"
                            id="mfaCode"
                            name="code"
                            placeholder="000000"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            minlength="6"
                            maxlength="6"
                            autocomplete="one-time-code"
                            required
                            autofocus>
                        <span class="auth-input-icon"><i class="fas fa-key"></i></span>
                    </div>
                    @error('code')
                    <div style="color:var(--color-danger);font-size:.76rem;margin-top:4px;"><i class="fas fa-circle-xmark me-1"></i>{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn-auth">
                    <i class="fas fa-check-circle"></i>
                    <span class="btn-text">Verify &amp; Enable</span>
                </button>
            </form>

            <form method="POST" action="{{ route('mfa.cancel') }}" style="margin:0;">
                @csrf
                <button type="submit" class="btn-cancel-mfa">
                    <i class="fas fa-times"></i> Cancel &amp; Return to Sign In
                </button>
            </form>
        </div>
    </main>
</div>

<script>
    (function () {
        const el = document.getElementById('mfaCode');
        if (el) {
            el.addEventListener('input', () => { el.value = el.value.replace(/\D/g, '').slice(0, 6); });
        }
    })();
</script>
</body>
</html>
