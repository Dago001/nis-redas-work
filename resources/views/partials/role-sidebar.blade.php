@php
    $navUser = auth()->user();
    $navCat = $navUser?->user_category;
    $navRole = $navUser?->role;
    $isSuperAdmin = $navUser && ($navCat === 'super_admin' || $navRole === 'super_admin');
    $isHqAdmin = $navUser && ! $isSuperAdmin && (in_array($navCat, ['hq_admin', 'admin'], true) || in_array($navRole, ['hq_admin', 'admin'], true));
    $isReviewer = in_array($navCat, ['desk_admin', 'zonal_commander', 'directorate_admin', 'cgis_desk_admin'], true);
    $isZonalAdmin = $navCat === 'zonal_commander';
    $homeRoute = $navUser ? $navUser->dashboardUrl() : route('user.dashboard');

    $brandSub = match ($navCat) {
        'zonal_commander' => 'Zonal Admin Portal',
        'zonal_officer' => 'Zonal Officer Portal',
        'directorate_admin' => 'Directorate Admin Portal',
        'cgis_desk_admin' => 'CGIS Desk Admin Portal',
        'desk_admin' => 'Desk Admin Portal',
        'directorate_user' => 'Directorate Portal',
        'cgis_unit_user' => 'CGIS Unit Portal',
        default => $navRole === 'directorate' ? 'Directorate Portal' : 'State Officer Portal',
    };

    $reviewerMenuLabel = match ($navCat) {
        'zonal_commander' => 'Zonal Admin Menu',
        'directorate_admin' => 'Directorate Admin Menu',
        'cgis_desk_admin' => 'CGIS Desk Menu',
        default => 'Desk Admin Menu',
    };
@endphp

@if($isSuperAdmin)
    @include('super-admin.sidebar-aside')
@elseif($isHqAdmin)
    @include('admin.headquarters.sidebar-aside')
