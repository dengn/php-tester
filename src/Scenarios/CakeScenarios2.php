<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use Cake\Database\Connection;
use MoTest\Connections;
use MoTest\Runner;
use MoTest\Support;

/**
 * Second wave of CakePHP database-layer scenarios — deeper query builder
 * coverage, expressions/function builder, type round-trips, schema reflection
 * and transaction behaviour. These exercise corners of cakephp/database that
 * the first CakeScenarios suite does not touch, surfacing MatrixOne gaps.
 *
 * Categories: query-builder2, function2, expression, type2, schema2,
 * transaction2.
 */
final class CakeScenarios2
{
    public static function register(Runner $r): void
    {
        self::queryBuilder($r);
        self::functions($r);
        self::expressions($r);
        self::types($r);
        self::schema($r);
        self::transactions($r);
    }

    /**
     * Create a uniquely-named, seeded table, run the closure with the live
     * connection + table name, and always drop the table afterwards.
     */
    private static function withTable(Runner $r, string $cat, string $name, callable $fn): void
    {
        $r->add('CakePHP', $cat, $name, function () use ($fn) {
            $conn = Connections::cake();
            $tn = Support::name('ck2');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50), age INT, city VARCHAR(50), salary DECIMAL(10,2))");
            $conn->execute("INSERT INTO `$tn` (name,age,city,salary) VALUES "
                . "('alice',30,'NY',5000.00),"
                . "('bob',25,'LA',3000.50),"
                . "('carol',40,'NY',7000.25),"
                . "('dave',35,'SF',4500.75),"
                . "('eve',28,'LA',3500.00)");
            try {
                return $fn($conn, $tn);
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });
    }

    // ------------------------------------------------------------------ //
    //  query-builder2                                                    //
    // ------------------------------------------------------------------ //

    private static function queryBuilder(Runner $r): void
    {
        $cat = 'query-builder2';

        self::withTable($r, $cat, 'where age >=', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name'])->from($t)->where(['age >=' => 35])->all();
            Support::assertEquals(2, count($rows), 'age >= count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'where age <', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name'])->from($t)->where(['age <' => 30])->all();
            Support::assertEquals(2, count($rows), 'age < count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'where name LIKE', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name'])->from($t)->where(['name LIKE' => '%a%'])->all();
            Support::assertEquals(3, count($rows), 'LIKE count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'where name NOT LIKE', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name'])->from($t)->where(['name NOT LIKE' => '%a%'])->all();
            Support::assertEquals(2, count($rows), 'NOT LIKE count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'where name IN array', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name'])->from($t)
                ->where(['name IN' => ['alice', 'bob', 'zzz']])->all();
            Support::assertEquals(2, count($rows), 'IN count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'where name NOT IN array', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name'])->from($t)
                ->where(['name NOT IN' => ['alice', 'bob']])->all();
            Support::assertEquals(3, count($rows), 'NOT IN count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'where age BETWEEN (expr builder)', function (Connection $conn, string $t) {
            // The database-layer where() parser does not accept the array
            // `'age BETWEEN' => [x, y]` operator form (it stringifies the array);
            // the supported idiom is the QueryExpression between() builder.
            $rows = $conn->selectQuery()->select(['name'])->from($t)
                ->where(function ($exp) {
                    return $exp->between('age', 28, 35);
                })->all();
            Support::assertEquals(3, count($rows), 'BETWEEN count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'where col IS NULL (no match)', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name'])->from($t)->where(['city IS' => null])->all();
            Support::assertEquals(0, count($rows), 'IS NULL count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'where col != value', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name'])->from($t)->where(['city !=' => 'NY'])->all();
            Support::assertEquals(3, count($rows), '!= count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'where OR via array nesting', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name'])->from($t)
                ->where(['OR' => [['city' => 'SF'], ['age >' => 38]]])->all();
            Support::assertEquals(2, count($rows), 'OR count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'where AND via array nesting', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name'])->from($t)
                ->where(['AND' => [['city' => 'NY'], ['age >' => 35]]])->all();
            Support::assertEquals(1, count($rows), 'AND count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'andWhere chaining', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name'])->from($t)
                ->where(['city' => 'LA'])->andWhere(['age >' => 26])->all();
            Support::assertEquals(1, count($rows), 'andWhere count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'orWhere via OR nesting', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name'])->from($t)
                ->where(['OR' => [['name' => 'alice'], ['name' => 'bob'], ['name' => 'eve']]])->all();
            Support::assertEquals(3, count($rows), 'OR list count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'whereNull', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name'])->from($t)->whereNull(['city'])->all();
            Support::assertEquals(0, count($rows), 'whereNull count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'whereNotNull', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name'])->from($t)->whereNotNull(['city'])->all();
            Support::assertEquals(5, count($rows), 'whereNotNull count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'whereInList', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name'])->from($t)
                ->whereInList('city', ['NY', 'SF'])->all();
            Support::assertEquals(3, count($rows), 'whereInList count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'whereNotInList', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name'])->from($t)
                ->whereNotInList('city', ['NY'])->all();
            Support::assertEquals(3, count($rows), 'whereNotInList count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'multiple orderBy', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name', 'city', 'age'])->from($t)
                ->orderBy(['city' => 'ASC', 'age' => 'DESC'])->all();
            // LA (bob 25, eve 28) => eve before bob within LA
            Support::assertEquals('eve', $rows[0]['name'], 'multi-order first row');
            return $rows[0]['name'] . ',' . $rows[1]['name'];
        });

        self::withTable($r, $cat, 'orderByAsc / orderByDesc', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name', 'age'])->from($t)->orderByDesc('age')->all();
            Support::assertEquals('carol', $rows[0]['name'], 'orderByDesc first');
            return $rows[0]['name'];
        });

        self::withTable($r, $cat, 'limit + offset + page', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name'])->from($t)
                ->orderBy(['id' => 'ASC'])->limit(2)->page(2)->all();
            // page 2 of size 2 => rows 3,4 => carol,dave
            Support::assertEquals('carol', $rows[0]['name'], 'page first');
            Support::assertEquals(2, count($rows), 'page count');
            return $rows[0]['name'] . ',' . $rows[1]['name'];
        });

        self::withTable($r, $cat, 'distinct', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['city'])->distinct()->from($t)->orderBy(['city' => 'ASC'])->all();
            Support::assertEquals(3, count($rows), 'distinct count');
            return 'distinct=' . count($rows);
        });

        self::withTable($r, $cat, 'distinct on column', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['city', 'name'])->distinct(['city'])->from($t)->all();
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'groupBy + having count', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['city', 'c' => $q->func()->count('*')])->from($t)
                ->groupBy(['city'])->having(['count(*) >' => 1]);
            $rows = $q->all();
            // NY(2) and LA(2) qualify, SF(1) does not
            Support::assertEquals(2, count($rows), 'group/having count');
            return 'groups=' . count($rows);
        });

        self::withTable($r, $cat, 'groupBy + multiple aggregates', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select([
                'city',
                'cnt' => $q->func()->count('*'),
                'total' => $q->func()->sum('salary'),
            ])->from($t)->groupBy(['city'])->orderBy(['city' => 'ASC']);
            $rows = $q->all();
            Support::assertEquals(3, count($rows), 'group count');
            return 'groups=' . count($rows);
        });

        self::withTable($r, $cat, 'leftJoin with conditions', function (Connection $conn, string $t) {
            $q = $conn->selectQuery()->select(['a.name', 'bname' => 'b.name'])->from(['a' => $t])
                ->leftJoin(['b' => $t], ['a.city = b.city', 'a.id <> b.id']);
            $rows = $q->all();
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'innerJoin with conditions', function (Connection $conn, string $t) {
            $q = $conn->selectQuery()->select(['a.name'])->from(['a' => $t])
                ->innerJoin(['b' => $t], ['a.city = b.city', 'a.id < b.id']);
            $rows = $q->all();
            // pairs within same city: NY(alice<carol), LA(bob<eve) => 2 rows
            Support::assertEquals(2, count($rows), 'innerJoin count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'union distinct', function (Connection $conn, string $t) {
            $q1 = $conn->selectQuery()->select(['name'])->from($t)->where(['city' => 'NY']);
            $q2 = $conn->selectQuery()->select(['name'])->from($t)->where(['city' => 'NY']);
            $rows = $q1->union($q2)->all();
            // identical rows deduped by UNION
            Support::assertEquals(2, count($rows), 'union distinct count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'unionAll', function (Connection $conn, string $t) {
            $q1 = $conn->selectQuery()->select(['name'])->from($t)->where(['city' => 'NY']);
            $q2 = $conn->selectQuery()->select(['name'])->from($t)->where(['city' => 'NY']);
            $rows = $q1->unionAll($q2)->all();
            Support::assertEquals(4, count($rows), 'unionAll count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'select expression alias', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['name', 'doubled' => $q->expr('age * 2')])->from($t)
                ->where(['name' => 'alice']);
            $rows = $q->all();
            Support::assertEquals(60, $rows[0]['doubled'], 'expression alias value');
            return (string) $rows[0]['doubled'];
        });

        self::withTable($r, $cat, 'select literal constant alias', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['name', 'flag' => $q->expr("'X'")])->from($t)->limit(1);
            $rows = $q->all();
            Support::assertEquals('X', $rows[0]['flag'], 'literal alias');
            return $rows[0]['flag'];
        });

        self::withTable($r, $cat, 'subquery in from', function (Connection $conn, string $t) {
            $sub = $conn->selectQuery()->select(['name', 'age'])->from($t)->where(['age >' => 28]);
            $q = $conn->selectQuery()->select(['c' => 'COUNT(*)'])->from(['sub' => $sub]);
            $rows = $q->all();
            Support::assertEquals(3, $rows[0]['c'], 'subquery-from count');
            return (string) $rows[0]['c'];
        });

        self::withTable($r, $cat, 'subquery in where (IN)', function (Connection $conn, string $t) {
            $sub = $conn->selectQuery()->select(['city'])->from($t)->where(['age >' => 35]);
            $rows = $conn->selectQuery()->select(['name'])->from($t)->where(['city IN' => $sub])->all();
            // age>35 => carol(NY) => cities {NY}; names in NY => alice,carol
            Support::assertEquals(2, count($rows), 'subquery-in count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'insert ... select', function (Connection $conn, string $t) {
            $t2 = Support::name('ck2dst');
            $conn->execute("CREATE TABLE `$t2` (name VARCHAR(50), age INT)");
            try {
                $select = $conn->selectQuery()->select(['name', 'age'])->from($t)->where(['city' => 'NY']);
                $conn->insertQuery()->insert(['name', 'age'])->into($t2)->values($select)->execute();
                $n = $conn->execute("SELECT COUNT(*) FROM `$t2`")->fetch()[0];
                Support::assertEquals(2, $n, 'insert-select count');
                return 'inserted=' . $n;
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$t2`");
            }
        });

        self::withTable($r, $cat, 'update with expression set', function (Connection $conn, string $t) {
            $q = $conn->updateQuery()->update($t);
            $q->set(['age' => $q->expr('age + 10')])->where(['name' => 'alice'])->execute();
            $age = $conn->execute("SELECT age FROM `$t` WHERE name='alice'")->fetch()[0];
            Support::assertEquals(40, $age, 'update expression');
            return (string) $age;
        });

        self::withTable($r, $cat, 'update multiple columns', function (Connection $conn, string $t) {
            $conn->updateQuery()->update($t)->set(['age' => 99, 'city' => 'ZZ'])
                ->where(['name' => 'bob'])->execute();
            $row = $conn->execute("SELECT age,city FROM `$t` WHERE name='bob'")->fetch('assoc');
            Support::assertEquals(99, $row['age'], 'updated age');
            Support::assertEquals('ZZ', $row['city'], 'updated city');
            return 'ok';
        });

        self::withTable($r, $cat, 'delete with complex where', function (Connection $conn, string $t) {
            $conn->deleteQuery()->delete()->from($t)
                ->where(['OR' => [['city' => 'LA'], ['age >' => 38]]])->execute();
            $n = $conn->execute("SELECT COUNT(*) FROM `$t`")->fetch()[0];
            // removes bob,eve (LA) and carol (age 40) => 2 remain
            Support::assertEquals(2, $n, 'complex delete remaining');
            return 'remaining=' . $n;
        });

        self::withTable($r, $cat, 'count via func + all', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['c' => $q->func()->count('*')])->from($t)->where(['city' => 'NY']);
            $n = (int) $q->all()[0]['c'];
            Support::assertEquals(2, $n, 'count via func');
            return 'count=' . $n;
        });

        self::withTable($r, $cat, 'modifier (SQL_NO_CACHE)', function (Connection $conn, string $t) {
            $rows = $conn->selectQuery()->select(['name'])->from($t)
                ->modifier('SQL_NO_CACHE')->where(['city' => 'NY'])->all();
            Support::assertEquals(2, count($rows), 'modifier count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'epilog FOR UPDATE', function (Connection $conn, string $t) {
            $conn->begin();
            try {
                $rows = $conn->selectQuery()->select(['name'])->from($t)
                    ->where(['city' => 'NY'])->epilog('FOR UPDATE')->all();
                Support::assertEquals(2, count($rows), 'for update count');
                return 'rows=' . count($rows);
            } finally {
                $conn->rollback();
            }
        });

        self::withTable($r, $cat, 'identifier auto-quoting on', function (Connection $conn, string $t) {
            $conn->getDriver()->enableAutoQuoting(true);
            try {
                $rows = $conn->selectQuery()->select(['name', 'age'])->from($t)
                    ->where(['age >' => 20])->orderBy(['age' => 'ASC'])->all();
                Support::assertEquals(5, count($rows), 'autoquote count');
                return 'rows=' . count($rows);
            } finally {
                $conn->getDriver()->enableAutoQuoting(false);
            }
        });

        self::withTable($r, $cat, 'select with comparison func in where', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['name'])->from($t)->where(function ($exp) {
                return $exp->gte('age', 30)->lte('age', 40);
            });
            $rows = $q->all();
            Support::assertEquals(3, count($rows), 'callable-where count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'order by expression', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['name', 'salary'])->from($t)->orderBy($q->expr('salary DESC'));
            $rows = $q->all();
            Support::assertEquals('carol', $rows[0]['name'], 'order-by-expr first');
            return $rows[0]['name'];
        });
    }

    // ------------------------------------------------------------------ //
    //  function2 — FunctionsBuilder ($q->func())                          //
    // ------------------------------------------------------------------ //

    private static function functions(Runner $r): void
    {
        $cat = 'function2';

        self::withTable($r, $cat, 'func count', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['c' => $q->func()->count('*')])->from($t);
            Support::assertEquals(5, $q->all()[0]['c'], 'count');
            return 'ok';
        });

        self::withTable($r, $cat, 'func sum', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['s' => $q->func()->sum('age')])->from($t);
            Support::assertEquals(158, $q->all()[0]['s'], 'sum age');
            return 'ok';
        });

        self::withTable($r, $cat, 'func avg', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['a' => $q->func()->avg('age')])->from($t);
            Support::assertValueEquals(31.6, $q->all()[0]['a'], 'avg age');
            return 'ok';
        });

        self::withTable($r, $cat, 'func min', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['m' => $q->func()->min('age')])->from($t);
            Support::assertEquals(25, $q->all()[0]['m'], 'min age');
            return 'ok';
        });

        self::withTable($r, $cat, 'func max', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['m' => $q->func()->max('age')])->from($t);
            Support::assertEquals(40, $q->all()[0]['m'], 'max age');
            return 'ok';
        });

        self::withTable($r, $cat, 'func concat', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['v' => $q->func()->concat(['name' => 'identifier', '-', 'city' => 'identifier'])])
                ->from($t)->where(['name' => 'alice']);
            Support::assertEquals('alice-NY', $q->all()[0]['v'], 'concat');
            return 'ok';
        });

        self::withTable($r, $cat, 'func coalesce', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['v' => $q->func()->coalesce([$q->expr('NULL'), 'fallback' => 'literal'])])
                ->from($t)->limit(1);
            Support::assertEquals('fallback', $q->all()[0]['v'], 'coalesce');
            return 'ok';
        });

        self::withTable($r, $cat, 'func now', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['n' => $q->func()->now()])->from($t)->limit(1);
            $v = $q->all()[0]['n'];
            Support::assert($v !== null && (string) $v !== '', 'now() empty');
            return (string) $v;
        });

        self::withTable($r, $cat, 'func extract year', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['y' => $q->func()->extract('YEAR', $q->expr("'2026-06-23'"))])->from($t)->limit(1);
            Support::assertEquals(2026, $q->all()[0]['y'], 'extract year');
            return 'ok';
        });

        self::withTable($r, $cat, 'func datePart month', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['m' => $q->func()->datePart('MONTH', $q->expr("'2026-06-23'"))])->from($t)->limit(1);
            Support::assertValueEquals(6, $q->all()[0]['m'], 'datePart month');
            return 'ok';
        });

        self::withTable($r, $cat, 'func dateDiff', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['d' => $q->func()->dateDiff([
                $q->expr("'2026-06-23'"),
                $q->expr("'2026-06-20'"),
            ])])->from($t)->limit(1);
            Support::assertEquals(3, $q->all()[0]['d'], 'dateDiff');
            return 'ok';
        });

        self::withTable($r, $cat, 'func rand', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['r' => $q->func()->rand()])->from($t)->limit(1);
            $v = (float) $q->all()[0]['r'];
            Support::assert($v >= 0.0 && $v < 1.0, 'rand out of range: ' . $v);
            return 'ok';
        });

        self::withTable($r, $cat, 'func cast to char', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['v' => $q->func()->cast('age', 'char')])->from($t)->where(['name' => 'alice']);
            Support::assertEquals('30', $q->all()[0]['v'], 'cast char');
            return 'ok';
        });

        self::withTable($r, $cat, 'func cast to decimal', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['v' => $q->func()->cast($q->expr('age'), 'decimal(10,2)')])->from($t)
                ->where(['name' => 'alice']);
            Support::assertValueEquals(30, $q->all()[0]['v'], 'cast decimal');
            return 'ok';
        });

        self::withTable($r, $cat, 'arbitrary func UPPER via __call', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['u' => $q->func()->upper(['name' => 'identifier'])])->from($t)->where(['name' => 'alice']);
            Support::assertEquals('ALICE', $q->all()[0]['u'], 'upper');
            return 'ok';
        });

        self::withTable($r, $cat, 'arbitrary func LENGTH via __call', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['l' => $q->func()->length(['name' => 'identifier'])])->from($t)->where(['name' => 'carol']);
            Support::assertEquals(5, $q->all()[0]['l'], 'length');
            return 'ok';
        });

        self::withTable($r, $cat, 'arbitrary func ABS via __call', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['a' => $q->func()->abs([$q->expr('0 - age')])])->from($t)->where(['name' => 'bob']);
            Support::assertEquals(25, $q->all()[0]['a'], 'abs');
            return 'ok';
        });

        self::withTable($r, $cat, 'aggregate with group by', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['city', 'm' => $q->func()->max('salary')])->from($t)
                ->groupBy(['city'])->orderBy(['city' => 'ASC']);
            $rows = $q->all();
            Support::assertEquals(3, count($rows), 'group count');
            // LA max salary = 3500.00
            Support::assertValueEquals(3500.00, $rows[0]['m'], 'LA max salary');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'func sum filtered by where', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['s' => $q->func()->sum('salary')])->from($t)->where(['city' => 'NY']);
            Support::assertValueEquals(12000.25, $q->all()[0]['s'], 'NY salary sum');
            return 'ok';
        });
    }

    // ------------------------------------------------------------------ //
    //  expression — QueryExpression / CASE builders                       //
    // ------------------------------------------------------------------ //

    private static function expressions(Runner $r): void
    {
        $cat = 'expression';

        self::withTable($r, $cat, 'CASE WHEN via case()', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $case = $q->expr()->case()
                ->when(['age >=' => 35])->then('senior')
                ->else('junior');
            $q->select(['name', 'band' => $case])->from($t)->where(['name' => 'carol']);
            Support::assertEquals('senior', $q->all()[0]['band'], 'case carol');
            return 'ok';
        });

        self::withTable($r, $cat, 'CASE WHEN else branch', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $case = $q->expr()->case()
                ->when(['age >=' => 35])->then('senior')
                ->else('junior');
            $q->select(['name', 'band' => $case])->from($t)->where(['name' => 'bob']);
            Support::assertEquals('junior', $q->all()[0]['band'], 'case bob');
            return 'ok';
        });

        self::withTable($r, $cat, 'newExpr eq builder', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['name'])->from($t)->where($q->expr()->eq('city', 'SF'));
            $rows = $q->all();
            Support::assertEquals(1, count($rows), 'eq builder count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'newExpr between builder', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['name'])->from($t)->where($q->expr()->between('age', 28, 35));
            $rows = $q->all();
            Support::assertEquals(3, count($rows), 'between builder count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'newExpr or() combinator', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['name'])->from($t)->where(function ($exp) {
                return $exp->or(['city' => 'SF', 'name' => 'alice']);
            });
            $rows = $q->all();
            Support::assertEquals(2, count($rows), 'or combinator count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'newExpr and() combinator', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['name'])->from($t)->where(function ($exp) {
                return $exp->and(['city' => 'NY', 'age >' => 35]);
            });
            $rows = $q->all();
            Support::assertEquals(1, count($rows), 'and combinator count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'newExpr isNull builder', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['name'])->from($t)->where($q->expr()->isNull('city'));
            $rows = $q->all();
            Support::assertEquals(0, count($rows), 'isNull builder count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'newExpr like builder', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['name'])->from($t)->where($q->expr()->like('name', '%e%'));
            $rows = $q->all();
            Support::assertEquals(3, count($rows), 'like builder count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'newExpr in builder', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['name'])->from($t)->where($q->expr()->in('city', ['NY', 'SF']));
            $rows = $q->all();
            Support::assertEquals(3, count($rows), 'in builder count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'newExpr not() combinator', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['name'])->from($t)->where(function ($exp) {
                return $exp->not(['city' => 'NY']);
            });
            $rows = $q->all();
            Support::assertEquals(3, count($rows), 'not combinator count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'raw newExpr arithmetic in where', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $q->select(['name'])->from($t)->where($q->expr('age % 2 = 0'));
            $rows = $q->all();
            // even ages: 30(alice),40(carol),28(eve) => 3
            Support::assertEquals(3, count($rows), 'arith where count');
            return 'rows=' . count($rows);
        });

        self::withTable($r, $cat, 'CASE in aggregate (conditional sum)', function (Connection $conn, string $t) {
            $q = $conn->selectQuery();
            $case = $q->expr()->case()->when(['city' => 'NY'])->then(1)->else(0);
            $q->select(['ny' => $q->func()->sum($case)])->from($t);
            Support::assertEquals(2, $q->all()[0]['ny'], 'conditional sum');
            return 'ok';
        });
    }

    // ------------------------------------------------------------------ //
    //  type2 — typed binding round-trips                                  //
    // ------------------------------------------------------------------ //

    private static function types(Runner $r): void
    {
        $cat = 'type2';

        $cases = [
            ['integer', 'INT', 'integer', 2147483647, '2147483647'],
            ['biginteger', 'BIGINT', 'biginteger', '9223372036854775807', '9223372036854775807'],
            ['decimal', 'DECIMAL(12,4)', 'decimal', '12345.6789', '12345.6789'],
            ['float', 'DOUBLE', 'float', 3.5, '3.5'],
            ['string', 'VARCHAR(100)', 'string', 'hello world', 'hello world'],
            ['text', 'TEXT', 'text', str_repeat('x', 500), str_repeat('x', 500)],
            ['boolean true', 'TINYINT(1)', 'boolean', true, '1'],
            ['boolean false', 'TINYINT(1)', 'boolean', false, '0'],
            ['date', 'DATE', 'date', new \DateTime('2026-06-23'), '2026-06-23'],
            ['datetime', 'DATETIME', 'datetime', new \DateTime('2026-06-23 10:20:30'), '2026-06-23 10:20:30'],
            ['time', 'TIME', 'time', new \DateTime('14:25:36'), '14:25:36'],
            ['timestamp', 'TIMESTAMP', 'timestamp', new \DateTime('2026-06-23 10:20:30'), '2026-06-23 10:20:30'],
            ['uuid', 'VARCHAR(36)', 'uuid', '550e8400-e29b-41d4-a716-446655440000', '550e8400-e29b-41d4-a716-446655440000'],
        ];

        foreach ($cases as [$label, $colType, $cakeType, $value, $expect]) {
            $r->add('CakePHP', $cat, "type $label", function () use ($colType, $cakeType, $value, $expect, $label) {
                $conn = Connections::cake();
                $tn = Support::name('ck2t');
                $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY, c $colType)");
                try {
                    $conn->execute(
                        "INSERT INTO `$tn` (id, c) VALUES (:id, :c)",
                        ['id' => 1, 'c' => $value],
                        ['c' => $cakeType]
                    );
                    $raw = $conn->execute("SELECT c FROM `$tn` WHERE id=1")->fetch()[0];
                    Support::assertValueEquals($expect, $raw, "$label round-trip");
                    return 'round-trip ok';
                } finally {
                    $conn->execute("DROP TABLE IF EXISTS `$tn`");
                }
            });
        }

        // JSON round-trip (compare decoded structure).
        $r->add('CakePHP', $cat, 'type json array', function () {
            $conn = Connections::cake();
            $tn = Support::name('ck2t');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY, c JSON)");
            try {
                $value = ['a' => 1, 'b' => ['c' => 2], 'd' => [3, 4, 5]];
                $conn->execute(
                    "INSERT INTO `$tn` (id, c) VALUES (:id, :c)",
                    ['id' => 1, 'c' => $value],
                    ['c' => 'json']
                );
                $raw = $conn->execute("SELECT c FROM `$tn` WHERE id=1")->fetch()[0];
                $decoded = json_decode((string) $raw, true);
                Support::assertEquals(1, $decoded['a'] ?? null, 'json a');
                Support::assertEquals(2, $decoded['b']['c'] ?? null, 'json nested');
                return 'json ok';
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });

        // Binary round-trip.
        $r->add('CakePHP', $cat, 'type binary', function () {
            $conn = Connections::cake();
            $tn = Support::name('ck2t');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY, c VARBINARY(64))");
            try {
                $value = "\x00\x01\x02binary\xff";
                $conn->execute(
                    "INSERT INTO `$tn` (id, c) VALUES (:id, :c)",
                    ['id' => 1, 'c' => $value],
                    ['c' => 'binary']
                );
                $raw = $conn->execute("SELECT c FROM `$tn` WHERE id=1")->fetch()[0];
                Support::assertEquals(bin2hex($value), bin2hex((string) $raw), 'binary round-trip');
                return 'binary ok';
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });

        // NULL binding for several types.
        foreach (['integer', 'string', 'decimal', 'datetime', 'boolean', 'json'] as $nt) {
            $r->add('CakePHP', $cat, "null binding $nt", function () use ($nt) {
                $conn = Connections::cake();
                $tn = Support::name('ck2t');
                $colType = match ($nt) {
                    'integer' => 'INT',
                    'string' => 'VARCHAR(50)',
                    'decimal' => 'DECIMAL(10,2)',
                    'datetime' => 'DATETIME',
                    'boolean' => 'TINYINT(1)',
                    'json' => 'JSON',
                    default => 'VARCHAR(50)',
                };
                $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY, c $colType NULL)");
                try {
                    $conn->execute(
                        "INSERT INTO `$tn` (id, c) VALUES (:id, :c)",
                        ['id' => 1, 'c' => null],
                        ['c' => $nt]
                    );
                    $raw = $conn->execute("SELECT c FROM `$tn` WHERE id=1")->fetch()[0];
                    Support::assert($raw === null, "$nt null expected, got " . var_export($raw, true));
                    return 'null ok';
                } finally {
                    $conn->execute("DROP TABLE IF EXISTS `$tn`");
                }
            });
        }
    }

    // ------------------------------------------------------------------ //
    //  schema2 — schema reflection & TableSchema generation               //
    // ------------------------------------------------------------------ //

    private static function schema(Runner $r): void
    {
        $cat = 'schema2';

        $r->add('CakePHP', $cat, 'listTables includes created table', function () {
            $conn = Connections::cake();
            $tn = Support::name('ck2s');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $tables = $conn->getSchemaCollection()->listTables();
                Support::assert(in_array($tn, $tables, true), 'listTables missing table');
                return count($tables) . ' tables';
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('CakePHP', $cat, 'listTablesWithoutViews', function () {
            $conn = Connections::cake();
            $tn = Support::name('ck2s');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $coll = $conn->getSchemaCollection();
                if (!method_exists($coll, 'listTablesWithoutViews')) {
                    Support::skip('listTablesWithoutViews not available');
                }
                $tables = $coll->listTablesWithoutViews();
                Support::assert(in_array($tn, $tables, true), 'missing table');
                return count($tables) . ' base tables';
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('CakePHP', $cat, 'TableSchema createSql multi-type + constraints', function () {
            $conn = Connections::cake();
            $tn = Support::name('ck2s');
            $schema = new \Cake\Database\Schema\TableSchema($tn);
            $schema->addColumn('id', ['type' => 'integer', 'autoIncrement' => true, 'null' => false])
                ->addColumn('code', ['type' => 'string', 'length' => 20, 'null' => false])
                ->addColumn('descr', ['type' => 'text'])
                ->addColumn('price', ['type' => 'decimal', 'precision' => 12, 'scale' => 2])
                ->addColumn('active', ['type' => 'boolean', 'default' => true])
                ->addColumn('created', ['type' => 'datetime'])
                ->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']])
                ->addConstraint('uniq_code', ['type' => 'unique', 'columns' => ['code']])
                ->addIndex('idx_price', ['type' => 'index', 'columns' => ['price']]);
            try {
                foreach ($schema->createSql($conn) as $sql) {
                    $conn->execute($sql);
                }
                Support::assert(
                    in_array($tn, $conn->getSchemaCollection()->listTables(), true),
                    'table not created via TableSchema'
                );
                return 'created';
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('CakePHP', $cat, 'TableSchema dropSql', function () {
            $conn = Connections::cake();
            $tn = Support::name('ck2s');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            $dropped = false;
            try {
                $schema = new \Cake\Database\Schema\TableSchema($tn);
                $schema->addColumn('id', ['type' => 'integer'])
                    ->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);
                foreach ($schema->dropSql($conn) as $sql) {
                    $conn->execute($sql);
                }
                $dropped = true;
                Support::assert(
                    !in_array($tn, $conn->getSchemaCollection()->listTables(), true),
                    'table still present after dropSql'
                );
                return 'dropped';
            } finally {
                if (!$dropped) {
                    $conn->execute("DROP TABLE IF EXISTS `$tn`");
                }
            }
        });

        // describe() is known-broken on MatrixOne (information_schema.check_constraints
        // missing). Recorded as findings — failures are expected.
        $r->add('CakePHP', $cat, 'describe() columns (known-broken)', function () {
            $conn = Connections::cake();
            $tn = Support::name('ck2s');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50) NOT NULL, age INT DEFAULT 0)");
            try {
                $desc = $conn->getSchemaCollection()->describe($tn);
                $cols = $desc->columns();
                Support::assert(in_array('name', $cols, true), 'describe missing name');
                return implode(',', $cols);
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('CakePHP', $cat, 'describe() primaryKey (known-broken)', function () {
            $conn = Connections::cake();
            $tn = Support::name('ck2s');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY, code VARCHAR(20))");
            try {
                $desc = $conn->getSchemaCollection()->describe($tn);
                $pk = $desc->getPrimaryKey();
                Support::assert(in_array('id', $pk, true), 'primaryKey missing id');
                return implode(',', $pk);
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });
    }

    // ------------------------------------------------------------------ //
    //  transaction2                                                       //
    // ------------------------------------------------------------------ //

    private static function transactions(Runner $r): void
    {
        $cat = 'transaction2';

        $r->add('CakePHP', $cat, 'transactional() commit', function () {
            $conn = Connections::cake();
            $tn = Support::name('ck2x');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $conn->transactional(function (Connection $c) use ($tn) {
                    $c->execute("INSERT INTO `$tn` VALUES (1),(2)");
                    return true;
                });
                $n = $conn->execute("SELECT COUNT(*) FROM `$tn`")->fetch()[0];
                Support::assertEquals(2, $n, 'tx commit count');
                return 'committed';
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('CakePHP', $cat, 'transactional() rollback on false', function () {
            $conn = Connections::cake();
            $tn = Support::name('ck2x');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $conn->transactional(function (Connection $c) use ($tn) {
                    $c->execute("INSERT INTO `$tn` VALUES (1)");
                    return false; // signals rollback
                });
                $n = $conn->execute("SELECT COUNT(*) FROM `$tn`")->fetch()[0];
                Support::assertEquals(0, $n, 'tx rollback-on-false count');
                return 'rolled-back';
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('CakePHP', $cat, 'transactional() rollback on exception', function () {
            $conn = Connections::cake();
            $tn = Support::name('ck2x');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                try {
                    $conn->transactional(function (Connection $c) use ($tn) {
                        $c->execute("INSERT INTO `$tn` VALUES (1)");
                        throw new \RuntimeException('boom');
                    });
                } catch (\RuntimeException $e) {
                    // expected
                }
                $n = $conn->execute("SELECT COUNT(*) FROM `$tn`")->fetch()[0];
                Support::assertEquals(0, $n, 'tx rollback-on-exception count');
                return 'rolled-back';
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('CakePHP', $cat, 'manual begin/commit', function () {
            $conn = Connections::cake();
            $tn = Support::name('ck2x');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $conn->begin();
                $conn->execute("INSERT INTO `$tn` VALUES (1)");
                $conn->commit();
                $n = $conn->execute("SELECT COUNT(*) FROM `$tn`")->fetch()[0];
                Support::assertEquals(1, $n, 'manual commit count');
                return 'committed';
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('CakePHP', $cat, 'manual begin/rollback', function () {
            $conn = Connections::cake();
            $tn = Support::name('ck2x');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $conn->begin();
                $conn->execute("INSERT INTO `$tn` VALUES (1)");
                $conn->rollback();
                $n = $conn->execute("SELECT COUNT(*) FROM `$tn`")->fetch()[0];
                Support::assertEquals(0, $n, 'manual rollback count');
                return 'rolled-back';
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('CakePHP', $cat, 'inTransaction() reflects state', function () {
            $conn = Connections::cake();
            $tn = Support::name('ck2x');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                Support::assert($conn->inTransaction() === false, 'should not be in tx initially');
                $conn->begin();
                Support::assert($conn->inTransaction() === true, 'should be in tx after begin');
                $conn->commit();
                Support::assert($conn->inTransaction() === false, 'should not be in tx after commit');
                return 'ok';
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('CakePHP', $cat, 'nested transactional (savepoints)', function () {
            $conn = Connections::cake();
            $tn = Support::name('ck2x');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $conn->enableSavePoints(true);
                $conn->transactional(function (Connection $c) use ($tn) {
                    $c->execute("INSERT INTO `$tn` VALUES (1)");
                    $c->transactional(function (Connection $c2) use ($tn) {
                        $c2->execute("INSERT INTO `$tn` VALUES (2)");
                        return true;
                    });
                    return true;
                });
                $n = $conn->execute("SELECT COUNT(*) FROM `$tn`")->fetch()[0];
                Support::assertEquals(2, $n, 'nested tx count');
                return 'nested ok';
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('CakePHP', $cat, 'nested rollback via savepoint', function () {
            $conn = Connections::cake();
            $tn = Support::name('ck2x');
            $conn->execute("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $conn->enableSavePoints(true);
                $conn->begin();
                $conn->execute("INSERT INTO `$tn` VALUES (1)");
                $conn->begin(); // nested -> savepoint
                $conn->execute("INSERT INTO `$tn` VALUES (2)");
                $conn->rollback(); // rolls back inner savepoint only
                $conn->commit();
                $n = $conn->execute("SELECT COUNT(*) FROM `$tn`")->fetch()[0];
                Support::assertEquals(1, $n, 'savepoint rollback count');
                return 'savepoint ok';
            } finally {
                $conn->execute("DROP TABLE IF EXISTS `$tn`");
            }
        });
    }
}
