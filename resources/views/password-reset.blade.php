<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset password</title>
    @include('partials.head-meta')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @laravelPWA
    <style>*, *::before, *::after { box-sizing: border-box; }</style>
</head>
<body style="margin:0; padding:24px; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:Inter, system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, rgba(6, 36, 20, 0.58) 0%, rgba(10, 25, 47, 0.66) 100%), url('{{ asset('assets/images/nis-hq-bg.jpg') }}') center center / cover no-repeat fixed;">

@include('partials.preloader')

<div style="width:100%; max-width:520px; background:rgba(255,255,255,0.94); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,0.45); border-radius:20px; box-shadow:0 24px 60px rgba(0,0,0,0.35); padding:32px;">
    <h2>Reset password</h2>

    @if ($errors->any())
        <div style="background:#ffecec; border:1px solid #ffb3b3; padding:10px; margin-bottom:15px;">
            <ul style="margin:0; padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('password.reset', ['token' => $token]) }}">
        @csrf
        <div style="margin-bottom:12px;">
            <label>New password</label><br/>
            <input type="password" name="password" required style="width:100%; padding:10px;" />
        </div>

        <div style="margin-bottom:12px;">
            <label>Confirm new password</label><br/>
            <input type="password" name="password_confirmation" required style="width:100%; padding:10px;" />
        </div>

        <button type="submit" style="padding:10px 16px; cursor:pointer;">Reset password</button>
    </form>
</div>
</body>
</html>
