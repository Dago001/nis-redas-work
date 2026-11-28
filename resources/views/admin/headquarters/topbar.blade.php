<header class="redas-topbar">
    <button class="topbar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div class="topbar-breadcrumb">
        <span>NIS-REDAS</span>
        <span class="separator"><i class="fas fa-chevron-right" style="font-size:.6rem;"></i></span>
        <a href="{{ route('admin.dashboard') }}" style="color:var(--gray-500);text-decoration:none;">HQ Admin</a>
    </div>
    <div class="topbar-right">
        @include('partials.topbar-notifications')
        <div style="position:relative;">
            <button class="topbar-user" id="userMenuBtn" style="border:none;background:transparent;cursor:pointer;">
                <div class="topbar-avatar" style="overflow:hidden;padding:0;">
                    @if(auth()->user()?->profilePhotoUrl())
                        <img src="{{ auth()->user()->profilePhotoUrl() }}" alt="{{ auth()->user()->name }}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                    @else
                        {{ strtoupper(substr(auth()->user()->name ?? 'HQ', 0, 2)) }}
                    @endif
                </div>
                <div class="topbar-user-info">
                    <div class="topbar-user-name">{{ auth()->user()->name ?? 'HQ Admin' }}</div>
                    <div class="topbar-user-role">Headquarters Administration</div>
                </div>
                <i class="fas fa-chevron-down" style="font-size:.7rem;color:var(--gray-400);margin-left:4px;"></i>
            </button>
            <div id="userMenuDrop" onclick="event.stopPropagation()" style="display:none;position:absolute;right:0;top:calc(100% + 8px);width:190px;background:#fff;border-radius:var(--radius-md);box-shadow:var(--shadow-lg);border:1px solid var(--gray-100);z-index:200;overflow:hidden;">
                <a href="{{ route('user.profile') }}" style="display:flex;align-items:center;gap:10px;padding:10px 14px;font-size:.84rem;color:var(--gray-700);text-decoration:none;">
                    <i class="fas fa-user-cog" style="color:var(--gray-400);width:16px;"></i> Profile
                </a>
                <div style="border-top:1px solid var(--gray-100);"></div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" style="display:flex;align-items:center;gap:10px;padding:10px 14px;font-size:.84rem;color:var(--color-danger);background:none;border:none;cursor:pointer;width:100%;">
                        <i class="fas fa-sign-out-alt" style="width:16px;"></i> Sign Out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
