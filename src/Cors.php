<?php

declare(strict_types=1);

namespace FeedMySheep;

/**
 * Cross-origin access for the native applications.
 *
 * The Android and iOS packages serve their pages from their own local origin,
 * so every API call they make is cross-origin even though it is the same app
 * and the same server. Capacitor's native HTTP bridge normally issues those
 * requests outside the browser sandbox, where CORS does not apply, but any
 * request that falls back to the WebView's own fetch will be blocked without
 * these headers. Keeping them costs nothing and removes a failure mode that is
 * very hard to diagnose from a phone.
 *
 * The allowlist is exact-match only. No wildcard is used, because
 * Access-Control-Allow-Credentials and a wildcard origin cannot be combined,
 * and because session cookies are what these requests carry.
 */
final class Cors
{
    /** The origins the native shells load their pages from. */
    private const NATIVE_ORIGINS = [
        'capacitor://localhost',
        'ionic://localhost',
        'https://localhost',
        'http://localhost',
    ];

    public static function apply(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (!self::isNativeRequest()) {
            // Same-origin web traffic sends no Origin header worth echoing, and
            // an unknown origin is simply not answered. A preflight from an
            // unknown origin is refused outright rather than left to the
            // endpoint, which would answer 405 and look like a routing bug.
            if ($method === 'OPTIONS' && $origin !== '') {
                http_response_code(403);
                exit;
            }
            return;
        }

        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');

        if ($method === 'OPTIONS') {
            header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Accept, Content-Type, X-CSRF-Token, X-App-Client, X-Session-Id');
            header('Access-Control-Max-Age: 86400');
            http_response_code(204);
            exit;
        }
    }

    /** True when the request came from one of the packaged applications. */
    public static function isNativeRequest(): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        return $origin !== '' && in_array($origin, self::NATIVE_ORIGINS, true);
    }
}
