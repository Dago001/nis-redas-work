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
        }

        .mfa-page {
            min-height: 100vh;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background:
                linear-gradient(135deg, rgba(6, 36, 20, 0.58) 0%, rgba(10, 25, 47, 0.66) 100%),
                url("{{ asset('assets/images/nis-hq-bg.jpg') }}") center center / cover no-repeat fixed;
        }

        .mfa-card {
            width: 100%;
            max-width: 460px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.45);
            border-radius: 20px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
            padding: 28px 24px;
        }

        .mfa-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 14px;
        }

        .mfa-brand img {
            width: 36px;
            height: 36px;
            object-fit: contain;
        }

        .mfa-brand-name {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--nis-700);
            letter-spacing: .02em;
        }

        .mfa-title {
            margin: 0;
            text-align: center;
            font-size: 1.45rem;
            line-height: 1.2;
            font-weight: 600;
            color: #334155;
        }

        .mfa-subtitle {
            margin: 8px 0 18px;
            text-align: center;
            color: #475569;
            font-size: .88rem;
            line-height: 1.45;
        }

        .backup-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }

        .backup-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: .92rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            color: #0f172a;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            padding: 9px 10px;
            text-align: center;
            user-select: all;
        }

        .btn-cancel-mfa {
            width: 100%;
            margin-top: 10px;
            padding: 10px 16px;
            border-radius: 12px;
            background: transparent;
            color: #475569;
            border: 1px solid #cbd5e1;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-cancel-mfa:hover {
            background: #f8fafc;
            color: #0f172a;
        }

        @media (max-width: 576px) {
            .mfa-page { padding: 16px 12px; }
            .mfa-card { border-radius: 16px; padding: 22px 16px; }
            .mfa-title { font-size: 1.3rem; }
        }
    </style>
</head>
<body>

@include('partials.preloader')

<div class="mfa-page">
    <main class="mfa-card">
        <div class="mfa-brand">
            <img src="{{ asset('assets/images/nis.png') }}" alt="NIS Logo">
            <span class="mfa-brand-name">NIS REDAS</span>
        </div>

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
    </main>
</div>

</body>
</html>
