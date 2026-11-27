<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Session Expired | NIS-REDAS</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/images/nis.png') }}">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background:
                linear-gradient(135deg, rgba(6, 36, 20, 0.56) 0%, rgba(10, 25, 47, 0.64) 100%),
                url("{{ asset('assets/images/nis-hq-bg.jpg') }}") center center / cover no-repeat fixed;
            color: #1f2937;
        }
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.45);
            max-width: 460px;
            width: 100%;
            padding: 34px 30px;
            border-radius: 20px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
            text-align: center;
        }
        .brand-logo {
            width: 60px;
            height: 60px;
            object-fit: contain;
            margin-bottom: 10px;
        }
        .brand-sub {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #006838;
            margin-bottom: 14px;
        }
        h1 { font-size: 1.3rem; font-weight: 700; color: #0f172a; margin: 0 0 8px; }
        p { font-size: .9rem; color: #475569; line-height: 1.5; margin: 0; }
        .note {
            margin-top: 16px;
            padding: 10px 14px;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 10px;
            font-size: .82rem;
            color: #065f46;
            line-height: 1.45;
        }
        .actions { margin-top: 22px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 22px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: .88rem;
            transition: background .15s, transform .15s;
        }
        .btn-primary { background: #006838; color: #fff; }
        .btn-primary:hover { background: #00522c; }
        .btn-secondary { background: #f1f5f9; color: #1e293b; border: 1px solid #cbd5e1; }
        .btn-secondary:hover { background: #e2e8f0; }
    </style>
</head>
<body>
    <div class="card">
        <img src="{{ asset('assets/images/nis.png') }}" alt="NIS Logo" class="brand-logo">
        <div class="brand-sub">NIS REDAS</div>
        <h1>Your session has expired</h1>
        <p>For your security, this session timed out due to inactivity. Please log in again to continue.</p>
        <div class="note">If you were filling out a return form, your draft was saved automatically in this browser and will reload once you reopen the form.</div>
        <div class="actions">
            <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
            <a href="{{ route('login') }}" class="btn btn-primary">Log In Again</a>
        </div>
    </div>
</body>
</html>
