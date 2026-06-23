<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use MoTest\Connections;
use MoTest\Entity\DocAuthor;
use MoTest\Entity\DocBook;
use MoTest\Entity\DocEvent;
use MoTest\Entity\DocProfile;
use MoTest\Entity\DocTag;
use MoTest\Runner;
use MoTest\Support;

/**
 * Second Doctrine compatibility suite — additional coverage that does not
 * overlap with DoctrineScenarios.
 *
 * DBAL: extra column types (immutable date/time, dateinterval, guid, large
 * binary/text, nested json, numeric boundaries), the expression-builder query
 * builder, platform DDL generation (create/alter/truncate SQL) and
 * single-table introspection. ORM: deeper CRUD/unit-of-work behaviour, a broad
 * DQL surface (functions, joins, aggregates, NEW DTO, UPDATE/DELETE), every
 * association kind (incl. OneToOne via DocProfile), lifecycle callbacks (via
 * DocEvent) and repository querying (Criteria, matching, query builder).
 */
final class DoctrineScenarios2
{
    private const DB = 'mo_compat_doctrine';

    public static function register(Runner $r): void
    {
        self::types($r);
        self::queryBuilder($r);
        self::platform($r);
        self::introspection($r);
        self::ormCrud($r);
        self::ormDql($r);
        self::ormAssociation($r);
        self::ormLifecycle($r);
        self::ormRepository($r);
    }

    private static function conn(): DbalConnection
    {
        return Connections::dbal(self::DB);
    }

    private static function em(): EntityManagerInterface
    {
        return Connections::entityManager(self::DB, [dirname(__DIR__) . '/Entity']);
    }

    // ===================================================================== TYPES

    private static function compareTyped(mixed $expected, mixed $actual, string $label): void
    {
        if (is_resource($actual)) {
            $actual = stream_get_contents($actual);
        }
        if ($expected === null) {
            Support::assert($actual === null, "$label: expected NULL, got " . var_export($actual, true));
            return;
        }
        if ($expected instanceof \DateInterval) {
            Support::assert($actual instanceof \DateInterval, "$label: expected DateInterval, got " . get_debug_type($actual));
            $ref = new \DateTimeImmutable('2000-01-01 00:00:00');
            Support::assertEquals(
                $ref->add($expected)->format('Y-m-d H:i:s'),
                $ref->add($actual)->format('Y-m-d H:i:s'),
                "$label interval"
            );
            return;
        }
        if ($expected instanceof \DateTimeInterface) {
            Support::assert($actual instanceof \DateTimeInterface, "$label: expected DateTime, got " . get_debug_type($actual));
            $fmt = (str_contains($label, 'time') && !str_contains($label, 'datetime')) ? 'H:i:s'
                : (str_contains($label, 'date') && !str_contains($label, 'datetime') ? 'Y-m-d' : 'Y-m-d H:i:s');
            Support::assertEquals($expected->format($fmt), $actual->format($fmt), "$label datetime");
            return;
        }
        if (is_array($expected)) {
            Support::assert(is_array($actual) && $actual == $expected, "$label: array round-trip mismatch got " . var_export($actual, true));
            return;
        }
        if (is_bool($expected)) {
            Support::assert((bool) $actual === $expected, "$label: boolean mismatch got " . var_export($actual, true));
            return;
        }
        if (is_float($expected)) {
            Support::assert(abs((float) $actual - $expected) < 1e-4, "$label: float mismatch got " . var_export($actual, true));
            return;
        }
        Support::assertEquals($expected, $actual, "$label scalar");
    }

    /**
     * Build a single-column table via a Schema object, insert a typed value,
     * read it back raw and convert through the DBAL type. Mirrors the helper in
     * the original suite but lives here so the two suites stay independent.
     */
    private static function typeRoundTrip(Runner $r, string $label, string $typeName, array $opts, mixed $value): void
    {
        $r->add('Doctrine', 'dbal:type2', "type $label", function () use ($typeName, $opts, $value, $label) {
            $conn = self::conn();
            $platform = $conn->getDatabasePlatform();
            $tn = Support::name('dt2');
            $schema = new Schema();
            $t = $schema->createTable($tn);
            $t->addColumn('id', 'integer');
            $t->addColumn('c', $typeName, array_merge(['notnull' => false], $opts));
            $t->setPrimaryKey(['id']);
            try {
                foreach ($schema->toSql($platform) as $sql) {
                    $conn->executeStatement($sql);
                }
                $conn->insert($tn, ['id' => 1, 'c' => $value], ['c' => $typeName]);
                $raw = $conn->fetchOne("SELECT c FROM `$tn` WHERE id=1");
                $php = Type::getType($typeName)->convertToPHPValue($raw, $platform);
                self::compareTyped($value, $php, $label);
                return 'round-trip ok';
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$tn`");
            }
        });
    }

    private static function types(Runner $r): void
    {
        $dt = new \DateTime('2026-06-23 12:34:56');
        $dti = new \DateTimeImmutable('2026-06-23 12:34:56');
        $dateI = new \DateTimeImmutable('2026-06-23');
        $timeI = new \DateTimeImmutable('1970-01-01 10:20:30');

        // Immutable date/time family.
        self::typeRoundTrip($r, 'datetime_immutable', Types::DATETIME_IMMUTABLE, [], $dti);
        self::typeRoundTrip($r, 'date_immutable', Types::DATE_IMMUTABLE, [], $dateI);
        self::typeRoundTrip($r, 'time_immutable', Types::TIME_IMMUTABLE, [], $timeI);
        self::typeRoundTrip($r, 'datetimetz_immutable', Types::DATETIMETZ_IMMUTABLE, [], $dti);

        // DateInterval stored as its ISO-8601 spec string.
        self::typeRoundTrip($r, 'dateinterval', Types::DATEINTERVAL, ['length' => 255], new \DateInterval('P1Y2M10DT2H30M'));

        // simple_array edge cases: single element, numeric strings, empty-ish.
        self::typeRoundTrip($r, 'simple_array single', Types::SIMPLE_ARRAY, [], ['solo']);
        self::typeRoundTrip($r, 'simple_array numbers', Types::SIMPLE_ARRAY, [], ['1', '2', '3', '42']);
        self::typeRoundTrip($r, 'simple_array spaces', Types::SIMPLE_ARRAY, [], ['a b', 'c d', 'e']);

        // JSON nesting and scalar-root variants.
        self::typeRoundTrip($r, 'json nested objects', Types::JSON, [], ['user' => ['name' => 'x', 'roles' => ['a', 'b']], 'n' => 3]);
        self::typeRoundTrip($r, 'json array of objects', Types::JSON, [], [['id' => 1], ['id' => 2], ['id' => 3]]);
        self::typeRoundTrip($r, 'json deep nesting', Types::JSON, [], ['a' => ['b' => ['c' => ['d' => [1, 2, 3]]]]]);
        self::typeRoundTrip($r, 'json scalar string', Types::JSON, [], 'just a string');
        self::typeRoundTrip($r, 'json bool/null mix', Types::JSON, [], ['t' => true, 'f' => false, 'z' => null, 'pi' => 3.14]);
        self::typeRoundTrip($r, 'json unicode', Types::JSON, [], ['greeting' => 'héllo wörld', 'emoji-free' => 'ok']);

        // DECIMAL precision / sign edges.
        self::typeRoundTrip($r, 'decimal high precision', Types::DECIMAL, ['precision' => 20, 'scale' => 6], '12345678901234.567890');
        self::typeRoundTrip($r, 'decimal negative', Types::DECIMAL, ['precision' => 12, 'scale' => 2], '-9999.99');
        self::typeRoundTrip($r, 'decimal zero scale', Types::DECIMAL, ['precision' => 10, 'scale' => 0], '12345');
        self::typeRoundTrip($r, 'decimal small fraction', Types::DECIMAL, ['precision' => 8, 'scale' => 4], '0.0001');

        // Integer boundaries.
        self::typeRoundTrip($r, 'bigint max', Types::BIGINT, [], '9223372036854775807');
        self::typeRoundTrip($r, 'bigint min', Types::BIGINT, [], '-9223372036854775808');
        self::typeRoundTrip($r, 'smallint max', Types::SMALLINT, [], 32767);
        self::typeRoundTrip($r, 'smallint min', Types::SMALLINT, [], -32768);
        self::typeRoundTrip($r, 'integer min', Types::INTEGER, [], -2147483648);

        // boolean false specifically (true is covered in suite 1).
        self::typeRoundTrip($r, 'boolean false', Types::BOOLEAN, [], false);

        // ascii_string round-trip with punctuation.
        self::typeRoundTrip($r, 'ascii_string punct', Types::ASCII_STRING, ['length' => 100], 'a-b_c.d@e:f/g');

        // large text / blob / binary.
        self::typeRoundTrip($r, 'text large', Types::TEXT, [], str_repeat('Lorem ipsum dolor sit amet. ', 2000));
        self::typeRoundTrip($r, 'blob binary bytes', Types::BLOB, [], "\x00\x01\x02\xff\xfe\x7f binary\x00data");
        self::typeRoundTrip($r, 'binary fixed bytes', Types::BINARY, ['length' => 64], "\x10\x20\x30bytes");

        // GUID.
        self::typeRoundTrip($r, 'guid', Types::GUID, [], 'f47ac10b-58cc-4372-a567-0e02b2c3d479');

        // Nullable round-trips: insert NULL into each family.
        self::typeRoundTrip($r, 'null integer', Types::INTEGER, [], null);
        self::typeRoundTrip($r, 'null string', Types::STRING, ['length' => 50], null);
        self::typeRoundTrip($r, 'null decimal', Types::DECIMAL, ['precision' => 10, 'scale' => 2], null);
        self::typeRoundTrip($r, 'null datetime', Types::DATETIME_MUTABLE, [], null);
        self::typeRoundTrip($r, 'null json', Types::JSON, [], null);
        self::typeRoundTrip($r, 'null text', Types::TEXT, [], null);
        self::typeRoundTrip($r, 'null boolean', Types::BOOLEAN, [], null);
        self::typeRoundTrip($r, 'null blob', Types::BLOB, [], null);
    }

    // ============================================================= QUERY BUILDER

    /** Seed a uniquely named table, run, drop in finally. */
    private static function withTable(Runner $r, string $cat, string $name, callable $fn): void
    {
        $r->add('Doctrine', $cat, $name, function () use ($fn) {
            $conn = self::conn();
            $tn = Support::name('dq2');
            $conn->executeStatement("CREATE TABLE `$tn` (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50), age INT, city VARCHAR(50), salary DECIMAL(10,2))");
            $conn->executeStatement("INSERT INTO `$tn`(name,age,city,salary) VALUES
                ('alice',30,'NY',5000.00),
                ('bob',25,'LA',4200.50),
                ('carol',40,'NY',8000.00),
                ('dave',35,'LA',6100.00),
                ('erin',28,'SF',5500.00)");
            try {
                return $fn($conn, $tn);
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$tn`");
            }
        });
    }

