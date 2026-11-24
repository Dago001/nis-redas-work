<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Super Admin Dashboard') | NIS-REDAS</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/images/nis.png') }}">
    @include('partials.head-meta')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @laravelPWA
</head>
<body class="redas-dashboard">

@include('partials.preloader')

<div class="sidebar-overlay" id="sidebarOverlay"></div>

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
            <span class="link-text">Executive Dashboard</span>
        </a>
        <a href="{{ route('superadmin.returns') }}" class="sidebar-link {{ request()->routeIs('superadmin.returns*') ? 'active' : '' }}">
            <span class="link-icon"><i class="fas fa-folder-open"></i></span>
            <span class="link-text">All Returns Register</span>
        </a>
        <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <span class="link-icon"><i class="fas fa-landmark"></i></span>
            <span class="link-text">HQ Admin Portal</span>
        </a>

        <div class="sidebar-section-label">Reporting &amp; Option C</div>
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

<div class="redas-main" id="redasMain">
    <header class="redas-topbar">
        <button class="topbar-toggle" id="sidebarToggle" aria-label="Toggle navigation"><i class="fas fa-bars"></i></button>
        <div class="topbar-breadcrumb">
            <span>NIS-REDAS</span>
            <span class="separator"><i class="fas fa-chevron-right" style="font-size:.6rem;"></i></span>
            <a href="{{ route('superadmin.dashboard') }}" style="color:var(--gray-500);text-decoration:none;">Super Admin</a>
        </div>
        <div class="topbar-right">
            <div style="position:relative;">
                <button class="topbar-user" id="userMenuBtn" style="border:none;background:transparent;cursor:pointer;">
                    <div class="topbar-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'SA', 0, 2)) }}</div>
                    <div class="topbar-user-info">
                        <div class="topbar-user-name">{{ auth()->user()->name ?? 'Super Administrator' }}</div>
                        <div class="topbar-user-role">Super Administrator &bull; {{ auth()->user()->service_number ?? 'HQ' }}</div>
                    </div>
                    <i class="fas fa-chevron-down" style="font-size:.7rem;color:var(--gray-400);margin-left:4px;"></i>
                </button>
                <div id="userMenuDrop" onclick="event.stopPropagation()" style="display:none;position:absolute;right:0;top:calc(100% + 8px);width:210px;background:#fff;border-radius:var(--radius-md);box-shadow:var(--shadow-lg);border:1px solid var(--gray-100);z-index:200;overflow:hidden;">
                    <a href="{{ route('admin.users') }}" style="display:flex;align-items:center;gap:10px;padding:10px 14px;font-size:.84rem;color:var(--gray-700);text-decoration:none;">
                        <i class="fas fa-users-cog" style="color:var(--gray-400);width:16px;"></i> Manage Users
                    </a>
                    <a href="{{ route('admin.settings.index') }}" style="display:flex;align-items:center;gap:10px;padding:10px 14px;font-size:.84rem;color:var(--gray-700);text-decoration:none;">
                        <i class="fas fa-cogs" style="color:var(--gray-400);width:16px;"></i> System Settings
                    </a>
                    <a href="{{ route('user.profile') }}" style="display:flex;align-items:center;gap:10px;padding:10px 14px;font-size:.84rem;color:var(--gray-700);text-decoration:none;">
                        <i class="fas fa-user-lock" style="color:var(--gray-400);width:16px;"></i> Profile &amp; Password
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

    @yield('content')

    @include('partials.footer')
</div>

@stack('scripts')
</body>
</html>
