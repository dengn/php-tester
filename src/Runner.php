<?php

declare(strict_types=1);

namespace MoTest;

/**
 * Minimal but robust scenario runner.
 *
 * A scenario is a callable that performs some operation against MatrixOne and:
 *   - returns a string (or null) on success  => PASS
 *   - throws SkipScenario                     => SKIP
 *   - throws BehaviorMismatch                 => FAIL (behaviour difference)
 *   - throws anything else                    => FAIL (error / incompatibility)
 *
 * The runner isolates each scenario: one exploding scenario never aborts the
 * run, and timing + error classification are captured for the report.
 */
final class Runner
{
    /** @var array<int, array{framework:string, category:string, name:string, fn:callable}> */
    private array $scenarios = [];

    /** @var TestResult[] */
    private array $results = [];

    private int $nextId = 1;

    private bool $verbose;

    public function __construct(bool $verbose = false)
    {
        $this->verbose = $verbose;
    }

    public function add(string $framework, string $category, string $name, callable $fn): void
    {
        $this->scenarios[] = [
            'framework' => $framework,
            'category' => $category,
            'name' => $name,
            'fn' => $fn,
        ];
    }

    public function count(): int
    {
        return count($this->scenarios);
    }

    public function run(): void
    {
        $total = count($this->scenarios);
        foreach ($this->scenarios as $i => $s) {
            $id = $this->nextId++;
            $start = microtime(true);
            $status = TestResult::PASS;
            $detail = null;
            $errorCode = null;
            $errorMessage = null;
            $sql = null;

            try {
                $detail = ($s['fn'])();
                if (is_array($detail)) {
                    // scenarios may return ['detail' => ..., 'sql' => ...]
                    $sql = $detail['sql'] ?? null;
                    $detail = $detail['detail'] ?? null;
                }
            } catch (SkipScenario $e) {
                $status = TestResult::SKIP;
                $detail = $e->getMessage();
            } catch (BehaviorMismatch $e) {
                $status = TestResult::FAIL;
                $errorCode = 'BEHAVIOR';
                $errorMessage = $e->getMessage();
            } catch (\Throwable $e) {
                $status = TestResult::FAIL;
                [$errorCode, $errorMessage] = self::classify($e);
                $sql = self::extractSql($e);
            }

            $durationMs = (microtime(true) - $start) * 1000;
            $this->results[] = new TestResult(
                $id,
                $s['framework'],
                $s['category'],
                $s['name'],
                $status,
                $durationMs,
                is_string($detail) ? $detail : null,
                $errorCode,
                $errorMessage,
                $sql,
            );

            if ($this->verbose || $status === TestResult::FAIL) {
                $marker = $status === TestResult::PASS ? '.' : ($status === TestResult::SKIP ? 's' : 'F');
                if ($status === TestResult::FAIL) {
                    fwrite(STDERR, sprintf(
                        "\n[FAIL #%d] %s / %s / %s\n        %s %s\n",
                        $id,
                        $s['framework'],
                        $s['category'],
                        $s['name'],
                        $errorCode ? "($errorCode)" : '',
                        self::short($errorMessage ?? '')
                    ));
                } else {
                    fwrite(STDOUT, $marker);
                }
            } else {
                fwrite(STDOUT, '.');
            }

            if (($id % 100) === 0) {
                fwrite(STDOUT, sprintf(" [%d/%d]\n", $id, $total));
            }
        }
        fwrite(STDOUT, "\n");
    }

    /** @return TestResult[] */
    public function results(): array
    {
        return $this->results;
    }

    /**
     * Pull a vendor-agnostic error code out of whatever exception bubbled up.
     * MatrixOne surfaces MySQL-style numeric codes inside the message
     * ("General error: 1064 ...", "internal error: ...") as well as SQLSTATE.
     *
     * @return array{0:string,1:string}
     */
    public static function classify(\Throwable $e): array
    {
        $msg = $e->getMessage();

        // Doctrine/DBAL wraps the driver exception; dig for the original.
        $prev = $e->getPrevious();
        if ($prev instanceof \Throwable && $prev->getMessage() !== '') {
            $msg = $prev->getMessage();
        }

        $code = 'ERROR';
        if (preg_match('/SQLSTATE\[(\w+)\]/', $msg, $m)) {
            $code = $m[1];
        }
        // MatrixOne numeric error: "General error: 1064" or "error: 20101"
        if (preg_match('/(?:General error|error):\s*(\d{3,5})/', $msg, $m)) {
            $code = $m[1];
        } elseif (preg_match('/\b(\d{4,5})\b\s+(?:SQL parser error|internal error)/', $msg, $m)) {
            $code = $m[1];
        }
        // Recognisable MatrixOne "not been implemented" / "not support" phrases.
        if (stripos($msg, 'not been implemented') !== false || stripos($msg, 'not implemented') !== false) {
            $code = $code === 'ERROR' ? 'UNIMPLEMENTED' : $code;
        }

        return [$code, trim($msg)];
    }

    private static function extractSql(\Throwable $e): ?string
    {
        // DBAL exceptions expose the SQL via getSQL() on some classes.
        if (method_exists($e, 'getSQL')) {
            $sql = $e->getSQL();
            if (is_string($sql) && $sql !== '') {
                return $sql;
            }
        }
        return null;
    }

    private static function short(string $s, int $len = 160): string
    {
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        return strlen($s) > $len ? substr($s, 0, $len) . '…' : $s;
    }
}
