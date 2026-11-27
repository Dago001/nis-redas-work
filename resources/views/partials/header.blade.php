<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $hdrTitle = match (auth()->user()?->user_category) {
            'super_admin' => 'Super Admin Portal',
            'hq_admin', 'admin' => 'HQ Admin Portal',
            'zonal_commander' => 'Zonal Admin Portal',
            'zonal_officer' => 'Zonal Officer Portal',
            'directorate_admin' => 'Directorate Admin Portal',
            'cgis_desk_admin' => 'CGIS Desk Admin Portal',
            'desk_admin' => 'Desk Admin Portal',
            'directorate_user' => 'Directorate Portal',
            'cgis_unit_user' => 'CGIS Unit Portal',
            default => 'Officer Portal',
        };
    @endphp
    <title>{{ $hdrTitle }} | NIS-REDAS</title>
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
