<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use MoTest\Config;
use MoTest\Runner;
use MoTest\Support;
use RedBeanPHP\R;

/**
 * RedBeanPHP (gabordemooij/redbean v5.7) compatibility matrices against
 * MatrixOne.
 *
 * RedBean uses a single GLOBAL toolbox (the \RedBeanPHP\R facade). Because the
 * whole suite runs sequentially in one PHP process, this provider:
 *   - calls R::setup(...) exactly ONCE, lazily, guarded by a static flag,
 *     pointing at the shared 'pdo' database;
 *   - gives every scenario a UNIQUE, lowercase-alphanumeric bean type
 *     (e.g. "rb" . <hex>) so two scenarios never share a table;
 *   - DROPs every table it created in a finally{} block (with FK checks off so
 *     many-to-many link tables can be removed), never calling R::nuke() — which
 *     would drop OTHER providers' tables in the shared database.
 *
 * Confirmed RedBean-on-MatrixOne behaviour used to write expectations (probed):
 *   - Fluid type inference: int -> INT UNSIGNED, negative/float -> DOUBLE,
 *     decimal-looking string -> DECIMAL(10,2), short string -> VARCHAR(191),
 *     long string -> TEXT, datetime string -> DATETIME, bool -> TINYINT UNSIGNED.
 *   - Column WIDENING is REJECTED by MatrixOne's strict typing: storing a
 *     string into an INT-inferred column errors 20203, an over-range int errors
 *     1690, varchar->text widening errors 20101. (RedBean fluid relies on
 *     ALTER ... MODIFY + recast which MatrixOne refuses.) -> documented findings.
 *   - R::transaction(closure) in FLUID mode silently fails to commit (returns
 *     false, work is lost); in FROZEN mode it commits. begin/commit/rollback
 *     work in both modes. -> documented finding; tx scenarios seed+freeze first.
 *   - Many-to-many sharedList creates a FK link table; R::wipe()/TRUNCATE on a
 *     FK-referenced table errors 20101, so cleanup must DROP with FK checks off.
 *
 * A failing scenario here is a genuine RedBean/MatrixOne finding, not a test
 * bug. Only PHP-harness mistakes (undefined var / null call / TypeError) are
 * forbidden.
 */
final class RedBeanScenarios
{
    private const FW = 'RedBean';

    private static bool $booted = false;

    public static function register(Runner $r): void
    {
        self::crud($r);
        self::fluid($r);
        self::frozen($r);
        self::query($r);
        self::finder($r);
        self::relation($r);
        self::typeRoundTrips($r);
        self::transaction($r);
    }

    // ----------------------------------------------------------------- harness

