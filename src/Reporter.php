<?php

declare(strict_types=1);

namespace MoTest;

/**
 * Turns a flat list of TestResult objects into:
 *   - a console summary
 *   - reports/results.json   (full machine-readable dump)
 *   - reports/report.md      (human-readable compatibility report)
 */
final class Reporter
{
    /** @param TestResult[] $results */
    public function __construct(private array $results, private string $dir)
    {
    }

    public function write(): array
    {
        @mkdir($this->dir, 0777, true);

        $total = count($this->results);
        $pass = $fail = $skip = 0;
        foreach ($this->results as $r) {
            match ($r->status) {
                TestResult::PASS => $pass++,
                TestResult::FAIL => $fail++,
                TestResult::SKIP => $skip++,
                default => null,
            };
        }

        file_put_contents(
            $this->dir . '/results.json',
            json_encode([
                'generated_at' => date('c'),
                'target' => [
                    'host' => Config::host(),
                    'port' => Config::port(),
                    'server_version' => Config::serverVersion(),
                ],
                'summary' => compact('total', 'pass', 'fail', 'skip'),
                'results' => array_map(fn (TestResult $r) => $r->toArray(), $this->results),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        file_put_contents($this->dir . '/report.md', $this->markdown($total, $pass, $fail, $skip));

        return compact('total', 'pass', 'fail', 'skip');
    }

    private function markdown(int $total, int $pass, int $fail, int $skip): string
    {
        $version = '';
        try {
            $version = Connections::pdo()->query('SELECT version()')->fetchColumn();
        } catch (\Throwable) {
            $version = Config::serverVersion();
        }

        $out = [];
        $out[] = '# PHP ORM ⇆ MatrixOne Compatibility Report';
        $out[] = '';
        $out[] = '- **Generated:** ' . date('Y-m-d H:i:s T');
        $out[] = '- **Target:** `' . Config::host() . ':' . Config::port() . '`';
        $out[] = '- **Server version():** `' . $version . '`';
        $out[] = sprintf('- **Scenarios:** %d total — **%d passed**, **%d failed**, **%d skipped**', $total, $pass, $fail, $skip);
        $rate = $total > 0 ? round($pass / max(1, $total - $skip) * 100, 1) : 0;
        $out[] = sprintf('- **Pass rate (excl. skipped):** %.1f%%', $rate);
        $out[] = '';

        // ---- Per framework x status matrix ----
        $out[] = '## Summary by framework';
        $out[] = '';
        $out[] = '| Framework | Total | Pass | Fail | Skip | Pass % |';
        $out[] = '|---|--:|--:|--:|--:|--:|';
        foreach ($this->groupBy('framework') as $fw => $rs) {
            [$t, $p, $f, $s] = $this->tally($rs);
            $pr = ($t - $s) > 0 ? round($p / ($t - $s) * 100, 1) : 0;
            $out[] = sprintf('| %s | %d | %d | %d | %d | %.1f%% |', $fw, $t, $p, $f, $s, $pr);
        }
        $out[] = '';

        // ---- Per category ----
        $out[] = '## Summary by category';
        $out[] = '';
        $out[] = '| Category | Total | Pass | Fail | Skip |';
        $out[] = '|---|--:|--:|--:|--:|';
        foreach ($this->groupBy('category') as $cat => $rs) {
            [$t, $p, $f, $s] = $this->tally($rs);
            $out[] = sprintf('| %s | %d | %d | %d | %d |', $cat, $t, $p, $f, $s);
        }
        $out[] = '';

        // ---- Compatibility issues, grouped by error code ----
        $out[] = '## Compatibility issues (failures) grouped by error signature';
        $out[] = '';
        $byCode = [];
        foreach ($this->results as $r) {
            if ($r->status !== TestResult::FAIL) {
                continue;
            }
            $byCode[$r->errorCode ?? 'ERROR'][] = $r;
        }
        if (!$byCode) {
            $out[] = '_No failures recorded._';
            $out[] = '';
        } else {
            uasort($byCode, fn ($a, $b) => count($b) <=> count($a));
            foreach ($byCode as $code => $rs) {
                $out[] = sprintf('### Error `%s` — %d scenario(s)', $code, count($rs));
                $out[] = '';
                // Representative message
                $sample = $rs[0];
                $out[] = '> ' . $this->oneLine($sample->errorMessage ?? '');
                $out[] = '';
                $out[] = '| # | Framework | Category | Scenario |';
                $out[] = '|--:|---|---|---|';
                foreach ($rs as $r) {
                    $out[] = sprintf('| %d | %s | %s | %s |', $r->id, $r->framework, $r->category, $this->esc($r->name));
                }
                $out[] = '';
            }
        }

        // ---- Full failure detail ----
        $out[] = '## Detailed failures';
        $out[] = '';
        foreach ($this->results as $r) {
            if ($r->status !== TestResult::FAIL) {
                continue;
            }
            $out[] = sprintf('#### #%d [%s / %s] %s', $r->id, $r->framework, $r->category, $r->name);
            if ($r->sql) {
                $out[] = '';
                $out[] = '```sql';
                $out[] = $r->sql;
                $out[] = '```';
            }
            $out[] = '';
            $out[] = '- **Code:** `' . ($r->errorCode ?? 'ERROR') . '`';
            $out[] = '- **Message:** ' . $this->oneLine($r->errorMessage ?? '');
            $out[] = '';
        }

        return implode("\n", $out) . "\n";
    }

    /** @return array<string, TestResult[]> */
    private function groupBy(string $field): array
    {
        $g = [];
        foreach ($this->results as $r) {
            $g[$r->$field][] = $r;
        }
        ksort($g);
        return $g;
    }

    /** @param TestResult[] $rs @return array{0:int,1:int,2:int,3:int} */
    private function tally(array $rs): array
    {
        $t = count($rs);
        $p = $f = $s = 0;
        foreach ($rs as $r) {
            match ($r->status) {
                TestResult::PASS => $p++,
                TestResult::FAIL => $f++,
                TestResult::SKIP => $s++,
                default => null,
            };
        }
        return [$t, $p, $f, $s];
    }

    private function oneLine(string $s): string
    {
        return $this->esc(trim(preg_replace('/\s+/', ' ', $s) ?? $s));
    }

    private function esc(string $s): string
    {
        return str_replace(['|', "\n"], ['\|', ' '], $s);
    }
}
