<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $response = $next($request);

        $response->headers->set(
            'X-Content-Type-Options',
            'nosniff',
        );
        $response->headers->set(
            'X-Frame-Options',
            'SAMEORIGIN',
        );
        $response->headers->set(
            'Referrer-Policy',
            'strict-origin-when-cross-origin',
        );
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=()',
        );
        $response->headers->set(
            'Content-Security-Policy',
            $this->contentSecurityPolicy($request),
        );

        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        return $response;
    }

    private function contentSecurityPolicy(
        Request $request,
    ): string {
        $basePolicy =
            "base-uri 'self'; frame-ancestors 'self'; object-src 'none'; form-action 'self'";

        if ($this->requiresFrameworkInlineAssets($request)) {
            return $basePolicy;
        }

        return $basePolicy
            ."; script-src 'self'; style-src 'self'";
    }

    private function requiresFrameworkInlineAssets(
        Request $request,
    ): bool {
        return $request->is(
            'admin',
            'admin/*',
            'livewire',
            'livewire/*',
            'filament',
            'filament/*',
        );
    }
}
