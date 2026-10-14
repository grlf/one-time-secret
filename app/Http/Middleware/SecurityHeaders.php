<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->add([
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);

        // Pages and responses can hold a secret or a link to one, so keep
        // them out of every cache, including the browser's back button.
        $response->headers->set('Cache-Control', 'no-store, private');

        // Laravel's debug error page relies on inline scripts and styles.
        if (! ($response->isServerError() && config('app.debug'))) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        // The Typekit hosts serve the proxima-nova kit in layouts/app.blade.php.
        $policy = [
            'default-src' => ["'none'"],
            'script-src' => ["'self'"],
            'style-src' => ["'self'", 'https://use.typekit.net', 'https://p.typekit.net'],
            'font-src' => ['https://use.typekit.net'],
            'img-src' => ["'self'", 'data:'],
            'connect-src' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'none'"],
            'require-trusted-types-for' => ["'script'"],
            'trusted-types' => ["'none'"],
        ];

        // `npm run dev` serves assets from the Vite dev server, injects <style>
        // tags, and keeps a websocket open to it.
        if (app()->isLocal() && Vite::isRunningHot()) {
            $origin = rtrim(file_get_contents(Vite::hotFile()));

            $policy['script-src'][] = $origin;
            $policy['style-src'][] = $origin;
            $policy['style-src'][] = "'unsafe-inline'";
            $policy['font-src'][] = $origin;
            $policy['img-src'][] = $origin;
            $policy['connect-src'][] = $origin;
            $policy['connect-src'][] = preg_replace('/^http/', 'ws', $origin);
            unset($policy['require-trusted-types-for'], $policy['trusted-types']);
        }

        return collect($policy)
            ->map(fn (array $sources, string $directive) => $directive.' '.implode(' ', $sources))
            ->implode('; ');
    }
}
