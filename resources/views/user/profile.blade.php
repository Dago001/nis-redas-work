@include('partials.header')

<main class="redas-content" style="max-width:720px;margin:32px auto;padding:0 16px;">
    <div class="page-header">
        <div>
            <h1 class="page-title">Profile</h1>
            <p class="page-subtitle">Update your password. Your user details cannot be changed here.</p>
        </div>
        <a href="{{ url()->previous() ?? route('user.dashboard') }}" class="btn-nis btn-ghost">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;">
            <span><i class="fas fa-check-circle me-1"></i> {{ session('status') }}</span>
            @if(! $user->must_change_password)
                @php
                    $dashUrl = match ($user->role) {
                        'super_admin' => route('superadmin.dashboard'),
                        'admin', 'hq_admin' => route('admin.dashboard'),
                        'zonal_commander' => url('/zonal/dashboard'),
                        'desk_admin', 'directorate_admin' => url('/desk-admin/dashboard'),
                        'directorate' => route('user.directorates.dashboard'),
                        'cgis_unit' => route('user.cgis-units.dashboard'),
                        default => route('user.dashboard'),
                    };
                @endphp
                <a href="{{ $dashUrl }}" class="btn-nis btn-primary-nis btn-sm">
                    Continue to Dashboard <i class="fas fa-arrow-right ms-1"></i>
                </a>
            @endif
        </div>
    @endif

    @if($user->must_change_password)
        <div class="alert" style="margin-bottom:16px;background:#fffbeb;border:1px solid #fcd34d;color:#92400e;padding:12px 16px;border-radius:8px;">
            <i class="fas fa-triangle-exclamation"></i>
            For your security, you must change your initial password before you can continue using the system.
        </div>
    @endif

    <div class="redas-card">
        <div class="card-head">
            <div class="card-head-title">
                <div class="card-head-icon" style="background:#e0f2fe;color:#0369a1;"><i class="fas fa-user-lock"></i></div>
                Change Password
            </div>
        </div>
        <div class="card-body" style="padding:24px;">
            <form method="POST" action="{{ route('user.profile.update') }}">
                @csrf
                @method('PATCH')

                <div class="auth-form-group" style="margin-bottom:18px;">
                    <label class="form-label-nis">Service Number</label>
                    <input type="text" class="auth-input" value="{{ $user->service_number }}" disabled>
                </div>

                <div class="auth-form-group" style="margin-bottom:18px;">
                    <label class="form-label-nis">Your Name</label>
                    <input type="text" class="auth-input" value="{{ $user->name }}" disabled>
                </div>

                <div class="auth-form-group" style="margin-bottom:18px;">
                    <label class="form-label-nis">Email Address</label>
                    <input type="email" class="auth-input" value="{{ $user->email }}" disabled>
                </div>

                <div class="auth-form-group" style="margin-bottom:18px;">
                    <label class="form-label-nis">Current Password</label>
                    <div class="auth-input-wrap pw-wrap" style="position:relative;">
                        <input type="password" name="current_password" class="auth-input" style="padding-right:40px;" placeholder="Enter your current password" required>
                        <span class="auth-input-icon"><i class="fas fa-key"></i></span>
                        <button type="button" class="pw-toggle" aria-label="Toggle current password visibility" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400);padding:4px;">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    @error('current_password')
                        <div class="inline-error"><i class="fas fa-circle-xmark"></i>{{ $message }}</div>
                    @enderror
                </div>

                <div class="auth-form-group" style="margin-bottom:18px;">
                    <label class="form-label-nis">New Password</label>
                    <div class="auth-input-wrap pw-wrap" style="position:relative;">
                        <input type="password" name="password" class="auth-input" style="padding-right:40px;" placeholder="Enter new password" minlength="6" required>
                        <span class="auth-input-icon"><i class="fas fa-lock"></i></span>
                        <button type="button" class="pw-toggle" aria-label="Toggle new password visibility" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400);padding:4px;">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div style="font-size:.78rem;color:#475569;margin-top:6px;">
                        Minimum 6 characters: must include uppercase, lowercase, number, and special character.
                    </div>
                    @error('password')
                        <div class="inline-error"><i class="fas fa-circle-xmark"></i>{{ $message }}</div>
                    @enderror
                </div>

                <div class="auth-form-group" style="margin-bottom:24px;">
                    <label class="form-label-nis">Confirm New Password</label>
                    <div class="auth-input-wrap pw-wrap" style="position:relative;">
                        <input type="password" name="password_confirmation" class="auth-input" style="padding-right:40px;" placeholder="Confirm new password" minlength="6" required>
                        <span class="auth-input-icon"><i class="fas fa-lock"></i></span>
                        <button type="button" class="pw-toggle" aria-label="Toggle confirm password visibility" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400);padding:4px;">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-nis btn-primary-nis full-width">
                    <i class="fas fa-sync-alt"></i> Update Password
                </button>
            </form>
        </div>
    </div>
</main>

@include('partials.footer')
