<aside class="redas-sidebar" id="redasSidebar">
    <a href="{{ route('superadmin.dashboard') }}" class="sidebar-brand">
        <img src="{{ asset('assets/images/nis.png') }}" alt="NIS" class="sidebar-brand-logo">
        <div class="sidebar-brand-text">
            <span class="sidebar-brand-title">NIS&nbsp;REDAS</span>
            <span class="sidebar-brand-sub">Super Admin Console</span>
        </div>
    </a>

    <nav class="sidebar-nav">
        <div class="sidebar-section-label">Executive Overview</div>
        <a href="{{ route('superadmin.dashboard') }}" class="sidebar-link {{ request()->routeIs('superadmin.dashboard') ? 'active' : '' }}">
            <span class="link-icon"><i class="fas fa-gauge-high"></i></span>
            <span class="link-text">Dashboard</span>
        </a>
        <a href="{{ route('superadmin.returns') }}" class="sidebar-link {{ request()->routeIs('superadmin.returns*') ? 'active' : '' }}">
            <span class="link-icon"><i class="fas fa-folder-open"></i></span>
            <span class="link-text">All Returns Register</span>
        </a>
        <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <span class="link-icon"><i class="fas fa-landmark"></i></span>
            <span class="link-text">HQ Admin Portal</span>
        </a>

        <div class="sidebar-section-label">Reporting &amp; Consolidation</div>
        <a href="{{ route('prs.dashboard') }}" class="sidebar-link {{ request()->routeIs('prs.dashboard') ? 'active' : '' }}">
            <span class="link-icon"><i class="fas fa-chart-pie"></i></span>
            <span class="link-text">PRS Central Dashboard</span>
        </a>
        <a href="{{ route('admin.consolidated.index') }}" class="sidebar-link {{ request()->routeIs('admin.consolidated*') ? 'active' : '' }}">
            <span class="link-icon"><i class="fas fa-layer-group"></i></span>
            <span class="link-text">Consolidated Reports</span>
        </a>
        <a href="{{ route('admin.consolidation') }}" class="sidebar-link {{ request()->routeIs('admin.consolidation*') ? 'active' : '' }}">
            <span class="link-icon"><i class="fas fa-chart-column"></i></span>
            <span class="link-text">National Consolidation</span>
        </a>
        <a href="{{ route('prs.indicators') }}" class="sidebar-link {{ request()->routeIs('prs.indicators*') ? 'active' : '' }}">
            <span class="link-icon"><i class="fas fa-sliders"></i></span>
            <span class="link-text">Indicators Catalog</span>
        </a>
        <a href="{{ route('user.reports') }}" class="sidebar-link {{ request()->routeIs('user.reports*') ? 'active' : '' }}">
            <span class="link-icon"><i class="fas fa-file-export"></i></span>
            <span class="link-text">Report Builder</span>
        </a>
        <a href="{{ route('user.archive') }}" class="sidebar-link {{ request()->routeIs('user.archive*') ? 'active' : '' }}">
            <span class="link-icon"><i class="fas fa-box-archive"></i></span>
            <span class="link-text">Document Archive</span>
        </a>

        <div class="sidebar-section-label">User &amp; Access Governance</div>
        <a href="{{ route('admin.users') }}" class="sidebar-link {{ request()->routeIs('admin.users') || request()->routeIs('admin.users.edit') ? 'active' : '' }}">
            <span class="link-icon"><i class="fas fa-users-cog"></i></span>
            <span class="link-text">Manage All Users</span>
        </a>
        <a href="{{ route('admin.users.create') }}" class="sidebar-link {{ request()->routeIs('admin.users.create') ? 'active' : '' }}">
            <span class="link-icon"><i class="fas fa-user-plus"></i></span>
            <span class="link-text">Create New User</span>
        </a>

        <div class="sidebar-section-label">Security &amp; System</div>
        <a href="{{ route('admin.audit-log.index') }}" class="sidebar-link {{ request()->routeIs('admin.audit-log*') ? 'active' : '' }}">
            <span class="link-icon"><i class="fas fa-shield-halved"></i></span>
            <span class="link-text">Immutable Audit Logs</span>
        </a>
        <a href="{{ route('admin.settings.index') }}" class="sidebar-link {{ request()->routeIs('admin.settings*') ? 'active' : '' }}">
            <span class="link-icon"><i class="fas fa-cogs"></i></span>
            <span class="link-text">System &amp; ABAC Settings</span>
        </a>
        <a href="{{ route('user.profile') }}" class="sidebar-link {{ request()->routeIs('user.profile*') ? 'active' : '' }}">
            <span class="link-icon"><i class="fas fa-key"></i></span>
            <span class="link-text">My Profile &amp; Security</span>
        </a>
    </nav>
</aside>
