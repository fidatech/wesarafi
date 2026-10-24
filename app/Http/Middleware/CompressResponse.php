<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompressResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! str_contains(strtolower($request->header('Accept-Encoding', '')), 'gzip')
            || $response instanceof BinaryFileResponse
            || $response instanceof StreamedResponse
            || $response->headers->has('Content-Encoding')
            || ! function_exists('gzencode')) {
            return $response;
        }

        $type = strtolower((string) $response->headers->get('Content-Type'));
        $compressible = str_starts_with($type, 'text/')
            || str_contains($type, 'json')
            || str_contains($type, 'javascript')
            || str_contains($type, 'xml')
            || str_contains($type, 'svg');
        $content = $response->getContent();

        if (! $compressible || ! is_string($content) || strlen($content) < 1024) {
            return $response;
        }

        $compressed = gzencode($content, 6);
        if ($compressed === false || strlen($compressed) >= strlen($content)) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($compressed));
        $response->headers->set('Vary', 'Accept-Encoding', false);

        return $response;
    }
}
