<?php

declare(strict_types=1);

namespace MoTest;

/**
 * A single scenario outcome.
 *
 * status:
 *   PASS  - MatrixOne behaved as a MySQL-compatible database should
 *   FAIL  - a compatibility issue: an error, or an observable behaviour
 *           difference versus MySQL semantics
 *   SKIP  - scenario was not applicable in this environment
 */
final class TestResult
{
    public const PASS = 'PASS';
    public const FAIL = 'FAIL';
    public const SKIP = 'SKIP';

    public function __construct(
        public readonly int $id,
        public readonly string $framework,
        public readonly string $category,
        public readonly string $name,
        public readonly string $status,
        public readonly float $durationMs,
        public readonly ?string $detail = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $sql = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'framework' => $this->framework,
            'category' => $this->category,
            'name' => $this->name,
            'status' => $this->status,
            'duration_ms' => round($this->durationMs, 2),
            'detail' => $this->detail,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'sql' => $this->sql,
        ];
    }
}
