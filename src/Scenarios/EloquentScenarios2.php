<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use MoTest\Connections;
use MoTest\Models\MoArticle;
use MoTest\Models\MoCitizen;
use MoTest\Models\MoComment;
use MoTest\Models\MoCountry;
use MoTest\Models\MoPost;
use MoTest\Models\MoProfile;
use MoTest\Models\MoRole;
use MoTest\Models\MoScoped;
use MoTest\Models\MoSoftItem;
use MoTest\Models\MoTag;
use MoTest\Models\MoUser;
use MoTest\Models\MoUser2;
use MoTest\Runner;
use MoTest\Support;

/**
 * Second wave of Laravel Eloquent compatibility scenarios for MatrixOne.
 *
 * Where {@see EloquentScenarios} covers the breadth of the schema builder and
 * the common query/model/relationship surface, this class drills into the
 * deeper end of Eloquent: date-part where clauses, conditional builders,
 * sub-queries, JSON predicates, the full cast matrix, attribute change
 * tracking, local/global scopes, soft deletes, through- and polymorphic
 * many-to-many relationships, relationship aggregates, the pagination family
 * and savepoint-/lock-based transaction behaviour.
 *
 * Every scenario isolates its own state: query-builder cases spin up a fresh
 * uniquely-named table, while model/relationship cases rebuild the fixed-name
 * model schema up-front. Failures here are genuine MatrixOne findings.
 */
final class EloquentScenarios2
{
    public static function register(Runner $r): void
    {
        self::queryBuilder2($r);
        self::aggregate2($r);
        self::json2($r);
        self::model2($r);
        self::casts2($r);
        self::scopes($r);
        self::softDeletes($r);
        self::relationships2($r);
        self::pagination2($r);
        self::transactions2($r);
    }

    private static function db(): Connection
    {
        return Connections::eloquent()->getConnection();
    }

    /**
     * Query-builder isolation helper. Creates a fresh uniquely-named table,
     * seeds the standard four-row fixture (plus a couple of dated rows), runs
     * the scenario and always drops the table afterwards.
     */
    private static function withQb(Runner $r, string $category, string $name, callable $fn): void
    {
        $r->add('Eloquent', $category, $name, function () use ($fn) {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('qb');
            $s->create($tn, function (Blueprint $t) {
                $t->id();
                $t->string('name', 50);
                $t->integer('age');
                $t->string('city', 50)->nullable();
                $t->decimal('score', 8, 2)->default(0);
                $t->date('birth')->nullable();
                $t->dateTime('seen_at')->nullable();
            });
            try {
                $db->table($tn)->insert([
                    ['name' => 'alice', 'age' => 30, 'city' => 'NY', 'score' => 9.5, 'birth' => '1990-03-15', 'seen_at' => '2021-06-01 08:30:00'],
                    ['name' => 'bob', 'age' => 25, 'city' => 'LA', 'score' => 7.0, 'birth' => '1995-07-20', 'seen_at' => '2022-01-10 14:00:00'],
                    ['name' => 'carol', 'age' => 40, 'city' => 'NY', 'score' => 8.0, 'birth' => '1985-03-15', 'seen_at' => '2021-06-15 09:45:00'],
                    ['name' => 'dave', 'age' => 35, 'city' => null, 'score' => 6.5, 'birth' => '1988-12-01', 'seen_at' => null],
                ]);
                return $fn($db, $tn);
            } finally {
                $s->dropIfExists($tn);
            }
        });
    }

    /**
     * Rebuild the fixed-name model schema from scratch. Mirrors
     * EloquentScenarios::buildModelSchema (which is private) so model and
     * relationship scenarios start from a known-clean state.
     */
    private static function buildModelSchema(): void
    {
        $db = self::db();
        foreach (['mo_comments', 'mo_role_user', 'mo_roles', 'mo_profiles', 'mo_posts', 'mo_users'] as $t) {
            $db->statement("DROP TABLE IF EXISTS `$t`");
        }
        $db->statement('CREATE TABLE mo_users (id BIGINT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(100), email VARCHAR(150), active TINYINT DEFAULT 1, score DECIMAL(8,2) DEFAULT 0, meta JSON NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)');
        $db->statement('CREATE TABLE mo_posts (id BIGINT PRIMARY KEY AUTO_INCREMENT, user_id BIGINT, title VARCHAR(200), published TINYINT DEFAULT 0, tags JSON NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)');
        $db->statement('CREATE TABLE mo_profiles (id BIGINT PRIMARY KEY AUTO_INCREMENT, user_id BIGINT, bio VARCHAR(255))');
        $db->statement('CREATE TABLE mo_roles (id BIGINT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50))');
        $db->statement('CREATE TABLE mo_role_user (role_id BIGINT, user_id BIGINT)');
        $db->statement('CREATE TABLE mo_comments (id BIGINT PRIMARY KEY AUTO_INCREMENT, body VARCHAR(255), commentable_type VARCHAR(100), commentable_id BIGINT)');
    }

    /** Rebuild the rich MoUser2 table (casts / accessors / scopes). */
    private static function buildUser2Schema(): void
    {
        $db = self::db();
        $db->statement('DROP TABLE IF EXISTS `mo_users2`');
        $db->statement('CREATE TABLE mo_users2 (id BIGINT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(100) NULL, secret VARCHAR(100) NULL, options JSON NULL, profile JSON NULL, tags JSON NULL, is_active TINYINT DEFAULT 1, login_count INT DEFAULT 0, rating DOUBLE DEFAULT 0, balance DECIMAL(10,2) DEFAULT 0, born_on DATE NULL, seen_at DATETIME NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)');
    }

    /** Rebuild the soft-delete table. */
    private static function buildSoftSchema(): void
    {
        $db = self::db();
        $db->statement('DROP TABLE IF EXISTS `mo_soft_items`');
        $db->statement('CREATE TABLE mo_soft_items (id BIGINT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(100), qty INT DEFAULT 0, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL)');
    }

    /** Rebuild the global-scope table. */
    private static function buildScopedSchema(): void
    {
        $db = self::db();
        $db->statement('DROP TABLE IF EXISTS `mo_scoped`');
        $db->statement('CREATE TABLE mo_scoped (id BIGINT PRIMARY KEY AUTO_INCREMENT, title VARCHAR(100), published TINYINT DEFAULT 0)');
    }

    /**
     * Force the connection's transaction counter back to zero. MatrixOne does
     * not implement SAVEPOINT, so a failed nested rollBack can leave Eloquent's
     * internal level stuck; this resets it (raw ROLLBACK + reflection) so one
     * transaction scenario never pollutes the next.
     */
    private static function resetTransactions(): void
    {
        $db = self::db();
        try {
            $db->getPdo()->exec('ROLLBACK');
        } catch (\Throwable) {
            // ignore — nothing to roll back
        }
        try {
            $ref = new \ReflectionProperty($db, 'transactions');
            $ref->setAccessible(true);
            $ref->setValue($db, 0);
        } catch (\Throwable) {
            // property layout changed; best effort only
        }
    }

    /** Rebuild the through-chain + polymorphic-tag tables. */
    private static function buildThroughSchema(): void
    {
        $db = self::db();
        foreach (['mo_taggables', 'mo_tags', 'mo_articles', 'mo_citizens', 'mo_countries'] as $t) {
            $db->statement("DROP TABLE IF EXISTS `$t`");
        }
        $db->statement('CREATE TABLE mo_countries (id BIGINT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(100))');
        $db->statement('CREATE TABLE mo_citizens (id BIGINT PRIMARY KEY AUTO_INCREMENT, country_id BIGINT, name VARCHAR(100))');
        $db->statement('CREATE TABLE mo_articles (id BIGINT PRIMARY KEY AUTO_INCREMENT, citizen_id BIGINT, title VARCHAR(200))');
        $db->statement('CREATE TABLE mo_tags (id BIGINT PRIMARY KEY AUTO_INCREMENT, label VARCHAR(50))');
        $db->statement('CREATE TABLE mo_taggables (tag_id BIGINT, taggable_id BIGINT, taggable_type VARCHAR(100), note VARCHAR(50) NULL)');
    }

