@auth
@php
    $navUser = auth()->user();
    $homeUrl = match ($navUser->role) {
        'super_admin' => route('superadmin.dashboard'),
        'admin', 'hq_admin' => route('admin.dashboard'),
        'zonal_commander' => url('/zonal/dashboard'),
        'desk_admin', 'directorate_admin' => url('/desk-admin/dashboard'),
        'directorate' => route('user.directorates.dashboard'),
        'cgis_unit' => route('user.cgis-units.dashboard'),
        default => route('user.dashboard'),
    };
    $isPrsViewer = in_array($navUser->role, ['super_admin', 'admin', 'hq_admin'], true)
        || ($navUser->role === 'directorate_admin' && strtolower((string) $navUser->assigned_directorate_code) === 'prs');
    $canConsolidate = in_array($navUser->role, ['zonal_commander', 'directorate_admin'], true);
@endphp
<nav class="redas-mobile-bottom-nav" aria-label="Mobile navigation">
    <a href="{{ $homeUrl }}" class="redas-mobile-nav-item {{ request()->is('*dashboard') ? 'active' : '' }}">
        <i class="fas fa-home" aria-hidden="true"></i>
        <span>Home</span>
    </a>

    @if ($canConsolidate)
        <a href="{{ route('consolidated.index') }}" class="redas-mobile-nav-item {{ request()->is('consolidated*') ? 'active' : '' }}">
            <i class="fas fa-layer-group" aria-hidden="true"></i>
            <span>Consolidate</span>
        </a>
    @elseif ($isPrsViewer)
        <a href="{{ route('prs.dashboard') }}" class="redas-mobile-nav-item {{ request()->is('prs*') ? 'active' : '' }}">
            <i class="fas fa-chart-pie" aria-hidden="true"></i>
            <span>PRS</span>
        </a>
    @else
        <a href="{{ route('user.archive') }}" class="redas-mobile-nav-item {{ request()->is('user/archive*') ? 'active' : '' }}">
            <i class="fas fa-folder-open" aria-hidden="true"></i>
            <span>Archive</span>
        </a>
    @endif

    <a href="{{ route('user.reports') }}" class="redas-mobile-nav-item {{ request()->is('user/reports*') ? 'active' : '' }}">
        <i class="fas fa-file-export" aria-hidden="true"></i>
        <span>Reports</span>
    </a>

    <button type="button" class="redas-mobile-nav-item" id="mobileMenuDrawerBtn" aria-label="Open menu">
        <i class="fas fa-bars" aria-hidden="true"></i>
        <span>Menu</span>
    </button>
</nav>
<script>
    (function () {
        const drawerBtn = document.getElementById('mobileMenuDrawerBtn');
        const sidebar = document.getElementById('redasSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (drawerBtn && sidebar) {
            drawerBtn.addEventListener('click', function () {
                sidebar.classList.toggle('mobile-open');
                if (overlay) overlay.classList.toggle('active');
            });
        }
    })();
</script>
@endauth
