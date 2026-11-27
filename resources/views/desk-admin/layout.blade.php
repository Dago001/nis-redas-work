<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@php
        $tabTitle = match (auth()->user()?->user_category) {
            'zonal_commander' => 'Zonal Admin Dashboard',
            'directorate_admin' => 'Directorate Admin Dashboard',
            'cgis_desk_admin' => 'CGIS Desk Admin Dashboard',
            'hq_admin' => 'HQ Admin Review Queue',
            'super_admin' => 'Super Admin Review Queue',
            default => 'Desk Admin Dashboard',
        };
    @endphp{{ $tabTitle }} | NIS-REDAS</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/images/nis.png') }}">
    @include('partials.head-meta')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @laravelPWA
</head>
<body class="redas-dashboard">

@include('partials.preloader')

<div class="sidebar-overlay" id="sidebarOverlay"></div>

@php
    $deskUser = auth()->user();
    $deskCat = $deskUser?->user_category;
    $isSuperAdmin = $deskUser && ($deskCat === 'super_admin' || $deskUser->role === 'super_admin');
    $isHqAdmin = $deskUser && ! $isSuperAdmin && (in_array($deskCat, ['hq_admin', 'admin'], true) || in_array($deskUser->role, ['hq_admin', 'admin'], true));
    $isZonal = $deskCat === 'zonal_commander';
    $homeRoute = $deskUser ? $deskUser->dashboardUrl() : route('user.desk.home');
    $portalSub = match ($deskCat) {
        'zonal_commander' => 'Zonal Admin Portal',
        'directorate_admin' => 'Directorate Admin Portal',
        'cgis_desk_admin' => 'CGIS Desk Admin Portal',
        'hq_admin' => 'HQ Admin Portal',
        'super_admin' => 'Super Admin Console',
        default => 'Desk Admin Portal',
    };
    $menuLabel = match ($deskCat) {
        'zonal_commander' => 'Zonal Admin Menu',
        'directorate_admin' => 'Directorate Admin Menu',
        'cgis_desk_admin' => 'CGIS Desk Menu',
        default => 'Desk Admin Menu',
    };
    $breadcrumbTitle = match ($deskCat) {
        'zonal_commander' => 'Zonal Admin Dashboard',
        'directorate_admin' => 'Directorate Admin Dashboard',
        'cgis_desk_admin' => 'CGIS Desk Admin Dashboard',
        'hq_admin' => 'HQ Admin',
        'super_admin' => 'Super Admin',
        default => 'Desk Admin Dashboard',
    };
    $reportsRoute = $isZonal ? route('user.reports') : route('desk.admin.reports');
    $reportsActive = $isZonal ? request()->routeIs('user.reports*') : request()->routeIs('desk.admin.reports');
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
            <span class="sidebar-brand-sub">{{ $portalSub }}</span>
        </div>
    </a>

    <nav class="sidebar-nav">
        <div class="sidebar-section-label">{{ $menuLabel }}</div>
        <a href="{{ $homeRoute }}" class="sidebar-link {{ request()->routeIs('user.desk.home', 'user.zonal.home') ? 'active' : '' }}">
            <span class="link-icon"><i class="fas fa-th-large"></i></span>
            <span class="link-text">Review Dashboard</span>
        </a>
        @if(in_array($deskCat, ['zonal_commander', 'directorate_admin'], true))
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
        <a href="{{ $reportsRoute }}" class="sidebar-link {{ $reportsActive ? 'active' : '' }}">
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
    </nav>
</aside>
@endif

<div class="redas-main" id="redasMain">
    @if($isSuperAdmin)
        @include('super-admin.topbar')
    @elseif($isHqAdmin)
        @include('admin.headquarters.topbar')
    @else
    <header class="redas-topbar">
        <button class="topbar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <div class="topbar-breadcrumb">
            <span>NIS-REDAS</span>
            <span class="separator"><i class="fas fa-chevron-right" style="font-size:.6rem;"></i></span>
            <a href="{{ $homeRoute }}" style="color:var(--gray-500);text-decoration:none;">{{ $breadcrumbTitle }}</a>
        </div>
        <div class="topbar-right">
            @include('partials.topbar-notifications')
            <div style="position:relative;">
                <button class="topbar-user" id="userMenuBtn" style="border:none;background:transparent;cursor:pointer;">
                    <div class="topbar-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'DA', 0, 2)) }}</div>
                    <div class="topbar-user-info">
                        <div class="topbar-user-name">{{ auth()->user()->name ?? 'Desk Admin' }}</div>
                        <div class="topbar-user-role">{{ match ($deskCat) {
                            'zonal_commander' => 'Zonal Admin',
                            'directorate_admin' => 'Directorate Admin',
                            'cgis_desk_admin' => 'CGIS Desk Admin',
                            'hq_admin' => 'HQ Admin',
                            'super_admin' => 'Super Admin',
                            default => 'Desk Administration',
                        } }}</div>
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

    @yield('content')

    @include('partials.footer')
</body>
</html>
