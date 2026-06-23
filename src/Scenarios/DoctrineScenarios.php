<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use MoTest\Config;
use MoTest\Connections;
use MoTest\Entity\DocAuthor;
use MoTest\Entity\DocBook;
use MoTest\Entity\DocTag;
use MoTest\Runner;
use MoTest\Support;

/**
 * Doctrine — the second most widely used PHP ORM and the engine behind Symfony.
 *
 * Covers the DBAL layer (schema introspection, schema-tool DDL generation,
 * typed value round-trips, the query builder, transactions) and the ORM layer
 * (EntityManager unit-of-work, DQL, and every association kind).
 */
final class DoctrineScenarios
{
    private const DB = 'mo_compat_doctrine';

    public static function register(Runner $r): void
    {
        self::introspection($r);
        self::schemaDdl($r);
        self::types($r);
        self::queryBuilder($r);
        self::transactions($r);
        self::orm($r);
    }

    private static function conn(): DbalConnection
    {
        return Connections::dbal(self::DB);
    }

    private static function em(): EntityManagerInterface
    {
        return Connections::entityManager(self::DB, [dirname(__DIR__) . '/Entity']);
    }

    // ------------------------------------------------------------ introspection
    private static function introspection(Runner $r): void
    {
        $mk = function (string $name, callable $fn) use ($r) {
            $r->add('Doctrine', 'dbal:introspection', $name, function () use ($fn) {
                $conn = self::conn();
                $tn = Support::name('dc');
                $conn->executeStatement("CREATE TABLE `$tn` (id INT NOT NULL PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50) NOT NULL, price DECIMAL(10,2) DEFAULT 0, bio TEXT, created DATETIME, flag TINYINT(1), doc JSON, UNIQUE KEY uq_name (name))");
                try {
                    return $fn($conn->createSchemaManager(), $tn, $conn);
                } finally {
                    $conn->executeStatement("DROP TABLE IF EXISTS `$tn`");
                }
            });
        };

        $mk('listDatabases', fn ($sm) => count($sm->listDatabases()) . ' dbs');
        $mk('listTables (bulk)', fn ($sm) => count($sm->listTables()) . ' tables');
        $mk('listTableNames', fn ($sm) => count($sm->listTableNames()) . ' names');
        $mk('tablesExist', function ($sm, $tn) {
            Support::assert($sm->tablesExist([$tn]), 'tablesExist false');
            return 'exists';
        });
        $mk('listTableColumns', function ($sm, $tn) {
            $cols = $sm->listTableColumns($tn);
            Support::assertEquals(7, count($cols), 'column count');
            $names = array_map(fn ($c) => Type::lookupName($c->getType()), $cols);
            return implode(',', $names);
        });
        $mk('listTableIndexes', function ($sm, $tn) {
            $idx = $sm->listTableIndexes($tn);
            Support::assert(count($idx) >= 1, 'no indexes');
            return count($idx) . ' indexes';
        });
        $mk('introspectTable', function ($sm, $tn) {
            $table = $sm->introspectTable($tn);
            Support::assert($table->hasColumn('name'), 'introspected table missing name col');
            return 'introspected ' . count($table->getColumns()) . ' cols';
        });
        $mk('introspect column default', function ($sm, $tn) {
            $cols = $sm->listTableColumns($tn);
            $price = $cols['price'] ?? null;
            Support::assert($price !== null, 'price column not found');
            return 'default=' . var_export($price->getDefault(), true);
        });
        $mk('listTableForeignKeys', function ($sm, $tn, $conn) {
            $child = Support::name('dcfk');
            $conn->executeStatement("CREATE TABLE `$child` (id INT PRIMARY KEY, ref INT, FOREIGN KEY (ref) REFERENCES `$tn`(id))");
            try {
                $fks = $sm->listTableForeignKeys($child);
                return count($fks) . ' fks';
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$child`");
            }
        });

        // information_schema tables Doctrine and other tools rely upon.
        foreach ([
            'COLLATION_CHARACTER_SET_APPLICABILITY',
            'COLLATIONS',
            'CHARACTER_SETS',
            'KEY_COLUMN_USAGE',
            'REFERENTIAL_CONSTRAINTS',
            'TABLE_CONSTRAINTS',
            'STATISTICS',
            'ENGINES',
        ] as $isTable) {
            $r->add('Doctrine', 'dbal:information_schema', "information_schema.$isTable", function () use ($isTable) {
                $conn = self::conn();
                $sql = "SELECT * FROM information_schema.`$isTable` LIMIT 1";
                $conn->fetchAllAssociative($sql);
                return ['detail' => 'queryable', 'sql' => $sql];
            });
        }
    }

    // --------------------------------------------------------------- schema DDL
    private static function schemaDdl(Runner $r): void
    {
        $r->add('Doctrine', 'dbal:schema-ddl', 'create table via Schema object', function () {
            $conn = self::conn();
            $platform = $conn->getDatabasePlatform();
            $tn = Support::name('ds');
            $schema = new Schema();
            $t = $schema->createTable($tn);
            $t->addColumn('id', 'integer', ['autoincrement' => true]);
            $t->addColumn('name', 'string', ['length' => 80]);
            $t->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2, 'notnull' => false]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['name'], 'ix_name');
            try {
                foreach ($schema->toSql($platform) as $sql) {
                    $conn->executeStatement($sql);
                }
                Support::assert($conn->createSchemaManager()->tablesExist([$tn]), 'table not created');
                return 'created via schema toSql';
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('Doctrine', 'dbal:schema-ddl', 'schema diff / migrate (Comparator)', function () {
            $conn = self::conn();
            $sm = $conn->createSchemaManager();
            $tn = Support::name('ds');
            $conn->executeStatement("CREATE TABLE `$tn` (id INT PRIMARY KEY, a INT)");
            try {
                $from = $sm->introspectSchema();
                $to = $sm->introspectSchema();
                $toTable = $to->getTable(self::DB . '.' . $tn);
                if (!$toTable->hasColumn('b')) {
                    $toTable->addColumn('b', 'string', ['length' => 20, 'notnull' => false]);
                }
                $comparator = $sm->createComparator();
                $diff = $comparator->compareSchemas($from, $to);
                $platform = $conn->getDatabasePlatform();
                $stmts = $platform->getAlterSchemaSQL($diff);
                foreach ($stmts as $sql) {
                    $conn->executeStatement($sql);
                }
                Support::assert($sm->introspectTable($tn)->hasColumn('b'), 'diff did not add column b');
                return count($stmts) . ' migration statements applied';
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$tn`");
            }
        });

        $r->add('Doctrine', 'dbal:schema-ddl', 'SchemaManager createTable/dropTable', function () {
            $conn = self::conn();
            $sm = $conn->createSchemaManager();
            $tn = Support::name('ds');
            $table = new \Doctrine\DBAL\Schema\Table($tn);
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('label', 'string', ['length' => 40]);
            $table->setPrimaryKey(['id']);
            try {
                $sm->createTable($table);
                Support::assert($sm->tablesExist([$tn]), 'createTable failed');
                $sm->dropTable($tn);
                Support::assert(!$sm->tablesExist([$tn]), 'dropTable failed');
                return 'create/drop ok';
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$tn`");
            }
        });
    }

    // ------------------------------------------------------------------- types
    private static function types(Runner $r): void
    {
        $now = new \DateTime('2026-06-23 12:34:56');
        $cases = [
            ['integer', Types::INTEGER, [], 2147483647],
            ['bigint', Types::BIGINT, [], '9007199254740993'],
            ['smallint', Types::SMALLINT, [], 32000],
            ['boolean true', Types::BOOLEAN, [], true],
            ['boolean false', Types::BOOLEAN, [], false],
            ['decimal', Types::DECIMAL, ['precision' => 12, 'scale' => 2], '12345.67'],
            ['float', Types::FLOAT, [], 3.14159],
            ['string', Types::STRING, ['length' => 100], 'doctrine string'],
            ['text', Types::TEXT, [], str_repeat('t', 500)],
            ['guid', Types::GUID, [], '550e8400-e29b-41d4-a716-446655440000'],
            ['binary', Types::BINARY, ['length' => 32], 'binarydata'],
            ['blob', Types::BLOB, [], 'blobcontents'],
            ['date', Types::DATE_MUTABLE, [], new \DateTime('2026-06-23')],
            ['datetime', Types::DATETIME_MUTABLE, [], $now],
            ['datetimetz', Types::DATETIMETZ_MUTABLE, [], $now],
            ['time', Types::TIME_MUTABLE, [], new \DateTime('1970-01-01 10:20:30')],
            ['json', Types::JSON, [], ['a' => 1, 'b' => [2, 3]]],
            ['simple_array', Types::SIMPLE_ARRAY, [], ['x', 'y', 'z']],
            ['ascii_string', Types::ASCII_STRING, ['length' => 50], 'ascii'],
        ];
        foreach ($cases as [$label, $typeName, $opts, $value]) {
            $r->add('Doctrine', 'dbal:type', "type $label", function () use ($typeName, $opts, $value, $label) {
                $conn = self::conn();
                $platform = $conn->getDatabasePlatform();
                $tn = Support::name('dt');
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
    }

    private static function compareTyped(mixed $expected, mixed $actual, string $label): void
    {
        // Doctrine's blob/binary types hydrate to a stream resource.
        if (is_resource($actual)) {
            $actual = stream_get_contents($actual);
        }
        if ($expected instanceof \DateTimeInterface) {
            Support::assert($actual instanceof \DateTimeInterface, "$label: expected DateTime, got " . get_debug_type($actual));
            // Compare on the precision the column preserves (date or datetime).
            $fmt = str_contains($label, 'time') && !str_contains($label, 'datetime') ? 'H:i:s'
                : ($label === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s');
            Support::assertEquals($expected->format($fmt), $actual->format($fmt), "$label datetime");
            return;
        }
        if (is_array($expected)) {
            Support::assert(is_array($actual) && $actual == $expected, "$label: array round-trip mismatch");
            return;
        }
        if (is_bool($expected)) {
            Support::assert((bool) $actual === $expected, "$label: boolean mismatch got " . var_export($actual, true));
            return;
        }
        if (is_float($expected)) {
            Support::assert(abs((float) $actual - $expected) < 1e-4, "$label: float mismatch");
            return;
        }
        Support::assertEquals($expected, $actual, "$label scalar");
    }

    // ----------------------------------------------------------- query builder
    private static function withTable(Runner $r, string $cat, string $name, callable $fn): void
    {
        $r->add('Doctrine', $cat, $name, function () use ($fn) {
            $conn = self::conn();
            $tn = Support::name('dq');
            $conn->executeStatement("CREATE TABLE `$tn` (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50), age INT, city VARCHAR(50))");
            $conn->executeStatement("INSERT INTO `$tn`(name,age,city) VALUES ('alice',30,'NY'),('bob',25,'LA'),('carol',40,'NY')");
            try {
                return $fn($conn, $tn);
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$tn`");
            }
        });
    }

