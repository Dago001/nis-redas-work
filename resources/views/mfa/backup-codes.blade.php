<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Save your MFA backup codes for NIS-REDAS.">
    <title>NIS-REDAS | Backup Codes</title>
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
            padding: 28px 24px;
        }
        .mfa-title { margin: 0; text-align: center; font-size: 1.45rem; line-height: 1.2; font-weight: 600; color: #334155; }
        .mfa-subtitle { margin: 8px 0 18px; text-align: center; color: #475569; font-size: .88rem; line-height: 1.45; }

        .backup-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-bottom: 20px; }
        .backup-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: .92rem; font-weight: 600; letter-spacing: 0.05em; color: #0f172a;
            background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px;
            padding: 9px 10px; text-align: center; user-select: all;
        }

        .btn-cancel-mfa {
            width: 100%; margin-top: 10px; padding: 10px 16px; border-radius: 12px;
            background: transparent; color: #475569; border: 1px solid #cbd5e1;
            font-size: .88rem; font-weight: 600; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-cancel-mfa:hover { background: #f8fafc; color: #0f172a; }

        @media (max-width: 900px) {
            .mfa-split { flex-direction: column; }
            .mfa-side { width: 100%; border-right: none; border-bottom: 1px solid rgba(255, 255, 255, 0.14); padding: 20px 18px; gap: 14px; }
            .mfa-side-brand { flex-direction: row; align-items: center; gap: 12px; }
            .mfa-side-brand img { width: 40px; height: 40px; margin-bottom: 0; }
            .mfa-side-bar, .mfa-side-foot { display: none; }
            .mfa-features { gap: 10px; }
            .mfa-feature-icon { width: 32px; height: 32px; font-size: .85rem; }
            .mfa-main { padding: 16px 12px 24px; }
            .mfa-card { border-radius: 16px; padding: 22px 16px; }
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
                <div class="mfa-feature-icon"><i class="fas fa-save"></i></div>
                <div>
                    <div class="mfa-feature-title">Save These Codes</div>
                    <div class="mfa-feature-desc">Store them securely or print them. You will not see them again.</div>
                </div>
            </div>
            <div class="mfa-feature">
                <div class="mfa-feature-icon"><i class="fas fa-lock"></i></div>
                <div>
                    <div class="mfa-feature-title">Single Use Only</div>
                    <div class="mfa-feature-desc">Each backup code works once if your authenticator phone is unavailable.</div>
                </div>
            </div>
            <div class="mfa-feature">
                <div class="mfa-feature-icon"><i class="fas fa-shield-alt"></i></div>
                <div>
                    <div class="mfa-feature-title">Keep Confidential</div>
                    <div class="mfa-feature-desc">Backup codes grant account access — never share them with anyone.</div>
                </div>
            </div>
        </div>

        <div class="mfa-side-foot">
            <i class="fas fa-lock me-1"></i> RESTRICTED — For Official NIS Use Only<br>
            &copy; {{ date('Y') }} Nigeria Immigration Service
        </div>
    </aside>

    <main class="mfa-main">
        <div class="mfa-card">
            <h1 class="mfa-title">MFA Enabled Successfully</h1>
            <p class="mfa-subtitle">Save these single-use backup codes in a safe place before continuing.</p>

            <div style="background:#dcfce7;border:1px solid #86efac;color:#15803d;border-radius:var(--radius-md);padding:10px 14px;font-size:.84rem;margin-bottom:16px;display:flex;gap:8px;align-items:flex-start;">
                <i class="fas fa-check-circle" style="margin-top:2px;flex-shrink:0;"></i>
                <span>Your authenticator is active. These codes recover access if your phone is unavailable.</span>
            </div>

            <div class="backup-grid">
                @foreach($codes as $code)
                <div class="backup-code">{{ $code }}</div>
                @endforeach
            </div>

            <form method="POST" action="{{ route('mfa.complete') }}">
                @csrf
                <button type="submit" class="btn-auth">
                    <i class="fas fa-arrow-right"></i>
                    <span class="btn-text">Continue</span>
                </button>
            </form>

            <form method="POST" action="{{ route('mfa.cancel') }}" style="margin:0;">
                @csrf
                <button type="submit" class="btn-cancel-mfa">
                    <i class="fas fa-sign-in-alt"></i> Return to Sign In
                </button>
            </form>
        </div>
    </main>
</div>

</body>
</html>
