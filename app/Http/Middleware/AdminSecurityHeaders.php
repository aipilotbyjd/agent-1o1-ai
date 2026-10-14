<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strict browser headers for admin panel pages: a nonce-based CSP that only
 * runs the panel's own Vite bundles, no framing, no caching of any page and
 * no indexing. While `npm run dev` is running, the Vite dev server is
 * allowed too so hot reload keeps working locally.
 */
class AdminSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce));
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        $scripts = ["'self'", "'nonce-{$nonce}'"];
        $styles = ["'self'", "'nonce-{$nonce}'"];
        $connect = ["'self'"];

        if (Vite::isRunningHot()) {
            $devServer = rtrim(trim((string) file_get_contents(Vite::hotFile())), '/');

            $scripts[] = $devServer;
            $styles = ["'self'", "'unsafe-inline'", $devServer];
            $connect[] = $devServer;
            $connect[] = preg_replace('#^http#', 'ws', $devServer);
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $scripts),
            'style-src '.implode(' ', $styles),
            "img-src 'self' data:",
            "font-src 'self'",
            'connect-src '.implode(' ', $connect),
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'none'",
            "object-src 'none'",
        ]);
    }
}
