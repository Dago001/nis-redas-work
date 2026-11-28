@php
    $topUser = auth()->user();
    $topCat = $topUser?->user_category;
    $topRole = $topUser?->role;
    $isSuperAdmin = $topUser && ($topCat === 'super_admin' || ($topCat === null && $topRole === 'super_admin'));
    $isHqAdmin = $topUser && ! $isSuperAdmin && (in_array($topCat, ['hq_admin', 'admin'], true) || ($topCat === null && in_array($topRole, ['hq_admin', 'admin'], true)));
    $isPrsAdmin = $topCat === 'directorate_admin' && $topUser?->directorateSlug() === 'prs';
    $homeRoute = $topUser ? $topUser->dashboardUrl() : route('user.dashboard');

    $breadcrumbLabel = $isPrsAdmin ? 'PRS Directorate Portal' : match ($topCat) {
        'zonal_commander' => 'Zonal Admin Portal',
        'zonal_officer' => 'Zonal Officer Portal',
        'directorate_admin' => 'Directorate Admin Portal',
        'cgis_desk_admin' => 'CGIS Desk Admin Portal',
        'desk_admin' => 'Desk Admin Portal',
        'directorate_user' => 'Directorate Portal',
        'cgis_unit_user' => 'CGIS Unit Portal',
        default => $topRole === 'directorate' ? 'Directorate Portal' : 'State Officer Portal',
    };

    $roleSubLabel = $isPrsAdmin ? 'PRS Directorate Admin' : match ($topCat) {
        'zonal_commander' => 'Zonal Admin',
        'zonal_officer' => 'Zonal Officer',
        'directorate_admin' => 'Directorate Admin',
        'cgis_desk_admin' => 'CGIS Desk Admin',
        'desk_admin' => 'Desk Admin',
        'directorate_user' => 'Directorate Officer',
        'cgis_unit_user' => 'CGIS Unit Officer',
        default => $topRole === 'directorate' ? 'Directorate Officer' : 'State Officer',
    };
@endphp

@if($isSuperAdmin)
    @include('super-admin.topbar')
@elseif($isHqAdmin)
    @include('admin.headquarters.topbar')
@else
<header class="redas-topbar">
    <button class="topbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
    <div class="topbar-breadcrumb">
        <span>NIS-REDAS</span>
        <span class="separator"><i class="fas fa-chevron-right" style="font-size:.6rem;"></i></span>
        <a href="{{ $homeRoute }}" style="color:var(--gray-500);text-decoration:none;">{{ $breadcrumbLabel }}</a>
    </div>
    <div class="topbar-right">
        @include('partials.topbar-notifications')
        <div style="position:relative;">
            <button type="button" class="topbar-user" id="userMenuBtn" style="border:none;background:transparent;cursor:pointer;">
                <div class="topbar-avatar" style="overflow:hidden;padding:0;">
                    @if($topUser?->profilePhotoUrl())
                        <img src="{{ $topUser->profilePhotoUrl() }}" alt="{{ $topUser->name }}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                    @else
                        {{ strtoupper(substr($topUser->name ?? 'U', 0, 2)) }}
                    @endif
                </div>
                <div class="topbar-user-info">
                    <div class="topbar-user-name">{{ $topUser->name ?? 'Officer' }}</div>
                    <div class="topbar-user-role">{{ $roleSubLabel }}</div>
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
@endif
