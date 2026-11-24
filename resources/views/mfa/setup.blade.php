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
        }

        .mfa-page {
            min-height: 100vh;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 16px;
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
            padding: 24px 24px;
        }

        .mfa-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .mfa-brand img {
            width: 34px;
            height: 34px;
            object-fit: contain;
        }

        .mfa-brand-name {
            font-size: 1rem;
            font-weight: 800;
            color: var(--nis-700);
            letter-spacing: .02em;
        }

        .mfa-title {
            margin: 0;
            text-align: center;
            font-size: 1.4rem;
            line-height: 1.2;
            font-weight: 600;
            color: #334155;
        }

        .mfa-subtitle {
            margin: 6px 0 16px;
            text-align: center;
            color: #475569;
            font-size: .88rem;
            line-height: 1.4;
        }

        .qr-panel {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        .qr-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 10px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .qr-box svg {
            width: 164px !important;
            height: 164px !important;
            display: block;
        }

        .secret-wrap {
            width: 100%;
            text-align: center;
        }

        .secret-label {
            font-size: .7rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .secret-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: .84rem;
            font-weight: 600;
            color: #0f172a;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 8px 10px;
            word-break: break-all;
            margin-top: 4px;
            user-select: all;
        }

        .secret-hint {
            font-size: .74rem;
            color: #64748b;
            margin: 6px 0 0;
        }

        .auth-form-group {
            margin-bottom: 14px;
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
            .mfa-page { padding: 14px 12px; align-items: flex-start; }
            .mfa-card { border-radius: 16px; padding: 20px 16px; }
            .mfa-title { font-size: 1.25rem; }
            .qr-box svg { width: 150px !important; height: 150px !important; }
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

        <h1 class="mfa-title">Set Up Google Authenticator</h1>
        <p class="mfa-subtitle">Scan the QR code with Google Authenticator, then enter the 6-digit code below.</p>

        @if($errors->any())
        <div style="background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;border-radius:var(--radius-md);padding:10px 14px;font-size:.85rem;margin-bottom:14px;display:flex;gap:8px;align-items:flex-start;">
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