    // ------------------------------------------------------- query-builder2
    private static function queryBuilder2(Runner $r): void
    {
        $cat = 'query-builder2';

        self::withQb($r, $cat, 'whereDate', function (Connection $db, string $t) {
            $n = $db->table($t)->whereDate('birth', '1990-03-15')->count();
            Support::assertEquals(1, $n, 'whereDate');
            return 'ok';
        });
        self::withQb($r, $cat, 'whereYear', function (Connection $db, string $t) {
            $n = $db->table($t)->whereYear('birth', 1985)->count();
            Support::assertEquals(1, $n, 'whereYear');
            return 'ok';
        });
        self::withQb($r, $cat, 'whereMonth', function (Connection $db, string $t) {
            $n = $db->table($t)->whereMonth('birth', 3)->count();
            Support::assertEquals(2, $n, 'whereMonth');
            return 'ok';
        });
        self::withQb($r, $cat, 'whereDay', function (Connection $db, string $t) {
            $n = $db->table($t)->whereDay('birth', 15)->count();
            Support::assertEquals(2, $n, 'whereDay');
            return 'ok';
        });
        self::withQb($r, $cat, 'whereTime', function (Connection $db, string $t) {
            $n = $db->table($t)->whereTime('seen_at', '>=', '14:00:00')->count();
            Support::assertEquals(1, $n, 'whereTime');
            return 'ok';
        });
        self::withQb($r, $cat, 'whereColumn equals', function (Connection $db, string $t) {
            // age never equals id here; just ensure the predicate runs
            $rows = $db->table($t)->whereColumn('age', '=', 'id')->get();
            return 'rows=' . count($rows);
        });
        self::withQb($r, $cat, 'whereColumn 3-arg gt', function (Connection $db, string $t) {
            $rows = $db->table($t)->whereColumn('age', '>', 'id')->get();
            Support::assert(count($rows) >= 1, 'whereColumn gt found nothing');
            return 'rows=' . count($rows);
        });
        self::withQb($r, $cat, 'whereColumn array form', function (Connection $db, string $t) {
            $rows = $db->table($t)->whereColumn([['age', '>', 'id'], ['score', '>', 'id']])->get();
            return 'rows=' . count($rows);
        });
        self::withQb($r, $cat, 'whereBetweenColumns', function (Connection $db, string $t) {
            // id BETWEEN age-derived columns is not meaningful; use age between id and score*?
            $rows = $db->table($t)->whereBetweenColumns('id', ['id', 'age'])->get();
            Support::assertEquals(4, count($rows), 'whereBetweenColumns (id within [id,age])');
            return 'ok';
        });
        self::withQb($r, $cat, 'orWhereIn', function (Connection $db, string $t) {
            $n = $db->table($t)->where('age', 40)->orWhereIn('name', ['alice', 'bob'])->count();
            Support::assertEquals(3, $n, 'orWhereIn');
            return 'ok';
        });
        self::withQb($r, $cat, 'whereNotIn', function (Connection $db, string $t) {
            $n = $db->table($t)->whereNotIn('name', ['alice', 'bob'])->count();
            Support::assertEquals(2, $n, 'whereNotIn');
            return 'ok';
        });
        self::withQb($r, $cat, 'whereNull + whereNotNull combo', function (Connection $db, string $t) {
            $n = $db->table($t)->whereNotNull('city')->whereNull('birth')->count();
            Support::assertEquals(0, $n, 'null combo');
            return 'ok';
        });
        self::withQb($r, $cat, 'orWhereNull', function (Connection $db, string $t) {
            $n = $db->table($t)->where('age', 30)->orWhereNull('city')->count();
            Support::assertEquals(2, $n, 'orWhereNull');
            return 'ok';
        });
        self::withQb($r, $cat, 'whereExists', function (Connection $db, string $t) {
            $rows = $db->table($t . ' as a')->whereExists(function ($q) use ($t) {
                $q->select($q->raw(1))->from($t . ' as b')->whereColumn('b.city', 'a.city')->whereColumn('b.id', '<>', 'a.id');
            })->get();
            return 'rows=' . count($rows);
        });
        self::withQb($r, $cat, 'whereNotExists', function (Connection $db, string $t) {
            $rows = $db->table($t . ' as a')->whereNotExists(function ($q) use ($t) {
                $q->select($q->raw(1))->from($t . ' as b')->whereColumn('b.city', 'a.city')->whereColumn('b.id', '<>', 'a.id');
            })->get();
            return 'rows=' . count($rows);
        });
        self::withQb($r, $cat, 'having', function (Connection $db, string $t) {
            $rows = $db->table($t)->select('city', $db->raw('COUNT(*) as c'))
                ->whereNotNull('city')->groupBy('city')->having('c', '>', 1)->get();
            Support::assertEquals(1, count($rows), 'having');
            return 'ok';
        });
        self::withQb($r, $cat, 'havingBetween', function (Connection $db, string $t) {
            $rows = $db->table($t)->select('city', $db->raw('COUNT(*) as c'))
                ->whereNotNull('city')->groupBy('city')->havingBetween('c', [1, 1])->get();
            Support::assertEquals(1, count($rows), 'havingBetween');
            return 'ok';
        });
        self::withQb($r, $cat, 'orderByRaw', function (Connection $db, string $t) {
            $row = $db->table($t)->orderByRaw('age DESC')->first();
            Support::assertEquals('carol', $row->name, 'orderByRaw');
            return 'ok';
        });
        self::withQb($r, $cat, 'orderByDesc', function (Connection $db, string $t) {
            $row = $db->table($t)->orderByDesc('age')->first();
            Support::assertEquals('carol', $row->name, 'orderByDesc');
            return 'ok';
        });
        self::withQb($r, $cat, 'latest/oldest', function (Connection $db, string $t) {
            $old = $db->table($t)->oldest('age')->first();
            $new = $db->table($t)->latest('age')->first();
            Support::assertEquals('bob', $old->name, 'oldest by age');
            Support::assertEquals('carol', $new->name, 'latest by age');
            return 'ok';
        });
        self::withQb($r, $cat, 'reorder', function (Connection $db, string $t) {
            $q = $db->table($t)->orderBy('age', 'desc');
            $row = $q->reorder('age', 'asc')->first();
            Support::assertEquals('bob', $row->name, 'reorder');
            return 'ok';
        });
        self::withQb($r, $cat, 'groupByRaw', function (Connection $db, string $t) {
            $rows = $db->table($t)->select($db->raw('city as c'), $db->raw('COUNT(*) as n'))
                ->whereNotNull('city')->groupByRaw('city')->get();
            Support::assertEquals(2, count($rows), 'groupByRaw');
            return 'ok';
        });
        self::withQb($r, $cat, 'skip/take', function (Connection $db, string $t) {
            $rows = $db->table($t)->orderBy('id')->skip(1)->take(2)->get();
            Support::assertEquals(2, count($rows), 'skip/take');
            return 'ok';
        });
        self::withQb($r, $cat, 'forPage', function (Connection $db, string $t) {
            $rows = $db->table($t)->orderBy('id')->forPage(2, 2)->get();
            Support::assertEquals(2, count($rows), 'forPage');
            Support::assertEquals('carol', $rows[0]->name, 'forPage first');
            return 'ok';
        });
        self::withQb($r, $cat, 'when() conditional true', function (Connection $db, string $t) {
            $n = $db->table($t)->when(true, fn ($q) => $q->where('city', 'NY'))->count();
            Support::assertEquals(2, $n, 'when true');
            return 'ok';
        });
        self::withQb($r, $cat, 'when() conditional false', function (Connection $db, string $t) {
            $n = $db->table($t)->when(false, fn ($q) => $q->where('city', 'NY'))->count();
            Support::assertEquals(4, $n, 'when false');
            return 'ok';
        });
        self::withQb($r, $cat, 'unless() conditional', function (Connection $db, string $t) {
            $n = $db->table($t)->unless(false, fn ($q) => $q->where('city', 'NY'))->count();
            Support::assertEquals(2, $n, 'unless');
            return 'ok';
        });
        self::withQb($r, $cat, 'tap()', function (Connection $db, string $t) {
            $tapped = false;
            $n = $db->table($t)->tap(function ($q) use (&$tapped) {
                $tapped = true;
            })->where('city', 'NY')->count();
            Support::assert($tapped, 'tap not invoked');
            Support::assertEquals(2, $n, 'tap count');
            return 'ok';
        });
        self::withQb($r, $cat, 'addSelect', function (Connection $db, string $t) {
            $row = $db->table($t)->select('name')->addSelect('age')->where('name', 'alice')->first();
            Support::assertEquals(30, $row->age, 'addSelect');
            return 'ok';
        });
        self::withQb($r, $cat, 'selectSub', function (Connection $db, string $t) {
            $sub = $db->table($t)->selectRaw('MAX(age)');
            $row = $db->table($t)->selectSub($sub, 'max_age')->first();
            Support::assertEquals(40, (int) $row->max_age, 'selectSub');
            return 'ok';
        });
        self::withQb($r, $cat, 'fromSub', function (Connection $db, string $t) {
            $sub = $db->table($t)->where('city', 'NY');
            $n = $db->table($db->raw(''))->fromSub($sub, 'ny')->count();
            Support::assertEquals(2, $n, 'fromSub');
            return 'ok';
        });
        self::withQb($r, $cat, 'joinSub', function (Connection $db, string $t) {
            $sub = $db->table($t)->select('city', $db->raw('COUNT(*) as c'))->whereNotNull('city')->groupBy('city');
            $rows = $db->table($t . ' as main')
                ->joinSub($sub, 'agg', fn ($j) => $j->on('main.city', '=', 'agg.city'))
                ->get();
            Support::assert(count($rows) >= 1, 'joinSub empty');
            return 'rows=' . count($rows);
        });
        self::withQb($r, $cat, 'leftJoinSub', function (Connection $db, string $t) {
            $sub = $db->table($t)->select('city', $db->raw('COUNT(*) as c'))->whereNotNull('city')->groupBy('city');
            $rows = $db->table($t . ' as main')
                ->leftJoinSub($sub, 'agg', fn ($j) => $j->on('main.city', '=', 'agg.city'))
                ->get();
            Support::assertEquals(4, count($rows), 'leftJoinSub keeps all rows');
            return 'ok';
        });
        self::withQb($r, $cat, 'crossJoin', function (Connection $db, string $t) {
            $rows = $db->table($t . ' as a')->crossJoin($t . ' as b')->get();
            Support::assertEquals(16, count($rows), 'crossJoin 4x4');
            return 'ok';
        });
        self::withQb($r, $cat, 'unionAll', function (Connection $db, string $t) {
            $q1 = $db->table($t)->where('age', '<', 30);
            $rows = $db->table($t)->where('age', '<', 30)->unionAll($q1)->get();
            Support::assertEquals(2, count($rows), 'unionAll dup kept');
            return 'ok';
        });
        self::withQb($r, $cat, 'value()', function (Connection $db, string $t) {
            $v = $db->table($t)->where('name', 'carol')->value('age');
            Support::assertEquals(40, $v, 'value');
            return 'ok';
        });
        self::withQb($r, $cat, 'pluck()', function (Connection $db, string $t) {
            $names = $db->table($t)->orderBy('id')->pluck('name');
            Support::assertEquals('alice', $names[0], 'pluck first');
            return 'ok';
        });
        self::withQb($r, $cat, 'implode()', function (Connection $db, string $t) {
            $s = $db->table($t)->orderBy('id')->implode('name', ',');
            Support::assertEquals('alice,bob,carol,dave', $s, 'implode');
            return 'ok';
        });
        self::withQb($r, $cat, 'sole()', function (Connection $db, string $t) {
            $row = $db->table($t)->where('name', 'alice')->sole();
            Support::assertEquals(30, $row->age, 'sole');
            return 'ok';
        });
        self::withQb($r, $cat, 'exists() / doesntExist()', function (Connection $db, string $t) {
            Support::assert($db->table($t)->where('name', 'alice')->exists(), 'exists true');
            Support::assert($db->table($t)->where('name', 'zzz')->doesntExist(), 'doesntExist true');
            return 'ok';
        });
        self::withQb($r, $cat, 'updateOrInsert', function (Connection $db, string $t) {
            $db->table($t)->updateOrInsert(['name' => 'alice'], ['age' => 31]);
            $db->table($t)->updateOrInsert(['name' => 'frank'], ['age' => 50, 'score' => 1]);
            Support::assertEquals(31, $db->table($t)->where('name', 'alice')->value('age'), 'updateOrInsert update');
            Support::assertEquals(1, $db->table($t)->where('name', 'frank')->count(), 'updateOrInsert insert');
            return 'ok';
        });
        self::withQb($r, $cat, 'insertUsing', function (Connection $db, string $t) {
            $sub = $db->table($t)->select('name', 'age', 'score')->where('city', 'NY');
            $db->table($t)->insertUsing(['name', 'age', 'score'], $sub);
            Support::assertEquals(6, $db->table($t)->count(), 'insertUsing added 2');
            return 'ok';
        });
        self::withQb($r, $cat, 'upsert multi unique cols', function (Connection $db, string $t) {
            // unique on (name, city); needs an index for ON DUPLICATE KEY to fire
            $db->statement("ALTER TABLE `$t` ADD UNIQUE KEY uq_name_city (name, city)");
            $db->table($t)->upsert(
                [['name' => 'alice', 'city' => 'NY', 'age' => 99, 'score' => 1]],
                ['name', 'city'],
                ['age']
            );
            Support::assertEquals(99, $db->table($t)->where('name', 'alice')->value('age'), 'upsert multi-col');
            return 'ok';
        });
        self::withQb($r, $cat, 'incrementEach', function (Connection $db, string $t) {
            $db->table($t)->where('name', 'alice')->incrementEach(['age' => 1, 'score' => 2]);
            $row = $db->table($t)->where('name', 'alice')->first();
            Support::assertEquals(31, $row->age, 'incrementEach age');
            Support::assertValueEquals(11.5, $row->score, 'incrementEach score');
            return 'ok';
        });
        self::withQb($r, $cat, 'decrement', function (Connection $db, string $t) {
            $db->table($t)->where('name', 'carol')->decrement('age', 5);
            Support::assertEquals(35, $db->table($t)->where('name', 'carol')->value('age'), 'decrement');
            return 'ok';
        });
        self::withQb($r, $cat, 'lazy()', function (Connection $db, string $t) {
            $n = 0;
            foreach ($db->table($t)->orderBy('id')->lazy(2) as $_) {
                $n++;
            }
            Support::assertEquals(4, $n, 'lazy');
            return 'ok';
        });
        self::withQb($r, $cat, 'lazyById()', function (Connection $db, string $t) {
            $n = 0;
            foreach ($db->table($t)->lazyById(2) as $_) {
                $n++;
            }
            Support::assertEquals(4, $n, 'lazyById');
            return 'ok';
        });
        self::withQb($r, $cat, 'cursor()', function (Connection $db, string $t) {
            $n = 0;
            foreach ($db->table($t)->orderBy('id')->cursor() as $_) {
                $n++;
            }
            Support::assertEquals(4, $n, 'cursor');
            return 'ok';
        });
        self::withQb($r, $cat, 'chunkById()', function (Connection $db, string $t) {
            $seen = 0;
            $db->table($t)->chunkById(2, function ($rows) use (&$seen) {
                $seen += count($rows);
            });
            Support::assertEquals(4, $seen, 'chunkById');
            return 'ok';
        });
        self::withQb($r, $cat, 'each()', function (Connection $db, string $t) {
            $seen = 0;
            $db->table($t)->orderBy('id')->each(function ($row) use (&$seen) {
                $seen++;
            }, 2);
            Support::assertEquals(4, $seen, 'each');
            return 'ok';
        });
        self::withQb($r, $cat, 'whereLike', function (Connection $db, string $t) {
            $n = $db->table($t)->whereLike('name', 'a%')->count();
            Support::assertEquals(1, $n, 'whereLike a%');
            return 'ok';
        });
        self::withQb($r, $cat, 'inRandomOrder', function (Connection $db, string $t) {
            $rows = $db->table($t)->inRandomOrder()->get();
            Support::assertEquals(4, count($rows), 'inRandomOrder count');
            return 'ok';
        });
        self::withQb($r, $cat, 'distinct on column', function (Connection $db, string $t) {
            $cities = $db->table($t)->whereNotNull('city')->distinct()->pluck('city');
            Support::assertEquals(2, count($cities), 'distinct cities');
            return 'ok';
        });
        self::withQb($r, $cat, 'raw binding in where', function (Connection $db, string $t) {
            $n = $db->table($t)->whereRaw('age + ? > ?', [5, 40])->count();
            Support::assertEquals(1, $n, 'raw binding');
            return 'ok';
        });
        self::withQb($r, $cat, 'subquery in where', function (Connection $db, string $t) {
            $rows = $db->table($t)->where('age', '>', function ($q) use ($t) {
                $q->selectRaw('AVG(age)')->from($t);
            })->get();
            Support::assert(count($rows) >= 1, 'subquery where empty');
            return 'rows=' . count($rows);
        });
        self::withQb($r, $cat, 'whereIn with subquery', function (Connection $db, string $t) {
            $rows = $db->table($t)->whereIn('city', function ($q) use ($t) {
                $q->select('city')->from($t)->whereNotNull('city');
            })->get();
            Support::assertEquals(3, count($rows), 'whereIn subquery');
            return 'ok';
        });
        self::withQb($r, $cat, 'union with orderBy', function (Connection $db, string $t) {
            $q1 = $db->table($t)->select('name', 'age')->where('age', '<', 30);
            $rows = $db->table($t)->select('name', 'age')->where('age', '>', 35)
                ->union($q1)->orderBy('age')->get();
            Support::assertEquals(2, count($rows), 'union+orderBy');
            return 'ok';
        });
        self::withQb($r, $cat, 'whereNotBetween', function (Connection $db, string $t) {
            $n = $db->table($t)->whereNotBetween('age', [30, 36])->count();
            Support::assertEquals(2, $n, 'whereNotBetween');
            return 'ok';
        });
        self::withQb($r, $cat, 'orWhereBetween', function (Connection $db, string $t) {
            $n = $db->table($t)->where('age', 25)->orWhereBetween('age', [38, 42])->count();
            Support::assertEquals(2, $n, 'orWhereBetween');
            return 'ok';
        });
        self::withQb($r, $cat, 'nested where group', function (Connection $db, string $t) {
            $n = $db->table($t)->where(function ($q) {
                $q->where('city', 'NY')->orWhere('age', 25);
            })->count();
            Support::assertEquals(3, $n, 'nested where group');
            return 'ok';
        });
        self::withQb($r, $cat, 'whereNot closure', function (Connection $db, string $t) {
            // NOT(city='NY') with a NULL city yields NULL (not TRUE), so the
            // null-city row is excluded -> only LA/bob matches, like MySQL.
            $n = $db->table($t)->whereNot(function ($q) {
                $q->where('city', 'NY');
            })->count();
            Support::assertEquals(1, $n, 'whereNot group (LA only; null excluded)');
            return 'ok';
        });
        self::withQb($r, $cat, 'whereAll', function (Connection $db, string $t) {
            $n = $db->table($t)->whereAll(['age'], '>', 20)->count();
            Support::assertEquals(4, $n, 'whereAll');
            return 'ok';
        });
        self::withQb($r, $cat, 'whereAny', function (Connection $db, string $t) {
            $n = $db->table($t)->whereAny(['city'], '=', 'NY')->count();
            Support::assertEquals(2, $n, 'whereAny');
            return 'ok';
        });
        self::withQb($r, $cat, 'orderBy multiple columns', function (Connection $db, string $t) {
            $rows = $db->table($t)->orderBy('city')->orderBy('age', 'desc')->get();
            Support::assertEquals(4, count($rows), 'multi orderBy count');
            return 'ok';
        });
        self::withQb($r, $cat, 'limit + offset', function (Connection $db, string $t) {
            $rows = $db->table($t)->orderBy('id')->limit(2)->offset(2)->get();
            Support::assertEquals('carol', $rows[0]->name, 'limit+offset');
            return 'ok';
        });
        self::withQb($r, $cat, 'first() returns null when empty', function (Connection $db, string $t) {
            $row = $db->table($t)->where('name', 'nobody')->first();
            Support::assert($row === null, 'first null on no match');
            return 'ok';
        });
        self::withQb($r, $cat, 'find() by id', function (Connection $db, string $t) {
            $row = $db->table($t)->find(1);
            Support::assert($row !== null, 'find by id');
            return 'ok';
        });
        self::withQb($r, $cat, 'count column ignores null', function (Connection $db, string $t) {
            $n = $db->table($t)->count('city');
            Support::assertEquals(3, $n, 'count(city) ignores null');
            return 'ok';
        });
        self::withQb($r, $cat, 'addBinding raw having', function (Connection $db, string $t) {
            $rows = $db->table($t)->select('city', $db->raw('COUNT(*) as c'))
                ->whereNotNull('city')->groupBy('city')
                ->havingRaw('COUNT(*) >= ?', [1])->get();
            Support::assertEquals(2, count($rows), 'havingRaw binding');
            return 'ok';
        });
        self::withQb($r, $cat, 'where with array of conditions', function (Connection $db, string $t) {
            $n = $db->table($t)->where([['city', '=', 'NY'], ['age', '>', 35]])->count();
            Support::assertEquals(1, $n, 'where array conditions');
            return 'ok';
        });
    }

