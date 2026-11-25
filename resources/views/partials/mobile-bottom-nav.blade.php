@auth
@php
    $navUser = auth()->user();
    $homeUrl = $navUser->dashboardUrl();
    $isPrsViewer = \App\Services\ReportAccess::isPrsViewer($navUser);
    $canConsolidate = \App\Services\ReportAccess::canManageConsolidated($navUser);
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
