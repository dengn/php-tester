<?php

declare(strict_types=1);

namespace MoTest;

/**
 * Central connection configuration for the MatrixOne target.
 *
 * Defaults point at the local MatrixOne container used for compatibility
 * testing, but every value can be overridden through environment variables so
 * the exact same suite can be pointed at a remote MatrixOne instance (e.g. the
 * managed CloudSigma endpoint) the moment network egress to port 6001 is
 * permitted.
 */
final class Config
{
    public static function host(): string
    {
        return getenv('MO_HOST') ?: '127.0.0.1';
    }

    public static function port(): int
    {
        return (int) (getenv('MO_PORT') ?: 6001);
    }

    public static function user(): string
    {
        return getenv('MO_USER') ?: 'root';
    }

    public static function password(): string
    {
        $p = getenv('MO_PASS');
        return $p !== false ? $p : '111';
    }

    /** MatrixOne reports itself as MySQL 8.0.30 over the wire. */
    public static function serverVersion(): string
    {
        return getenv('MO_SERVER_VERSION') ?: '8.0.30';
    }

    /**
     * Each framework gets its own database so concurrent table names never
     * collide and teardown is a single DROP DATABASE.
     */
    public static function database(string $framework): string
    {
        return 'mo_compat_' . $framework;
    }
}
