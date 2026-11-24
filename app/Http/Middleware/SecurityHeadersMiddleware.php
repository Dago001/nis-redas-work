<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense-in-depth HTTP security headers for every web and API response.
 *
 * - Blocks clickjacking (X-Frame-Options + frame-ancestors 'none')
 * - Blocks MIME sniffing (X-Content-Type-Options: nosniff)
 * - Enforces Content-Security-Policy with self-hosted assets only
 * - Enforces HSTS on HTTPS / production requests
 * - Prevents browser/proxy caching of authenticated or sensitive pages
 * - Strips server/framework fingerprinting headers (X-Powered-By)
 */
class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Remove framework/PHP fingerprinting headers where present
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        $response->headers->set('X-Frame-Options', 'DENY', false);
        $response->headers->set('X-Content-Type-Options', 'nosniff', false);
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none', false);
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin', false);
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin', false);
        $response->headers->set(
            'Permissions-Policy',
            'accelerometer=(), autoplay=(), camera=(), display-capture=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()',
            false
        );

        // Self-hosted CSP: no third-party scripts, fonts, or framing allowed.
        // Inline styles/scripts remain permitted only for existing Blade views;
        // external script/object/base/form exfiltration is strictly blocked.
        $csp = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self' 'unsafe-inline'",
            "connect-src 'self'",
            "manifest-src 'self'",
            "worker-src 'self'",
        ]);

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $csp);
        }

        // Strict-Transport-Security when served over HTTPS or in production
        if ($request->isSecure() || app()->isProduction()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
                false
            );
        }

        // Never allow shared proxies or browsers to cache authenticated/API/auth responses
        $isSensitive = $request->user() !== null
            || $request->is('api/*')
            || $request->is('login')
            || $request->is('mfa/*')
            || $request->is('password/*');

        if ($isSensitive && ! $response->headers->has('Content-Disposition')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