    // ------------------------------------------------------------ aggregate2
    private static function aggregate2(Runner $r): void
    {
        $cat = 'aggregate2';

        self::withQb($r, $cat, 'count with groupBy', function (Connection $db, string $t) {
            $rows = $db->table($t)->select('city', $db->raw('COUNT(*) as c'))
                ->whereNotNull('city')->groupBy('city')->get();
            Support::assertEquals(2, count($rows), 'count groupBy');
            return 'ok';
        });
        self::withQb($r, $cat, 'sum with groupBy', function (Connection $db, string $t) {
            $rows = $db->table($t)->select('city', $db->raw('SUM(age) as s'))
                ->whereNotNull('city')->groupBy('city')->orderBy('city')->get();
            Support::assertEquals(70, (int) $rows[1]->s, 'sum NY = 30+40');
            return 'ok';
        });
        self::withQb($r, $cat, 'avg with groupBy', function (Connection $db, string $t) {
            $v = $db->table($t)->where('city', 'NY')->avg('age');
            Support::assertValueEquals(35, $v, 'avg NY');
            return 'ok';
        });
        self::withQb($r, $cat, 'min/max with groupBy', function (Connection $db, string $t) {
            $rows = $db->table($t)->select('city', $db->raw('MIN(age) as mn'), $db->raw('MAX(age) as mx'))
                ->whereNotNull('city')->groupBy('city')->orderBy('city')->get();
            Support::assertEquals(30, (int) $rows[1]->mn, 'min NY');
            Support::assertEquals(40, (int) $rows[1]->mx, 'max NY');
            return 'ok';
        });
        self::withQb($r, $cat, 'exists with groupBy/having', function (Connection $db, string $t) {
            $exists = $db->table($t)->select('city')->whereNotNull('city')
                ->groupBy('city')->havingRaw('COUNT(*) > 1')->exists();
            Support::assert($exists, 'groupBy having exists');
            return 'ok';
        });
        self::withQb($r, $cat, 'count(distinct col)', function (Connection $db, string $t) {
            $n = $db->table($t)->distinct()->count('city');
            Support::assertEquals(2, $n, 'count distinct city');
            return 'ok';
        });
        self::withQb($r, $cat, 'sum decimal', function (Connection $db, string $t) {
            $v = $db->table($t)->sum('score');
            Support::assertValueEquals(31.0, $v, 'sum score');
            return 'ok';
        });
        self::withQb($r, $cat, 'avg decimal precision', function (Connection $db, string $t) {
            $v = $db->table($t)->avg('score');
            Support::assertValueEquals(7.75, $v, 'avg score');
            return 'ok';
        });
        self::withQb($r, $cat, 'max on date column', function (Connection $db, string $t) {
            $v = $db->table($t)->max('birth');
            Support::assertEquals('1995-07-20', substr((string) $v, 0, 10), 'max birth');
            return 'ok';
        });
        self::withQb($r, $cat, 'min on date column', function (Connection $db, string $t) {
            $v = $db->table($t)->min('birth');
            Support::assertEquals('1985-03-15', substr((string) $v, 0, 10), 'min birth');
            return 'ok';
        });
        self::withQb($r, $cat, 'count() whole table', function (Connection $db, string $t) {
            Support::assertEquals(4, $db->table($t)->count(), 'count all');
            return 'ok';
        });
        self::withQb($r, $cat, 'sum returns 0 on empty filter', function (Connection $db, string $t) {
            $v = $db->table($t)->where('name', 'nobody')->sum('age');
            Support::assertValueEquals(0, $v, 'sum empty = 0');
            return 'ok';
        });
        self::withQb($r, $cat, 'avg returns null on empty', function (Connection $db, string $t) {
            $v = $db->table($t)->where('name', 'nobody')->avg('age');
            Support::assert($v === null, 'avg empty = null');
            return 'ok';
        });
        self::withQb($r, $cat, 'aggregate with where + groupBy + having', function (Connection $db, string $t) {
            $rows = $db->table($t)->select('city', $db->raw('AVG(age) as a'))
                ->where('age', '>', 20)->whereNotNull('city')
                ->groupBy('city')->having('a', '>', 30)->get();
            Support::assert(count($rows) >= 1, 'aggregate composite');
            return 'rows=' . count($rows);
        });
    }