@else
<aside class="redas-sidebar" id="redasSidebar">
    <a href="{{ $homeRoute }}" class="sidebar-brand">
        <img src="{{ asset('assets/images/nis.png') }}" alt="NIS" class="sidebar-brand-logo">
        <div class="sidebar-brand-text">
            <span class="sidebar-brand-title">NIS&nbsp;REDAS</span>
            <span class="sidebar-brand-sub">{{ $brandSub }}</span>
        </div>
    </a>

    <nav class="sidebar-nav">
        @if($isReviewer)
            <div class="sidebar-section-label">{{ $reviewerMenuLabel }}</div>
            <a href="{{ $homeRoute }}" class="sidebar-link {{ request()->routeIs('user.desk.home', 'user.zonal.home') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-th-large"></i></span>
                <span class="link-text">Review Dashboard</span>
            </a>
            @if(in_array($navCat, ['zonal_commander', 'directorate_admin'], true))
            <a href="{{ route('consolidated.index') }}" class="sidebar-link {{ request()->routeIs('consolidated.*') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-layer-group"></i></span>
                <span class="link-text">Consolidated Reports</span>
            </a>
            @endif
            @can('viewPrsDashboard')
            <a href="{{ route('prs.dashboard') }}" class="sidebar-link {{ request()->routeIs('prs.*') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-chart-line"></i></span>
                <span class="link-text">PRS Reporting</span>
            </a>
            @endcan
            <a href="{{ $isZonalAdmin ? route('user.reports') : route('desk.admin.reports') }}" class="sidebar-link {{ request()->routeIs('user.reports*', 'desk.admin.reports') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-file-export"></i></span>
                <span class="link-text">Report Generation</span>
            </a>
            <a href="{{ route('user.archive') }}" class="sidebar-link {{ request()->routeIs('user.archive*') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-archive"></i></span>
                <span class="link-text">Archived Documents</span>
            </a>
            <a href="{{ route('user.notifications') }}" class="sidebar-link {{ request()->is('user/notifications') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-bell"></i></span>
                <span class="link-text">Notifications</span>
            </a>
        @elseif($navCat === 'directorate_user')
            <div class="sidebar-section-label">Directorate Menu</div>
            <a href="{{ route('user.directorates.dashboard') }}" class="sidebar-link {{ request()->routeIs('user.directorates.dashboard') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-tachometer-alt"></i></span>
                <span class="link-text">Dashboard</span>
            </a>
            @php $userDirectorateSlug = $navUser?->directorateSlug(); @endphp
            @if($userDirectorateSlug)
            <a href="{{ route('user.directorates.show', ['slug' => $userDirectorateSlug]) }}" class="sidebar-link {{ request()->routeIs('user.directorates.show') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-file-signature"></i></span>
                <span class="link-text">Submit Return</span>
            </a>
            @endif
            <a href="{{ route('user.archive') }}" class="sidebar-link {{ request()->routeIs('user.archive*') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-archive"></i></span>
                <span class="link-text">Archived Documents</span>
            </a>
            <a href="{{ route('user.reports') }}" class="sidebar-link {{ request()->routeIs('user.reports*') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-file-export"></i></span>
                <span class="link-text">Generate Report</span>
            </a>
            <a href="{{ route('user.notifications') }}" class="sidebar-link {{ request()->is('user/notifications') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-bell"></i></span>
                <span class="link-text">Notifications</span>
            </a>
        @elseif($navCat === 'cgis_unit_user')
            <div class="sidebar-section-label">CGIS Unit Menu</div>
            <a href="{{ route('user.cgis-units.dashboard') }}" class="sidebar-link {{ request()->routeIs('user.cgis-units.dashboard') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-tachometer-alt"></i></span>
                <span class="link-text">Dashboard</span>
            </a>
            @php $userCgisSlug = $navUser?->cgisUnitSlug(); @endphp
            @if($userCgisSlug)
            <a href="{{ route('user.cgis-units.show', ['slug' => $userCgisSlug]) }}" class="sidebar-link {{ request()->routeIs('user.cgis-units.show') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-file-signature"></i></span>
                <span class="link-text">My Unit Return</span>
            </a>
            @endif
            <a href="{{ route('user.archive') }}" class="sidebar-link {{ request()->routeIs('user.archive*') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-archive"></i></span>
                <span class="link-text">Archived Documents</span>
            </a>
            <a href="{{ route('user.reports') }}" class="sidebar-link {{ request()->routeIs('user.reports*') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-file-export"></i></span>
                <span class="link-text">Generate Report</span>
            </a>
            <a href="{{ route('user.notifications') }}" class="sidebar-link {{ request()->is('user/notifications') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-bell"></i></span>
                <span class="link-text">Notifications</span>
            </a>
        @else
            <div class="sidebar-section-label">{{ $navCat === 'zonal_officer' ? 'Zonal Officer Menu' : 'Main Menu' }}</div>
            <a href="{{ route('user.dashboard') }}" class="sidebar-link {{ request()->routeIs('user.dashboard') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-tachometer-alt"></i></span>
                <span class="link-text">Dashboard</span>
            </a>
            <a href="{{ route('user.returns.create') }}" class="sidebar-link {{ request()->is('user/returns/create') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-plus-circle"></i></span>
                <span class="link-text">Submit New Return</span>
            </a>
            <a href="{{ route('user.submissions') }}" class="sidebar-link {{ request()->is('user/submissions') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-inbox"></i></span>
                <span class="link-text">My Submissions</span>
            </a>
            <a href="{{ route('user.archive') }}" class="sidebar-link {{ request()->routeIs('user.archive*') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-archive"></i></span>
                <span class="link-text">Archive</span>
            </a>
            <a href="{{ route('user.reports') }}" class="sidebar-link {{ request()->routeIs('user.reports*') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-file-export"></i></span>
                <span class="link-text">Generate Report</span>
            </a>
            <a href="{{ route('user.notifications') }}" class="sidebar-link {{ request()->is('user/notifications') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-bell"></i></span>
                <span class="link-text">Notifications</span>
            </a>
        @endif
    </nav>
</aside>
@endif

@once
<script>
(function () {
    const STORAGE_KEY = 'redas_sidebar_scroll_top';
    const aside = document.getElementById('redasSidebar');
    if (!aside) return;

    const nav = aside.querySelector('.sidebar-nav');
    const scroller = (nav && nav.scrollHeight > nav.clientHeight) ? nav : aside;

    // 1. Restore saved scroll position immediately before paint
    const saved = sessionStorage.getItem(STORAGE_KEY);
    if (saved !== null) {
        const y = parseInt(saved, 10);
        if (!Number.isNaN(y) && y >= 0) {
            scroller.scrollTop = y;
            if (nav) nav.scrollTop = y;
            aside.scrollTop = y;
        }
    }

    // 2. Ensure the active menu item is always visible in the sidebar viewport
    const activeLink = aside.querySelector('.sidebar-link.active');
    if (activeLink) {
        const sRect = scroller.getBoundingClientRect();
        const aRect = activeLink.getBoundingClientRect();
        if (aRect.top < sRect.top || aRect.bottom > sRect.bottom) {
            activeLink.scrollIntoView({ block: 'center', behavior: 'instant' });
        }
    }

    function persistScroll() {
        const top = (nav && nav.scrollTop > 0) ? nav.scrollTop : aside.scrollTop;
        try {
            sessionStorage.setItem(STORAGE_KEY, String(Math.round(top)));
        } catch (e) {}
    }

    nav?.addEventListener('scroll', persistScroll, { passive: true });
    aside.addEventListener('scroll', persistScroll, { passive: true });
    aside.addEventListener('click', (e) => {
        if (e.target.closest('a.sidebar-link')) {
            persistScroll();
        }
    });
})();
</script>
@endonce
