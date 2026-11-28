<?php

namespace App\Http\Middleware;

use App\Services\SettingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (! Schema::hasTable('settings')) {
                return $next($request);
            }

            $enabled = filter_var(
                SettingService::get('maintenance_mode', false),
                FILTER_VALIDATE_BOOLEAN
            );
        } catch (\Throwable) {
            return $next($request);
        }

        if (! $enabled) {
            return $next($request);
        }

        // Always permit Super Admins and authentication / health endpoints so admins are never locked out
        $user = $request->user();
        if ($user && ($user->user_category === 'super_admin' || $user->role === 'super_admin')) {
            return $next($request);
        }

        if ($request->is('login', 'logout', 'mfa/*', 'session/*', 'password/*', 'up')) {
            return $next($request);
        }

        $message = (string) SettingService::get(
            'maintenance_message',
            'NIS-REDAS is currently undergoing scheduled system maintenance. Please check back shortly.'
        );
        $eta = (string) SettingService::get('maintenance_eta', '');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'maintenance_mode' => true,
                'estimated_return' => $eta !== '' ? $eta : null,
            ], 503);
        }

        return response()->view('errors.maintenance', [
            'maintenanceMessage' => $message,
            'maintenanceEta' => $eta,
        ], 503);
    }
}
