<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Verify your identity with Google Authenticator for NIS-REDAS.">
    <title>NIS-REDAS | Authenticator Verification</title>
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

        .mfa-split { min-height: 100vh; width: 100%; display: flex; align-items: stretch; }

        .mfa-side {
            width: 410px;
            flex-shrink: 0;
            background: linear-gradient(165deg, rgba(5, 36, 20, 0.84) 0%, rgba(9, 28, 44, 0.88) 100%);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-right: 1px solid rgba(255, 255, 255, 0.12);
            color: #ffffff;
            padding: 36px 32px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 26px;
        }

        .mfa-side-brand { display: flex; flex-direction: column; align-items: flex-start; }
        .mfa-side-brand img { width: 52px; height: 52px; object-fit: contain; margin-bottom: 12px; }
        .mfa-side-name { font-size: 1.45rem; font-weight: 800; letter-spacing: .04em; color: #ffffff; line-height: 1.1; }
        .mfa-side-sub { font-size: .85rem; color: rgba(255, 255, 255, 0.78); margin-top: 6px; }
        .mfa-side-bar { width: 44px; height: 3px; background: var(--gold-500, #d4a843); border-radius: 2px; margin-top: 14px; }

        .mfa-features { display: flex; flex-direction: column; gap: 16px; }
        .mfa-feature { display: flex; align-items: flex-start; gap: 12px; }
        .mfa-feature-icon {
            width: 38px; height: 38px; border-radius: 10px;
            background: rgba(212, 168, 67, 0.16); border: 1px solid rgba(212, 168, 67, 0.35);
            color: #facc15; display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; font-size: .95rem;
        }
        .mfa-feature-title { font-size: .9rem; font-weight: 700; color: #ffffff; margin-bottom: 2px; }
        .mfa-feature-desc { font-size: .8rem; color: rgba(255, 255, 255, 0.75); line-height: 1.4; }
        .mfa-side-foot { font-size: .74rem; color: rgba(255, 255, 255, 0.6); border-top: 1px solid rgba(255, 255, 255, 0.12); padding-top: 14px; line-height: 1.5; }

        .mfa-main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 24px; overflow-y: auto; }
        .mfa-card {
            width: 100%; max-width: 450px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.45);
            border-radius: 20px; box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
            padding: 30px 24px;
        }
        .mfa-title { margin: 0; text-align: center; font-size: 1.5rem; line-height: 1.2; font-weight: 600; color: #334155; }
        .mfa-subtitle { margin: 8px 0 22px; text-align: center; color: #475569; font-size: .9rem; line-height: 1.45; }
        .auth-form-group { margin-bottom: 16px; }

        .btn-cancel-mfa {
            width: 100%; margin-top: 10px; padding: 10px 16px; border-radius: 12px;
            background: transparent; color: #475569; border: 1px solid #cbd5e1;
            font-size: .88rem; font-weight: 600; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-cancel-mfa:hover { background: #f8fafc; color: #0f172a; }

        @media (max-width: 900px) {
            .mfa-split { flex-direction: column; }
            .mfa-side { display: none !important; }
            .mfa-main { min-height: 100vh; padding: 16px 12px; }
            .mfa-card { border-radius: 16px; padding: 24px 16px; }
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
                    <div class="mfa-feature-title">Two-Step Verification</div>
                    <div class="mfa-feature-desc">An extra layer of security for your NIS account.</div>
                </div>
            </div>
            <div class="mfa-feature">
                <div class="mfa-feature-icon"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="mfa-feature-title">Time-Based Codes</div>
                    <div class="mfa-feature-desc">Codes refresh every 30 seconds in Google Authenticator.</div>
                </div>
            </div>
            <div class="mfa-feature">
                <div class="mfa-feature-icon"><i class="fas fa-user-lock"></i></div>
                <div>
                    <div class="mfa-feature-title">Account Protection</div>
                    <div class="mfa-feature-desc">Even with your password, your account stays protected.</div>
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
            <h1 class="mfa-title">Authenticator Code</h1>
            <p class="mfa-subtitle">Open Google Authenticator and enter the 6-digit code for Service No <strong>{{ $user->service_number }}</strong>.</p>

            @if($errors->any())
            <div style="background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;border-radius:var(--radius-md);padding:10px 14px;font-size:.85rem;margin-bottom:16px;display:flex;gap:8px;align-items:flex-start;">
                <i class="fas fa-exclamation-circle" style="margin-top:2px;flex-shrink:0;"></i>
                <span>{{ $errors->first() }}</span>
            </div>
            @endif

            @if(session('status'))
            <div style="background:#dcfce7;border:1px solid #86efac;color:#15803d;border-radius:var(--radius-md);padding:10px 14px;font-size:.85rem;margin-bottom:16px;display:flex;gap:8px;align-items:flex-start;">
                <i class="fas fa-check-circle" style="margin-top:2px;flex-shrink:0;"></i>
                <span>{{ session('status') }}</span>
            </div>
            @endif

            <form method="POST" action="{{ route('mfa.challenge.verify') }}">
                @csrf

                <div class="auth-form-group">
                    <label class="form-label-nis" for="mfaCode">
                        <i class="fas fa-shield-alt me-1 text-nis"></i> 6-Digit Code or Backup Code
                    </label>
                    <div class="auth-input-wrap">
                        <input type="text"
                            class="auth-input @error('code') is-invalid @enderror"
                            id="mfaCode"
                            name="code"
                            placeholder="000000"
                            maxlength="20"
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
                    <i class="fas fa-sign-in-alt"></i>
                    <span class="btn-text">Verify &amp; Continue</span>
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

</body>
</html>