    private static function queryBuilder(Runner $r): void
    {
        self::withTable($r, 'dbal:querybuilder', 'select + where (named param)', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('name')->from($t)->where('age > :a')->setParameter('a', 28);
            $rows = $qb->executeQuery()->fetchAllAssociative();
            Support::assertEquals(2, count($rows), 'qb where count');
            return 'ok';
        });
        self::withTable($r, 'dbal:querybuilder', 'andWhere/orWhere + orderBy + setMaxResults', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('*')->from($t)
               ->where('city = :c')->setParameter('c', 'NY')
               ->orderBy('age', 'DESC')->setMaxResults(1);
            $row = $qb->executeQuery()->fetchAssociative();
            Support::assertEquals('carol', $row['name'], 'qb order');
            return 'ok';
        });
        self::withTable($r, 'dbal:querybuilder', 'setFirstResult/setMaxResults (offset)', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('name')->from($t)->orderBy('id')->setFirstResult(1)->setMaxResults(1);
            $row = $qb->executeQuery()->fetchAssociative();
            Support::assertEquals('bob', $row['name'], 'qb offset');
            return 'ok';
        });
        self::withTable($r, 'dbal:querybuilder', 'groupBy + having', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->select('city', 'COUNT(*) as c')->from($t)->groupBy('city')->having('COUNT(*) > 1');
            $rows = $qb->executeQuery()->fetchAllAssociative();
            Support::assertEquals(1, count($rows), 'qb having');
            return 'ok';
        });
        self::withTable($r, 'dbal:querybuilder', 'insert builder', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->insert($t)->values(['name' => ':n', 'age' => ':a'])->setParameter('n', 'dave')->setParameter('a', 50);
            $qb->executeStatement();
            Support::assertEquals(4, (int) $conn->fetchOne("SELECT COUNT(*) FROM `$t`"), 'insert builder');
            return 'ok';
        });
        self::withTable($r, 'dbal:querybuilder', 'update builder', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->update($t)->set('age', ':a')->where('name = :n')->setParameter('a', 99)->setParameter('n', 'bob');
            $n = $qb->executeStatement();
            Support::assertEquals(1, $n, 'update builder affected');
            return 'ok';
        });
        self::withTable($r, 'dbal:querybuilder', 'delete builder', function (DbalConnection $conn, string $t) {
            $qb = $conn->createQueryBuilder();
            $qb->delete($t)->where('name = :n')->setParameter('n', 'alice');
            Support::assertEquals(1, $qb->executeStatement(), 'delete builder affected');
            return 'ok';
        });
        self::withTable($r, 'dbal:querybuilder', 'fetchAllKeyValue', function (DbalConnection $conn, string $t) {
            $map = $conn->fetchAllKeyValue("SELECT name, age FROM `$t`");
            Support::assertEquals(30, $map['alice'], 'fetchAllKeyValue');
            return 'ok';
        });
        self::withTable($r, 'dbal:querybuilder', 'fetchFirstColumn', function (DbalConnection $conn, string $t) {
            $names = $conn->fetchFirstColumn("SELECT name FROM `$t` ORDER BY id");
            Support::assertEquals('alice', $names[0], 'fetchFirstColumn');
            return 'ok';
        });
        self::withTable($r, 'dbal:querybuilder', 'iterateAssociative (streaming)', function (DbalConnection $conn, string $t) {
            $n = 0;
            foreach ($conn->iterateAssociative("SELECT * FROM `$t`") as $_) {
                $n++;
            }
            Support::assertEquals(3, $n, 'iterate count');
            return 'ok';
        });
        self::withTable($r, 'dbal:querybuilder', 'positional params (?)', function (DbalConnection $conn, string $t) {
            $row = $conn->fetchAssociative("SELECT * FROM `$t` WHERE name = ? AND age = ?", ['bob', 25]);
            Support::assert($row !== false, 'positional params no row');
            return 'ok';
        });
        self::withTable($r, 'dbal:querybuilder', 'lastInsertId', function (DbalConnection $conn, string $t) {
            $conn->insert($t, ['name' => 'zoe', 'age' => 21]);
            $id = $conn->lastInsertId();
            Support::assert((int) $id > 0, 'no lastInsertId');
            return "id=$id";
        });
    }

    // ------------------------------------------------------------ transactions
    private static function transactions(Runner $r): void
    {
        $r->add('Doctrine', 'dbal:transaction', 'transactional() commit', function () {
            $conn = self::conn();
            $tn = Support::name('dtx');
            $conn->executeStatement("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $conn->transactional(function (DbalConnection $c) use ($tn) {
                    $c->executeStatement("INSERT INTO `$tn` VALUES (1)");
                });
                Support::assertEquals(1, (int) $conn->fetchOne("SELECT COUNT(*) FROM `$tn`"), 'tx commit');
                return 'ok';
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('Doctrine', 'dbal:transaction', 'manual rollback', function () {
            $conn = self::conn();
            $tn = Support::name('dtx');
            $conn->executeStatement("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $conn->beginTransaction();
                $conn->executeStatement("INSERT INTO `$tn` VALUES (1)");
                $conn->rollBack();
                Support::assertEquals(0, (int) $conn->fetchOne("SELECT COUNT(*) FROM `$tn`"), 'rollback');
                return 'ok';
            } finally {
                $conn->executeStatement("DROP TABLE IF EXISTS `$tn`");
            }
        });
        $r->add('Doctrine', 'dbal:transaction', 'nested with savepoints', function () {
            $conn = self::conn();
            $tn = Support::name('dtx');
            $conn->executeStatement("CREATE TABLE `$tn` (id INT PRIMARY KEY)");
            try {
                $conn->setNestTransactionsWithSavepoints(true);
                $conn->beginTransaction();
                $conn->executeStatement("INSERT INTO `$tn` VALUES (1)");
                $conn->beginTransaction(); // SAVEPOINT
                $conn->executeStatement("INSERT INTO `$tn` VALUES (2)");
                $conn->rollBack(); // ROLLBACK TO SAVEPOINT
                $conn->commit();
                Support::assertEquals(1, (int) $conn->fetchOne("SELECT COUNT(*) FROM `$tn`"), 'savepoint nested rollback');
                return 'ok';
            } finally {
                try {
                    while ($conn->isTransactionActive()) {
                        $conn->rollBack();
                    }
                } catch (\Throwable) {
                }
                $conn->executeStatement("DROP TABLE IF EXISTS `$tn`");
            }
        });
    }

    // --------------------------------------------------------------------- ORM
    private static function freshOrmSchema(EntityManagerInterface $em): void
    {
        $em->clear();
        $tool = new SchemaTool($em);
        $classes = [
            $em->getClassMetadata(DocAuthor::class),
            $em->getClassMetadata(DocBook::class),
            $em->getClassMetadata(DocTag::class),
        ];
        // Drop via raw SQL to avoid FK ordering issues, then create.
        $conn = $em->getConnection();
        foreach (['doc_book_tag', 'doc_books', 'doc_tags', 'doc_authors'] as $t) {
            $conn->executeStatement("DROP TABLE IF EXISTS `$t`");
        }
        $tool->createSchema($classes);
    }

    private static function orm(Runner $r): void
    {
        $r->add('Doctrine', 'orm:schema-tool', 'SchemaTool createSchema from entities', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $exists = $em->getConnection()->createSchemaManager()->tablesExist(['doc_authors', 'doc_books', 'doc_tags', 'doc_book_tag']);
            Support::assert($exists, 'schema tool did not create all tables');
            return 'created entity schema';
        });

        $r->add('Doctrine', 'orm:crud', 'persist + flush + find', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'Tolkien';
            $a->birthYear = 1892;
            $em->persist($a);
            $em->flush();
            $id = $a->id;
            $em->clear();
            $found = $em->find(DocAuthor::class, $id);
            Support::assertEquals('Tolkien', $found->name, 'find name');
            return "id=$id";
        });

        $r->add('Doctrine', 'orm:crud', 'update via managed entity', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'Orig';
            $em->persist($a);
            $em->flush();
            $a->name = 'Changed';
            $em->flush();
            $em->clear();
            Support::assertEquals('Changed', $em->find(DocAuthor::class, $a->id)->name, 'orm update');
            return 'ok';
        });

        $r->add('Doctrine', 'orm:crud', 'remove entity', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'Doomed';
            $em->persist($a);
            $em->flush();
            $id = $a->id;
            $em->remove($a);
            $em->flush();
            $em->clear();
            Support::assert($em->find(DocAuthor::class, $id) === null, 'still present after remove');
            return 'ok';
        });

        $r->add('Doctrine', 'orm:crud', 'decimal + json column round-trip', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $b = new DocBook();
            $b->title = 'Typed';
            $b->price = '19.99';
            $b->meta = ['genre' => 'fiction', 'pages' => 320];
            $em->persist($b);
            $em->flush();
            $em->clear();
            $found = $em->find(DocBook::class, $b->id);
            Support::assertEquals('19.99', $found->price, 'decimal');
            Support::assertEquals('fiction', $found->meta['genre'], 'json');
            return 'ok';
        });

        $r->add('Doctrine', 'orm:association', 'ManyToOne + OneToMany cascade', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'Author';
            $b1 = new DocBook();
            $b1->title = 'B1';
            $b1->author = $a;
            $b2 = new DocBook();
            $b2->title = 'B2';
            $b2->author = $a;
            $a->books->add($b1);
            $a->books->add($b2);
            $em->persist($a);
            $em->flush();
            $em->clear();
            $found = $em->find(DocAuthor::class, $a->id);
            Support::assertEquals(2, count($found->books), 'OneToMany count');
            Support::assertEquals('Author', $found->books[0]->author->name, 'ManyToOne back-ref');
            return 'ok';
        });

        $r->add('Doctrine', 'orm:association', 'ManyToMany join table', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $b = new DocBook();
            $b->title = 'Tagged';
            $t1 = new DocTag();
            $t1->label = 'sci-fi';
            $t2 = new DocTag();
            $t2->label = 'classic';
            $em->persist($t1);
            $em->persist($t2);
            $b->tags->add($t1);
            $b->tags->add($t2);
            $em->persist($b);
            $em->flush();
            $em->clear();
            $found = $em->find(DocBook::class, $b->id);
            Support::assertEquals(2, count($found->tags), 'ManyToMany count');
            return 'ok';
        });

        $r->add('Doctrine', 'orm:dql', 'DQL select with where', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            foreach (['Asimov', 'Clarke', 'Herbert'] as $i => $n) {
                $a = new DocAuthor();
                $a->name = $n;
                $a->birthYear = 1900 + $i * 10;
                $em->persist($a);
            }
            $em->flush();
            $em->clear();
            $q = $em->createQuery('SELECT a FROM ' . DocAuthor::class . ' a WHERE a.birthYear >= :y ORDER BY a.name');
            $q->setParameter('y', 1910);
            $res = $q->getResult();
            Support::assertEquals(2, count($res), 'DQL where count');
            return 'ok';
        });

        $r->add('Doctrine', 'orm:dql', 'DQL aggregate COUNT', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            for ($i = 0; $i < 4; $i++) {
                $a = new DocAuthor();
                $a->name = 'A' . $i;
                $em->persist($a);
            }
            $em->flush();
            $n = (int) $em->createQuery('SELECT COUNT(a.id) FROM ' . DocAuthor::class . ' a')->getSingleScalarResult();
            Support::assertEquals(4, $n, 'DQL count');
            return 'ok';
        });

        $r->add('Doctrine', 'orm:dql', 'DQL JOIN across association', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            $a = new DocAuthor();
            $a->name = 'Joiner';
            $b = new DocBook();
            $b->title = 'JoinedBook';
            $b->author = $a;
            $a->books->add($b);
            $em->persist($a);
            $em->flush();
            $em->clear();
            $q = $em->createQuery('SELECT a.name, b.title FROM ' . DocAuthor::class . ' a JOIN a.books b');
            $rows = $q->getArrayResult();
            Support::assertEquals('JoinedBook', $rows[0]['title'], 'DQL join');
            return 'ok';
        });

        $r->add('Doctrine', 'orm:repository', 'repository findBy / findOneBy / count', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            foreach (['R1', 'R2', 'R2'] as $n) {
                $a = new DocAuthor();
                $a->name = $n;
                $em->persist($a);
            }
            $em->flush();
            $em->clear();
            $repo = $em->getRepository(DocAuthor::class);
            Support::assertEquals(2, count($repo->findBy(['name' => 'R2'])), 'findBy');
            Support::assert($repo->findOneBy(['name' => 'R1']) !== null, 'findOneBy');
            Support::assertEquals(3, $repo->count([]), 'repo count');
            return 'ok';
        });

        $r->add('Doctrine', 'orm:pagination', 'Doctrine Paginator', function () {
            $em = self::em();
            self::freshOrmSchema($em);
            for ($i = 0; $i < 5; $i++) {
                $a = new DocAuthor();
                $a->name = 'P' . $i;
                $em->persist($a);
            }
            $em->flush();
            $q = $em->createQuery('SELECT a FROM ' . DocAuthor::class . ' a ORDER BY a.id')
                ->setFirstResult(0)->setMaxResults(2);
            $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($q, true);
            Support::assertEquals(5, count($paginator), 'paginator total');
            return 'ok';
        });
    }
}
