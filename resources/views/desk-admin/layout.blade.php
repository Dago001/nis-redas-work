<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $tabTitle = match (auth()->user()?->user_category) {
            'super_admin' => 'Super Admin Portal',
            'hq_admin', 'admin' => 'HQ Admin Portal',
            'zonal_commander' => 'Zonal Admin Dashboard',
            'directorate_admin' => 'Directorate Admin Dashboard',
            'cgis_desk_admin' => 'CGIS Desk Admin Dashboard',
            default => 'Desk Admin Dashboard',
        };
    @endphp
    <title>@yield('title', $tabTitle) | NIS-REDAS</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/images/nis.png') }}">
    @include('partials.head-meta')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @laravelPWA
</head>
<body class="redas-dashboard">

@include('partials.preloader')

<div class="sidebar-overlay" id="sidebarOverlay"></div>

@include('partials.role-sidebar')

<div class="redas-main" id="redasMain">
    @include('partials.role-topbar')

    @yield('content')

    @include('partials.footer')
</div>

@stack('scripts')
@include('partials.month-picker-script')
</body>
</html>
