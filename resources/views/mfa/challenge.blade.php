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
            padding: 30px 24px;
        }

        .mfa-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 18px;
        }

        .mfa-brand img {
            width: 38px;
            height: 38px;
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
            font-size: 1.55rem;
            line-height: 1.2;
            font-weight: 600;
            color: #334155;
        }

        .mfa-subtitle {
            margin: 8px 0 22px;
            text-align: center;
            color: #475569;
            font-size: .92rem;
            line-height: 1.45;
        }

        .auth-form-group { margin-bottom: 16px; }

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
            .mfa-card { border-radius: 16px; padding: 24px 16px; }
            .mfa-title { font-size: 1.35rem; }
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
    </main>
</div>

</body>
</html>
