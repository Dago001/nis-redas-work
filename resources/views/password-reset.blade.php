<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Set a new password for NIS-REDAS — Nigeria Immigration Service Reporting Dashboard & Archiving System.">
    <title>NIS-REDAS | Reset Password</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/images/nis.png') }}">
    @include('partials.head-meta')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @laravelPWA

    <style>
        *, *::before, *::after { box-sizing: border-box; }

        .login-page {
            min-height: 100vh;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background:
                linear-gradient(135deg, rgba(6, 36, 20, 0.58) 0%, rgba(10, 25, 47, 0.66) 100%),
                url("{{ asset('assets/images/nis-hq-bg.jpg') }}") center center / cover no-repeat fixed;
        }

        .login-card {
            width: 100%;
            max-width: 460px;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.45);
            border-radius: 20px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
            padding: 30px 24px;
        }

        .login-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 18px;
        }

        .login-brand img {
            width: 38px;
            height: 38px;
            object-fit: contain;
        }

        .login-brand-name {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--nis-700);
            letter-spacing: .02em;
        }

        .login-title {
            margin: 0;
            text-align: center;
            font-size: 1.7rem;
            line-height: 1.15;
            font-weight: 800;
            color: #0f172a;
        }

        .login-subtitle {
            margin: 8px 0 24px;
            text-align: center;
            color: #475569;
            font-size: .94rem;
        }

        .auth-form-group {
            margin-bottom: 16px;
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
            color: var(--gray-400);
            padding: 4px;
        }
        .pw-wrap .pw-toggle:hover { color: var(--gray-600); }
        .pw-wrap input { padding-right: 40px; }

        .password-hint {
            font-size: .78rem;
            color: #475569;
            margin-top: 6px;
            line-height: 1.4;
        }

        .inline-error {
            color: var(--color-danger);
            font-size: .78rem;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .back-link-wrap {
            margin-top: 18px;
            text-align: center;
        }

        @media (max-width: 576px) {
            .login-page { padding: 16px; }
            .login-card { border-radius: 14px; padding: 24px 16px; }
            .login-title { font-size: 1.45rem; }
        }
    </style>
</head>
<body>

@include('partials.preloader')

<div class="login-page">
    <main class="login-card">
        <div class="login-brand">
            <img src="{{ asset('assets/images/nis.png') }}" alt="NIS Logo">
            <span class="login-brand-name">NIS REDAS</span>
        </div>

        <h1 class="login-title">Reset Password</h1>
        <p class="login-subtitle">Create a strong new password for your NIS account.</p>

        @if ($errors->any())
        <div style="background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;border-radius:var(--radius-md);padding:12px 16px;font-size:.875rem;margin-bottom:20px;display:flex;gap:10px;align-items:flex-start;">
            <i class="fas fa-exclamation-circle" style="margin-top:2px;flex-shrink:0;"></i>
            <span>{{ $errors->first() }}</span>
        </div>
        @endif

        <form method="POST" action="{{ route('password.reset', ['token' => $token]) }}">
            @csrf

            <div class="auth-form-group">
                <label class="form-label-nis" for="password">
                    <i class="fas fa-lock me-1 text-nis"></i> New Password
                </label>
                <div class="auth-input-wrap pw-wrap">
                    <input type="password"
                        class="auth-input @error('password') is-invalid @enderror"
                        id="password"
                        name="password"
                        placeholder="Enter new password"
                        autocomplete="new-password"
                        minlength="6"
                        required>
                    <span class="auth-input-icon"><i class="fas fa-lock"></i></span>
                    <button type="button" class="pw-toggle" aria-label="Toggle password visibility">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="password-hint">
                    Minimum 6 characters: must include uppercase, lowercase, number, and special character.
                </div>
                @error('password')
                <div class="inline-error"><i class="fas fa-circle-xmark"></i>{{ $message }}</div>
                @enderror
            </div>

            <div class="auth-form-group">
                <label class="form-label-nis" for="password_confirmation">
                    <i class="fas fa-lock me-1 text-nis"></i> Confirm New Password
                </label>
                <div class="auth-input-wrap pw-wrap">
                    <input type="password"
                        class="auth-input"
                        id="password_confirmation"
                        name="password_confirmation"
                        placeholder="Confirm new password"
                        autocomplete="new-password"
                        minlength="6"
                        required>
                    <span class="auth-input-icon"><i class="fas fa-lock"></i></span>
                    <button type="button" class="pw-toggle" aria-label="Toggle confirm password visibility">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-auth">
                <i class="fas fa-key"></i>
                <span class="btn-text">Reset Password</span>
            </button>
        </form>

        <div class="back-link-wrap">
            <a href="{{ route('login') }}" class="auth-back-link">
                <i class="fas fa-arrow-left"></i> Back to Sign In
            </a>
        </div>
    </main>
</div>

</body>
</html>
