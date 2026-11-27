<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'HQ Admin Dashboard') | NIS-REDAS</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/images/nis.png') }}">
    @include('partials.head-meta')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @laravelPWA
</head>
<body class="redas-dashboard">

@include('partials.preloader')

<div class="sidebar-overlay" id="sidebarOverlay"></div>

@if(auth()->check() && (auth()->user()->user_category === 'super_admin' || auth()->user()->role === 'super_admin'))
    @include('super-admin.sidebar-aside')
@else
    @include('admin.headquarters.sidebar-aside')
@endif

<div class="redas-main" id="redasMain">
    @if(auth()->check() && (auth()->user()->user_category === 'super_admin' || auth()->user()->role === 'super_admin'))
        @include('super-admin.topbar')
    @else
        @include('admin.headquarters.topbar')
    @endif

    @yield('content')

    @include('partials.footer')
</div>

@stack('scripts')
</body>
</html>