    private static function queryBuilder(Runner $r): void
    {
        $cat = 'dbal:querybuilder2';

        self::withTable($r, $cat, 'expr eq', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('name')->from($t)->where($qb->expr()->eq('name', ':n'))->setParameter('n', 'alice');
            Support::assertEquals('alice', $qb->executeQuery()->fetchOne(), 'expr eq');
            return 'ok';
        });
        self::withTable($r, $cat, 'expr neq', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('COUNT(*)')->from($t)->where($qb->expr()->neq('city', ':c'))->setParameter('c', 'NY');
            Support::assertEquals(3, (int) $qb->executeQuery()->fetchOne(), 'expr neq');
            return 'ok';
        });
        self::withTable($r, $cat, 'expr lt', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('COUNT(*)')->from($t)->where($qb->expr()->lt('age', ':a'))->setParameter('a', 30);
            Support::assertEquals(2, (int) $qb->executeQuery()->fetchOne(), 'expr lt');
            return 'ok';
        });
        self::withTable($r, $cat, 'expr lte', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('COUNT(*)')->from($t)->where($qb->expr()->lte('age', ':a'))->setParameter('a', 30);
            Support::assertEquals(3, (int) $qb->executeQuery()->fetchOne(), 'expr lte');
            return 'ok';
        });
        self::withTable($r, $cat, 'expr gt', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('COUNT(*)')->from($t)->where($qb->expr()->gt('age', ':a'))->setParameter('a', 30);
            Support::assertEquals(2, (int) $qb->executeQuery()->fetchOne(), 'expr gt');
            return 'ok';
        });
        self::withTable($r, $cat, 'expr gte', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('COUNT(*)')->from($t)->where($qb->expr()->gte('age', ':a'))->setParameter('a', 30);
            Support::assertEquals(3, (int) $qb->executeQuery()->fetchOne(), 'expr gte');
            return 'ok';
        });
        self::withTable($r, $cat, 'expr in', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('COUNT(*)')->from($t)
               ->where($qb->expr()->in('name', ':names'))
               ->setParameter('names', ['alice', 'bob', 'carol'], \Doctrine\DBAL\ArrayParameterType::STRING);
            Support::assertEquals(3, (int) $qb->executeQuery()->fetchOne(), 'expr in');
            return 'ok';
        });
        self::withTable($r, $cat, 'expr notIn', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('COUNT(*)')->from($t)
               ->where($qb->expr()->notIn('name', ':names'))
               ->setParameter('names', ['alice', 'bob'], \Doctrine\DBAL\ArrayParameterType::STRING);
            Support::assertEquals(3, (int) $qb->executeQuery()->fetchOne(), 'expr notIn');
            return 'ok';
        });
        self::withTable($r, $cat, 'expr like', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('COUNT(*)')->from($t)->where($qb->expr()->like('name', ':p'))->setParameter('p', '%a%');
            Support::assertEquals(3, (int) $qb->executeQuery()->fetchOne(), 'expr like');
            return 'ok';
        });
        self::withTable($r, $cat, 'expr notLike', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('COUNT(*)')->from($t)->where($qb->expr()->notLike('name', ':p'))->setParameter('p', '%a%');
            Support::assertEquals(2, (int) $qb->executeQuery()->fetchOne(), 'expr notLike');
            return 'ok';
        });
        self::withTable($r, $cat, 'expr isNull', function (DbalConnection $conn, string $t) {
            $conn->executeStatement("INSERT INTO `$t`(name,age,city,salary) VALUES ('frank',NULL,'NY',NULL)");
            $qb = $conn->createQueryBuilder();
            $qb->select('name')->from($t)->where($qb->expr()->isNull('age'));
            Support::assertEquals('frank', $qb->executeQuery()->fetchOne(), 'expr isNull');
            return 'ok';
        });
        self::withTable($r, $cat, 'expr isNotNull', function (DbalConnection $conn, string $t) {
            $conn->executeStatement("INSERT INTO `$t`(name,age,city) VALUES ('frank',NULL,'NY')");
            $qb = $conn->createQueryBuilder();
            $qb->select('COUNT(*)')->from($t)->where($qb->expr()->isNotNull('age'));
            Support::assertEquals(5, (int) $qb->executeQuery()->fetchOne(), 'expr isNotNull');
            return 'ok';
        });
        self::withTable($r, $cat, 'between (raw predicate)', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('COUNT(*)')->from($t)->where('age BETWEEN :lo AND :hi')
               ->setParameter('lo', 28)->setParameter('hi', 35);
            Support::assertEquals(3, (int) $qb->executeQuery()->fetchOne(), 'between');
            return 'ok';
        });
        self::withTable($r, $cat, 'expr andX composite', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('name')->from($t)
               ->where($qb->expr()->and($qb->expr()->eq('city', ':c'), $qb->expr()->gt('age', ':a')))
               ->setParameter('c', 'NY')->setParameter('a', 30);
            Support::assertEquals('carol', $qb->executeQuery()->fetchOne(), 'andX');
            return 'ok';
        });
        self::withTable($r, $cat, 'expr orX composite', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('COUNT(*)')->from($t)
               ->where($qb->expr()->or($qb->expr()->eq('city', ':a'), $qb->expr()->eq('city', ':b')))
               ->setParameter('a', 'NY')->setParameter('b', 'SF');
            Support::assertEquals(3, (int) $qb->executeQuery()->fetchOne(), 'orX');
            return 'ok';
        });
        self::withTable($r, $cat, 'expr nested and/or', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $expr = $qb->expr();
            $qb->select('COUNT(*)')->from($t)
               ->where($expr->and($expr->or($expr->eq('city', ':ny'), $expr->eq('city', ':la')), $expr->gte('age', ':a')))
               ->setParameter('ny', 'NY')->setParameter('la', 'LA')->setParameter('a', 30);
            Support::assertEquals(3, (int) $qb->executeQuery()->fetchOne(), 'nested and/or');
            return 'ok';
        });
        self::withTable($r, $cat, 'join (self-join via second table)', function (DbalConnection $conn, string $t) {
            $cities = Support::name('dqc');
            $conn->executeStatement("CREATE TABLE `$cities` (code VARCHAR(50) PRIMARY KEY, region VARCHAR(50))");
            $conn->executeStatement("INSERT INTO `$cities` VALUES ('NY','East'),('LA','West'),('SF','West')");
            try {
                $qb = $conn->createQueryBuilder();
                $qb->select('p.name', 'c.region')->from($t, 'p')
                   ->join('p', $cities, 'c', 'p.city = c.code')
                   ->where('p.name = :n')->setParameter('n', 'alice');
                $row = $qb->executeQuery()->fetchAssociative();
                Support::assertEquals('East', $row['region'], 'join region');
                return 'ok';
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$cities`");
            }
        });
        self::withTable($r, $cat, 'innerJoin', function (DbalConnection $conn, string $t) {
            $cities = Support::name('dqc');
            $conn->executeStatement("CREATE TABLE `$cities` (code VARCHAR(50) PRIMARY KEY, region VARCHAR(50))");
            $conn->executeStatement("INSERT INTO `$cities` VALUES ('NY','East'),('LA','West')");
            try {
                $qb = $conn->createQueryBuilder();
                $qb->select('COUNT(*)')->from($t, 'p')
                   ->innerJoin('p', $cities, 'c', 'p.city = c.code');
                // SF has no city row -> excluded.
                Support::assertEquals(4, (int) $qb->executeQuery()->fetchOne(), 'innerJoin count');
                return 'ok';
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$cities`");
            }
        });
        self::withTable($r, $cat, 'leftJoin', function (DbalConnection $conn, string $t) {
            $cities = Support::name('dqc');
            $conn->executeStatement("CREATE TABLE `$cities` (code VARCHAR(50) PRIMARY KEY, region VARCHAR(50))");
            $conn->executeStatement("INSERT INTO `$cities` VALUES ('NY','East'),('LA','West')");
            try {
                $qb = $conn->createQueryBuilder();
                $qb->select('COUNT(*)')->from($t, 'p')
                   ->leftJoin('p', $cities, 'c', 'p.city = c.code');
                Support::assertEquals(5, (int) $qb->executeQuery()->fetchOne(), 'leftJoin count');
                return 'ok';
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$cities`");
            }
        });
        self::withTable($r, $cat, 'groupBy + having', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('city', 'COUNT(*) AS c')->from($t)->groupBy('city')->having('COUNT(*) >= :m')->setParameter('m', 2);
            $rows = $qb->executeQuery()->fetchAllAssociative();
            Support::assertEquals(2, count($rows), 'groupBy/having');
            return 'ok';
        });
        self::withTable($r, $cat, 'groupBy + addGroupBy + aggregate', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('city', 'SUM(salary) AS total')->from($t)->groupBy('city')->orderBy('total', 'DESC');
            $rows = $qb->executeQuery()->fetchAllAssociative();
            Support::assertEquals('NY', $rows[0]['city'], 'group sum order');
            return 'ok';
        });
        self::withTable($r, $cat, 'orderBy + addOrderBy (multiple)', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('name')->from($t)->orderBy('city', 'ASC')->addOrderBy('age', 'DESC');
            $names = $qb->executeQuery()->fetchFirstColumn();
            // city LA: dave(35),bob(25); NY: carol(40),alice(30); SF: erin(28)
            Support::assertEquals('dave', $names[0], 'multi order first');
            return 'ok';
        });
        self::withTable($r, $cat, 'setFirstResult + setMaxResults', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('name')->from($t)->orderBy('id')->setFirstResult(2)->setMaxResults(2);
            $names = $qb->executeQuery()->fetchFirstColumn();
            Support::assertEquals('carol', $names[0], 'offset/limit');
            Support::assertEquals(2, count($names), 'limit count');
            return 'ok';
        });
        self::withTable($r, $cat, 'subquery in where', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $sub = $conn->createQueryBuilder();
            $sub->select('AVG(age)')->from($t);
            $qb->select('COUNT(*)')->from($t)->where('age > (' . $sub->getSQL() . ')');
            // avg age = (30+25+40+35+28)/5 = 31.6 -> ages > 31.6: 40,35 = 2
            Support::assertEquals(2, (int) $qb->executeQuery()->fetchOne(), 'subquery where');
            return 'ok';
        });
        self::withTable($r, $cat, 'INSERT builder multi-value rows', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->insert($t)->values(['name' => ':n', 'age' => ':a', 'city' => ':c'])
               ->setParameter('n', 'gina')->setParameter('a', 22)->setParameter('c', 'BOS');
            $affected = $qb->executeStatement();
            Support::assertEquals(1, $affected, 'insert affected');
            Support::assertEquals(6, (int) $conn->fetchOne("SELECT COUNT(*) FROM `$t`"), 'insert count');
            return 'ok';
        });
        self::withTable($r, $cat, 'UPDATE builder multiple set', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->update($t)->set('age', ':a')->set('city', ':c')->where('name = :n')
               ->setParameter('a', 99)->setParameter('c', 'XX')->setParameter('n', 'bob');
            Support::assertEquals(1, $qb->executeStatement(), 'update affected');
            $row = $conn->fetchAssociative("SELECT age, city FROM `$t` WHERE name='bob'");
            Support::assertEquals(99, $row['age'], 'updated age');
            Support::assertEquals('XX', $row['city'], 'updated city');
            return 'ok';
        });
        self::withTable($r, $cat, 'DELETE builder with predicate', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->delete($t)->where('city = :c')->setParameter('c', 'NY');
            Support::assertEquals(2, $qb->executeStatement(), 'delete affected');
            Support::assertEquals(3, (int) $conn->fetchOne("SELECT COUNT(*) FROM `$t`"), 'remaining');
            return 'ok';
        });
        self::withTable($r, $cat, 'positional params in builder', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('name')->from($t)->where('age > ?')->andWhere('city = ?')
               ->setParameter(0, 20)->setParameter(1, 'LA')->orderBy('age');
            $names = $qb->executeQuery()->fetchFirstColumn();
            Support::assertEquals('bob', $names[0], 'positional builder');
            return 'ok';
        });
        self::withTable($r, $cat, 'named params in builder', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('name')->from($t)->where('age > :a')->andWhere('city = :c')
               ->setParameter('a', 20)->setParameter('c', 'NY')->orderBy('age');
            $names = $qb->executeQuery()->fetchFirstColumn();
            Support::assertEquals('alice', $names[0], 'named builder');
            return 'ok';
        });
        self::withTable($r, $cat, 'fetchAllKeyValue from builder', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('name', 'age')->from($t);
            $map = $qb->executeQuery()->fetchAllKeyValue();
            Support::assertEquals(40, $map['carol'], 'keyvalue');
            return 'ok';
        });
        self::withTable($r, $cat, 'fetchFirstColumn from builder', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('name')->from($t)->orderBy('name');
            $col = $qb->executeQuery()->fetchFirstColumn();
            Support::assertEquals('alice', $col[0], 'firstcolumn');
            Support::assertEquals(5, count($col), 'firstcolumn count');
            return 'ok';
        });
        self::withTable($r, $cat, 'fetchAllAssociativeIndexed', function (DbalConnection $conn, string $t) {
            $rows = $conn->fetchAllAssociativeIndexed("SELECT id, name, age FROM `$t`");
            Support::assert(is_array($rows) && count($rows) === 5, 'indexed rows');
            return 'ok';
        });
        self::withTable($r, $cat, 'iterateAssociative streaming', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('*')->from($t);
            $n = 0;
            foreach ($qb->executeQuery()->iterateAssociative() as $_) {
                $n++;
            }
            Support::assertEquals(5, $n, 'iterate count');
            return 'ok';
        });
        self::withTable($r, $cat, 'iterateKeyValue streaming', function (DbalConnection $conn, string $t) {
            $seen = [];
            foreach ($conn->iterateKeyValue("SELECT name, age FROM `$t`") as $k => $v) {
                $seen[$k] = $v;
            }
            Support::assertEquals(30, $seen['alice'], 'iterateKeyValue');
            return 'ok';
        });
        self::withTable($r, $cat, 'executeStatement return value (UPDATE all)', function (DbalConnection $conn, string $t) {
            $n = $conn->executeStatement("UPDATE `$t` SET city = 'ZZ'");
            Support::assertEquals(5, $n, 'updated all rows');
            return 'ok';
        });
        self::withTable($r, $cat, 'count via builder COUNT(DISTINCT)', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('COUNT(DISTINCT city)')->from($t);
            Support::assertEquals(3, (int) $qb->executeQuery()->fetchOne(), 'count distinct');
            return 'ok';
        });
        self::withTable($r, $cat, 'select expression alias + where on alias-free', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('name', 'salary * 12 AS annual')->from($t)->where('salary > :s')->setParameter('s', 6000)->orderBy('salary', 'DESC');
            $rows = $qb->executeQuery()->fetchAllAssociative();
            Support::assertEquals('carol', $rows[0]['name'], 'expr alias');
            Support::assertEquals(96000, (int) $rows[0]['annual'], 'computed annual');
            return 'ok';
        });
    }

    // =================================================================== PLATFORM

    private static function platform(Runner $r): void
    {
        $cat = 'dbal:platform';

        $r->add('Doctrine', $cat, 'getCreateTableSQL (PK + index)', function () {
            $conn = self::conn();
            $platform = $conn->getDatabasePlatform();
            $tn = Support::name('dp');
            $table = new Table($tn);
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('name', 'string', ['length' => 80]);
            $table->addColumn('email', 'string', ['length' => 120]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['name'], 'ix_name');
            $table->addUniqueIndex(['email'], 'uq_email');
            $sqls = $platform->getCreateTableSQL($table);
            Support::assert(count($sqls) >= 1, 'no create SQL');
            try {
                foreach ($sqls as $sql) {
                    $conn->executeStatement($sql);
                }
                Support::assert($conn->createSchemaManager()->tablesExist([$tn]), 'platform create failed');
                return count($sqls) . ' create stmts';
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('Doctrine', $cat, 'getCreateTableSQL (FK)', function () {
            $conn = self::conn();
            $platform = $conn->getDatabasePlatform();
            $parent = Support::name('dpp');
            $child = Support::name('dpc');
            $p = new Table($parent);
            $p->addColumn('id', 'integer', ['autoincrement' => true]);
            $p->setPrimaryKey(['id']);
            $c = new Table($child);
            $c->addColumn('id', 'integer', ['autoincrement' => true]);
            $c->addColumn('parent_id', 'integer', ['notnull' => false]);
            $c->setPrimaryKey(['id']);
            $c->addForeignKeyConstraint($parent, ['parent_id'], ['id'], [], 'fk_pc');
            try {
                foreach ($platform->getCreateTableSQL($p) as $sql) {
                    $conn->executeStatement($sql);
                }
                foreach ($platform->getCreateTableSQL($c) as $sql) {
                    $conn->executeStatement($sql);
                }
                Support::assert($conn->createSchemaManager()->tablesExist([$child]), 'fk table not created');
                return 'fk table created';
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$child`");
                $conn->executeStatement("DROP TABLE IF EXISTS `$parent`");
            }
        });

        $r->add('Doctrine', $cat, 'getAlterTableSQL add column (Comparator)', function () {
            $conn = self::conn();
            $platform = $conn->getDatabasePlatform();
            $sm = $conn->createSchemaManager();
            $tn = Support::name('dp');
            $conn->executeStatement("CREATE TABLE `$tn` (id INT PRIMARY KEY, a INT)");
            try {
                $current = $sm->introspectTable($tn);
                $modified = clone $current;
                $modified->addColumn('b', 'string', ['length' => 30, 'notnull' => false]);
                $comparator = new Comparator($platform);
                $diff = $comparator->compareTables($current, $modified);
                $sqls = $platform->getAlterTableSQL($diff);
                foreach ($sqls as $sql) {
                    $conn->executeStatement($sql);
                }
                Support::assert($sm->introspectTable($tn)->hasColumn('b'), 'add column failed');
                return count($sqls) . ' alter stmts';
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('Doctrine', $cat, 'getAlterTableSQL drop column (Comparator)', function () {
            $conn = self::conn();
            $platform = $conn->getDatabasePlatform();
            $sm = $conn->createSchemaManager();
            $tn = Support::name('dp');
            $conn->executeStatement("CREATE TABLE `$tn` (id INT PRIMARY KEY, a INT, b INT)");
            try {
                $current = $sm->introspectTable($tn);
                $modified = clone $current;
                $modified->dropColumn('b');
                $comparator = new Comparator($platform);
                $diff = $comparator->compareTables($current, $modified);
                foreach ($platform->getAlterTableSQL($diff) as $sql) {
                    $conn->executeStatement($sql);
                }
                Support::assert(!$sm->introspectTable($tn)->hasColumn('b'), 'drop column failed');
                return 'dropped column b';
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('Doctrine', $cat, 'getAlterTableSQL change column type (Comparator)', function () {
            $conn = self::conn();
            $platform = $conn->getDatabasePlatform();
            $sm = $conn->createSchemaManager();
            $tn = Support::name('dp');
            $conn->executeStatement("CREATE TABLE `$tn` (id INT PRIMARY KEY, a VARCHAR(10))");
            try {
                $current = $sm->introspectTable($tn);
                $modified = clone $current;
                $modified->modifyColumn('a', ['length' => 100]);
                $comparator = new Comparator($platform);
                $diff = $comparator->compareTables($current, $modified);
                $sqls = $platform->getAlterTableSQL($diff);
                foreach ($sqls as $sql) {
                    $conn->executeStatement($sql);
                }
                $col = $sm->introspectTable($tn)->getColumn('a');
                Support::assert($col->getLength() >= 100, 'change column len failed, got ' . var_export($col->getLength(), true));
                return 'changed column length';
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('Doctrine', $cat, 'getAlterTableSQL add index (Comparator)', function () {
            $conn = self::conn();
            $platform = $conn->getDatabasePlatform();
            $sm = $conn->createSchemaManager();
            $tn = Support::name('dp');
            $conn->executeStatement("CREATE TABLE `$tn` (id INT PRIMARY KEY, a INT)");
            try {
                $current = $sm->introspectTable($tn);
                $modified = clone $current;
                $modified->addIndex(['a'], 'ix_a');
                $comparator = new Comparator($platform);
                $diff = $comparator->compareTables($current, $modified);
                foreach ($platform->getAlterTableSQL($diff) as $sql) {
                    $conn->executeStatement($sql);
                }
                $idx = $sm->listTableIndexes($tn);
                Support::assert(isset($idx['ix_a']) || count($idx) >= 2, 'add index failed');
                return 'added index';
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('Doctrine', $cat, 'getAlterTableSQL drop index (Comparator)', function () {
            $conn = self::conn();
            $platform = $conn->getDatabasePlatform();
            $sm = $conn->createSchemaManager();
            $tn = Support::name('dp');
            $conn->executeStatement("CREATE TABLE `$tn` (id INT PRIMARY KEY, a INT, INDEX ix_a (a))");
            try {
                $current = $sm->introspectTable($tn);
                $modified = clone $current;
                $modified->dropIndex('ix_a');
                $comparator = new Comparator($platform);
                $diff = $comparator->compareTables($current, $modified);
                foreach ($platform->getAlterTableSQL($diff) as $sql) {
                    $conn->executeStatement($sql);
                }
                $idx = $sm->listTableIndexes($tn);
                Support::assert(!isset($idx['ix_a']), 'drop index failed');
                return 'dropped index';
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('Doctrine', $cat, 'quoteIdentifier', function () {
            $conn = self::conn();
            $platform = $conn->getDatabasePlatform();
            $q = $platform->quoteIdentifier('my col');
            Support::assert(str_contains($q, 'my col'), 'quoteIdentifier missing name');
            Support::assert($q[0] === '`' || $q[0] === '"', 'quoteIdentifier not quoted: ' . $q);
            return $q;
        });

        $r->add('Doctrine', $cat, 'quoteStringLiteral', function () {
            $conn = self::conn();
            $platform = $conn->getDatabasePlatform();
            $q = $platform->quoteStringLiteral("O'Brien");
            Support::assert(str_contains($q, 'Brien'), 'quoteStringLiteral missing content');
            return $q;
        });

        $r->add('Doctrine', $cat, 'getTruncateTableSQL + execute', function () {
            $conn = self::conn();
            $platform = $conn->getDatabasePlatform();
            $tn = Support::name('dp');
            $conn->executeStatement("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            $conn->executeStatement("INSERT INTO `$tn` VALUES (1),(2),(3)");
            try {
                $sql = $platform->getTruncateTableSQL($tn);
                $conn->executeStatement($sql);
                Support::assertEquals(0, (int) $conn->fetchOne("SELECT COUNT(*) FROM `$tn`"), 'truncate did not empty');
                return 'truncated';
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('Doctrine', $cat, 'platform name + version-aware flags', function () {
            $conn = self::conn();
            $platform = $conn->getDatabasePlatform();
            $name = $platform::class;
            Support::assert(stripos($name, 'mysql') !== false || stripos($name, 'maria') !== false, 'unexpected platform: ' . $name);
            return $name;
        });

        $r->add('Doctrine', $cat, 'getCurrentDatabaseExpression / fetch DB name', function () {
            $conn = self::conn();
            $db = $conn->fetchOne('SELECT DATABASE()');
            Support::assertEquals(self::DB, $db, 'current database');
            return (string) $db;
        });
    }

    // ============================================================== INTROSPECTION

    private static function introspection(Runner $r): void
    {
        $cat = 'dbal:introspection2';

        $mk = function (string $name, callable $fn) use ($r, $cat) {
            $r->add('Doctrine', $cat, $name, function () use ($fn) {
                $conn = self::conn();
                $tn = Support::name('di2');
                $conn->executeStatement("CREATE TABLE `$tn` (
                    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
                    code VARCHAR(20) NOT NULL,
                    qty INT DEFAULT 0,
                    amount DECIMAL(12,4),
                    descr TEXT,
                    active TINYINT(1),
                    created DATETIME,
                    payload JSON,
                    UNIQUE KEY uq_code (code),
                    INDEX ix_qty (qty)
                )");
                try {
                    return $fn($conn->createSchemaManager(), $tn, $conn);
                } finally {
                    $conn->executeStatement("DROP TABLE IF EXISTS `$tn`");
                }
            });
        };

        $mk('listTableColumns count', function ($sm, $tn) {
            $cols = $sm->listTableColumns($tn);
            Support::assertEquals(8, count($cols), 'column count');
            return count($cols) . ' cols';
        });
        $mk('listTableColumns types', function ($sm, $tn) {
            $cols = $sm->listTableColumns($tn);
            $names = array_map(fn ($c) => Type::lookupName($c->getType()), $cols);
            Support::assert(in_array('integer', $names, true) || in_array('bigint', $names, true), 'no integer type');
            return implode(',', $names);
        });
        $mk('column nullability', function ($sm, $tn) {
            $cols = $sm->listTableColumns($tn);
            Support::assert($cols['code']->getNotnull() === true, 'code should be NOT NULL');
            Support::assert($cols['descr']->getNotnull() === false, 'descr should be nullable');
            return 'nullability ok';
        });
        $mk('column length on varchar', function ($sm, $tn) {
            $cols = $sm->listTableColumns($tn);
            Support::assertEquals(20, $cols['code']->getLength(), 'varchar length');
            return 'len ok';
        });
        $mk('decimal precision/scale', function ($sm, $tn) {
            $cols = $sm->listTableColumns($tn);
            $amount = $cols['amount'];
            Support::assertEquals(12, $amount->getPrecision(), 'precision');
            Support::assertEquals(4, $amount->getScale(), 'scale');
            return 'p/s ok';
        });
        $mk('autoincrement flag', function ($sm, $tn) {
            $cols = $sm->listTableColumns($tn);
            Support::assert($cols['id']->getAutoincrement() === true, 'id should be autoincrement');
            return 'autoinc ok';
        });
        $mk('listTableIndexes (named + unique)', function ($sm, $tn) {
            $idx = $sm->listTableIndexes($tn);
            Support::assert(count($idx) >= 2, 'expected >=2 indexes, got ' . count($idx));
            $hasUnique = false;
            foreach ($idx as $i) {
                if ($i->isUnique() && !$i->isPrimary()) {
                    $hasUnique = true;
                }
            }
            Support::assert($hasUnique, 'no unique index detected');
            return count($idx) . ' indexes';
        });
        $mk('primary key columns', function ($sm, $tn) {
            $idx = $sm->listTableIndexes($tn);
            $pk = null;
            foreach ($idx as $i) {
                if ($i->isPrimary()) {
                    $pk = $i;
                }
            }
            Support::assert($pk !== null, 'no primary index');
            Support::assertEquals('id', $pk->getColumns()[0], 'pk column');
            return 'pk ok';
        });
        $mk('introspectTable round-trip', function ($sm, $tn) {
            $table = $sm->introspectTable($tn);
            Support::assert($table->hasColumn('payload'), 'missing payload col');
            Support::assert($table->hasIndex('uq_code') || count($table->getIndexes()) >= 2, 'missing index');
            return count($table->getColumns()) . ' cols introspected';
        });
        $mk('listTableForeignKeys on FK table', function ($sm, $tn, $conn) {
            $child = Support::name('difk');
            $conn->executeStatement("CREATE TABLE `$child` (id INT PRIMARY KEY, ref INT, FOREIGN KEY (ref) REFERENCES `$tn`(id))");
            try {
                $fks = $sm->listTableForeignKeys($child);
                Support::assert(count($fks) >= 1, 'no foreign keys found');
                $fk = array_values($fks)[0];
                Support::assertEquals('ref', $fk->getLocalColumns()[0], 'fk local column');
                return count($fks) . ' fks';
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$child`");
            }
        });
        $mk('tablesExist single', function ($sm, $tn) {
            Support::assert($sm->tablesExist([$tn]), 'table should exist');
            Support::assert(!$sm->tablesExist([$tn . '_nope']), 'phantom table exists');
            return 'exists ok';
        });
        $mk('introspect default value', function ($sm, $tn) {
            $cols = $sm->listTableColumns($tn);
            $qty = $cols['qty'] ?? null;
            Support::assert($qty !== null, 'qty col missing');
            return 'default=' . var_export($qty->getDefault(), true);
        });
    }

    // ==================================================================== ORM CRUD

    /** Drop & recreate all entity tables used by this suite, then clear UoW. */
    private static function freshOrmSchema(EntityManagerInterface $em): void
    {
        $em->clear();
        $tool = new SchemaTool($em);
        $classes = [
            $em->getClassMetadata(DocAuthor::class),
            $em->getClassMetadata(DocBook::class),
            $em->getClassMetadata(DocTag::class),
            $em->getClassMetadata(DocProfile::class),
            $em->getClassMetadata(DocEvent::class),
        ];
        // FK-safe drop via a dedicated raw connection (reliable across MatrixOne
        // sessions) before recreating the entity schema.
        Connections::dropTablesByPrefix(self::DB, 'doc_');
        $tool->createSchema($classes);
    }

    private static function ormCrud(Runner $r): void
    {
        $cat = 'orm:crud2';

        $r->add('Doctrine', $cat, 'persist + flush batch of 10', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            for ($i = 0; $i < 10; $i++) {
                $a = new DocAuthor();
                $a->name = 'Batch' . $i;
                $a->birthYear = 1900 + $i;
                $em->persist($a);
            }
            $em->flush();
            $em->clear();
            $n = (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM doc_authors');
            Support::assertEquals(10, $n, 'batch insert count');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'find + modify + flush (managed update)', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'Before';
            $em->persist($a);
            $em->flush();
            $id = $a->id;
            $em->clear();
            $managed = $em->find(DocAuthor::class, $id);
            $managed->name = 'After';
            $em->flush();
            $em->clear();
            Support::assertEquals('After', $em->find(DocAuthor::class, $id)->name, 'managed update');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'partial update (single field) keeps others', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'Keep';
            $a->birthYear = 1950;
            $em->persist($a);
            $em->flush();
            $id = $a->id;
            $a->birthYear = 1951;
            $em->flush();
            $em->clear();
            $found = $em->find(DocAuthor::class, $id);
            Support::assertEquals('Keep', $found->name, 'name preserved');
            Support::assertEquals(1951, $found->birthYear, 'year updated');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'remove + cascade remove children', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'Cascade';
            $b1 = new DocBook();
            $b1->title = 'C1';
            $b1->author = $a;
            $b2 = new DocBook();
            $b2->title = 'C2';
            $b2->author = $a;
            $a->books->add($b1);
            $a->books->add($b2);
            $em->persist($a);
            $em->flush();
            $em->remove($a);
            $em->flush();
            $em->clear();
            $books = (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM doc_books');
            Support::assertEquals(0, $books, 'cascade remove children');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'refresh() reloads DB state', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'Fresh';
            $em->persist($a);
            $em->flush();
            // Change row out-of-band, then refresh.
            $em->getConnection()->executeStatement('UPDATE doc_authors SET name = ? WHERE id = ?', ['OutOfBand', $a->id]);
            $em->refresh($a);
            Support::assertEquals('OutOfBand', $a->name, 'refresh reload');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'detach() then contains()', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'Detach';
            $em->persist($a);
            $em->flush();
            Support::assert($em->contains($a), 'should be managed before detach');
            $em->detach($a);
            Support::assert(!$em->contains($a), 'should be detached');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'getReference() proxy + lazy resolve', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'Ref';
            $em->persist($a);
            $em->flush();
            $id = $a->id;
            $em->clear();
            $ref = $em->getReference(DocAuthor::class, $id);
            Support::assertEquals($id, $ref->id, 'reference id');
            Support::assertEquals('Ref', $ref->name, 'reference lazy-loads name');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'multiple entities in one flush', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'Multi';
            $t = new DocTag();
            $t->label = 'misc';
            $b = new DocBook();
            $b->title = 'MultiBook';
            $b->author = $a;
            $a->books->add($b);
            $em->persist($a);
            $em->persist($t);
            $em->persist($b);
            $em->flush();
            $em->clear();
            Support::assertEquals(1, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM doc_authors'), 'authors');
            Support::assertEquals(1, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM doc_books'), 'books');
            Support::assertEquals(1, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM doc_tags'), 'tags');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'flush ordering (parent before child FK)', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'Ordered';
            $b = new DocBook();
            $b->title = 'OrderedBook';
            $b->author = $a;
            // persist child first; Doctrine must order parent INSERT before child.
            $em->persist($b);
            $em->persist($a);
            $em->flush();
            $em->clear();
            $found = $em->find(DocBook::class, $b->id);
            Support::assert($found->author !== null, 'fk ordering broke association');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'nullable association set to null', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'WillDetachBook';
            $b = new DocBook();
            $b->title = 'Orphanable';
            $b->author = $a;
            $a->books->add($b);
            $em->persist($a);
            $em->flush();
            $b->author = null;
            $em->flush();
            $em->clear();
            $found = $em->find(DocBook::class, $b->id);
            Support::assert($found->author === null, 'association not nulled');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'clear() then re-find from DB', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'Cleared';
            $em->persist($a);
            $em->flush();
            $id = $a->id;
            $em->clear();
            Support::assert(!$em->contains($a), 'entity still managed after clear');
            $found = $em->find(DocAuthor::class, $id);
            Support::assertEquals('Cleared', $found->name, 're-find after clear');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'decimal + json + datetime round-trip via ORM', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $b = new DocBook();
            $b->title = 'Rich';
            $b->price = '1234.56';
            $b->meta = ['tags' => ['a', 'b'], 'nested' => ['x' => 1]];
            $em->persist($b);
            $em->flush();
            $em->clear();
            $found = $em->find(DocBook::class, $b->id);
            Support::assertEquals('1234.56', $found->price, 'orm decimal');
            Support::assertEquals(1, $found->meta['nested']['x'], 'orm json nested');
            return 'ok';
        });
    }

    // ===================================================================== ORM DQL

    private static function seedAuthorsBooks(EntityManagerInterface $em): void
    {
        $data = [
            ['Asimov', 1920, [['Foundation', '15.00'], ['I Robot', '12.50']]],
            ['Clarke', 1917, [['Rendezvous', '20.00']]],
            ['Herbert', 1920, [['Dune', '25.00'], ['Dune Messiah', '18.00'], ['Children', '17.00']]],
            ['Le Guin', 1929, []],
        ];
        foreach ($data as [$name, $year, $books]) {
            $a = new DocAuthor();
            $a->name = $name;
            $a->birthYear = $year;
            foreach ($books as [$title, $price]) {
                $b = new DocBook();
                $b->title = $title;
                $b->price = $price;
                $b->author = $a;
                $a->books->add($b);
            }
            $em->persist($a);
        }
        $em->flush();
        $em->clear();
    }

    private static function ormDql(Runner $r): void
    {
        $cat = 'orm:dql2';
        $A = DocAuthor::class;
        $B = DocBook::class;

        $dql = function (string $name, callable $fn) use ($r, $cat) {
            $r->add('Doctrine', $cat, $name, function () use ($fn) {
                $em = self::em();
                self::freshOrmSchema($em);
                self::seedAuthorsBooks($em);
                return $fn($em);
            });
        };

        $dql('WHERE AND', function ($em) use ($A) {
            $q = $em->createQuery("SELECT a FROM $A a WHERE a.birthYear = :y AND a.name = :n");
            $q->setParameter('y', 1920)->setParameter('n', 'Herbert');
            Support::assertEquals(1, count($q->getResult()), 'where and');
            return 'ok';
        });
        $dql('WHERE OR', function ($em) use ($A) {
            $q = $em->createQuery("SELECT a FROM $A a WHERE a.name = :n1 OR a.name = :n2");
            $q->setParameter('n1', 'Asimov')->setParameter('n2', 'Clarke');
            Support::assertEquals(2, count($q->getResult()), 'where or');
            return 'ok';
        });
        $dql('LIKE', function ($em) use ($A) {
            $q = $em->createQuery("SELECT a FROM $A a WHERE a.name LIKE :p");
            $q->setParameter('p', '%e%');
            $res = $q->getResult();
            Support::assert(count($res) >= 2, 'like count');
            return 'ok';
        });
        $dql('IN', function ($em) use ($A) {
            $q = $em->createQuery("SELECT a FROM $A a WHERE a.name IN (:names)");
            $q->setParameter('names', ['Asimov', 'Herbert', 'Le Guin']);
            Support::assertEquals(3, count($q->getResult()), 'in count');
            return 'ok';
        });
        $dql('BETWEEN', function ($em) use ($A) {
            $q = $em->createQuery("SELECT a FROM $A a WHERE a.birthYear BETWEEN :lo AND :hi");
            $q->setParameter('lo', 1918)->setParameter('hi', 1921);
            Support::assertEquals(2, count($q->getResult()), 'between count');
            return 'ok';
        });
        $dql('IS NULL on association', function ($em) use ($B) {
            $q = $em->createQuery("SELECT b FROM $B b WHERE b.author IS NOT NULL");
            Support::assertEquals(6, count($q->getResult()), 'all books have author');
            return 'ok';
        });
        $dql('ORDER BY DESC', function ($em) use ($A) {
            $q = $em->createQuery("SELECT a FROM $A a ORDER BY a.name DESC");
            $res = $q->getResult();
            Support::assertEquals('Le Guin', $res[0]->name, 'order desc');
            return 'ok';
        });
        $dql('GROUP BY + HAVING', function ($em) use ($A, $B) {
            $q = $em->createQuery("SELECT a.name, COUNT(b.id) AS c FROM $A a JOIN a.books b GROUP BY a.id, a.name HAVING COUNT(b.id) > 1");
            $rows = $q->getArrayResult();
            // Asimov(2) and Herbert(3)
            Support::assertEquals(2, count($rows), 'group/having');
            return 'ok';
        });
        $dql('COUNT', function ($em) use ($A) {
            $n = (int) $em->createQuery("SELECT COUNT(a.id) FROM $A a")->getSingleScalarResult();
            Support::assertEquals(4, $n, 'count');
            return 'ok';
        });
        $dql('SUM', function ($em) use ($B) {
            $sum = $em->createQuery("SELECT SUM(b.price) FROM $B b")->getSingleScalarResult();
            Support::assertEquals(107.5, (float) $sum, 'sum price');
            return 'ok';
        });
        $dql('AVG', function ($em) use ($B) {
            $avg = (float) $em->createQuery("SELECT AVG(b.price) FROM $B b")->getSingleScalarResult();
            Support::assert(abs($avg - (107.5 / 6)) < 0.01, 'avg price got ' . $avg);
            return 'ok';
        });
        $dql('MIN/MAX', function ($em) use ($B) {
            $row = $em->createQuery("SELECT MIN(b.price) AS lo, MAX(b.price) AS hi FROM $B b")->getSingleResult();
            Support::assertEquals(12.5, (float) $row['lo'], 'min');
            Support::assertEquals(25.0, (float) $row['hi'], 'max');
            return 'ok';
        });
        $dql('JOIN association', function ($em) use ($A) {
            $q = $em->createQuery("SELECT a.name, b.title FROM $A a JOIN a.books b WHERE a.name = :n");
            $q->setParameter('n', 'Asimov');
            Support::assertEquals(2, count($q->getArrayResult()), 'join count');
            return 'ok';
        });
        $dql('JOIN ... WITH', function ($em) use ($A) {
            $q = $em->createQuery("SELECT a.name, b.title FROM $A a JOIN a.books b WITH b.price > :p");
            $q->setParameter('p', '19.00');
            $rows = $q->getArrayResult();
            // Rendezvous 20, Dune 25 -> 2
            Support::assertEquals(2, count($rows), 'join with');
            return 'ok';
        });
        $dql('LEFT JOIN keeps authorless', function ($em) use ($A) {
            $q = $em->createQuery("SELECT a.name, b.title FROM $A a LEFT JOIN a.books b");
            $rows = $q->getArrayResult();
            // Le Guin has no books -> still present with null title
            $names = array_column($rows, 'name');
            Support::assert(in_array('Le Guin', $names, true), 'left join missing authorless');
            return 'ok';
        });
        $dql('fetch join (collection)', function ($em) use ($A) {
            $q = $em->createQuery("SELECT a, b FROM $A a JOIN a.books b WHERE a.name = :n");
            $q->setParameter('n', 'Herbert');
            $res = $q->getResult();
            Support::assertEquals(3, count($res[0]->books), 'fetch join collection');
            return 'ok';
        });
        $dql('LOWER/UPPER', function ($em) use ($A) {
            $row = $em->createQuery("SELECT LOWER(a.name) AS lo, UPPER(a.name) AS hi FROM $A a WHERE a.name = :n")
                ->setParameter('n', 'Asimov')->getSingleResult();
            Support::assertEquals('asimov', $row['lo'], 'lower');
            Support::assertEquals('ASIMOV', $row['hi'], 'upper');
            return 'ok';
        });
        $dql('TRIM/LENGTH', function ($em) use ($A) {
            $row = $em->createQuery("SELECT LENGTH(a.name) AS len FROM $A a WHERE a.name = :n")
                ->setParameter('n', 'Asimov')->getSingleResult();
            Support::assertEquals(6, (int) $row['len'], 'length');
            return 'ok';
        });
        $dql('ABS/MOD/SQRT', function ($em) use ($A) {
            $row = $em->createQuery("SELECT ABS(a.birthYear - 2000) AS ab, MOD(a.birthYear, 100) AS md, SQRT(a.birthYear) AS sq FROM $A a WHERE a.name = :n")
                ->setParameter('n', 'Asimov')->getSingleResult();
            Support::assertEquals(80, (int) $row['ab'], 'abs');       // |1920-2000| = 80
            Support::assertEquals(20, (int) $row['md'], 'mod');       // 1920 % 100 = 20
            Support::assert(abs((float) $row['sq'] - sqrt(1920)) < 0.01, 'sqrt got ' . $row['sq']);
            return 'ok';
        });
        $dql('CONCAT', function ($em) use ($A) {
            $row = $em->createQuery("SELECT CONCAT(a.name, '!') AS c FROM $A a WHERE a.name = :n")
                ->setParameter('n', 'Clarke')->getSingleResult();
            Support::assertEquals('Clarke!', $row['c'], 'concat');
            return 'ok';
        });
        $dql('SUBSTRING', function ($em) use ($A) {
            $row = $em->createQuery("SELECT SUBSTRING(a.name, 1, 3) AS s FROM $A a WHERE a.name = :n")
                ->setParameter('n', 'Asimov')->getSingleResult();
            Support::assertEquals('Asi', $row['s'], 'substring');
            return 'ok';
        });
        $dql('COALESCE', function ($em) use ($A) {
            $row = $em->createQuery("SELECT COALESCE(a.birthYear, 0) AS y FROM $A a WHERE a.name = :n")
                ->setParameter('n', 'Asimov')->getSingleResult();
            Support::assertEquals(1920, (int) $row['y'], 'coalesce');
            return 'ok';
        });
        $dql('CASE WHEN', function ($em) use ($A) {
            $q = $em->createQuery("SELECT a.name, CASE WHEN a.birthYear < 1920 THEN 'old' ELSE 'new' END AS era FROM $A a WHERE a.name = :n");
            $row = $q->setParameter('n', 'Clarke')->getSingleResult();
            Support::assertEquals('old', $row['era'], 'case when');
            return 'ok';
        });
        $dql('positional parameters', function ($em) use ($A) {
            $q = $em->createQuery("SELECT a FROM $A a WHERE a.birthYear = ?1");
            $q->setParameter(1, 1929);
            Support::assertEquals(1, count($q->getResult()), 'positional');
            return 'ok';
        });
        $dql('named parameters', function ($em) use ($A) {
            $q = $em->createQuery("SELECT a FROM $A a WHERE a.birthYear = :y");
            $q->setParameter('y', 1917);
            Support::assertEquals(1, count($q->getResult()), 'named');
            return 'ok';
        });
        $dql('setMaxResults/setFirstResult', function ($em) use ($A) {
            $q = $em->createQuery("SELECT a FROM $A a ORDER BY a.name")->setFirstResult(1)->setMaxResults(2);
            $res = $q->getResult();
            Support::assertEquals(2, count($res), 'paged count');
            Support::assertEquals('Clarke', $res[0]->name, 'paged first');
            return 'ok';
        });
        $dql('getArrayResult', function ($em) use ($A) {
            $rows = $em->createQuery("SELECT a.name FROM $A a ORDER BY a.name")->getArrayResult();
            Support::assertEquals('Asimov', $rows[0]['name'], 'array result');
            return 'ok';
        });
        $dql('getScalarResult', function ($em) use ($A) {
            $rows = $em->createQuery("SELECT a.name FROM $A a ORDER BY a.name")->getScalarResult();
            Support::assert(is_array($rows) && count($rows) === 4, 'scalar result');
            return 'ok';
        });
        $dql('getSingleScalarResult', function ($em) use ($A) {
            $n = $em->createQuery("SELECT COUNT(a) FROM $A a")->getSingleScalarResult();
            Support::assertEquals(4, (int) $n, 'single scalar');
            return 'ok';
        });
        $dql('NEW DTO syntax', function ($em) use ($A) {
            $q = $em->createQuery('SELECT NEW ' . AuthorDto::class . "(a.name, a.birthYear) FROM $A a WHERE a.name = :n");
            $q->setParameter('n', 'Asimov');
            $res = $q->getResult();
            Support::assert($res[0] instanceof AuthorDto, 'not a DTO');
            Support::assertEquals('Asimov', $res[0]->name, 'dto name');
            Support::assertEquals(1920, $res[0]->birthYear, 'dto year');
            return 'ok';
        });
        $dql('DQL UPDATE statement', function ($em) use ($A) {
            $n = $em->createQuery("UPDATE $A a SET a.birthYear = a.birthYear + 1 WHERE a.name = :n")
                ->setParameter('n', 'Asimov')->execute();
            Support::assertEquals(1, $n, 'dql update affected');
            $em->clear();
            $a = $em->getRepository($A)->findOneBy(['name' => 'Asimov']);
            Support::assertEquals(1921, $a->birthYear, 'dql update value');
            return 'ok';
        });
        $dql('DQL DELETE statement', function ($em) use ($A, $B) {
            // delete books first to avoid FK, then an author.
            $em->createQuery("DELETE $B b")->execute();
            $n = $em->createQuery("DELETE $A a WHERE a.name = :n")->setParameter('n', 'Le Guin')->execute();
            Support::assertEquals(1, $n, 'dql delete affected');
            $em->clear();
            Support::assertEquals(3, count($em->getRepository($A)->findAll()), 'remaining authors');
            return 'ok';
        });
        $dql('COUNT DISTINCT in DQL', function ($em) use ($A) {
            $n = (int) $em->createQuery("SELECT COUNT(DISTINCT a.birthYear) FROM $A a")->getSingleScalarResult();
            // years: 1920,1917,1920,1929 -> distinct 3
            Support::assertEquals(3, $n, 'count distinct');
            return 'ok';
        });
    }

    // ============================================================= ORM ASSOCIATION

    private static function ormAssociation(Runner $r): void
    {
        $cat = 'orm:association2';

        $r->add('Doctrine', $cat, 'bidirectional OneToMany navigation', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'BiDir';
            $b = new DocBook();
            $b->title = 'Owned';
            $b->author = $a;
            $a->books->add($b);
            $em->persist($a);
            $em->flush();
            $em->clear();
            $found = $em->find(DocAuthor::class, $a->id);
            Support::assertEquals(1, count($found->books), 'collection size');
            Support::assertEquals('BiDir', $found->books[0]->author->name, 'inverse navigation');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'cascade persist via collection only', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'CascadePersist';
            $b1 = new DocBook();
            $b1->title = 'X1';
            $b1->author = $a;
            $b2 = new DocBook();
            $b2->title = 'X2';
            $b2->author = $a;
            $a->books->add($b1);
            $a->books->add($b2);
            // Only persist the parent; children cascade.
            $em->persist($a);
            $em->flush();
            $em->clear();
            Support::assertEquals(2, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM doc_books'), 'cascade persist count');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'remove one child from collection (orphan-like)', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'OrphanLike';
            $b1 = new DocBook();
            $b1->title = 'Y1';
            $b1->author = $a;
            $b2 = new DocBook();
            $b2->title = 'Y2';
            $b2->author = $a;
            $a->books->add($b1);
            $a->books->add($b2);
            $em->persist($a);
            $em->flush();
            // Explicitly remove one child + detach from author.
            $a->books->removeElement($b1);
            $em->remove($b1);
            $em->flush();
            $em->clear();
            Support::assertEquals(1, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM doc_books'), 'orphan removal');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'ManyToMany add + re-query', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $b = new DocBook();
            $b->title = 'M2M';
            $t1 = new DocTag();
            $t1->label = 'alpha';
            $t2 = new DocTag();
            $t2->label = 'beta';
            $em->persist($t1);
            $em->persist($t2);
            $b->tags->add($t1);
            $b->tags->add($t2);
            $em->persist($b);
            $em->flush();
            $em->clear();
            $found = $em->find(DocBook::class, $b->id);
            Support::assertEquals(2, count($found->tags), 'm2m add count');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'ManyToMany remove element', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $b = new DocBook();
            $b->title = 'M2Mremove';
            $t1 = new DocTag();
            $t1->label = 'keep';
            $t2 = new DocTag();
            $t2->label = 'drop';
            $em->persist($t1);
            $em->persist($t2);
            $b->tags->add($t1);
            $b->tags->add($t2);
            $em->persist($b);
            $em->flush();
            $b->tags->removeElement($t2);
            $em->flush();
            $em->clear();
            $found = $em->find(DocBook::class, $b->id);
            Support::assertEquals(1, count($found->tags), 'm2m remove count');
            Support::assertEquals('keep', $found->tags[0]->label, 'm2m remaining');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'ManyToMany clear all', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $b = new DocBook();
            $b->title = 'M2Mclear';
            $t1 = new DocTag();
            $t1->label = 'c1';
            $t2 = new DocTag();
            $t2->label = 'c2';
            $em->persist($t1);
            $em->persist($t2);
            $b->tags->add($t1);
            $b->tags->add($t2);
            $em->persist($b);
            $em->flush();
            $b->tags->clear();
            $em->flush();
            $em->clear();
            $found = $em->find(DocBook::class, $b->id);
            Support::assertEquals(0, count($found->tags), 'm2m clear count');
            $joins = (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM doc_book_tag');
            Support::assertEquals(0, $joins, 'join rows cleared');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'lazy collection triggers extra query', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'Lazy';
            $b = new DocBook();
            $b->title = 'LazyBook';
            $b->author = $a;
            $a->books->add($b);
            $em->persist($a);
            $em->flush();
            $em->clear();
            $found = $em->find(DocAuthor::class, $a->id);
            // collection is lazy; accessing it triggers initialization
            Support::assert(!$found->books->isInitialized(), 'collection eagerly loaded');
            $count = count($found->books);
            Support::assert($found->books->isInitialized(), 'collection not initialized after access');
            Support::assertEquals(1, $count, 'lazy collection count');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'ManyToOne proxy lazy init', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'ProxyParent';
            $b = new DocBook();
            $b->title = 'ProxyChild';
            $b->author = $a;
            $a->books->add($b);
            $em->persist($a);
            $em->flush();
            $em->clear();
            $found = $em->find(DocBook::class, $b->id);
            // author is a proxy until accessed
            $name = $found->author->name;
            Support::assertEquals('ProxyParent', $name, 'proxy resolved name');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'OneToOne owning persist + load', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'WithProfile';
            $p = new DocProfile();
            $p->bio = 'A short bio';
            $p->author = $a;
            $em->persist($a);
            $em->persist($p);
            $em->flush();
            $em->clear();
            $found = $em->find(DocProfile::class, $p->id);
            Support::assertEquals('A short bio', $found->bio, 'profile bio');
            Support::assertEquals('WithProfile', $found->author->name, 'one-to-one navigation');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'OneToOne update relation', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a1 = new DocAuthor();
            $a1->name = 'First';
            $a2 = new DocAuthor();
            $a2->name = 'Second';
            $p = new DocProfile();
            $p->bio = 'movable';
            $p->author = $a1;
            $em->persist($a1);
            $em->persist($a2);
            $em->persist($p);
            $em->flush();
            $p->author = $a2;
            $em->flush();
            $em->clear();
            $found = $em->find(DocProfile::class, $p->id);
            Support::assertEquals('Second', $found->author->name, 'one-to-one reassigned');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'collection re-query after flush sees new child', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'Grow';
            $b1 = new DocBook();
            $b1->title = 'G1';
            $b1->author = $a;
            $a->books->add($b1);
            $em->persist($a);
            $em->flush();
            // add another book to the managed author
            $b2 = new DocBook();
            $b2->title = 'G2';
            $b2->author = $a;
            $a->books->add($b2);
            $em->flush();
            $em->clear();
            $found = $em->find(DocAuthor::class, $a->id);
            Support::assertEquals(2, count($found->books), 'collection grew');
            return 'ok';
        });
    }

    // =============================================================== ORM LIFECYCLE

    private static function ormLifecycle(Runner $r): void
    {
        $cat = 'orm:lifecycle';

        $r->add('Doctrine', $cat, 'PrePersist fires + sets createdAt', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $e = new DocEvent();
            $e->name = 'Created';
            $em->persist($e);
            $em->flush();
            Support::assert($e->prePersistFired, 'PrePersist did not fire');
            Support::assert($e->createdAt instanceof \DateTimeInterface, 'createdAt not set');
            $id = $e->id;
            $em->clear();
            $found = $em->find(DocEvent::class, $id);
            Support::assert($found->createdAt instanceof \DateTimeInterface, 'createdAt not persisted');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'PreUpdate fires + sets updatedAt', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $e = new DocEvent();
            $e->name = 'ToUpdate';
            $em->persist($e);
            $em->flush();
            $id = $e->id;
            // modify a mapped field so an UPDATE is issued
            $e->name = 'Updated';
            $em->flush();
            Support::assert($e->preUpdateFired, 'PreUpdate did not fire');
            Support::assert($e->updatedAt instanceof \DateTimeInterface, 'updatedAt not set');
            $em->clear();
            $found = $em->find(DocEvent::class, $id);
            Support::assert($found->updatedAt instanceof \DateTimeInterface, 'updatedAt not persisted');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'PreUpdate does NOT fire on persist-only', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $e = new DocEvent();
            $e->name = 'NoUpdate';
            $em->persist($e);
            $em->flush();
            Support::assert(!$e->preUpdateFired, 'PreUpdate fired on insert');
            return 'ok';
        });

        $r->add('Doctrine', $cat, 'createdAt unchanged across update', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $e = new DocEvent();
            $e->name = 'Stable';
            $em->persist($e);
            $em->flush();
            $original = $e->createdAt->format('Y-m-d H:i:s');
            $e->name = 'StableChanged';
            $em->flush();
            Support::assertEquals($original, $e->createdAt->format('Y-m-d H:i:s'), 'createdAt mutated');
            return 'ok';
        });
    }

    // ============================================================== ORM REPOSITORY

    private static function ormRepository(Runner $r): void
    {
        $cat = 'orm:repository2';

        $repo = function (string $name, callable $fn) use ($r, $cat) {
            $r->add('Doctrine', $cat, $name, function () use ($fn) {
                $em = self::em();
                self::freshOrmSchema($em);
                foreach ([['Ann', 1980], ['Bea', 1985], ['Cy', 1985], ['Dee', 1990], ['Eve', 1990], ['Fay', 1990]] as [$n, $y]) {
                    $a = new DocAuthor();
                    $a->name = $n;
                    $a->birthYear = $y;
                    $em->persist($a);
                }
                $em->flush();
                $em->clear();
                return $fn($em, $em->getRepository(DocAuthor::class));
            });
        };

        $repo('findBy criteria + orderBy + limit + offset', function ($em, $repository) {
            $res = $repository->findBy(['birthYear' => 1990], ['name' => 'ASC'], 2, 1);
            Support::assertEquals(2, count($res), 'findBy limit');
            Support::assertEquals('Eve', $res[0]->name, 'findBy offset+order');
            return 'ok';
        });
        $repo('findBy orderBy DESC', function ($em, $repository) {
            $res = $repository->findBy([], ['name' => 'DESC']);
            Support::assertEquals('Fay', $res[0]->name, 'findBy desc');
            return 'ok';
        });
        $repo('findOneBy', function ($em, $repository) {
            $a = $repository->findOneBy(['name' => 'Bea']);
            Support::assert($a !== null, 'findOneBy null');
            Support::assertEquals(1985, $a->birthYear, 'findOneBy year');
            return 'ok';
        });
        $repo('findOneBy with order', function ($em, $repository) {
            $a = $repository->findOneBy(['birthYear' => 1990], ['name' => 'DESC']);
            Support::assertEquals('Fay', $a->name, 'findOneBy ordered');
            return 'ok';
        });
        $repo('count with criteria', function ($em, $repository) {
            Support::assertEquals(3, $repository->count(['birthYear' => 1990]), 'count criteria');
            Support::assertEquals(6, $repository->count([]), 'count all');
            return 'ok';
        });
        $repo('findAll', function ($em, $repository) {
            Support::assertEquals(6, count($repository->findAll()), 'findAll');
            return 'ok';
        });
        $repo('find by id', function ($em, $repository) {
            $one = $repository->findOneBy(['name' => 'Ann']);
            $again = $repository->find($one->id);
            Support::assertEquals('Ann', $again->name, 'find by id');
            return 'ok';
        });
        $repo('matching() Criteria eq', function ($em, $repository) {
            $criteria = Criteria::create()->where(Criteria::expr()->eq('birthYear', 1985));
            $res = $repository->matching($criteria);
            Support::assertEquals(2, count($res), 'matching eq');
            return 'ok';
        });
        $repo('matching() Criteria gt + orderBy', function ($em, $repository) {
            $criteria = Criteria::create()
                ->where(Criteria::expr()->gt('birthYear', 1980))
                ->orderBy(['name' => 'ASC']);
            $res = $repository->matching($criteria);
            Support::assertEquals(5, count($res), 'matching gt count');
            Support::assertEquals('Bea', $res[0]->name, 'matching order');
            return 'ok';
        });
        $repo('matching() Criteria in + limit', function ($em, $repository) {
            $criteria = Criteria::create()
                ->where(Criteria::expr()->in('name', ['Ann', 'Bea', 'Cy']))
                ->orderBy(['name' => 'ASC'])
                ->setMaxResults(2);
            $res = $repository->matching($criteria);
            Support::assertEquals(2, count($res), 'matching in+limit');
            Support::assertEquals('Ann', $res[0]->name, 'matching in first');
            return 'ok';
        });
        $repo('matching() Criteria andX', function ($em, $repository) {
            $expr = Criteria::expr();
            $criteria = Criteria::create()->where($expr->andX(
                $expr->eq('birthYear', 1990),
                $expr->neq('name', 'Dee')
            ))->orderBy(['name' => 'ASC']);
            $res = $repository->matching($criteria);
            Support::assertEquals(2, count($res), 'matching andX');
            Support::assertEquals('Eve', $res[0]->name, 'matching andX first');
            return 'ok';
        });
        $repo('createQueryBuilder on repository', function ($em, $repository) {
            $qb = $repository->createQueryBuilder('a');
            $qb->where('a.birthYear >= :y')->setParameter('y', 1985)->orderBy('a.name', 'ASC');
            $res = $qb->getQuery()->getResult();
            Support::assertEquals(5, count($res), 'repo qb count');
            Support::assertEquals('Bea', $res[0]->name, 'repo qb first');
            return 'ok';
        });
        $repo('createQueryBuilder with aggregate', function ($em, $repository) {
            $qb = $repository->createQueryBuilder('a');
            $qb->select('COUNT(a.id)')->where('a.birthYear = :y')->setParameter('y', 1990);
            $n = (int) $qb->getQuery()->getSingleScalarResult();
            Support::assertEquals(3, $n, 'repo qb aggregate');
            return 'ok';
        });
    }
}

/**
 * Tiny DTO target used by the "SELECT NEW ..." DQL scenario.
 */
final class AuthorDto
{
    public function __construct(public string $name, public ?int $birthYear)
    {
    }
}