    /** Lazily set up the global RedBean toolbox exactly once. */
    private static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        $db = Config::database('pdo');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            Config::host(),
            Config::port(),
            $db
        );
        R::setup($dsn, Config::user(), Config::password());
        R::freeze(false);
        self::$booted = true;
    }

    /** A unique lowercase-alphanumeric bean type (RedBean requires no _ in type). */
    private static function type(string $prefix = 'rb'): string
    {
        // Support::name yields prefix_<seq>_<hex>; strip non-alphanumerics and
        // lowercase so RedBean accepts it as a bean type / table name.
        $raw = Support::name($prefix);
        $clean = strtolower(preg_replace('/[^a-z0-9]/i', '', $raw) ?? $raw);
        // ensure it starts with a letter
        return 'rb' . substr($clean, 0, 28);
    }

    /** Drop a set of tables, FK-safe, swallowing teardown errors. */
    private static function dropTables(array $tables): void
    {
        try {
            R::exec('SET FOREIGN_KEY_CHECKS=0');
        } catch (\Throwable) {
        }
        foreach ($tables as $t) {
            try {
                R::exec("DROP TABLE IF EXISTS `$t`");
            } catch (\Throwable) {
            }
        }
        try {
            R::exec('SET FOREIGN_KEY_CHECKS=1');
        } catch (\Throwable) {
        }
    }

    /** RedBean's link-table name for a many-to-many between two types. */
    private static function linkTable(string $a, string $b): string
    {
        return ($a < $b) ? "{$a}_{$b}" : "{$b}_{$a}";
    }

    // ================================================================== CRUD

    private static function crud(Runner $r): void
    {
        // ---- round-trip single typed values through dispense/store/load -----
        // [label, php value, expected-string-back]
        $values = [
            ['int small', 7, '7'],
            ['int zero', 0, '0'],
            ['int large', 1000000, '1000000'],
            ['int 3e9', 3000000000, '3000000000'],
            ['negative int', -42, '-42'],
            ['float half', 2.5, '2.5'],
            ['float pi-ish', 3.25, '3.25'],
            ['decimal string', '19.99', '19.99'],
            ['money string', '1234.56', '1234.56'],
            ['short text', 'hello world', 'hello world'],
            ['empty string', '', ''],
            ['unicode text', 'café déjà', 'café déjà'],
            ['emoji text', 'hi 🐝 bean', 'hi 🐝 bean'],
            ['bool true', true, '1'],
            ['bool false', false, '0'],
            ['numeric string', '007', '7'],
            ['datetime string', '2026-06-23 14:05:09', '2026-06-23 14:05:09'],
            ['date string', '2026-06-23', '2026-06-23'],
            ['json string', '{"k":"v","n":1}', '{"k":"v","n":1}'],
            ['long text 400', str_repeat('z', 400), str_repeat('z', 400)],
            ['spaces preserved', '  pad  ', '  pad  '],
            ['quote text', "O'Brien", "O'Brien"],
            ['backslash text', 'a\\b', 'a\\b'],
        ];
        foreach ($values as [$label, $val, $exp]) {
            self::addRoundTrip($r, 'redbean:crud', "store/load value: $label", $val, $exp);
        }

        // ---- store returns id; ids increment ; update keeps id ---------------
        $r->add(self::FW, 'redbean:crud', 'store returns positive id', function () {
            self::boot();
            $t = self::type();
            try {
                $b = R::dispense($t);
                $b->name = 'x';
                $id = R::store($b);
                Support::assert((int) $id >= 1, "store returned non-positive id: " . var_export($id, true));
                return ['detail' => "id=$id", 'sql' => "R::store dispense('$t')"];
            } finally {
                self::dropTables([$t]);
            }
        });

        $r->add(self::FW, 'redbean:crud', 'sequential ids increment', function () {
            self::boot();
            $t = self::type();
            try {
                $a = R::dispense($t);
                $a->v = 1;
                $id1 = (int) R::store($a);
                $b = R::dispense($t);
                $b->v = 2;
                $id2 = (int) R::store($b);
                Support::assert($id2 > $id1, "ids did not increment: $id1 then $id2");
                return ['detail' => "id1=$id1 id2=$id2", 'sql' => 'two stores'];
            } finally {
                self::dropTables([$t]);
            }
        });

        $r->add(self::FW, 'redbean:crud', 'update via re-store keeps id', function () {
            self::boot();
            $t = self::type();
            try {
                $b = R::dispense($t);
                $b->v = 'first';
                $id = (int) R::store($b);
                $b->v = 'second';
                $id2 = (int) R::store($b);
                Support::assertEquals($id, $id2, 'update changed id');
                $re = R::load($t, $id);
                Support::assertEquals('second', $re->v, 'update not persisted');
                return ['detail' => "id=$id v=second", 'sql' => 'store, mutate, store'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- trash removes the row ------------------------------------------
        $r->add(self::FW, 'redbean:crud', 'trash removes row', function () {
            self::boot();
            $t = self::type();
            try {
                $b = R::dispense($t);
                $b->v = 1;
                $id = (int) R::store($b);
                Support::assertEquals('1', (string) R::count($t), 'count before trash');
                R::trash(R::load($t, $id));
                Support::assertEquals('0', (string) R::count($t), 'count after trash');
                return ['detail' => 'trashed', 'sql' => 'R::trash'];
            } finally {
                self::dropTables([$t]);
            }
        });

        $r->add(self::FW, 'redbean:crud', 'trash by type+id', function () {
            self::boot();
            $t = self::type();
            try {
                $b = R::dispense($t);
                $b->v = 1;
                $id = (int) R::store($b);
                R::trash($t, $id);
                Support::assertEquals('0', (string) R::count($t), 'count after trash by id');
                return ['detail' => 'trashed', 'sql' => "R::trash('$t', id)"];
            } finally {
                self::dropTables([$t]);
            }
        });

        $r->add(self::FW, 'redbean:crud', 'trashAll removes many', function () {
            self::boot();
            $t = self::type();
            try {
                foreach (range(1, 5) as $i) {
                    $b = R::dispense($t);
                    $b->v = $i;
                    R::store($b);
                }
                R::trashAll(R::findAll($t));
                Support::assertEquals('0', (string) R::count($t), 'trashAll left rows');
                return ['detail' => 'trashed 5', 'sql' => 'R::trashAll'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- load missing returns empty bean --------------------------------
        $r->add(self::FW, 'redbean:crud', 'load missing returns empty bean', function () {
            self::boot();
            $t = self::type();
            try {
                $b = R::dispense($t);
                $b->v = 1;
                R::store($b);
                $miss = R::load($t, 999999);
                Support::assert((int) $miss->id === 0, 'missing bean id should be 0, got ' . var_export($miss->id, true));
                Support::assertEquals($t, $miss->getMeta('type'), 'empty bean keeps its type');
                return ['detail' => 'empty bean id=0', 'sql' => "R::load('$t', 999999)"];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- export() / import() --------------------------------------------
        $r->add(self::FW, 'redbean:crud', 'export() yields id + props', function () {
            self::boot();
            $t = self::type();
            try {
                $b = R::dispense($t);
                $b->name = 'alice';
                $b->age = 30;
                $id = (int) R::store($b);
                $exp = R::load($t, $id)->export();
                Support::assert(isset($exp['id']) && isset($exp['name']) && isset($exp['age']), 'export missing keys: ' . json_encode(array_keys($exp)));
                Support::assertEquals('alice', $exp['name'], 'export name');
                return ['detail' => json_encode($exp), 'sql' => 'bean->export()'];
            } finally {
                self::dropTables([$t]);
            }
        });

        $r->add(self::FW, 'redbean:crud', 'import() populates bean', function () {
            self::boot();
            $t = self::type();
            try {
                $b = R::dispense($t);
                $b->import(['name' => 'bob', 'age' => 25]);
                Support::assertEquals('bob', $b->name, 'import name');
                Support::assertEquals('25', (string) $b->age, 'import age');
                $id = (int) R::store($b);
                $re = R::load($t, $id);
                Support::assertEquals('bob', $re->name, 'imported value persisted');
                return ['detail' => 'imported+stored', 'sql' => 'bean->import()'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- getMeta type/tainted -------------------------------------------
        $r->add(self::FW, 'redbean:crud', 'getMeta type of dispensed bean', function () {
            self::boot();
            $t = self::type();
            try {
                $b = R::dispense($t);
                Support::assertEquals($t, $b->getMeta('type'), 'dispensed bean type');
                return ['detail' => "type=$t", 'sql' => "dispense('$t')->getMeta('type')"];
            } finally {
                // nothing stored, nothing to drop, but be safe
                self::dropTables([$t]);
            }
        });

        // ---- multi-property bean store/reload over different property sets ---
        $propSets = [
            ['a' => 1],
            ['a' => 1, 'b' => 2],
            ['name' => 'x', 'qty' => 3, 'price' => '4.50'],
            ['title' => 'doc', 'body' => str_repeat('w', 200), 'active' => true],
            ['x' => -1, 'y' => 0, 'z' => 99999],
            ['created' => '2026-01-01 00:00:00', 'flag' => false, 'note' => 'café'],
            ['c1' => 1, 'c2' => 2, 'c3' => 3, 'c4' => 4, 'c5' => 5],
        ];
        foreach ($propSets as $i => $set) {
            $cols = implode(',', array_keys($set));
            self::addPropSet($r, 'redbean:crud', "round-trip prop set [$cols]", $set);
        }

        // ---- dispense N at once ---------------------------------------------
        $r->add(self::FW, 'redbean:crud', 'dispense multiple beans', function () {
            self::boot();
            $t = self::type();
            try {
                $beans = R::dispense($t, 3);
                Support::assert(is_array($beans) && count($beans) === 3, 'dispense(type,3) should return 3 beans');
                foreach ($beans as $i => $bn) {
                    $bn->v = $i;
                    R::store($bn);
                }
                Support::assertEquals('3', (string) R::count($t), 'stored 3 dispensed beans');
                return ['detail' => 'dispensed+stored 3', 'sql' => "dispense('$t',3)"];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- storeAll / loadAll batch ---------------------------------------
        $r->add(self::FW, 'redbean:crud', 'storeAll then count', function () {
            self::boot();
            $t = self::type();
            try {
                $beans = [];
                foreach (range(1, 4) as $i) {
                    $bn = R::dispense($t);
                    $bn->v = $i;
                    $beans[] = $bn;
                }
                R::storeAll($beans);
                Support::assertEquals('4', (string) R::count($t), 'storeAll count');
                return ['detail' => 'storeAll 4', 'sql' => 'R::storeAll'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- duplicate a bean (dup) -----------------------------------------
        $r->add(self::FW, 'redbean:crud', 'dup creates new row', function () {
            self::boot();
            $t = self::type();
            try {
                $b = R::dispense($t);
                $b->name = 'orig';
                R::store($b);
                $dup = R::dup($b);
                Support::assert((int) $dup->id === 0, 'dup should have id 0 before store');
                R::store($dup);
                Support::assertEquals('2', (string) R::count($t), 'dup should add a row');
                return ['detail' => 'dup+stored', 'sql' => 'R::dup'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- large-ish batch (single scale scenario) ------------------------
        $r->add(self::FW, 'redbean:crud', 'batch store 50 and count', function () {
            self::boot();
            $t = self::type();
            try {
                $beans = [];
                foreach (range(1, 50) as $i) {
                    $bn = R::dispense($t);
                    $bn->v = $i;
                    $beans[] = $bn;
                }
                R::storeAll($beans);
                Support::assertEquals('50', (string) R::count($t), 'batch 50 count');
                return ['detail' => '50 rows', 'sql' => 'storeAll 50'];
            } finally {
                self::dropTables([$t]);
            }
        });
    }

    /** Store a single property of a given value, reload, assert the string-back. */
    private static function addRoundTrip(Runner $r, string $cat, string $name, mixed $val, string $expected): void
    {
        $r->add(self::FW, $cat, $name, function () use ($val, $expected, $name) {
            self::boot();
            $t = self::type();
            try {
                $b = R::dispense($t);
                $b->val = $val;
                $id = (int) R::store($b);
                $got = R::load($t, $id)->val;
                Support::assertValueEquals($expected, $got, $name);
                return ['detail' => 'back=' . var_export($got, true), 'sql' => "store/load prop val"];
            } finally {
                self::dropTables([$t]);
            }
        });
    }

    /** Store a full property set, reload, assert each value round-trips. */
    private static function addPropSet(Runner $r, string $cat, string $name, array $set): void
    {
        $r->add(self::FW, $cat, $name, function () use ($set, $name) {
            self::boot();
            $t = self::type();
            try {
                $b = R::dispense($t);
                foreach ($set as $k => $v) {
                    $b->$k = $v;
                }
                $id = (int) R::store($b);
                $re = R::load($t, $id);
                foreach ($set as $k => $v) {
                    $exp = is_bool($v) ? ($v ? '1' : '0') : (string) $v;
                    Support::assertValueEquals($exp, $re->$k, "$name prop $k");
                }
                return ['detail' => 'round-tripped ' . count($set) . ' props', 'sql' => 'multi-prop store/load'];
            } finally {
                self::dropTables([$t]);
            }
        });
    }

    // ================================================================== FLUID

    private static function fluid(Runner $r): void
    {
        // ---- fluid schema auto-creation: each value type infers a column ----
        // [prop value, expected inferred column type substring]
        $infer = [
            ['int 7', 7, 'INT UNSIGNED'],
            ['int 300', 300, 'INT UNSIGNED'],
            ['int 70000', 70000, 'INT UNSIGNED'],
            ['int 3e9', 3000000000, 'INT UNSIGNED'],
            ['negative -5', -5, 'DOUBLE'],
            ['float 1.5', 1.5, 'DOUBLE'],
            ['float 99.99', 99.99, 'DECIMAL'],
            ['decimal str 19.99', '19.99', 'DECIMAL'],
            ['money str 1234.56', '1234.56', 'DECIMAL'],
            ['short str', 'hello', 'VARCHAR'],
            ['empty str', '', 'VARCHAR'],
            ['long str 300', str_repeat('x', 300), 'TEXT'],
            ['datetime str', '2026-01-02 03:04:05', 'DATETIME'],
            ['bool true', true, 'TINYINT'],
            ['bool false', false, 'TINYINT'],
        ];
        foreach ($infer as [$label, $val, $expectType]) {
            $r->add(self::FW, 'redbean:fluid', "infer column type: $label -> $expectType", function () use ($val, $expectType, $label) {
                self::boot();
                R::freeze(false);
                $t = self::type();
                try {
                    $b = R::dispense($t);
                    $b->p = $val;
                    R::store($b);
                    $cols = R::getColumns($t);
                    Support::assert(isset($cols['p']), "column 'p' was not created; cols=" . json_encode($cols));
                    Support::assert(
                        stripos((string) $cols['p'], $expectType) !== false,
                        "expected type containing '$expectType' for $label, got '" . $cols['p'] . "'"
                    );
                    return ['detail' => "p => " . $cols['p'], 'sql' => "fluid infer $label"];
                } finally {
                    self::dropTables([$t]);
                }
            });
        }

        // ---- adding a NEW property creates a NEW column ---------------------
        $r->add(self::FW, 'redbean:fluid', 'new property auto-adds column', function () {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $b = R::dispense($t);
                $b->a = 1;
                R::store($b);
                $before = R::getColumns($t);
                Support::assert(!isset($before['b']), "column b unexpectedly present");
                $b2 = R::dispense($t);
                $b2->a = 2;
                $b2->b = 'new';
                R::store($b2);
                $after = R::getColumns($t);
                Support::assert(isset($after['b']), "fluid did not add column b: " . json_encode($after));
                return ['detail' => 'column b added on demand', 'sql' => 'fluid add column'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- id column is auto-created INT UNSIGNED -------------------------
        $r->add(self::FW, 'redbean:fluid', 'id column auto-created as INT UNSIGNED', function () {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $b = R::dispense($t);
                $b->x = 1;
                R::store($b);
                $cols = R::getColumns($t);
                Support::assert(isset($cols['id']), 'no id column created');
                Support::assert(stripos((string) $cols['id'], 'INT') !== false, "id column type: " . $cols['id']);
                return ['detail' => 'id => ' . $cols['id'], 'sql' => 'fluid id column'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- inspect() lists the table after fluid create -------------------
        $r->add(self::FW, 'redbean:fluid', 'inspect lists fluid-created table', function () {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $b = R::dispense($t);
                $b->x = 1;
                R::store($b);
                $tables = R::inspect();
                Support::assert(in_array($t, $tables, true), "table $t not in inspect(): " . json_encode($tables));
                $colsViaInspect = R::inspect($t);
                Support::assert(isset($colsViaInspect['x']), "inspect(type) missing column x");
                return ['detail' => 'inspected', 'sql' => 'R::inspect'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- WIDENING is rejected by MatrixOne strict typing (findings) -----
        // store value A (infers type), then store value B that needs widening.
        // [label, first value, second value]  -> MatrixOne should REJECT the 2nd.
        $widen = [
            ['int col -> string value', 42, 'now a long string'],
            ['int col -> overflow bigint', 5, 9999999999999],
            ['varchar col -> 500-char text', 'short', str_repeat('y', 500)],
            ['bool col -> long string', true, str_repeat('q', 250)],
        ];
        foreach ($widen as [$label, $v1, $v2]) {
            $r->add(self::FW, 'redbean:fluid', "widening rejected: $label", function () use ($v1, $v2, $label) {
                self::boot();
                R::freeze(false);
                $t = self::type();
                try {
                    $b = R::dispense($t);
                    $b->p = $v1;
                    R::store($b);
                    $rejected = false;
                    $err = '';
                    try {
                        $b2 = R::dispense($t);
                        $b2->p = $v2;
                        R::store($b2);
                    } catch (\Throwable $e) {
                        $rejected = true;
                        $err = $e->getMessage();
                    }
                    Support::assert(
                        $rejected,
                        "MatrixOne accepted a fluid widening for $label (expected strict rejection)"
                    );
                    return ['detail' => 'rejected: ' . substr(preg_replace('/\s+/', ' ', $err) ?? $err, 0, 90), 'sql' => "fluid widen $label"];
                } finally {
                    self::dropTables([$t]);
                }
            });
        }

        // ---- widening that fits the SAME inferred type SUCCEEDS -------------
        // int 5 -> int 200 stays INT UNSIGNED, no ALTER needed.
        $sameType = [
            ['int small -> int medium', 5, 200],
            ['int -> larger int (fits uint32)', 100, 4000000000],
            ['short str -> medium str (<=191)', 'a', str_repeat('b', 150)],
            ['float -> float', 1.5, 2.75],
        ];
        foreach ($sameType as [$label, $v1, $v2]) {
            $r->add(self::FW, 'redbean:fluid', "in-type re-store: $label", function () use ($v1, $v2, $label) {
                self::boot();
                R::freeze(false);
                $t = self::type();
                try {
                    $b = R::dispense($t);
                    $b->p = $v1;
                    R::store($b);
                    $b2 = R::dispense($t);
                    $b2->p = $v2;
                    $id2 = (int) R::store($b2);
                    $got = R::load($t, $id2)->p;
                    Support::assertValueEquals(is_bool($v2) ? ($v2 ? '1' : '0') : (string) $v2, $got, $label);
                    return ['detail' => "back=" . var_export($got, true), 'sql' => "in-type re-store $label"];
                } finally {
                    self::dropTables([$t]);
                }
            });
        }

        // ---- fluid creates many columns from a rich bean --------------------
        $r->add(self::FW, 'redbean:fluid', 'rich bean infers full schema', function () {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $b = R::dispense($t);
                $b->iv = 42;
                $b->fv = 3.14;
                $b->sv = 'hello';
                $b->big = str_repeat('x', 300);
                $b->dt = '2026-01-02 03:04:05';
                $b->bv = true;
                R::store($b);
                $cols = R::getColumns($t);
                foreach (['iv', 'fv', 'sv', 'big', 'dt', 'bv'] as $c) {
                    Support::assert(isset($cols[$c]), "missing inferred column $c: " . json_encode($cols));
                }
                return ['detail' => json_encode($cols), 'sql' => 'fluid rich schema'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- per-type fluid table existence + column presence sweep ---------
        // generate more fluid scenarios: one column per common type each in its
        // own table to broaden coverage.
        $typeCells = [
            'tinyint-bool' => true,
            'int' => 12345,
            'double' => 6.28,
            'decimal' => '3.14',
            'varchar' => 'mo',
            'text' => str_repeat('t', 260),
            'datetime' => '2025-12-31 23:59:59',
            'date' => '2025-12-31',
            'neg-double' => -7,
            'zero' => 0,
            'big-int' => 2000000000,
            'json-as-str' => '{"a":1}',
            'unicode' => 'naïve café',
        ];
        foreach ($typeCells as $label => $val) {
            $r->add(self::FW, 'redbean:fluid', "fluid creates+stores: $label", function () use ($val, $label) {
                self::boot();
                R::freeze(false);
                $t = self::type();
                try {
                    $b = R::dispense($t);
                    $b->c = $val;
                    $id = (int) R::store($b);
                    $cols = R::getColumns($t);
                    Support::assert(isset($cols['c']), "column c not created for $label");
                    $got = R::load($t, $id)->c;
                    return ['detail' => "type=" . $cols['c'] . " back=" . var_export($got, true), 'sql' => "fluid $label"];
                } finally {
                    self::dropTables([$t]);
                }
            });
        }
    }

    // ================================================================ FROZEN

    private static function frozen(Runner $r): void
    {
        // ---- frozen mode rejects an unknown column --------------------------
        $r->add(self::FW, 'redbean:frozen', 'frozen rejects unknown column', function () {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $b = R::dispense($t);
                $b->a = 1;
                R::store($b);
                R::freeze(true);
                $rejected = false;
                $err = '';
                try {
                    $b2 = R::dispense($t);
                    $b2->a = 2;
                    $b2->unknowncol = 'x';
                    R::store($b2);
                } catch (\Throwable $e) {
                    $rejected = true;
                    $err = $e->getMessage();
                }
                Support::assert($rejected, 'frozen mode accepted an unknown column');
                return ['detail' => 'rejected: ' . substr(preg_replace('/\s+/', ' ', $err) ?? $err, 0, 80), 'sql' => 'frozen unknown column'];
            } finally {
                R::freeze(false);
                self::dropTables([$t]);
            }
        });

        // ---- frozen mode: known columns still work --------------------------
        $r->add(self::FW, 'redbean:frozen', 'frozen allows known columns', function () {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $b = R::dispense($t);
                $b->a = 1;
                $b->name = 'seed';
                R::store($b);
                R::freeze(true);
                $b2 = R::dispense($t);
                $b2->a = 2;
                $b2->name = 'second';
                $id = (int) R::store($b2);
                Support::assertEquals('second', R::load($t, $id)->name, 'frozen store of known cols');
                return ['detail' => 'stored in frozen mode', 'sql' => 'frozen known columns'];
            } finally {
                R::freeze(false);
                self::dropTables([$t]);
            }
        });

        // ---- frozen mode does not auto-create a new table -------------------
        $r->add(self::FW, 'redbean:frozen', 'frozen does not create new table', function () {
            self::boot();
            R::freeze(true);
            $t = self::type();
            try {
                $rejected = false;
                try {
                    $b = R::dispense($t);
                    $b->a = 1;
                    R::store($b);
                } catch (\Throwable) {
                    $rejected = true;
                }
                Support::assert($rejected, 'frozen mode created a new table for an unknown type');
                return ['detail' => 'no table auto-created', 'sql' => 'frozen new table'];
            } finally {
                R::freeze(false);
                self::dropTables([$t]);
            }
        });

        // ---- frozen read of existing data works -----------------------------
        $r->add(self::FW, 'redbean:frozen', 'frozen read existing rows', function () {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                foreach (range(1, 3) as $i) {
                    $b = R::dispense($t);
                    $b->v = $i;
                    R::store($b);
                }
                R::freeze(true);
                $n = R::count($t);
                Support::assertEquals('3', (string) $n, 'frozen count');
                $all = R::findAll($t);
                Support::assertEquals('3', (string) count($all), 'frozen findAll');
                return ['detail' => "read $n rows frozen", 'sql' => 'frozen read'];
            } finally {
                R::freeze(false);
                self::dropTables([$t]);
            }
        });
    }

    // ================================================================= QUERY

    private static function query(Runner $r): void
    {
        // A shared dataset builder used by many query scenarios. Each scenario
        // builds its OWN table (unique type) so they stay isolated.

        // ---- predicate / operator sweep over R::find ------------------------
        // dataset: rows with n (int), s (string), p (decimal-ish via string)
        // [where snippet, bindings, expected matching count]
        $rows = [
            ['n' => 1, 's' => 'apple', 'g' => 1],
            ['n' => 2, 's' => 'banana', 'g' => 1],
            ['n' => 3, 's' => 'cherry', 'g' => 2],
            ['n' => 4, 's' => 'date', 'g' => 2],
            ['n' => 5, 's' => 'elderberry', 'g' => 3],
            ['n' => 10, 's' => 'fig', 'g' => 3],
            ['n' => 20, 's' => 'grape', 'g' => 3],
        ];
        $predicates = [
            [' WHERE n = ? ', [3], 1],
            [' WHERE n > ? ', [4], 3],
            [' WHERE n >= ? ', [5], 3],
            [' WHERE n < ? ', [3], 2],
            [' WHERE n <= ? ', [2], 2],
            [' WHERE n <> ? ', [1], 6],
            [' WHERE n BETWEEN ? AND ? ', [2, 5], 4],
            [' WHERE n IN (?, ?, ?) ', [1, 3, 5], 3],
            [' WHERE n NOT IN (?, ?) ', [1, 2], 5],
            [' WHERE s = ? ', ['banana'], 1],
            [' WHERE s LIKE ? ', ['%erry%'], 2],
            [' WHERE s LIKE ? ', ['a%'], 1],
            [' WHERE s LIKE ? ', ['%e'], 2],
            [' WHERE n > ? AND g = ? ', [2, 3], 3],
            [' WHERE n < ? OR n > ? ', [2, 10], 1 + 1],
            [' WHERE g = ? ORDER BY n DESC ', [3], 3],
            [' WHERE n % 2 = 0 ', [], 3],
            [' WHERE LENGTH(s) > ? ', [5], 3],
        ];
        foreach ($predicates as $i => [$where, $binds, $expCount]) {
            $r->add(self::FW, 'redbean:query', "find predicate #$i: " . trim($where), function () use ($rows, $where, $binds, $expCount) {
                self::boot();
                R::freeze(false);
                $t = self::type();
                try {
                    foreach ($rows as $row) {
                        $b = R::dispense($t);
                        foreach ($row as $k => $v) {
                            $b->$k = $v;
                        }
                        R::store($b);
                    }
                    $found = R::find($t, $where, $binds);
                    Support::assertEquals((string) $expCount, (string) count($found), "find $where matched");
                    return ['detail' => 'matched ' . count($found), 'sql' => "find $where"];
                } finally {
                    self::dropTables([$t]);
                }
            });
        }

        // ---- findAll with ORDER / LIMIT / OFFSET ----------------------------
        $orderCases = [
            [' ORDER BY n ASC ', 7, 1, 20],
            [' ORDER BY n DESC ', 7, 20, 1],
            [' ORDER BY n ASC LIMIT 3 ', 3, 1, 3],
            [' ORDER BY n DESC LIMIT 2 ', 2, 20, 10],
            [' ORDER BY n ASC LIMIT 2 OFFSET 1 ', 2, 2, 3],
            [' ORDER BY s ASC LIMIT 1 ', 1, null, null],
        ];
        foreach ($orderCases as $i => [$snip, $expCount, $firstN, $lastN]) {
            $r->add(self::FW, 'redbean:query', "findAll order/limit #$i:" . trim($snip), function () use ($rows, $snip, $expCount, $firstN, $lastN) {
                self::boot();
                R::freeze(false);
                $t = self::type();
                try {
                    foreach ($rows as $row) {
                        $b = R::dispense($t);
                        foreach ($row as $k => $v) {
                            $b->$k = $v;
                        }
                        R::store($b);
                    }
                    $found = array_values(R::findAll($t, $snip));
                    Support::assertEquals((string) $expCount, (string) count($found), "findAll $snip count");
                    if ($firstN !== null && count($found) > 0) {
                        Support::assertEquals((string) $firstN, (string) $found[0]->n, "first row n");
                    }
                    if ($lastN !== null && count($found) > 0) {
                        Support::assertEquals((string) $lastN, (string) end($found)->n, "last row n");
                    }
                    return ['detail' => 'rows=' . count($found), 'sql' => "findAll $snip"];
                } finally {
                    self::dropTables([$t]);
                }
            });
        }

        // ---- findOne / findLast ---------------------------------------------
        $r->add(self::FW, 'redbean:query', 'findOne returns first match', function () use ($rows) {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                foreach ($rows as $row) {
                    $b = R::dispense($t);
                    foreach ($row as $k => $v) {
                        $b->$k = $v;
                    }
                    R::store($b);
                }
                $one = R::findOne($t, ' WHERE g = ? ORDER BY n ASC ', [2]);
                Support::assert($one !== null, 'findOne returned null');
                Support::assertEquals('3', (string) $one->n, 'findOne first n');
                return ['detail' => 'n=' . $one->n, 'sql' => 'findOne'];
            } finally {
                self::dropTables([$t]);
            }
        });

        $r->add(self::FW, 'redbean:query', 'findOne no match returns null', function () use ($rows) {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                foreach ($rows as $row) {
                    $b = R::dispense($t);
                    foreach ($row as $k => $v) {
                        $b->$k = $v;
                    }
                    R::store($b);
                }
                $one = R::findOne($t, ' WHERE n = ? ', [99999]);
                Support::assert($one === null, 'findOne should be null for no match, got ' . var_export($one, true));
                return ['detail' => 'null as expected', 'sql' => 'findOne no match'];
            } finally {
                self::dropTables([$t]);
            }
        });

        $r->add(self::FW, 'redbean:query', 'findLast returns last match', function () use ($rows) {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                foreach ($rows as $row) {
                    $b = R::dispense($t);
                    foreach ($row as $k => $v) {
                        $b->$k = $v;
                    }
                    R::store($b);
                }
                $last = R::findLast($t, ' ORDER BY n ASC ');
                Support::assert($last !== null, 'findLast null');
                Support::assertEquals('20', (string) $last->n, 'findLast n');
                return ['detail' => 'n=' . $last->n, 'sql' => 'findLast'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- R::count with conditions ---------------------------------------
        $countCases = [
            [' WHERE n > ? ', [4], 3],
            [' WHERE g = ? ', [1], 2],
            [' WHERE s LIKE ? ', ['%a%'], 4],
            ['', [], 7],
        ];
        foreach ($countCases as $i => [$where, $binds, $exp]) {
            $r->add(self::FW, 'redbean:query', "count #$i: " . trim($where ?: 'all'), function () use ($rows, $where, $binds, $exp) {
                self::boot();
                R::freeze(false);
                $t = self::type();
                try {
                    foreach ($rows as $row) {
                        $b = R::dispense($t);
                        foreach ($row as $k => $v) {
                            $b->$k = $v;
                        }
                        R::store($b);
                    }
                    $n = R::count($t, $where, $binds);
                    Support::assertEquals((string) $exp, (string) $n, "count $where");
                    return ['detail' => "count=$n", 'sql' => "R::count $where"];
                } finally {
                    self::dropTables([$t]);
                }
            });
        }

        // ---- raw getAll / getRow / getCol / getCell / getAssoc --------------
        $rawCases = [
            ['getAll rows', function (string $t) {
                $rows = R::getAll("SELECT n FROM $t ORDER BY n");
                Support::assertEquals('7', (string) count($rows), 'getAll count');
                Support::assertEquals('1', (string) $rows[0]['n'], 'getAll first');
            }],
            ['getRow single', function (string $t) {
                $row = R::getRow("SELECT n, s FROM $t ORDER BY n LIMIT 1");
                Support::assertEquals('1', (string) $row['n'], 'getRow n');
                Support::assertEquals('apple', $row['s'], 'getRow s');
            }],
            ['getCol column', function (string $t) {
                $col = R::getCol("SELECT n FROM $t ORDER BY n");
                Support::assertEquals('7', (string) count($col), 'getCol count');
                Support::assertEquals('20', (string) end($col), 'getCol last');
            }],
            ['getCell scalar', function (string $t) {
                $c = R::getCell("SELECT COUNT(*) FROM $t");
                Support::assertEquals('7', (string) $c, 'getCell count');
            }],
            ['getCell sum', function (string $t) {
                $c = R::getCell("SELECT SUM(n) FROM $t");
                Support::assertEquals('45', (string) $c, 'getCell sum');
            }],
            ['getAssoc map', function (string $t) {
                $a = R::getAssoc("SELECT n, s FROM $t ORDER BY n");
                Support::assertEquals('apple', $a[1], 'getAssoc[1]');
            }],
            ['getAll aggregate group', function (string $t) {
                $g = R::getAll("SELECT g, COUNT(*) c FROM $t GROUP BY g ORDER BY g");
                Support::assertEquals('3', (string) count($g), 'group count');
            }],
            ['getCell max', function (string $t) {
                Support::assertEquals('20', (string) R::getCell("SELECT MAX(n) FROM $t"), 'max');
            }],
            ['getCell min', function (string $t) {
                Support::assertEquals('1', (string) R::getCell("SELECT MIN(n) FROM $t"), 'min');
            }],
            ['getCell avg', function (string $t) {
                $avg = R::getCell("SELECT AVG(n) FROM $t");
                Support::assert((float) $avg > 6.0 && (float) $avg < 7.0, "avg out of range: $avg");
            }],
        ];
        foreach ($rawCases as [$name, $check]) {
            $r->add(self::FW, 'redbean:query', "raw $name", function () use ($rows, $check, $name) {
                self::boot();
                R::freeze(false);
                $t = self::type();
                try {
                    foreach ($rows as $row) {
                        $b = R::dispense($t);
                        foreach ($row as $k => $v) {
                            $b->$k = $v;
                        }
                        R::store($b);
                    }
                    $check($t);
                    return ['detail' => "ok", 'sql' => "raw $name"];
                } finally {
                    self::dropTables([$t]);
                }
            });
        }

        // ---- parameter binding: positional + named --------------------------
        $bindingCases = [
            ['positional single', "SELECT COUNT(*) FROM %T WHERE n = ?", [3], '1'],
            ['positional multi', "SELECT COUNT(*) FROM %T WHERE n IN (?,?,?)", [1, 2, 3], '3'],
            ['named single', "SELECT COUNT(*) FROM %T WHERE n = :n", [':n' => 5], '1'],
            ['named multi', "SELECT COUNT(*) FROM %T WHERE n > :lo AND n < :hi", [':lo' => 2, ':hi' => 10], '3'],
            ['positional like', "SELECT COUNT(*) FROM %T WHERE s LIKE ?", ['%erry%'], '2'],
            ['named string', "SELECT COUNT(*) FROM %T WHERE s = :s", [':s' => 'fig'], '1'],
        ];
        foreach ($bindingCases as [$name, $tmpl, $binds, $exp]) {
            $r->add(self::FW, 'redbean:query', "binding $name", function () use ($rows, $tmpl, $binds, $exp, $name) {
                self::boot();
                R::freeze(false);
                $t = self::type();
                try {
                    foreach ($rows as $row) {
                        $b = R::dispense($t);
                        foreach ($row as $k => $v) {
                            $b->$k = $v;
                        }
                        R::store($b);
                    }
                    $sql = str_replace('%T', $t, $tmpl);
                    $got = R::getCell($sql, $binds);
                    Support::assertEquals($exp, (string) $got, "$name binding");
                    return ['detail' => "got=$got", 'sql' => $sql];
                } finally {
                    self::dropTables([$t]);
                }
            });
        }

        // ---- R::exec for DML (UPDATE / DELETE / INSERT) ---------------------
        $r->add(self::FW, 'redbean:query', 'exec UPDATE affects rows', function () use ($rows) {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                foreach ($rows as $row) {
                    $b = R::dispense($t);
                    foreach ($row as $k => $v) {
                        $b->$k = $v;
                    }
                    R::store($b);
                }
                R::exec("UPDATE $t SET g = ? WHERE n > ?", [9, 4]);
                $n = R::getCell("SELECT COUNT(*) FROM $t WHERE g = 9");
                Support::assertEquals('3', (string) $n, 'UPDATE affected count');
                return ['detail' => "updated 3", 'sql' => "exec UPDATE"];
            } finally {
                self::dropTables([$t]);
            }
        });

        $r->add(self::FW, 'redbean:query', 'exec DELETE removes rows', function () use ($rows) {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                foreach ($rows as $row) {
                    $b = R::dispense($t);
                    foreach ($row as $k => $v) {
                        $b->$k = $v;
                    }
                    R::store($b);
                }
                R::exec("DELETE FROM $t WHERE n < ?", [3]);
                Support::assertEquals('5', (string) R::count($t), 'after DELETE');
                return ['detail' => 'deleted 2', 'sql' => 'exec DELETE'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- find with snippet binding for IN via genSlots ------------------
        $r->add(self::FW, 'redbean:query', 'find IN via genSlots', function () use ($rows) {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                foreach ($rows as $row) {
                    $b = R::dispense($t);
                    foreach ($row as $k => $v) {
                        $b->$k = $v;
                    }
                    R::store($b);
                }
                $want = [1, 3, 5, 20];
                $slots = R::genSlots($want);
                $found = R::find($t, " WHERE n IN ($slots) ", $want);
                Support::assertEquals('4', (string) count($found), 'IN genSlots count');
                return ['detail' => "slots=$slots matched " . count($found), 'sql' => "find IN ($slots)"];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- aggregate predicate sweep --------------------------------------
        $aggCases = [
            ['SUM', "SELECT SUM(n) FROM %T", '45'],
            ['COUNT distinct g', "SELECT COUNT(DISTINCT g) FROM %T", '3'],
            ['MAX', "SELECT MAX(n) FROM %T", '20'],
            ['MIN', "SELECT MIN(n) FROM %T", '1'],
            ['COUNT *', "SELECT COUNT(*) FROM %T", '7'],
            ['SUM where', "SELECT SUM(n) FROM %T WHERE g = 3", '35'],
            ['COUNT where like', "SELECT COUNT(*) FROM %T WHERE s LIKE '%e%'", '4'],
            ['GROUP_CONCAT', "SELECT COUNT(*) FROM (SELECT g FROM %T GROUP BY g) x", '3'],
        ];
        foreach ($aggCases as [$name, $tmpl, $exp]) {
            $r->add(self::FW, 'redbean:query', "agg $name", function () use ($rows, $tmpl, $exp, $name) {
                self::boot();
                R::freeze(false);
                $t = self::type();
                try {
                    foreach ($rows as $row) {
                        $b = R::dispense($t);
                        foreach ($row as $k => $v) {
                            $b->$k = $v;
                        }
                        R::store($b);
                    }
                    $sql = str_replace('%T', $t, $tmpl);
                    $got = R::getCell($sql);
                    Support::assertEquals($exp, (string) $got, "$name");
                    return ['detail' => "got=$got", 'sql' => $sql];
                } finally {
                    self::dropTables([$t]);
                }
            });
        }

        // ---- findAndExport --------------------------------------------------
        $r->add(self::FW, 'redbean:query', 'findAndExport returns arrays', function () use ($rows) {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                foreach ($rows as $row) {
                    $b = R::dispense($t);
                    foreach ($row as $k => $v) {
                        $b->$k = $v;
                    }
                    R::store($b);
                }
                $arr = R::findAndExport($t, ' WHERE g = ? ', [1]);
                Support::assertEquals('2', (string) count($arr), 'findAndExport count');
                $first = reset($arr);
                Support::assert(is_array($first) && isset($first['n']), 'findAndExport not array rows');
                return ['detail' => 'exported ' . count($arr), 'sql' => 'findAndExport'];
            } finally {
                self::dropTables([$t]);
            }
        });
    }

    // ================================================================ FINDER

    private static function finder(Runner $r): void
    {
        $seed = function (string $t) {
            foreach ([['c' => 'red', 'n' => 1], ['c' => 'green', 'n' => 2], ['c' => 'blue', 'n' => 3], ['c' => 'red', 'n' => 4]] as $row) {
                $b = R::dispense($t);
                foreach ($row as $k => $v) {
                    $b->$k = $v;
                }
                R::store($b);
            }
        };

        // ---- findLike --------------------------------------------------------
        $likeCases = [
            ['single match', ['c' => 'green'], 1],
            ['multi match', ['c' => 'red'], 2],
            ['no match', ['c' => 'purple'], 0],
            ['by number', ['n' => 3], 1],
            ['multi field', ['c' => 'red', 'n' => 1], 1],
            ['IN list', ['c' => ['red', 'blue']], 3],
        ];
        foreach ($likeCases as [$name, $like, $exp]) {
            $r->add(self::FW, 'redbean:finder', "findLike $name", function () use ($seed, $like, $exp, $name) {
                self::boot();
                R::freeze(false);
                $t = self::type();
                try {
                    $seed($t);
                    $found = R::findLike($t, $like);
                    Support::assertEquals((string) $exp, (string) count($found), "findLike $name");
                    return ['detail' => 'matched ' . count($found), 'sql' => 'findLike ' . json_encode($like)];
                } finally {
                    self::dropTables([$t]);
                }
            });
        }

        // ---- findOrCreate ---------------------------------------------------
        $r->add(self::FW, 'redbean:finder', 'findOrCreate finds existing', function () use ($seed) {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $seed($t);
                $before = R::count($t);
                $bean = R::findOrCreate($t, ['c' => 'green']);
                Support::assert((int) $bean->id > 0, 'findOrCreate did not return persisted bean');
                Support::assertEquals((string) $before, (string) R::count($t), 'findOrCreate should not add a row');
                return ['detail' => "found id=" . $bean->id, 'sql' => 'findOrCreate existing'];
            } finally {
                self::dropTables([$t]);
            }
        });

        $r->add(self::FW, 'redbean:finder', 'findOrCreate creates missing', function () use ($seed) {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $seed($t);
                $before = R::count($t);
                $bean = R::findOrCreate($t, ['c' => 'yellow', 'n' => 9]);
                Support::assert((int) $bean->id > 0, 'findOrCreate did not create bean');
                Support::assertEquals((string) ($before + 1), (string) R::count($t), 'findOrCreate should add a row');
                Support::assertEquals('yellow', $bean->c, 'created bean field');
                return ['detail' => "created id=" . $bean->id, 'sql' => 'findOrCreate missing'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- findOrDispense -------------------------------------------------
        $r->add(self::FW, 'redbean:finder', 'findOrDispense finds existing', function () use ($seed) {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $seed($t);
                $beans = R::findOrDispense($t, ' WHERE c = ? ', ['blue']);
                Support::assertEquals('1', (string) count($beans), 'findOrDispense existing count');
                return ['detail' => 'found 1', 'sql' => 'findOrDispense existing'];
            } finally {
                self::dropTables([$t]);
            }
        });

        $r->add(self::FW, 'redbean:finder', 'findOrDispense dispenses fresh', function () use ($seed) {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $seed($t);
                $beans = R::findOrDispense($t, ' WHERE c = ? ', ['nonexistent-color']);
                Support::assertEquals('1', (string) count($beans), 'findOrDispense fresh count');
                $bean = reset($beans);
                Support::assert((int) $bean->id === 0, 'dispensed bean should have id 0');
                return ['detail' => 'dispensed fresh', 'sql' => 'findOrDispense fresh'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- batch / loadAll ------------------------------------------------
        $r->add(self::FW, 'redbean:finder', 'batch loads by ids', function () use ($seed) {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $seed($t);
                $ids = R::getCol("SELECT id FROM $t ORDER BY id LIMIT 2");
                $beans = R::batch($t, array_map('intval', $ids));
                Support::assertEquals('2', (string) count($beans), 'batch count');
                return ['detail' => 'batched ' . count($beans), 'sql' => 'R::batch'];
            } finally {
                self::dropTables([$t]);
            }
        });

        $r->add(self::FW, 'redbean:finder', 'loadAll loads by ids', function () use ($seed) {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $seed($t);
                $ids = array_map('intval', R::getCol("SELECT id FROM $t ORDER BY id"));
                $beans = R::loadAll($t, $ids);
                Support::assertEquals((string) count($ids), (string) count($beans), 'loadAll count');
                return ['detail' => 'loaded ' . count($beans), 'sql' => 'R::loadAll'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- findMulti ------------------------------------------------------
        $r->add(self::FW, 'redbean:finder', 'findMulti returns type-keyed map', function () use ($seed) {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $seed($t);
                $multi = R::findMulti($t, "SELECT * FROM $t");
                Support::assert(array_key_exists($t, $multi), 'findMulti missing type key: ' . json_encode(array_keys($multi)));
                return ['detail' => 'keys=' . json_encode(array_keys($multi)), 'sql' => 'R::findMulti'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- find with empty result -----------------------------------------
        $r->add(self::FW, 'redbean:finder', 'find returns empty array on no match', function () use ($seed) {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $seed($t);
                $found = R::find($t, ' WHERE c = ? ', ['none']);
                Support::assert(is_array($found) && count($found) === 0, 'expected empty array');
                return ['detail' => 'empty', 'sql' => 'find no match'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- count helper ---------------------------------------------------
        $r->add(self::FW, 'redbean:finder', 'count after finder seed', function () use ($seed) {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $seed($t);
                Support::assertEquals('4', (string) R::count($t), 'seed count');
                return ['detail' => 'count=4', 'sql' => 'R::count'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- findLike with empty array (returns all) ------------------------
        $r->add(self::FW, 'redbean:finder', 'findLike empty returns all', function () use ($seed) {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $seed($t);
                $found = R::findLike($t, []);
                Support::assertEquals('4', (string) count($found), 'findLike all count');
                return ['detail' => 'all ' . count($found), 'sql' => 'findLike []'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- findLike with ordering SQL ------------------------------------
        $r->add(self::FW, 'redbean:finder', 'findLike with ORDER snippet', function () use ($seed) {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $seed($t);
                $found = array_values(R::findLike($t, ['c' => 'red'], ' ORDER BY n DESC '));
                Support::assertEquals('2', (string) count($found), 'findLike ordered count');
                Support::assertEquals('4', (string) $found[0]->n, 'findLike order first');
                return ['detail' => 'ordered', 'sql' => 'findLike + ORDER'];
            } finally {
                self::dropTables([$t]);
            }
        });
    }

    // ============================================================== RELATION

    private static function relation(Runner $r): void
    {
        // ---- one-to-many ownList: build, reload, traverse, count ------------
        for ($i = 0; $i < 14; $i++) {
            $childCount = ($i % 4) + 1;
            $r->add(self::FW, 'redbean:relation', "one-to-many ownList ($childCount children) #$i", function () use ($childCount) {
                self::boot();
                R::freeze(false);
                $tu = self::type('rbu');
                $tp = self::type('rbo');
                $own = 'own' . ucfirst($tp) . 'List';
                try {
                    $u = R::dispense($tu);
                    $u->name = 'parent';
                    for ($c = 1; $c <= $childCount; $c++) {
                        $p = R::dispense($tp);
                        $p->title = "child$c";
                        $u->{$own}[] = $p;
                    }
                    $uid = (int) R::store($u);
                    $re = R::load($tu, $uid);
                    $list = $re->{$own};
                    Support::assertEquals((string) $childCount, (string) count($list), "owned children count");
                    // child carries a foreign key column <parent>_id
                    $childCols = R::getColumns($tp);
                    Support::assert(isset($childCols[$tu . '_id']), "child missing FK column {$tu}_id: " . json_encode($childCols));
                    return ['detail' => "children=$childCount fk={$tu}_id", 'sql' => 'ownList store+reload'];
                } finally {
                    self::dropTables([$tp, $tu]);
                }
            });
        }

        // ---- ownList: add child after, removing child ----------------------
        $r->add(self::FW, 'redbean:relation', 'ownList add child to existing parent', function () {
            self::boot();
            R::freeze(false);
            $tu = self::type('rbu');
            $tp = self::type('rbo');
            $own = 'own' . ucfirst($tp) . 'List';
            try {
                $u = R::dispense($tu);
                $u->name = 'p';
                $p1 = R::dispense($tp);
                $p1->title = 'a';
                $u->{$own}[] = $p1;
                $uid = (int) R::store($u);
                // reload, add another
                $re = R::load($tu, $uid);
                $p2 = R::dispense($tp);
                $p2->title = 'b';
                $re->{$own}[] = $p2;
                R::store($re);
                $re2 = R::load($tu, $uid);
                Support::assertEquals('2', (string) count($re2->{$own}), 'children after add');
                return ['detail' => '2 children', 'sql' => 'ownList incremental add'];
            } finally {
                self::dropTables([$tp, $tu]);
            }
        });

        $r->add(self::FW, 'redbean:relation', 'ownList trash parent removes children rows', function () {
            self::boot();
            R::freeze(false);
            $tu = self::type('rbu');
            $tp = self::type('rbo');
            $own = 'own' . ucfirst($tp) . 'List';
            try {
                $u = R::dispense($tu);
                $u->name = 'p';
                foreach (['a', 'b', 'c'] as $title) {
                    $p = R::dispense($tp);
                    $p->title = $title;
                    $u->{$own}[] = $p;
                }
                $uid = (int) R::store($u);
                // exclusive ownership: trashing parent should remove children
                R::trash(R::load($tu, $uid));
                $remainingChildren = R::count($tp);
                return ['detail' => "children left after parent trash=$remainingChildren", 'sql' => 'ownList cascade trash'];
            } finally {
                self::dropTables([$tp, $tu]);
            }
        });

        // ---- many-to-one parent bean ---------------------------------------
        for ($i = 0; $i < 12; $i++) {
            $r->add(self::FW, 'redbean:relation', "many-to-one parent bean #$i", function () use ($i) {
                self::boot();
                R::freeze(false);
                $tc = self::type('rbc');
                $td = self::type('rbd');
                try {
                    $cat = R::dispense($tc);
                    $cat->name = "cat$i";
                    $art = R::dispense($td);
                    $art->title = "art$i";
                    $art->{$tc} = $cat;
                    $aid = (int) R::store($art);
                    $re = R::load($td, $aid);
                    // child carries FK col
                    $cols = R::getColumns($td);
                    Support::assert(isset($cols[$tc . '_id']), "missing FK {$tc}_id: " . json_encode($cols));
                    $parent = $re->{$tc};
                    Support::assert($parent !== null && (int) $parent->id > 0, 'parent bean not resolvable');
                    Support::assertEquals("cat$i", $parent->name, 'parent name traversal');
                    return ['detail' => "parent=cat$i", 'sql' => 'parent bean store+traverse'];
                } finally {
                    self::dropTables([$td, $tc]);
                }
            });
        }

        // ---- many-to-one: multiple children share one parent ----------------
        $r->add(self::FW, 'redbean:relation', 'many children share one parent', function () {
            self::boot();
            R::freeze(false);
            $tc = self::type('rbc');
            $td = self::type('rbd');
            try {
                $cat = R::dispense($tc);
                $cat->name = 'shared';
                R::store($cat);
                $cat = R::load($tc, (int) $cat->id);
                foreach (['x', 'y', 'z'] as $title) {
                    $art = R::dispense($td);
                    $art->title = $title;
                    $art->{$tc} = $cat;
                    R::store($art);
                }
                $n = R::count($td, " WHERE {$tc}_id = ? ", [(int) $cat->id]);
                Support::assertEquals('3', (string) $n, 'children sharing parent');
                return ['detail' => '3 children share parent', 'sql' => 'shared parent'];
            } finally {
                self::dropTables([$td, $tc]);
            }
        });

        // ---- many-to-many sharedList (link table) ---------------------------
        for ($i = 0; $i < 14; $i++) {
            $tagCount = ($i % 3) + 1;
            $r->add(self::FW, 'redbean:relation', "many-to-many sharedList ($tagCount tags) #$i", function () use ($tagCount) {
                self::boot();
                R::freeze(false);
                $tb = self::type('rbb');
                $tg = self::type('rbg');
                $shared = 'shared' . ucfirst($tg) . 'List';
                $link = self::linkTable($tb, $tg);
                try {
                    $book = R::dispense($tb);
                    $book->title = 'book';
                    for ($c = 1; $c <= $tagCount; $c++) {
                        $tag = R::dispense($tg);
                        $tag->label = "tag$c";
                        $book->{$shared}[] = $tag;
                    }
                    $bid = (int) R::store($book);
                    $re = R::load($tb, $bid);
                    $list = $re->{$shared};
                    Support::assertEquals((string) $tagCount, (string) count($list), 'shared tags count');
                    // link table must exist
                    Support::assert(in_array($link, R::inspect(), true), "link table $link not created: " . json_encode(R::inspect()));
                    return ['detail' => "tags=$tagCount link=$link", 'sql' => 'sharedList store+reload'];
                } finally {
                    self::dropTables([$link, $tb, $tg]);
                }
            });
        }

        // ---- sharedList shared between two owners ---------------------------
        $r->add(self::FW, 'redbean:relation', 'sharedList tag reused across books', function () {
            self::boot();
            R::freeze(false);
            $tb = self::type('rbb');
            $tg = self::type('rbg');
            $shared = 'shared' . ucfirst($tg) . 'List';
            $link = self::linkTable($tb, $tg);
            try {
                $tag = R::dispense($tg);
                $tag->label = 'common';
                $book1 = R::dispense($tb);
                $book1->title = 'one';
                $book1->{$shared}[] = $tag;
                $b1 = (int) R::store($book1);
                $book2 = R::dispense($tb);
                $book2->title = 'two';
                // reuse same tag bean (reload to be safe)
                $book2->{$shared}[] = R::load($tg, (int) $tag->id);
                $b2 = (int) R::store($book2);
                Support::assertEquals('1', (string) R::count($tg), 'tag should be shared, not duplicated');
                Support::assertEquals('1', (string) count(R::load($tb, $b1)->{$shared}), 'book1 sees tag');
                Support::assertEquals('1', (string) count(R::load($tb, $b2)->{$shared}), 'book2 sees tag');
                return ['detail' => 'tag shared by 2 books', 'sql' => 'sharedList reuse'];
            } finally {
                self::dropTables([$link, $tb, $tg]);
            }
        });

        // ---- sharedList unlink ---------------------------------------------
        $r->add(self::FW, 'redbean:relation', 'sharedList remove one tag', function () {
            self::boot();
            R::freeze(false);
            $tb = self::type('rbb');
            $tg = self::type('rbg');
            $shared = 'shared' . ucfirst($tg) . 'List';
            $link = self::linkTable($tb, $tg);
            try {
                $book = R::dispense($tb);
                $book->title = 'b';
                $t1 = R::dispense($tg);
                $t1->label = 'one';
                $t2 = R::dispense($tg);
                $t2->label = 'two';
                $book->{$shared}[] = $t1;
                $book->{$shared}[] = $t2;
                $bid = (int) R::store($book);
                $re = R::load($tb, $bid);
                // remove first
                $list = $re->{$shared};
                $firstKey = array_key_first($list);
                unset($re->{$shared}[$firstKey]);
                R::store($re);
                $re2 = R::load($tb, $bid);
                Support::assertEquals('1', (string) count($re2->{$shared}), 'one tag should remain');
                return ['detail' => '1 tag remains', 'sql' => 'sharedList unlink'];
            } finally {
                self::dropTables([$link, $tb, $tg]);
            }
        });

        // ---- nested ownList graph -------------------------------------------
        $r->add(self::FW, 'redbean:relation', 'nested ownList graph (3 levels)', function () {
            self::boot();
            R::freeze(false);
            $ta = self::type('rba');
            $tbb = self::type('rbbb');
            $tcc = self::type('rbcc');
            $ownB = 'own' . ucfirst($tbb) . 'List';
            $ownC = 'own' . ucfirst($tcc) . 'List';
            try {
                $a = R::dispense($ta);
                $a->name = 'root';
                $b = R::dispense($tbb);
                $b->name = 'mid';
                $c = R::dispense($tcc);
                $c->name = 'leaf';
                $b->{$ownC}[] = $c;
                $a->{$ownB}[] = $b;
                $aid = (int) R::store($a);
                $re = R::load($ta, $aid);
                $mids = $re->{$ownB};
                Support::assertEquals('1', (string) count($mids), 'mid count');
                $mid = reset($mids);
                Support::assertEquals('1', (string) count($mid->{$ownC}), 'leaf count');
                return ['detail' => '3-level graph', 'sql' => 'nested ownList'];
            } finally {
                self::dropTables([$tcc, $tbb, $ta]);
            }
        });

        // ---- combined ownList + parent round trip ---------------------------
        for ($i = 0; $i < 6; $i++) {
            $r->add(self::FW, 'redbean:relation', "ownList count assertion variant #$i", function () use ($i) {
                self::boot();
                R::freeze(false);
                $tu = self::type('rbu');
                $tp = self::type('rbo');
                $own = 'own' . ucfirst($tp) . 'List';
                $n = $i + 2;
                try {
                    $u = R::dispense($tu);
                    $u->name = 'p';
                    for ($c = 0; $c < $n; $c++) {
                        $p = R::dispense($tp);
                        $p->seq = $c;
                        $u->{$own}[] = $p;
                    }
                    R::store($u);
                    Support::assertEquals((string) $n, (string) R::count($tp), "child rows count");
                    return ['detail' => "children=$n", 'sql' => 'ownList variant'];
                } finally {
                    self::dropTables([$tp, $tu]);
                }
            });
        }
    }

    // ================================================================== TYPE

    private static function typeRoundTrips(Runner $r): void
    {
        // [label, value, expected-string-back, expected-type-substring|null]
        $cells = [
            ['int positive', 12345, '12345', 'INT UNSIGNED'],
            ['int zero', 0, '0', 'TINYINT'],
            ['int boundary 255', 255, '255', 'INT UNSIGNED'],
            ['int boundary 256', 256, '256', 'INT UNSIGNED'],
            ['int 65535', 65535, '65535', 'INT UNSIGNED'],
            ['int big 2e9', 2000000000, '2000000000', 'INT UNSIGNED'],
            ['negative int', -1, '-1', 'DOUBLE'],
            ['negative large', -123456, '-123456', 'DOUBLE'],
            ['float simple', 1.5, '1.5', 'DOUBLE'],
            ['float repeating', 2.75, '2.75', 'DOUBLE'],
            ['decimal as string 2dp', '19.99', '19.99', 'DECIMAL'],
            ['decimal as string money', '1234.56', '1234.56', 'DECIMAL'],
            ['short varchar', 'short text', 'short text', 'VARCHAR'],
            ['exactly 191 chars', str_repeat('a', 191), str_repeat('a', 191), 'VARCHAR'],
            ['long text 192', str_repeat('b', 192), str_repeat('b', 192), 'TEXT'],
            ['long text 1000', str_repeat('c', 1000), str_repeat('c', 1000), 'TEXT'],
            ['datetime', '2026-06-23 14:05:09', '2026-06-23 14:05:09', 'DATETIME'],
            ['datetime midnight', '2026-01-01 00:00:00', '2026-01-01 00:00:00', 'DATETIME'],
            ['date only', '2026-06-23', '2026-06-23', null],
            ['bool true', true, '1', 'TINYINT'],
            ['bool false', false, '0', 'TINYINT'],
            ['json object string', '{"a":1,"b":[2,3]}', '{"a":1,"b":[2,3]}', null],
            ['json array string', '[1,2,3]', '[1,2,3]', null],
            ['unicode accents', 'naïve café résumé', 'naïve café résumé', null],
            ['emoji', 'bee 🐝 honey 🍯', 'bee 🐝 honey 🍯', null],
            ['numeric leading zero', '007', '7', null],
            ['whitespace string', '  spaced  ', '  spaced  ', null],
            ['newline string', "line1\nline2", "line1\nline2", null],
            ['tab string', "a\tb", "a\tb", null],
            ['quote string', "it's \"quoted\"", "it's \"quoted\"", null],
            ['percent string', '100%', '100%', null],
            ['negative decimal string', '-3.50', '-3.50', 'DECIMAL'],
        ];
        foreach ($cells as [$label, $val, $exp, $expType]) {
            $r->add(self::FW, 'redbean:type', "type round-trip: $label", function () use ($val, $exp, $expType, $label) {
                self::boot();
                R::freeze(false);
                $t = self::type();
                try {
                    $b = R::dispense($t);
                    $b->c = $val;
                    $id = (int) R::store($b);
                    $cols = R::getColumns($t);
                    $got = R::load($t, $id)->c;
                    Support::assertValueEquals($exp, $got, "$label value");
                    if ($expType !== null) {
                        Support::assert(
                            stripos((string) $cols['c'], $expType) !== false,
                            "$label expected col type ~'$expType', got '" . $cols['c'] . "'"
                        );
                    }
                    return ['detail' => "col=" . $cols['c'] . " back=" . var_export($got, true), 'sql' => "type $label"];
                } finally {
                    self::dropTables([$t]);
                }
            });
        }

        // ---- multiple types in one bean -------------------------------------
        $r->add(self::FW, 'redbean:type', 'mixed-type bean round-trips', function () {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $b = R::dispense($t);
                $b->i = 42;
                $b->f = 3.5;
                $b->money = '99.99';
                $b->s = 'hello';
                $b->dt = '2026-06-23 12:00:00';
                $b->flag = true;
                $id = (int) R::store($b);
                $re = R::load($t, $id);
                Support::assertEquals('42', (string) $re->i, 'i');
                Support::assertValueEquals('3.5', $re->f, 'f');
                Support::assertEquals('99.99', (string) $re->money, 'money');
                Support::assertEquals('hello', $re->s, 's');
                Support::assertEquals('2026-06-23 12:00:00', $re->dt, 'dt');
                Support::assertEquals('1', (string) $re->flag, 'flag');
                return ['detail' => 'mixed ok', 'sql' => 'mixed-type bean'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- NULL property round-trip ---------------------------------------
        $r->add(self::FW, 'redbean:type', 'NULL property round-trips as null', function () {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $b = R::dispense($t);
                $b->a = 1;
                $b->n = null;
                $id = (int) R::store($b);
                $re = R::load($t, $id);
                Support::assert($re->n === null, 'null prop should read back null, got ' . var_export($re->n, true));
                return ['detail' => 'null preserved', 'sql' => 'null prop'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- updating a null to a value and back ----------------------------
        $r->add(self::FW, 'redbean:type', 'null then value then null', function () {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $b = R::dispense($t);
                $b->a = 1;
                $b->v = 'present';
                $id = (int) R::store($b);
                $re = R::load($t, $id);
                $re->v = null;
                R::store($re);
                Support::assert(R::load($t, $id)->v === null, 'value not nulled');
                return ['detail' => 'null<->value cycle', 'sql' => 'null update'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- large integer precision check ----------------------------------
        $r->add(self::FW, 'redbean:type', 'integer stored as string preserves big digits', function () {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $big = '9007199254740993'; // beyond float precision
                $b = R::dispense($t);
                $b->v = $big;
                $id = (int) R::store($b);
                $got = R::load($t, $id)->v;
                return ['detail' => "stored=$big back=" . var_export($got, true) . " col=" . R::getColumns($t)['v'], 'sql' => 'big int as string'];
            } finally {
                self::dropTables([$t]);
            }
        });
    }

    // =========================================================== TRANSACTION

    private static function transaction(Runner $r): void
    {
        // NOTE: probed behaviour — R::transaction(closure) does NOT commit in
        // FLUID mode (returns false, work lost) but DOES in FROZEN mode. So
        // transaction scenarios seed+create the table first, then freeze.

        // ---- begin/commit persists -----------------------------------------
        $r->add(self::FW, 'redbean:transaction', 'begin+commit persists', function () {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $seed = R::dispense($t);
                $seed->v = 1;
                R::store($seed); // create table in fluid
                R::begin();
                $a = R::dispense($t);
                $a->v = 2;
                R::store($a);
                R::commit();
                Support::assertEquals('2', (string) R::count($t), 'count after commit');
                return ['detail' => 'committed', 'sql' => 'begin/commit'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- begin/rollback discards ----------------------------------------
        $r->add(self::FW, 'redbean:transaction', 'begin+rollback discards', function () {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $seed = R::dispense($t);
                $seed->v = 1;
                R::store($seed);
                R::begin();
                $a = R::dispense($t);
                $a->v = 2;
                R::store($a);
                R::rollback();
                Support::assertEquals('1', (string) R::count($t), 'count after rollback');
                return ['detail' => 'rolled back', 'sql' => 'begin/rollback'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- begin/commit/rollback cycle several times ----------------------
        for ($i = 0; $i < 6; $i++) {
            $commit = ($i % 2) === 0;
            $r->add(self::FW, 'redbean:transaction', "explicit tx " . ($commit ? 'commit' : 'rollback') . " #$i", function () use ($commit) {
                self::boot();
                R::freeze(false);
                $t = self::type();
                try {
                    $seed = R::dispense($t);
                    $seed->v = 0;
                    R::store($seed);
                    R::begin();
                    foreach (range(1, 3) as $k) {
                        $b = R::dispense($t);
                        $b->v = $k;
                        R::store($b);
                    }
                    if ($commit) {
                        R::commit();
                        Support::assertEquals('4', (string) R::count($t), 'after commit');
                    } else {
                        R::rollback();
                        Support::assertEquals('1', (string) R::count($t), 'after rollback');
                    }
                    return ['detail' => $commit ? 'committed 3' : 'rolled back 3', 'sql' => 'explicit tx'];
                } finally {
                    self::dropTables([$t]);
                }
            });
        }

        // ---- R::transaction(closure) commits in frozen mode -----------------
        $r->add(self::FW, 'redbean:transaction', 'R::transaction closure commits (frozen)', function () {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $seed = R::dispense($t);
                $seed->v = 1;
                R::store($seed);
                R::freeze(true);
                R::transaction(function () use ($t) {
                    $a = R::dispense($t);
                    $a->v = 2;
                    R::store($a);
                });
                Support::assertEquals('2', (string) R::count($t), 'transaction closure should commit in frozen mode');
                return ['detail' => 'closure committed', 'sql' => 'R::transaction (frozen)'];
            } finally {
                R::freeze(false);
                self::dropTables([$t]);
            }
        });

        // ---- R::transaction rolls back on exception -------------------------
        $r->add(self::FW, 'redbean:transaction', 'R::transaction rolls back on exception', function () {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $seed = R::dispense($t);
                $seed->v = 1;
                R::store($seed);
                R::freeze(true);
                $threw = false;
                try {
                    R::transaction(function () use ($t) {
                        $a = R::dispense($t);
                        $a->v = 2;
                        R::store($a);
                        throw new \RuntimeException('boom');
                    });
                } catch (\Throwable) {
                    $threw = true;
                }
                Support::assert($threw, 'transaction should rethrow the exception');
                Support::assertEquals('1', (string) R::count($t), 'failed transaction should roll back');
                return ['detail' => 'rolled back on exception', 'sql' => 'R::transaction throw'];
            } finally {
                R::freeze(false);
                self::dropTables([$t]);
            }
        });

        // ---- R::transaction in FLUID mode: documented finding ---------------
        // Probed: fluid transaction closure returns false and does NOT persist.
        $r->add(self::FW, 'redbean:transaction', 'R::transaction in fluid mode does not commit (finding)', function () {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                $seed = R::dispense($t);
                $seed->v = 1;
                R::store($seed);
                // stays in fluid mode
                R::transaction(function () use ($t) {
                    $a = R::dispense($t);
                    $a->v = 2;
                    R::store($a);
                });
                $count = (int) R::count($t);
                // Document the probed behaviour: the closure's work is lost in fluid mode.
                Support::assertEquals('1', (string) $count, 'fluid transaction unexpectedly committed (probed behaviour was no-commit)');
                return ['detail' => 'fluid tx did not commit (count=1) — RedBean/MatrixOne finding', 'sql' => 'R::transaction (fluid)'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- rollback isolation: data before tx survives --------------------
        $r->add(self::FW, 'redbean:transaction', 'rollback preserves pre-tx data', function () {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                foreach (range(1, 3) as $k) {
                    $b = R::dispense($t);
                    $b->v = $k;
                    R::store($b);
                }
                R::begin();
                R::exec("DELETE FROM $t");
                R::rollback();
                Support::assertEquals('3', (string) R::count($t), 'pre-tx rows should survive rollback');
                return ['detail' => '3 rows survive', 'sql' => 'rollback DELETE'];
            } finally {
                self::dropTables([$t]);
            }
        });

        // ---- commit of DML via R::exec inside tx ----------------------------
        $r->add(self::FW, 'redbean:transaction', 'commit persists exec DML', function () {
            self::boot();
            R::freeze(false);
            $t = self::type();
            try {
                foreach (range(1, 3) as $k) {
                    $b = R::dispense($t);
                    $b->v = $k;
                    R::store($b);
                }
                R::begin();
                R::exec("UPDATE $t SET v = v + 10");
                R::commit();
                $sum = R::getCell("SELECT SUM(v) FROM $t");
                Support::assertEquals('36', (string) $sum, 'committed update sum (6+30)');
                return ['detail' => "sum=$sum", 'sql' => 'commit UPDATE'];
            } finally {
                self::dropTables([$t]);
            }
        });
    }
}