    // ----------------------------------------------------------------- json2
    private static function json2(Runner $r): void
    {
        $cat = 'json';

        $r->add('Eloquent', $cat, 'whereJsonLength', function () {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('json');
            $s->create($tn, function (Blueprint $t) {
                $t->id();
                $t->json('doc')->nullable();
            });
            try {
                $db->table($tn)->insert([
                    ['doc' => json_encode(['list' => [1, 2, 3]])],
                    ['doc' => json_encode(['list' => [1]])],
                ]);
                $n = $db->table($tn)->whereJsonLength('doc->list', 3)->count();
                Support::assertEquals(1, $n, 'whereJsonLength = 3');
                return 'ok';
            } finally {
                $s->dropIfExists($tn);
            }
        });
        $r->add('Eloquent', $cat, 'whereJsonContains', function () {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('json');
            $s->create($tn, function (Blueprint $t) {
                $t->id();
                $t->json('doc')->nullable();
            });
            try {
                $db->table($tn)->insert([
                    ['doc' => json_encode(['roles' => ['admin', 'editor']])],
                    ['doc' => json_encode(['roles' => ['viewer']])],
                ]);
                $n = $db->table($tn)->whereJsonContains('doc->roles', 'admin')->count();
                Support::assertEquals(1, $n, 'whereJsonContains admin');
                return 'ok';
            } finally {
                $s->dropIfExists($tn);
            }
        });
        $r->add('Eloquent', $cat, 'json arrow select', function () {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('json');
            $s->create($tn, function (Blueprint $t) {
                $t->id();
                $t->json('doc')->nullable();
            });
            try {
                $db->table($tn)->insert([['doc' => json_encode(['k' => 42])]]);
                $v = $db->table($tn)->value('doc->k');
                Support::assertEquals(42, (int) $v, 'json arrow select value');
                return 'ok';
            } finally {
                $s->dropIfExists($tn);
            }
        });
        $r->add('Eloquent', $cat, 'json update arrow path', function () {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('json');
            $s->create($tn, function (Blueprint $t) {
                $t->id();
                $t->json('doc')->nullable();
            });
            try {
                $db->table($tn)->insert([['doc' => json_encode(['k' => 1])]]);
                $db->table($tn)->update(['doc->k' => 5]);
                $v = $db->table($tn)->value('doc->k');
                Support::assertEquals(5, (int) $v, 'json arrow update');
                return 'ok';
            } finally {
                $s->dropIfExists($tn);
            }
        });
        $r->add('Eloquent', $cat, 'whereJsonDoesntContain', function () {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('json');
            $s->create($tn, function (Blueprint $t) {
                $t->id();
                $t->json('doc')->nullable();
            });
            try {
                $db->table($tn)->insert([
                    ['doc' => json_encode(['roles' => ['admin']])],
                    ['doc' => json_encode(['roles' => ['viewer']])],
                ]);
                $n = $db->table($tn)->whereJsonDoesntContain('doc->roles', 'admin')->count();
                Support::assertEquals(1, $n, 'whereJsonDoesntContain');
                return 'ok';
            } finally {
                $s->dropIfExists($tn);
            }
        });
        $r->add('Eloquent', $cat, 'json nested arrow where', function () {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('json');
            $s->create($tn, function (Blueprint $t) {
                $t->id();
                $t->json('doc')->nullable();
            });
            try {
                $db->table($tn)->insert([
                    ['doc' => json_encode(['a' => ['b' => 7]])],
                    ['doc' => json_encode(['a' => ['b' => 9]])],
                ]);
                $n = $db->table($tn)->where('doc->a->b', 7)->count();
                Support::assertEquals(1, $n, 'nested arrow where');
                return 'ok';
            } finally {
                $s->dropIfExists($tn);
            }
        });
    }

