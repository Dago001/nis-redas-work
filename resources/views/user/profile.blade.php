@include('partials.header')
@php $dashUrl = $user->dashboardUrl(); @endphp

<main class="redas-content" style="max-width:720px;margin:32px auto;padding:0 16px;">
    <div class="page-header">
        <div>
            <h1 class="page-title">Profile</h1>
            <p class="page-subtitle">Manage your profile photo and account password.</p>
        </div>
        <a href="{{ $dashUrl }}" class="btn-nis btn-ghost">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;">
            <span><i class="fas fa-check-circle me-1"></i> {{ session('status') }}</span>
            @if(! $user->must_change_password)
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

    {{-- Profile Photo Upload / Change --}}
    <div class="redas-card" style="margin-bottom:20px;">
        <div class="card-head">
            <div class="card-head-title">
                <div class="card-head-icon" style="background:var(--nis-50);color:var(--nis-600);"><i class="fas fa-camera"></i></div>
                Profile Photo
            </div>
        </div>
        <div class="card-body" style="padding:20px 24px;">
            <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
                <div id="profilePhotoPreviewWrap" style="width:82px;height:82px;border-radius:50%;overflow:hidden;background:#006838;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;flex-shrink:0;border:3px solid #dcfce7;box-shadow:0 4px 12px rgba(0,104,56,.15);">
                    @if($user->profilePhotoUrl())
                        <img id="profilePhotoPreviewImg" src="{{ $user->profilePhotoUrl() }}" alt="{{ $user->name }}" style="width:100%;height:100%;object-fit:cover;display:block;">
                    @else
                        <span id="profilePhotoInitials">{{ strtoupper(substr($user->name ?? 'U', 0, 2)) }}</span>
                        <img id="profilePhotoPreviewImg" src="" alt="{{ $user->name }}" style="display:none;width:100%;height:100%;object-fit:cover;">
                    @endif
                </div>

                <div style="flex:1;min-width:220px;">
                    <form method="POST" action="{{ route('user.profile.photo.update') }}" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:10px;">
                        @csrf
                        <div>
                            <input type="file" id="profilePhotoInput" name="photo" accept="image/jpeg,image/png,image/webp" required style="font-size:.82rem;color:#334155;width:100%;">
                            <div style="font-size:.74rem;color:#64748b;margin-top:4px;">Accepted formats: JPG, PNG, or WEBP (max 2 MB). Your photo appears in the portal header.</div>
                            @error('photo')
                                <div class="inline-error" style="margin-top:4px;"><i class="fas fa-circle-xmark"></i> {{ $message }}</div>
                            @enderror
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <button type="submit" class="btn-nis btn-primary-nis btn-sm">
                                <i class="fas fa-upload"></i> {{ $user->profilePhotoUrl() ? 'Change Photo' : 'Upload Photo' }}
                            </button>
                            @if($user->profilePhotoUrl())
                                <button type="button" class="btn-nis btn-ghost btn-sm" style="color:#b91c1c;" onclick="document.getElementById('removePhotoForm').submit();">
                                    <i class="fas fa-trash"></i> Remove Photo
                                </button>
                            @endif
                        </div>
                    </form>
                    @if($user->profilePhotoUrl())
                        <form id="removePhotoForm" method="POST" action="{{ route('user.profile.photo.destroy') }}" style="display:none;">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

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

                @if($user->phone)
                <div class="auth-form-group" style="margin-bottom:18px;">
                    <label class="form-label-nis">Phone Number</label>
                    <input type="text" class="auth-input" value="{{ $user->phone }}" disabled>
                </div>
                @endif

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

<script>
document.getElementById('profilePhotoInput')?.addEventListener('change', function (e) {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function (ev) {
        const img = document.getElementById('profilePhotoPreviewImg');
        const initials = document.getElementById('profilePhotoInitials');
        if (img) {
            img.src = ev.target.result;
            img.style.display = 'block';
        }
        if (initials) initials.style.display = 'none';
    };
    reader.readAsDataURL(file);
});
</script>

@include('partials.footer')
