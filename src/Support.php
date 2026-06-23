<?php

declare(strict_types=1);

namespace MoTest;

final class Support
{
    private static int $seq = 0;

    /** Unique-ish identifier safe for table/column names. */
    public static function name(string $prefix = 't'): string
    {
        return $prefix . '_' . (++self::$seq) . '_' . substr(bin2hex(random_bytes(3)), 0, 5);
    }

    public static function assert(bool $cond, string $message): void
    {
        if (!$cond) {
            throw new BehaviorMismatch($message);
        }
    }

    /** Assert two scalar values are loosely-equal, else BehaviorMismatch. */
    public static function assertEquals(mixed $expected, mixed $actual, string $what): void
    {
        if ((string) $expected !== (string) $actual) {
            throw new BehaviorMismatch(sprintf(
                '%s: expected %s, got %s',
                $what,
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    /**
     * Treat two values as equal if they are numerically equal (ignoring decimal
     * formatting such as 1.56 vs 1.560 or 75.0 vs 75.0000) or string-identical.
     * Used for function-result comparisons where scale formatting is not a
     * meaningful compatibility difference.
     */
    public static function assertValueEquals(mixed $expected, mixed $actual, string $what): void
    {
        if ((string) $expected === (string) $actual) {
            return;
        }
        if (is_numeric($expected) && is_numeric($actual) && abs((float) $expected - (float) $actual) < 1e-9) {
            return;
        }
        throw new BehaviorMismatch(sprintf(
            '%s: expected %s, got %s',
            $what,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }

    public static function skip(string $why): never
    {
        throw new SkipScenario($why);
    }
}
