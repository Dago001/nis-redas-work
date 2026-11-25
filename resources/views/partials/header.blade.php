<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ in_array(auth()->user()?->user_category, ['desk_admin', 'directorate_admin']) ? 'Desk Admin Dashboard' : (auth()->user()?->role === 'directorate' ? 'Directorate Dashboard' : 'Officer Dashboard' ?? '') }} | NIS-REDAS</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/images/nis.png') }}">
    @include('partials.head-meta')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @laravelPWA
</head>
<body class="redas-dashboard">

@include('partials.preloader')

<!-- Mobile sidebar overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ════════════════════════════════ SIDEBAR ════════════════════════════════ -->
@if(auth()->check() && (auth()->user()->user_category === 'super_admin' || auth()->user()->role === 'super_admin'))
    @include('super-admin.sidebar-aside')
@else
<aside class="redas-sidebar" id="redasSidebar">

    <!-- Brand -->
    @php
        $hdrUser = auth()->user();
        $hdrCat = $hdrUser?->user_category;
        $brandRoute = $hdrUser ? $hdrUser->dashboardUrl() : route('user.dashboard');
        $brandText = match ($hdrCat) {
            'admin', 'hq_admin' => 'HQ Admin Portal',
            'zonal_commander' => 'Zonal Command Portal',
            'directorate_admin' => 'Directorate Admin Portal',
            'cgis_desk_admin' => 'CGIS Desk Admin Portal',
            'desk_admin' => 'Desk Admin Portal',
            'directorate_user' => 'Directorate Portal',
            'cgis_unit_user' => 'CGIS Unit Portal',
            default => 'State Officer Portal',
        };
        $isDeskGroup = in_array($hdrCat, ['desk_admin', 'directorate_admin', 'cgis_desk_admin'], true);
        $isZonalGroup = $hdrCat === 'zonal_commander';
        $isHqGroup = in_array($hdrCat, ['hq_admin', 'admin'], true);
    @endphp

    <a href="{{ $brandRoute }}" class="sidebar-brand">
        <img src="{{ asset('assets/images/nis.png') }}" alt="NIS" class="sidebar-brand-logo">
        <div class="sidebar-brand-text">
            <span class="sidebar-brand-title">NIS&nbsp;REDAS</span>
            <span class="sidebar-brand-sub">{{ $brandText }}</span>
        </div>
    </a>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        @if($isHqGroup)
            <div class="sidebar-section-label">Headquarters Menu</div>
            <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-th-large"></i></span>
                <span class="link-text">Dashboard</span>
            </a>
            <a href="{{ route('admin.hq.returns') }}" class="sidebar-link {{ request()->routeIs('admin.hq.returns*') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-folder-open"></i></span>
                <span class="link-text">All Returns</span>
            </a>
            <a href="{{ route('admin.hq.archive') }}" class="sidebar-link {{ request()->routeIs('admin.hq.archive') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-archive"></i></span>
                <span class="link-text">Archive</span>
            </a>
            <a href="{{ route('admin.hq.reports') }}" class="sidebar-link {{ request()->routeIs('admin.hq.reports*') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-file-excel"></i></span>
                <span class="link-text">Report Generation</span>
            </a>
            @if($hdrCat === 'hq_admin')
            <a href="{{ route('admin.users') }}" class="sidebar-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-users-gear"></i></span>
                <span class="link-text">User Management</span>
            </a>
            <a href="{{ route('admin.audit-log.index') }}" class="sidebar-link {{ request()->routeIs('admin.audit-log*') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-clipboard-list"></i></span>
                <span class="link-text">Audit Log</span>
            </a>
            @endif
        @elseif($isDeskGroup || $isZonalGroup)
            <div class="sidebar-section-label">{{ $isZonalGroup ? 'Zonal Command Menu' : 'Desk Admin Menu' }}</div>
            <a href="{{ $brandRoute }}" class="sidebar-link {{ request()->routeIs('user.desk.home', 'user.zonal.home') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-th-large"></i></span>
                <span class="link-text">Review Dashboard</span>
            </a>
            @if(in_array($hdrCat, ['zonal_commander', 'directorate_admin'], true))
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
            <a href="{{ $isZonalGroup ? route('user.reports') : route('desk.admin.reports') }}" class="sidebar-link {{ request()->routeIs('user.reports*', 'desk.admin.reports') ? 'active' : '' }}">
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
        @elseif($hdrCat === 'directorate_user')
            <div class="sidebar-section-label">Directorate Menu</div>
            <a href="{{ route('user.directorates.dashboard') }}" class="sidebar-link {{ request()->routeIs('user.directorates.dashboard') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-tachometer-alt"></i></span>
                <span class="link-text">Dashboard</span>
            </a>
            @php $userDirectorateSlug = $hdrUser?->directorateSlug(); @endphp
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
        @elseif($hdrCat === 'cgis_unit_user')
            <div class="sidebar-section-label">CGIS Unit Menu</div>
            <a href="{{ route('user.cgis-units.dashboard') }}" class="sidebar-link {{ request()->routeIs('user.cgis-units.dashboard') ? 'active' : '' }}">
                <span class="link-icon"><i class="fas fa-tachometer-alt"></i></span>
                <span class="link-text">Dashboard</span>
            </a>
            @php $userCgisSlug = $hdrUser?->cgisUnitSlug(); @endphp
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
            <div class="sidebar-section-label">Main Menu</div>
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

<!-- ═══════ MAIN ═══════ -->
<div class="redas-main" id="redasMain">

    <!-- Topbar -->
    @if(auth()->check() && (auth()->user()->user_category === 'super_admin' || auth()->user()->role === 'super_admin'))
        @include('super-admin.topbar')
    @else
    <header class="redas-topbar">
        <button class="topbar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <div class="topbar-breadcrumb">
            <span>NIS-REDAS</span>
            <span class="separator"><i class="fas fa-chevron-right" style="font-size:.6rem;"></i></span>
            <a href="{{ $brandRoute }}" style="color:var(--gray-500);text-decoration:none;">{{ $brandText }}</a>
        </div>
        <div class="topbar-right">
            @include('partials.topbar-notifications')
            <div style="position:relative;">
                <button class="topbar-user" id="userMenuBtn" style="border:none;background:transparent;cursor:pointer;">
                    <div class="topbar-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}</div>
                    <div class="topbar-user-info">
                        <div class="topbar-user-name">{{ auth()->user()->name ?? 'Officer' }}</div>
                        <div class="topbar-user-role">{{ auth()->user()?->role === 'directorate' ? 'Directorate User' : 'State User' }}</div>
                    </div>
                    <i class="fas fa-chevron-down" style="font-size:.7rem;color:var(--gray-400);margin-left:4px;"></i>
                </button>
                <div id="userMenuDrop" onclick="event.stopPropagation()" style="display:none;position:absolute;right:0;top:calc(100% + 8px);width:190px;background:#fff;border-radius:var(--radius-md);box-shadow:var(--shadow-lg);border:1px solid var(--gray-100);z-index:200;overflow:hidden;">
                    @if(auth()->user()?->role !== 'user')
                    <a href="{{ route('user.profile') }}" style="display:flex;align-items:center;gap:10px;padding:10px 14px;font-size:.84rem;color:var(--gray-700);text-decoration:none;">
                        <i @class(['fas', 'fa-user-cog']) style="color:var(--gray-400);width:16px;"></i> Profile
                    </a>
                    @endif
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