    // ---------------------------------------------------------------- model2
    private static function model2(Runner $r): void
    {
        $cat = 'model2';
        $cases = [
            'firstOrNew (new, unsaved)' => function () {
                self::buildModelSchema();
                $u = MoUser::firstOrNew(['email' => 'new@x.io'], ['name' => 'New']);
                Support::assert(!$u->exists, 'firstOrNew should be unsaved');
                Support::assertEquals('New', $u->name, 'firstOrNew name');
                Support::assertEquals(0, MoUser::count(), 'firstOrNew did not persist');
                return 'ok';
            },
            'firstOrNew (existing)' => function () {
                self::buildModelSchema();
                MoUser::create(['name' => 'Has', 'email' => 'has@x.io']);
                $u = MoUser::firstOrNew(['email' => 'has@x.io']);
                Support::assert($u->exists, 'firstOrNew existing should be loaded');
                return 'ok';
            },
            'firstOr (callback)' => function () {
                self::buildModelSchema();
                $val = MoUser::where('email', 'none@x.io')->firstOr(fn () => 'fallback');
                Support::assertEquals('fallback', $val, 'firstOr fallback');
                return 'ok';
            },
            'findOrFail throws' => function () {
                self::buildModelSchema();
                $threw = false;
                try {
                    MoUser::findOrFail(999999);
                } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                    $threw = true;
                }
                Support::assert($threw, 'findOrFail should throw ModelNotFoundException');
                return 'ok';
            },
            'findOrFail returns model' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'F']);
                $found = MoUser::findOrFail($u->id);
                Support::assertEquals('F', $found->name, 'findOrFail found');
                return 'ok';
            },
            'isDirty / getDirty' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Clean']);
                Support::assert(!$u->isDirty(), 'should be clean after create');
                $u->name = 'Dirty';
                Support::assert($u->isDirty('name'), 'name should be dirty');
                $dirty = $u->getDirty();
                Support::assert(array_key_exists('name', $dirty), 'getDirty has name');
                return 'ok';
            },
            'wasChanged / getOriginal' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Orig']);
                $u->name = 'Changed';
                // getOriginal reflects the last-synced value (pre-save).
                Support::assertEquals('Orig', $u->getOriginal('name'), 'getOriginal before save');
                $u->save();
                // After save Eloquent re-syncs originals, but wasChanged records
                // that the column changed during this save cycle.
                Support::assert($u->wasChanged('name'), 'wasChanged name');
                return 'ok';
            },
            'fresh()' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Fr']);
                MoUser::where('id', $u->id)->update(['name' => 'Outside']);
                $fresh = $u->fresh();
                Support::assertEquals('Outside', $fresh->name, 'fresh reflects DB');
                Support::assertEquals('Fr', $u->name, 'original instance untouched');
                return 'ok';
            },
            'refresh()' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Rf']);
                MoUser::where('id', $u->id)->update(['name' => 'Outside']);
                $u->refresh();
                Support::assertEquals('Outside', $u->name, 'refresh mutates instance');
                return 'ok';
            },
            'replicate()' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Rep', 'email' => 'rep@x.io']);
                $copy = $u->replicate();
                Support::assert(!$copy->exists, 'replica unsaved');
                $copy->save();
                Support::assert($copy->id !== $u->id, 'replica has new id');
                Support::assertEquals(2, MoUser::count(), 'two rows after replicate save');
                return 'ok';
            },
            'increment on model' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Inc', 'score' => 2]);
                $u->increment('score', 3);
                Support::assertEquals('5.00', (string) MoUser::find($u->id)->score, 'model increment');
                return 'ok';
            },
            'decrement on model' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Dec', 'score' => 10]);
                $u->decrement('score', 4);
                Support::assertEquals('6.00', (string) MoUser::find($u->id)->score, 'model decrement');
                return 'ok';
            },
            'touch()' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'T']);
                $orig = (string) $u->updated_at;
                usleep(1100000);
                $u->touch();
                $after = (string) MoUser::find($u->id)->updated_at;
                Support::assert($after !== '' && $after !== null, 'touch set updated_at');
                return 'ok';
            },
            'save with timestamps off' => function () {
                self::buildModelSchema();
                $u = new MoUser();
                $u->name = 'NoTs';
                $u->timestamps = false;
                $u->save();
                Support::assert($u->created_at === null, 'created_at should be null with timestamps off');
                return 'ok';
            },
            'mass-assignment guarding ($guarded=[] allows all)' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'G', 'email' => 'g@x.io', 'active' => 0]);
                Support::assertEquals('g@x.io', MoUser::find($u->id)->email, 'mass assign email');
                return 'ok';
            },
            'save() returns bool' => function () {
                self::buildModelSchema();
                $u = new MoUser();
                $u->name = 'B';
                $res = $u->save();
                Support::assert($res === true, 'save returns true');
                return 'ok';
            },
            'only() / except()' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'OE', 'email' => 'oe@x.io']);
                $only = $u->only(['name']);
                Support::assert(array_key_exists('name', $only) && !array_key_exists('email', $only), 'only filters');
                $except = $u->except(['email', 'meta']);
                Support::assert(!array_key_exists('email', $except), 'except removes email');
                return 'ok';
            },
            'make() / newInstance()' => function () {
                self::buildModelSchema();
                $u = (new MoUser())->newInstance(['name' => 'NI']);
                Support::assert(!$u->exists, 'newInstance unsaved');
                Support::assertEquals('NI', $u->name, 'newInstance attr');
                return 'ok';
            },
            'push() saves model + relations' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Push']);
                $p = $u->posts()->create(['title' => 'rel']);
                // Reload with the relation so push() cascades to it.
                $loaded = MoUser::with('posts')->find($u->id);
                $loaded->name = 'Pushed';
                $loaded->posts->first()->title = 'rel2';
                $ok = $loaded->push();
                Support::assert($ok, 'push returned true');
                Support::assertEquals('Pushed', MoUser::find($u->id)->name, 'push saved model');
                Support::assertEquals('rel2', MoPost::find($p->id)->title, 'push saved relation');
                return 'ok';
            },
            'value cast round-trip (array)' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'RT', 'meta' => ['x' => [1, 2, 3], 'y' => 'z']]);
                $back = MoUser::find($u->id);
                Support::assertEquals(3, count($back->meta['x']), 'array cast round-trip count');
                Support::assertEquals('z', $back->meta['y'], 'array cast round-trip value');
                return 'ok';
            },
            'updateOrCreate returns model' => function () {
                self::buildModelSchema();
                $u = MoUser::updateOrCreate(['email' => 'uoc@x.io'], ['name' => 'A']);
                Support::assert($u->exists, 'updateOrCreate returns persisted model');
                $u2 = MoUser::updateOrCreate(['email' => 'uoc@x.io'], ['name' => 'B']);
                Support::assertEquals($u->id, $u2->id, 'updateOrCreate same row');
                Support::assertEquals('B', MoUser::find($u->id)->name, 'updateOrCreate updated');
                return 'ok';
            },
            'getKey / getKeyName' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'K']);
                Support::assertEquals('id', $u->getKeyName(), 'key name');
                Support::assertEquals($u->id, $u->getKey(), 'get key');
                return 'ok';
            },
            'toArray / toJson + hidden via model2 user' => function () {
                self::buildUser2Schema();
                $u = MoUser2::create(['name' => 'arr', 'secret' => 'shh', 'options' => ['k' => 1]]);
                $arr = $u->toArray();
                Support::assert(!array_key_exists('secret', $arr), 'hidden hides secret');
                Support::assert(array_key_exists('display_name', $arr), 'appends adds display_name');
                $json = $u->toJson();
                Support::assert(is_string($json) && str_contains($json, 'display_name'), 'toJson has appended attr');
                return 'ok';
            },
            'makeVisible / makeHidden' => function () {
                self::buildUser2Schema();
                $u = MoUser2::create(['name' => 'vis', 'secret' => 'shh']);
                $arr = $u->makeVisible('secret')->toArray();
                Support::assert(array_key_exists('secret', $arr), 'makeVisible reveals');
                return 'ok';
            },
            'firstOrCreate returns existing' => function () {
                self::buildModelSchema();
                $a = MoUser::create(['name' => 'FOC', 'email' => 'foc@x.io']);
                $b = MoUser::firstOrCreate(['email' => 'foc@x.io'], ['name' => 'other']);
                Support::assertEquals($a->id, $b->id, 'firstOrCreate returns existing row');
                return 'ok';
            },
            'find returns null for missing' => function () {
                self::buildModelSchema();
                Support::assert(MoUser::find(123456) === null, 'find missing null');
                return 'ok';
            },
            'findMany' => function () {
                self::buildModelSchema();
                $a = MoUser::create(['name' => 'a']);
                $b = MoUser::create(['name' => 'b']);
                MoUser::create(['name' => 'c']);
                $found = MoUser::findMany([$a->id, $b->id]);
                Support::assertEquals(2, $found->count(), 'findMany count');
                return 'ok';
            },
            'pluck on model query' => function () {
                self::buildModelSchema();
                MoUser::create(['name' => 'p1']);
                MoUser::create(['name' => 'p2']);
                $names = MoUser::orderBy('id')->pluck('name');
                Support::assertEquals('p1', $names[0], 'model pluck');
                return 'ok';
            },
            'count via aggregate on model' => function () {
                self::buildModelSchema();
                MoUser::create(['name' => 'x', 'score' => 3]);
                MoUser::create(['name' => 'y', 'score' => 7]);
                Support::assertValueEquals(10, MoUser::sum('score'), 'model sum');
                Support::assertValueEquals(5, MoUser::avg('score'), 'model avg');
                return 'ok';
            },
            'getChanges after save' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'gc']);
                $u->name = 'gc2';
                $u->save();
                $changes = $u->getChanges();
                Support::assert(array_key_exists('name', $changes), 'getChanges has name');
                return 'ok';
            },
            'wasRecentlyCreated flag' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'wr']);
                Support::assert($u->wasRecentlyCreated === true, 'wasRecentlyCreated true after create');
                $found = MoUser::find($u->id);
                Support::assert($found->wasRecentlyCreated === false, 'wasRecentlyCreated false on fetch');
                return 'ok';
            },
            'is() identity comparison' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'idc']);
                $same = MoUser::find($u->id);
                Support::assert($u->is($same), 'is() true for same row');
                return 'ok';
            },
        ];
        foreach ($cases as $name => $fn) {
            $r->add('Eloquent', $cat, $name, $fn);
        }
    }

    // ----------------------------------------------------------------- scopes
    private static function scopes(Runner $r): void
    {
        $cat = 'scope';
        $cases = [
            'local scope (active)' => function () {
                self::buildUser2Schema();
                MoUser2::create(['name' => 'on', 'is_active' => 1]);
                MoUser2::create(['name' => 'off', 'is_active' => 0]);
                Support::assertEquals(1, MoUser2::active()->count(), 'local scope active');
                return 'ok';
            },
            'local scope with argument (ofRating)' => function () {
                self::buildUser2Schema();
                MoUser2::create(['name' => 'hi', 'rating' => 4.5]);
                MoUser2::create(['name' => 'lo', 'rating' => 1.0]);
                Support::assertEquals(1, MoUser2::ofRating(3.0)->count(), 'scope with arg');
                return 'ok';
            },
            'chained local scopes' => function () {
                self::buildUser2Schema();
                MoUser2::create(['name' => 'a', 'is_active' => 1, 'rating' => 5]);
                MoUser2::create(['name' => 'b', 'is_active' => 1, 'rating' => 1]);
                MoUser2::create(['name' => 'c', 'is_active' => 0, 'rating' => 5]);
                Support::assertEquals(1, MoUser2::active()->ofRating(3.0)->count(), 'chained scopes');
                return 'ok';
            },
            'global scope filters by default' => function () {
                self::buildScopedSchema();
                $db = self::db();
                $db->table('mo_scoped')->insert([
                    ['title' => 'pub', 'published' => 1],
                    ['title' => 'draft', 'published' => 0],
                ]);
                Support::assertEquals(1, MoScoped::count(), 'global scope hides drafts');
                return 'ok';
            },
            'withoutGlobalScope reveals all' => function () {
                self::buildScopedSchema();
                $db = self::db();
                $db->table('mo_scoped')->insert([
                    ['title' => 'pub', 'published' => 1],
                    ['title' => 'draft', 'published' => 0],
                ]);
                $n = MoScoped::withoutGlobalScope(MoScoped::PUBLISHED_SCOPE)->count();
                Support::assertEquals(2, $n, 'withoutGlobalScope all');
                return 'ok';
            },
            'withoutGlobalScopes reveals all' => function () {
                self::buildScopedSchema();
                $db = self::db();
                $db->table('mo_scoped')->insert([
                    ['title' => 'pub', 'published' => 1],
                    ['title' => 'draft', 'published' => 0],
                ]);
                $n = MoScoped::withoutGlobalScopes()->count();
                Support::assertEquals(2, $n, 'withoutGlobalScopes all');
                return 'ok';
            },
        ];
        foreach ($cases as $name => $fn) {
            $r->add('Eloquent', $cat, $name, $fn);
        }
    }

    // ----------------------------------------------------------------- casts2
    private static function casts2(Runner $r): void
    {
        $cat = 'casts';
        $cases = [
            'array cast' => function () {
                self::buildUser2Schema();
                $u = MoUser2::create(['name' => 'a', 'options' => ['x' => 1, 'y' => 2]]);
                $back = MoUser2::find($u->id);
                Support::assert(is_array($back->options), 'options is array');
                Support::assertEquals(2, $back->options['y'], 'array cast value');
                return 'ok';
            },
            'object cast' => function () {
                self::buildUser2Schema();
                $u = MoUser2::create(['name' => 'o', 'profile' => ['nick' => 'neo']]);
                $back = MoUser2::find($u->id);
                Support::assert(is_object($back->profile), 'profile is object');
                Support::assertEquals('neo', $back->profile->nick, 'object cast value');
                return 'ok';
            },
            'collection cast' => function () {
                self::buildUser2Schema();
                $u = MoUser2::create(['name' => 'c', 'tags' => ['a', 'b', 'c']]);
                $back = MoUser2::find($u->id);
                Support::assert($back->tags instanceof \Illuminate\Support\Collection, 'tags is Collection');
                Support::assertEquals(3, $back->tags->count(), 'collection cast count');
                return 'ok';
            },
            'boolean cast' => function () {
                self::buildUser2Schema();
                $u = MoUser2::create(['name' => 'b', 'is_active' => 1]);
                Support::assert(MoUser2::find($u->id)->is_active === true, 'boolean cast true');
                return 'ok';
            },
            'integer cast' => function () {
                self::buildUser2Schema();
                $u = MoUser2::create(['name' => 'i', 'login_count' => '7']);
                Support::assert(MoUser2::find($u->id)->login_count === 7, 'integer cast');
                return 'ok';
            },
            'float cast' => function () {
                self::buildUser2Schema();
                $u = MoUser2::create(['name' => 'f', 'rating' => '3.5']);
                Support::assert(MoUser2::find($u->id)->rating === 3.5, 'float cast');
                return 'ok';
            },
            'decimal:2 cast' => function () {
                self::buildUser2Schema();
                $u = MoUser2::create(['name' => 'd', 'balance' => 12.5]);
                Support::assertEquals('12.50', (string) MoUser2::find($u->id)->balance, 'decimal:2 cast');
                return 'ok';
            },
            'date cast' => function () {
                self::buildUser2Schema();
                $u = MoUser2::create(['name' => 'dt', 'born_on' => '1990-05-20']);
                $back = MoUser2::find($u->id);
                Support::assert($back->born_on instanceof \Illuminate\Support\Carbon, 'born_on is Carbon');
                Support::assertEquals('1990-05-20', $back->born_on->format('Y-m-d'), 'date cast value');
                return 'ok';
            },
            'datetime cast' => function () {
                self::buildUser2Schema();
                $u = MoUser2::create(['name' => 'sn', 'seen_at' => '2022-03-04 10:20:30']);
                $back = MoUser2::find($u->id);
                Support::assert($back->seen_at instanceof \Illuminate\Support\Carbon, 'seen_at is Carbon');
                Support::assertEquals('2022-03-04 10:20:30', $back->seen_at->format('Y-m-d H:i:s'), 'datetime cast value');
                return 'ok';
            },
            'timestamp columns cast to Carbon' => function () {
                self::buildUser2Schema();
                $u = MoUser2::create(['name' => 'ts']);
                $back = MoUser2::find($u->id);
                Support::assert($back->created_at instanceof \Illuminate\Support\Carbon, 'created_at Carbon');
                return 'ok';
            },
            'accessor (get) transforms name' => function () {
                self::buildUser2Schema();
                $u = MoUser2::create(['name' => 'neo']); // mutator lowercases on set
                // accessor ucfirsts on get
                Support::assertEquals('Neo', MoUser2::find($u->id)->name, 'accessor ucfirst');
                return 'ok';
            },
            'mutator (set) transforms name' => function () {
                self::buildUser2Schema();
                $u = MoUser2::create(['name' => 'MIXEDcase']);
                // raw stored value should be lowercased by mutator
                $raw = self::db()->table('mo_users2')->where('id', $u->id)->value('name');
                Support::assertEquals('mixedcase', $raw, 'mutator lowercased stored value');
                return 'ok';
            },
            'appends computed attribute' => function () {
                self::buildUser2Schema();
                $u = MoUser2::create(['name' => 'zed']);
                Support::assertEquals('@zed', $u->display_name, 'appended display_name');
                return 'ok';
            },
            'array cast empty array round-trip' => function () {
                self::buildUser2Schema();
                $u = MoUser2::create(['name' => 'e', 'options' => []]);
                $back = MoUser2::find($u->id);
                Support::assert(is_array($back->options) && count($back->options) === 0, 'empty array round-trip');
                return 'ok';
            },
            'array cast nested round-trip' => function () {
                self::buildUser2Schema();
                $u = MoUser2::create(['name' => 'n', 'options' => ['deep' => ['a' => ['b' => 1]]]]);
                $back = MoUser2::find($u->id);
                Support::assertEquals(1, $back->options['deep']['a']['b'], 'nested array round-trip');
                return 'ok';
            },
            'cast is applied on isDirty comparison' => function () {
                self::buildUser2Schema();
                $u = MoUser2::create(['name' => 'cmp', 'options' => ['a' => 1]]);
                $u->options = ['a' => 1];
                Support::assert(!$u->isDirty('options'), 'identical array cast not dirty');
                $u->options = ['a' => 2];
                Support::assert($u->isDirty('options'), 'changed array cast dirty');
                return 'ok';
            },
        ];
        foreach ($cases as $name => $fn) {
            $r->add('Eloquent', $cat, $name, $fn);
        }
    }

    // ------------------------------------------------------------- softdelete
    private static function softDeletes(Runner $r): void
    {
        $cat = 'softdelete';
        $cases = [
            'delete() soft-deletes + default scope hides' => function () {
                self::buildSoftSchema();
                $i = MoSoftItem::create(['name' => 'sd', 'qty' => 1]);
                $i->delete();
                Support::assertEquals(0, MoSoftItem::count(), 'soft-deleted hidden from default query');
                $raw = self::db()->table('mo_soft_items')->count();
                Support::assertEquals(1, $raw, 'row still physically present');
                return 'ok';
            },
            'trashed()' => function () {
                self::buildSoftSchema();
                $i = MoSoftItem::create(['name' => 'tr']);
                Support::assert(!$i->trashed(), 'not trashed before delete');
                $i->delete();
                Support::assert($i->trashed(), 'trashed after delete');
                return 'ok';
            },
            'withTrashed()' => function () {
                self::buildSoftSchema();
                $i = MoSoftItem::create(['name' => 'wt']);
                $i->delete();
                Support::assertEquals(1, MoSoftItem::withTrashed()->count(), 'withTrashed sees deleted');
                return 'ok';
            },
            'onlyTrashed()' => function () {
                self::buildSoftSchema();
                MoSoftItem::create(['name' => 'live']);
                $d = MoSoftItem::create(['name' => 'dead']);
                $d->delete();
                Support::assertEquals(1, MoSoftItem::onlyTrashed()->count(), 'onlyTrashed count');
                return 'ok';
            },
            'restore()' => function () {
                self::buildSoftSchema();
                $i = MoSoftItem::create(['name' => 'rs']);
                $i->delete();
                $i->restore();
                Support::assertEquals(1, MoSoftItem::count(), 'restore brings row back');
                Support::assert(!$i->trashed(), 'not trashed after restore');
                return 'ok';
            },
            'forceDelete()' => function () {
                self::buildSoftSchema();
                $i = MoSoftItem::create(['name' => 'fd']);
                $i->forceDelete();
                $raw = self::db()->table('mo_soft_items')->count();
                Support::assertEquals(0, $raw, 'forceDelete physically removes');
                return 'ok';
            },
            'deleted_at populated on delete' => function () {
                self::buildSoftSchema();
                $i = MoSoftItem::create(['name' => 'da']);
                $i->delete();
                $val = self::db()->table('mo_soft_items')->where('id', $i->id)->value('deleted_at');
                Support::assert($val !== null, 'deleted_at set');
                return 'ok';
            },
            'restore() clears deleted_at' => function () {
                self::buildSoftSchema();
                $i = MoSoftItem::create(['name' => 'cd']);
                $i->delete();
                $i->restore();
                $val = self::db()->table('mo_soft_items')->where('id', $i->id)->value('deleted_at');
                Support::assert($val === null, 'deleted_at cleared on restore');
                return 'ok';
            },
        ];
        foreach ($cases as $name => $fn) {
            $r->add('Eloquent', $cat, $name, $fn);
        }
    }

    // ------------------------------------------------------------ relationship2
    private static function relationships2(Runner $r): void
    {
        $cat = 'relationship2';

        // ---- through chain + polymorphic many-to-many (own schema) ----
        $through = [
            'hasManyThrough' => function () {
                self::buildThroughSchema();
                $c = MoCountry::create(['name' => 'Wonderland']);
                $cz = MoCitizen::create(['country_id' => $c->id, 'name' => 'Alice']);
                MoArticle::create(['citizen_id' => $cz->id, 'title' => 'A1']);
                MoArticle::create(['citizen_id' => $cz->id, 'title' => 'A2']);
                Support::assertEquals(2, MoCountry::find($c->id)->articles()->count(), 'hasManyThrough count');
                return 'ok';
            },
            'hasOneThrough' => function () {
                self::buildThroughSchema();
                $c = MoCountry::create(['name' => 'Oz']);
                $cz = MoCitizen::create(['country_id' => $c->id, 'name' => 'Dorothy']);
                MoArticle::create(['citizen_id' => $cz->id, 'title' => 'First']);
                $a = MoCountry::find($c->id)->firstArticle;
                Support::assert($a !== null, 'hasOneThrough returned null');
                Support::assertEquals('First', $a->title, 'hasOneThrough title');
                return 'ok';
            },
            'hasManyThrough eager load' => function () {
                self::buildThroughSchema();
                $c = MoCountry::create(['name' => 'Narnia']);
                $cz = MoCitizen::create(['country_id' => $c->id, 'name' => 'Lucy']);
                MoArticle::create(['citizen_id' => $cz->id, 'title' => 'X']);
                $loaded = MoCountry::with('articles')->find($c->id);
                Support::assertEquals(1, $loaded->articles->count(), 'eager hasManyThrough');
                return 'ok';
            },
            'morphToMany attach + count' => function () {
                self::buildThroughSchema();
                $a = MoArticle::create(['citizen_id' => 0, 'title' => 'Tagged']);
                $t1 = MoTag::create(['label' => 'php']);
                $t2 = MoTag::create(['label' => 'db']);
                $a->tags()->attach([$t1->id, $t2->id]);
                Support::assertEquals(2, MoArticle::find($a->id)->tags()->count(), 'morphToMany count');
                return 'ok';
            },
            'morphedByMany inverse' => function () {
                self::buildThroughSchema();
                $a1 = MoArticle::create(['citizen_id' => 0, 'title' => 'One']);
                $a2 = MoArticle::create(['citizen_id' => 0, 'title' => 'Two']);
                $t = MoTag::create(['label' => 'shared']);
                $a1->tags()->attach($t->id);
                $a2->tags()->attach($t->id);
                Support::assertEquals(2, MoTag::find($t->id)->articles()->count(), 'morphedByMany count');
                return 'ok';
            },
            'morphToMany detach' => function () {
                self::buildThroughSchema();
                $a = MoArticle::create(['citizen_id' => 0, 'title' => 'D']);
                $t1 = MoTag::create(['label' => 'a']);
                $t2 = MoTag::create(['label' => 'b']);
                $a->tags()->attach([$t1->id, $t2->id]);
                $a->tags()->detach($t1->id);
                Support::assertEquals(1, MoArticle::find($a->id)->tags()->count(), 'morphToMany detach');
                return 'ok';
            },
            'morphToMany taggable_type recorded' => function () {
                self::buildThroughSchema();
                $a = MoArticle::create(['citizen_id' => 0, 'title' => 'T']);
                $t = MoTag::create(['label' => 'x']);
                $a->tags()->attach($t->id);
                $type = self::db()->table('mo_taggables')->value('taggable_type');
                Support::assert(str_contains((string) $type, 'MoArticle'), 'morph type stored');
                return 'ok';
            },
        ];
        foreach ($through as $name => $fn) {
            $r->add('Eloquent', $cat, $name, $fn);
        }

        // ---- eager-loading & aggregate variants on the mo_users chain ----
        $cases = [
            'nested eager load with(posts.comments)' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'N']);
                $p = $u->posts()->create(['title' => 'P']);
                $p->comments()->create(['body' => 'c1']);
                $loaded = MoUser::with('posts.comments')->find($u->id);
                Support::assertEquals(1, $loaded->posts->first()->comments->count(), 'nested eager');
                return 'ok';
            },
            'constrained eager load closure' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'C']);
                $u->posts()->create(['title' => 'keep', 'published' => 1]);
                $u->posts()->create(['title' => 'drop', 'published' => 0]);
                $loaded = MoUser::with(['posts' => fn ($q) => $q->where('published', 1)])->find($u->id);
                Support::assertEquals(1, $loaded->posts->count(), 'constrained eager');
                return 'ok';
            },
            'eager load with select columns' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'S']);
                $u->posts()->create(['title' => 'P']);
                $loaded = MoUser::with(['posts' => fn ($q) => $q->select('id', 'user_id', 'title')])->find($u->id);
                Support::assertEquals('P', $loaded->posts->first()->title, 'eager select');
                return 'ok';
            },
            'load() lazy eager' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'L']);
                $u->posts()->create(['title' => 'P']);
                $fresh = MoUser::find($u->id);
                $fresh->load('posts');
                Support::assert($fresh->relationLoaded('posts'), 'load() set relation');
                Support::assertEquals(1, $fresh->posts->count(), 'load count');
                return 'ok';
            },
            'loadMissing()' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'LM']);
                $u->posts()->create(['title' => 'P']);
                $fresh = MoUser::find($u->id);
                $fresh->loadMissing('posts');
                Support::assertEquals(1, $fresh->posts->count(), 'loadMissing');
                return 'ok';
            },
            'loadCount()' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'LC']);
                $u->posts()->create(['title' => 'P1']);
                $u->posts()->create(['title' => 'P2']);
                $fresh = MoUser::find($u->id);
                $fresh->loadCount('posts');
                Support::assertEquals(2, $fresh->posts_count, 'loadCount');
                return 'ok';
            },
            'withSum()' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'WS']);
                $u->posts()->create(['title' => 'P1', 'published' => 1]);
                $u->posts()->create(['title' => 'P2', 'published' => 1]);
                $loaded = MoUser::withSum('posts', 'published')->find($u->id);
                Support::assertEquals(2, (int) $loaded->posts_sum_published, 'withSum');
                return 'ok';
            },
            'withAvg()' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'WA']);
                $u->posts()->create(['title' => 'P1', 'published' => 1]);
                $u->posts()->create(['title' => 'P2', 'published' => 0]);
                $loaded = MoUser::withAvg('posts', 'published')->find($u->id);
                Support::assertValueEquals(0.5, $loaded->posts_avg_published, 'withAvg');
                return 'ok';
            },
            'withMax() / withMin()' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'WM']);
                $u->posts()->create(['title' => 'P1', 'published' => 1]);
                $u->posts()->create(['title' => 'P2', 'published' => 0]);
                $loaded = MoUser::withMax('posts', 'published')->withMin('posts', 'published')->find($u->id);
                Support::assertEquals(1, (int) $loaded->posts_max_published, 'withMax');
                Support::assertEquals(0, (int) $loaded->posts_min_published, 'withMin');
                return 'ok';
            },
            'withExists()' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'WE']);
                $u->posts()->create(['title' => 'P']);
                $loaded = MoUser::withExists('posts')->find($u->id);
                Support::assert((bool) $loaded->posts_exists, 'withExists true');
                return 'ok';
            },
            'whereHas() with closure' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'WH']);
                $u->posts()->create(['title' => 'match', 'published' => 1]);
                MoUser::create(['name' => 'none']);
                $n = MoUser::whereHas('posts', fn ($q) => $q->where('published', 1))->count();
                Support::assertEquals(1, $n, 'whereHas closure');
                return 'ok';
            },
            'whereDoesntHave()' => function () {
                self::buildModelSchema();
                $a = MoUser::create(['name' => 'has']);
                $a->posts()->create(['title' => 'P']);
                MoUser::create(['name' => 'hasnt']);
                $n = MoUser::whereDoesntHave('posts')->count();
                Support::assertEquals(1, $n, 'whereDoesntHave');
                return 'ok';
            },
            'orWhereHas()' => function () {
                self::buildModelSchema();
                $a = MoUser::create(['name' => 'withpost']);
                $a->posts()->create(['title' => 'P']);
                MoUser::create(['name' => 'special']);
                $n = MoUser::where('name', 'special')->orWhereHas('posts')->count();
                Support::assertEquals(2, $n, 'orWhereHas');
                return 'ok';
            },
            'has() with operator + count' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'H']);
                $u->posts()->create(['title' => 'P1']);
                $u->posts()->create(['title' => 'P2']);
                MoUser::create(['name' => 'few'])->posts()->create(['title' => 'one']);
                Support::assertEquals(1, MoUser::has('posts', '>=', 2)->count(), 'has >= 2');
                return 'ok';
            },
            'belongsToMany withPivot extra column' => function () {
                self::buildModelSchema();
                $db = self::db();
                $db->statement('ALTER TABLE mo_role_user ADD COLUMN assigned_by VARCHAR(50) NULL');
                $u = MoUser::create(['name' => 'P']);
                $role = MoRole::create(['name' => 'admin']);
                $u->roles()->attach($role->id, ['assigned_by' => 'system']);
                $pivotVal = $db->table('mo_role_user')->where('user_id', $u->id)->value('assigned_by');
                Support::assertEquals('system', $pivotVal, 'pivot extra column stored');
                return 'ok';
            },
            'belongsToMany wherePivot' => function () {
                self::buildModelSchema();
                $db = self::db();
                $db->statement('ALTER TABLE mo_role_user ADD COLUMN level INT NULL');
                $u = MoUser::create(['name' => 'WP']);
                $r1 = MoRole::create(['name' => 'a']);
                $r2 = MoRole::create(['name' => 'b']);
                $u->roles()->attach($r1->id, ['level' => 1]);
                $u->roles()->attach($r2->id, ['level' => 2]);
                $rel = MoUser::find($u->id)->roles()->withPivot('level')->wherePivot('level', 2);
                Support::assertEquals(1, $rel->count(), 'wherePivot count');
                return 'ok';
            },
            'belongsToMany syncWithoutDetaching' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'SW']);
                $r1 = MoRole::create(['name' => 'a']);
                $r2 = MoRole::create(['name' => 'b']);
                $u->roles()->attach($r1->id);
                $u->roles()->syncWithoutDetaching([$r2->id]);
                Support::assertEquals(2, MoUser::find($u->id)->roles()->count(), 'syncWithoutDetaching keeps both');
                return 'ok';
            },
            'belongsToMany toggle' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'TG']);
                $r1 = MoRole::create(['name' => 'a']);
                $u->roles()->attach($r1->id);
                $u->roles()->toggle([$r1->id]); // should detach
                Support::assertEquals(0, MoUser::find($u->id)->roles()->count(), 'toggle detached');
                return 'ok';
            },
            'belongsToMany updateExistingPivot' => function () {
                self::buildModelSchema();
                $db = self::db();
                $db->statement('ALTER TABLE mo_role_user ADD COLUMN level INT NULL');
                $u = MoUser::create(['name' => 'UP']);
                $role = MoRole::create(['name' => 'a']);
                $u->roles()->attach($role->id, ['level' => 1]);
                $u->roles()->updateExistingPivot($role->id, ['level' => 9]);
                $v = $db->table('mo_role_user')->where('user_id', $u->id)->value('level');
                Support::assertEquals(9, $v, 'updateExistingPivot');
                return 'ok';
            },
            'hasMany save()' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'SV']);
                $p = new MoPost(['title' => 'saved']);
                $u->posts()->save($p);
                Support::assertEquals($u->id, MoPost::find($p->id)->user_id, 'hasMany save sets fk');
                return 'ok';
            },
            'hasMany saveMany()' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'SM']);
                $u->posts()->saveMany([new MoPost(['title' => 'a']), new MoPost(['title' => 'b'])]);
                Support::assertEquals(2, MoUser::find($u->id)->posts()->count(), 'saveMany');
                return 'ok';
            },
            'hasMany createMany()' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'CM']);
                $u->posts()->createMany([['title' => 'a'], ['title' => 'b'], ['title' => 'c']]);
                Support::assertEquals(3, MoUser::find($u->id)->posts()->count(), 'createMany');
                return 'ok';
            },
            'hasMany firstOrCreate on relation' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'FC']);
                $u->posts()->firstOrCreate(['title' => 'uniq']);
                $u->posts()->firstOrCreate(['title' => 'uniq']);
                Support::assertEquals(1, MoUser::find($u->id)->posts()->where('title', 'uniq')->count(), 'rel firstOrCreate dedup');
                return 'ok';
            },
            'belongsTo associate()' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'AS']);
                $p = new MoPost(['title' => 'P']);
                $p->user()->associate($u);
                $p->save();
                Support::assertEquals($u->id, MoPost::find($p->id)->user_id, 'associate set fk');
                return 'ok';
            },
            'belongsTo dissociate()' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'DS']);
                $p = MoPost::create(['title' => 'P', 'user_id' => $u->id]);
                $p->user()->dissociate();
                $p->save();
                Support::assert(MoPost::find($p->id)->user_id === null, 'dissociate nulled fk');
                return 'ok';
            },
        ];
        foreach ($cases as $name => $fn) {
            $r->add('Eloquent', $cat, $name, $fn);
        }
    }

    // ------------------------------------------------------------ pagination2
    private static function pagination2(Runner $r): void
    {
        $cat = 'pagination2';

        self::withQb($r, $cat, 'paginate total/perPage/currentPage', function (Connection $db, string $t) {
            $p = $db->table($t)->orderBy('id')->paginate(perPage: 2, page: 1);
            Support::assertEquals(4, $p->total(), 'total');
            Support::assertEquals(2, $p->perPage(), 'perPage');
            Support::assertEquals(1, $p->currentPage(), 'currentPage');
            Support::assertEquals(2, $p->lastPage(), 'lastPage');
            return 'ok';
        });
        self::withQb($r, $cat, 'paginate page 2 items', function (Connection $db, string $t) {
            $p = $db->table($t)->orderBy('id')->paginate(perPage: 2, page: 2);
            Support::assertEquals(2, $p->count(), 'page 2 count');
            Support::assertEquals('carol', $p->items()[0]->name, 'page 2 first item');
            return 'ok';
        });
        self::withQb($r, $cat, 'paginate hasMorePages', function (Connection $db, string $t) {
            $p1 = $db->table($t)->orderBy('id')->paginate(perPage: 2, page: 1);
            $p2 = $db->table($t)->orderBy('id')->paginate(perPage: 2, page: 2);
            Support::assert($p1->hasMorePages(), 'page1 has more');
            Support::assert(!$p2->hasMorePages(), 'page2 no more');
            return 'ok';
        });
        self::withQb($r, $cat, 'simplePaginate', function (Connection $db, string $t) {
            $p = $db->table($t)->orderBy('id')->simplePaginate(perPage: 2, page: 1);
            Support::assertEquals(2, $p->count(), 'simplePaginate count');
            Support::assert($p->hasMorePages(), 'simplePaginate hasMore');
            return 'ok';
        });
        self::withQb($r, $cat, 'cursorPaginate', function (Connection $db, string $t) {
            $p = $db->table($t)->orderBy('id')->cursorPaginate(2);
            Support::assertEquals(2, $p->count(), 'cursorPaginate count');
            Support::assert($p->hasMorePages(), 'cursorPaginate hasMore');
            return 'ok';
        });
        self::withQb($r, $cat, 'cursorPaginate next cursor', function (Connection $db, string $t) {
            $first = $db->table($t)->orderBy('id')->cursorPaginate(2);
            $cursor = $first->nextCursor();
            Support::assert($cursor !== null, 'next cursor present');
            $second = $db->table($t)->orderBy('id')->cursorPaginate(2, ['*'], 'cursor', $cursor);
            Support::assertEquals('carol', $second->items()[0]->name, 'cursor second page first');
            return 'ok';
        });

        // Eloquent-model paginate (separate isolation)
        $r->add('Eloquent', $cat, 'Model paginate', function () {
            self::buildModelSchema();
            for ($i = 0; $i < 5; $i++) {
                MoUser::create(['name' => 'U' . $i]);
            }
            $p = MoUser::orderBy('id')->paginate(perPage: 2, page: 2);
            Support::assertEquals(5, $p->total(), 'model paginate total');
            Support::assertEquals(3, $p->lastPage(), 'model paginate lastPage');
            Support::assertEquals(2, $p->count(), 'model paginate page count');
            return 'ok';
        });
        $r->add('Eloquent', $cat, 'Model simplePaginate', function () {
            self::buildModelSchema();
            for ($i = 0; $i < 5; $i++) {
                MoUser::create(['name' => 'U' . $i]);
            }
            $p = MoUser::orderBy('id')->simplePaginate(perPage: 2, page: 1);
            Support::assertEquals(2, $p->count(), 'model simplePaginate count');
            return 'ok';
        });
    }

    // ----------------------------------------------------------- transactions2
    private static function transactions2(Runner $r): void
    {
        $cat = 'transaction2';
        $cases = [
            'transaction returns value' => function () {
                self::buildModelSchema();
                $db = self::db();
                $val = $db->transaction(function () {
                    MoUser::create(['name' => 'RV']);
                    return 'returned';
                });
                Support::assertEquals('returned', $val, 'transaction return value');
                Support::assertEquals(1, MoUser::count(), 'committed');
                return 'ok';
            },
            'transaction rollback on exception' => function () {
                self::buildModelSchema();
                $db = self::db();
                try {
                    $db->transaction(function () {
                        MoUser::create(['name' => 'X']);
                        throw new \RuntimeException('boom');
                    });
                } catch (\RuntimeException) {
                    // expected
                }
                Support::assertEquals(0, MoUser::count(), 'rolled back on exception');
                return 'ok';
            },
            'nested savepoint inner rollback' => function () {
                self::buildModelSchema();
                $db = self::db();
                $db->transaction(function () use ($db) {
                    MoUser::create(['name' => 'Outer']);
                    try {
                        $db->transaction(function () {
                            MoUser::create(['name' => 'Inner']);
                            throw new \RuntimeException('inner');
                        });
                    } catch (\RuntimeException) {
                    }
                });
                // Correct savepoint behaviour => only Outer survives.
                Support::assertEquals(1, MoUser::count(), 'nested savepoint: only outer persists');
                return 'ok';
            },
            'transactionLevel tracking' => function () {
                self::buildModelSchema();
                $db = self::db();
                Support::assertEquals(0, $db->transactionLevel(), 'level 0 outside');
                $db->beginTransaction();
                Support::assertEquals(1, $db->transactionLevel(), 'level 1 after begin');
                $db->beginTransaction();
                Support::assertEquals(2, $db->transactionLevel(), 'level 2 nested begin');
                $db->rollBack();
                $db->rollBack();
                Support::assertEquals(0, $db->transactionLevel(), 'level back to 0');
                return 'ok';
            },
            'manual savepoint rollBack to level' => function () {
                self::buildModelSchema();
                $db = self::db();
                $db->beginTransaction();
                MoUser::create(['name' => 'Keep']);
                $db->beginTransaction(); // savepoint
                MoUser::create(['name' => 'Drop']);
                $db->rollBack(); // back to savepoint
                $db->commit();
                Support::assertEquals(1, MoUser::count(), 'savepoint partial rollback keeps outer');
                return 'ok';
            },
            'afterCommit callback fires' => function () {
                self::buildModelSchema();
                $db = self::db();
                // Capsule (used standalone) does not wire a transactions manager
                // by default, which afterCommit requires; provide one so this
                // exercises MatrixOne's commit path rather than a Laravel gap.
                $db->setTransactionManager(new \Illuminate\Database\DatabaseTransactionsManager());
                $fired = false;
                try {
                    $db->transaction(function () use ($db, &$fired) {
                        MoUser::create(['name' => 'AC']);
                        $db->afterCommit(function () use (&$fired) {
                            $fired = true;
                        });
                    });
                } finally {
                    $db->unsetTransactionManager();
                }
                Support::assert($fired, 'afterCommit fired');
                return 'ok';
            },
            'commit() persists data' => function () {
                self::buildModelSchema();
                $db = self::db();
                $db->beginTransaction();
                MoUser::create(['name' => 'Cm']);
                $db->commit();
                Support::assertEquals(1, MoUser::count(), 'commit persisted');
                return 'ok';
            },
            'lockForUpdate in transaction' => function () {
                self::buildModelSchema();
                $db = self::db();
                MoUser::create(['name' => 'Lk']);
                $rows = $db->transaction(function () {
                    return MoUser::where('name', 'Lk')->lockForUpdate()->get();
                });
                Support::assertEquals(1, $rows->count(), 'lockForUpdate returned row');
                return 'ok';
            },
            'sharedLock in transaction' => function () {
                self::buildModelSchema();
                $db = self::db();
                MoUser::create(['name' => 'Sh']);
                $rows = $db->transaction(function () {
                    return MoUser::where('name', 'Sh')->sharedLock()->get();
                });
                Support::assertEquals(1, $rows->count(), 'sharedLock returned row');
                return 'ok';
            },
            'transaction retry attempts param' => function () {
                self::buildModelSchema();
                $db = self::db();
                $db->transaction(function () {
                    MoUser::create(['name' => 'Retry']);
                }, 3);
                Support::assertEquals(1, MoUser::count(), 'retry param committed');
                return 'ok';
            },
        ];
        foreach ($cases as $name => $fn) {
            // Always reset the connection transaction counter afterwards:
            // MatrixOne lacks SAVEPOINT, so a failed nested rollBack can leave
            // Eloquent's level stuck and would otherwise poison later scenarios.
            $r->add('Eloquent', $cat, $name, function () use ($fn) {
                try {
                    return $fn();
                } finally {
                    self::resetTransactions();
                }
            });
        }
    }
}
