<?php

declare(strict_types=1);

use MoTest\Connections;
use MoTest\Reporter;
use MoTest\Runner;
use MoTest\Scenarios\BehaviorScenarios;
use MoTest\Scenarios\CakeScenarios;
use MoTest\Scenarios\DoctrineScenarios;
use MoTest\Scenarios\CakeScenarios2;
use MoTest\Scenarios\DoctrineScenarios2;
use MoTest\Scenarios\EloquentScenarios;
use MoTest\Scenarios\EloquentScenarios2;
use MoTest\Scenarios\ExtraScenarios;
use MoTest\Scenarios\MatrixOneFeatureScenarios;
use MoTest\Scenarios\PdoFunctionScenarios;
use MoTest\Scenarios\PdoFunctionScenarios2;
use MoTest\Scenarios\PdoSqlScenarios;
use MoTest\Scenarios\PdoSqlScenarios2;
use MoTest\Scenarios\PdoTypeScenarios;
use MoTest\Scenarios\PreparedStatementScenarios;
use MoTest\Scenarios\AppWorkloadScenarios;
use MoTest\Scenarios\TypeMatrixScenarios;
use MoTest\Scenarios\DdlSurfaceScenarios;
use MoTest\Scenarios\EloquentAppScenarios;
use MoTest\Scenarios\SqlSemanticsScenarios;
use MoTest\Scenarios\RedBeanScenarios;
use MoTest\Scenarios\MegaMatrixScenarios;
use MoTest\TestResult;

require __DIR__ . '/vendor/autoload.php';

// Promote driver-level PHP warnings (e.g. mysqlnd "Malformed server packet")
// into catchable exceptions so they surface as recorded failures instead of
// leaking to output. Deprecations from vendor code are intentionally ignored.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    // Honour the @-suppression operator and error_reporting level.
    if (!(error_reporting() & $severity)) {
        return false;
    }
    if ($severity === E_WARNING || $severity === E_USER_WARNING) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});

$verbose = in_array('--verbose', $argv, true) || in_array('-v', $argv, true);
$only = null;
foreach ($argv as $a) {
    if (str_starts_with($a, '--only=')) {
        $only = substr($a, 7);
    }
}

fwrite(STDOUT, "MatrixOne PHP ORM compatibility suite\n");
fwrite(STDOUT, str_repeat('=', 60) . "\n");

$frameworks = ['pdo', 'eloquent', 'doctrine', 'cake'];
fwrite(STDOUT, "Bootstrapping databases...\n");
Connections::bootstrapDatabases($frameworks);

$runner = new Runner($verbose);

$providers = [
    'pdo-type' => PdoTypeScenarios::class,
    'pdo-func' => PdoFunctionScenarios::class,
    'pdo-sql' => PdoSqlScenarios::class,
    'pdo-behavior' => BehaviorScenarios::class,
    'pdo-prepared' => PreparedStatementScenarios::class,
    'pdo-extra' => ExtraScenarios::class,
    'pdo-mo-feature' => MatrixOneFeatureScenarios::class,
    'pdo-func2' => PdoFunctionScenarios2::class,
    'pdo-sql2' => PdoSqlScenarios2::class,
    'eloquent' => EloquentScenarios::class,
    'eloquent2' => EloquentScenarios2::class,
    'doctrine' => DoctrineScenarios::class,
    'doctrine2' => DoctrineScenarios2::class,
    'cake' => CakeScenarios::class,
    'cake2' => CakeScenarios2::class,
    'type-matrix' => TypeMatrixScenarios::class,
    'app-workload' => AppWorkloadScenarios::class,
    'ddl-surface' => DdlSurfaceScenarios::class,
    'eloquent-app' => EloquentAppScenarios::class,
    'sql-semantics' => SqlSemanticsScenarios::class,
    'redbean' => RedBeanScenarios::class,
    'mega-matrix' => MegaMatrixScenarios::class,
];

foreach ($providers as $key => $class) {
    if ($only !== null && !str_contains($key, $only)) {
        continue;
    }
    if (!class_exists($class)) {
        continue;
    }
    $class::register($runner);
}

fwrite(STDOUT, sprintf("Registered %d scenarios. Running...\n\n", $runner->count()));

$t0 = microtime(true);
$runner->run();
$elapsed = microtime(true) - $t0;

$reporter = new Reporter($runner->results(), __DIR__ . '/reports');
$summary = $reporter->write();

fwrite(STDOUT, "\n" . str_repeat('=', 60) . "\n");
fwrite(STDOUT, sprintf(
    "DONE in %.1fs — %d scenarios: %d PASS, %d FAIL, %d SKIP\n",
    $elapsed,
    $summary['total'],
    $summary['pass'],
    $summary['fail'],
    $summary['skip'],
));
fwrite(STDOUT, "Reports written to reports/report.md and reports/results.json\n");
