<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Low-bandwidth HTTP Gzip compression middleware.
 *
 * Compresses compressible text responses (HTML, JSON, CSS, JS, SVG, CSV, XML)
 * >= 1 KB when the client advertises gzip support and the web server/PHP has
 * not already compressed the payload. Reduces typical REDAS form/dashboard
 * transfer sizes by 75-85% on slow 2G/3G networks.
 */
class CompressResponseMiddleware
{
    private const MIN_BYTES = 1024;

    private const COMPRESSIBLE_PREFIXES = [
        'text/html',
        'text/plain',
        'text/css',
        'text/csv',
        'text/javascript',
        'application/json',
        'application/javascript',
        'application/xml',
        'image/svg+xml',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! function_exists('gzencode')) {
            return $response;
        }

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        if ($response->headers->has('Content-Encoding')) {
            return $response;
        }

        $acceptEncoding = strtolower((string) $request->header('Accept-Encoding', ''));
        if (! str_contains($acceptEncoding, 'gzip')) {
            return $response;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));
        if (! $this->isCompressible($contentType)) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content) || strlen($content) < self::MIN_BYTES) {
            return $response;
        }

        // Avoid double-compression if PHP zlib.output_compression is active
        if (ini_get('zlib.output_compression') === '1' || strtolower((string) ini_get('zlib.output_compression')) === 'on') {
            return $response;
        }

        $compressed = gzencode($content, 6);
        if ($compressed === false || strlen($compressed) >= strlen($content)) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($compressed));

        $vary = $response->headers->get('Vary');
        if ($vary === null) {
            $response->headers->set('Vary', 'Accept-Encoding');
        } elseif (! str_contains(strtolower($vary), 'accept-encoding')) {
            $response->headers->set('Vary', $vary . ', Accept-Encoding');
        }

        return $response;
    }

    private function isCompressible(string $contentType): bool
    {
        foreach (self::COMPRESSIBLE_PREFIXES as $prefix) {
            if (str_starts_with($contentType, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
