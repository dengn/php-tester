<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use MoTest\Connections;
use MoTest\Models\MoComment;
use MoTest\Models\MoPost;
use MoTest\Models\MoProfile;
use MoTest\Models\MoRole;
use MoTest\Models\MoUser;
use MoTest\Runner;
use MoTest\Support;

/**
 * Laravel Eloquent — the single most widely used PHP ORM.
 *
 * Exercises the schema builder (Blueprint column types & modifiers), the
 * fluent query builder, Eloquent models with casts/timestamps, every
 * relationship kind, and transaction handling (including nested transactions
 * which rely on SAVEPOINTs).
 */
final class EloquentScenarios
{
    public static function register(Runner $r): void
    {
        self::schemaTypes($r);
        self::schemaModifiers($r);
        self::schemaOperations($r);
        self::queryBuilder($r);
        self::modelCrud($r);
        self::relationships($r);
        self::transactions($r);
        self::aggregatesAndPaging($r);
    }

    private static function db(): Connection
    {
        return Connections::eloquent()->getConnection();
    }

    // ---------------------------------------------------------------- schema
    private static function schemaTypes(Runner $r): void
    {
        $types = [
            'id (bigIncrements)' => fn (Blueprint $t) => $t->id(),
            'increments' => fn (Blueprint $t) => $t->increments('c'),
            'bigIncrements' => fn (Blueprint $t) => $t->bigIncrements('c'),
            'integer' => fn (Blueprint $t) => $t->integer('c'),
            'bigInteger' => fn (Blueprint $t) => $t->bigInteger('c'),
            'mediumInteger' => fn (Blueprint $t) => $t->mediumInteger('c'),
            'smallInteger' => fn (Blueprint $t) => $t->smallInteger('c'),
            'tinyInteger' => fn (Blueprint $t) => $t->tinyInteger('c'),
            'unsignedBigInteger' => fn (Blueprint $t) => $t->unsignedBigInteger('c'),
            'unsignedInteger' => fn (Blueprint $t) => $t->unsignedInteger('c'),
            'unsignedTinyInteger' => fn (Blueprint $t) => $t->unsignedTinyInteger('c'),
            'float' => fn (Blueprint $t) => $t->float('c'),
            'double' => fn (Blueprint $t) => $t->double('c'),
            'decimal(8,2)' => fn (Blueprint $t) => $t->decimal('c', 8, 2),
            'boolean' => fn (Blueprint $t) => $t->boolean('c'),
            'string' => fn (Blueprint $t) => $t->string('c', 100),
            'char' => fn (Blueprint $t) => $t->char('c', 10),
            'text' => fn (Blueprint $t) => $t->text('c'),
            'mediumText' => fn (Blueprint $t) => $t->mediumText('c'),
            'longText' => fn (Blueprint $t) => $t->longText('c'),
            'tinyText' => fn (Blueprint $t) => $t->tinyText('c'),
            'binary' => fn (Blueprint $t) => $t->binary('c'),
            'date' => fn (Blueprint $t) => $t->date('c'),
            'dateTime' => fn (Blueprint $t) => $t->dateTime('c'),
            'dateTimeTz' => fn (Blueprint $t) => $t->dateTimeTz('c'),
            'time' => fn (Blueprint $t) => $t->time('c'),
            'timeTz' => fn (Blueprint $t) => $t->timeTz('c'),
            'timestamp' => fn (Blueprint $t) => $t->timestamp('c'),
            'timestampTz' => fn (Blueprint $t) => $t->timestampTz('c'),
            'year' => fn (Blueprint $t) => $t->year('c'),
            'json' => fn (Blueprint $t) => $t->json('c'),
            'jsonb' => fn (Blueprint $t) => $t->jsonb('c'),
            'uuid' => fn (Blueprint $t) => $t->uuid('c'),
            'ulid' => fn (Blueprint $t) => $t->ulid('c'),
            'enum' => fn (Blueprint $t) => $t->enum('c', ['a', 'b', 'c']),
            'set' => fn (Blueprint $t) => $t->set('c', ['a', 'b']),
            'ipAddress' => fn (Blueprint $t) => $t->ipAddress('c'),
            'macAddress' => fn (Blueprint $t) => $t->macAddress('c'),
            'geometry' => fn (Blueprint $t) => $t->geometry('c'),
            'timestamps()' => fn (Blueprint $t) => $t->timestamps(),
            'softDeletes()' => fn (Blueprint $t) => $t->softDeletes(),
            'rememberToken()' => fn (Blueprint $t) => $t->rememberToken(),
            'morphs()' => fn (Blueprint $t) => $t->morphs('taggable'),
            'uuidMorphs()' => fn (Blueprint $t) => $t->uuidMorphs('taggable'),
            'foreignId()' => fn (Blueprint $t) => $t->foreignId('owner_id'),
        ];
        foreach ($types as $name => $build) {
            $r->add('Eloquent', 'schema:type', "Blueprint $name", function () use ($build, $name) {
                $schema = self::db()->getSchemaBuilder();
                $tn = Support::name('el');
                try {
                    $schema->create($tn, function (Blueprint $t) use ($build) {
                        $t->integer('anchor');
                        $build($t);
                    });
                    Support::assert($schema->hasTable($tn), "table $tn not created");
                    return "created with $name";
                } finally {
                    $schema->dropIfExists($tn);
                }
            });
        }
    }

    private static function schemaModifiers(Runner $r): void
    {
        $mods = [
            'nullable()' => fn (Blueprint $t) => $t->string('c')->nullable(),
            'default(literal)' => fn (Blueprint $t) => $t->string('c')->default('x'),
            'default(int)' => fn (Blueprint $t) => $t->integer('c')->default(7),
            'unsigned()' => fn (Blueprint $t) => $t->integer('c')->unsigned(),
            'comment()' => fn (Blueprint $t) => $t->integer('c')->comment('hi'),
            'unique()' => fn (Blueprint $t) => $t->string('c')->unique(),
            'index()' => fn (Blueprint $t) => $t->string('c')->index(),
            'primary()' => fn (Blueprint $t) => $t->integer('c')->primary(),
            'useCurrent()' => fn (Blueprint $t) => $t->timestamp('c')->useCurrent(),
            'useCurrentOnUpdate()' => fn (Blueprint $t) => $t->timestamp('c')->useCurrent()->useCurrentOnUpdate(),
            'autoIncrement()' => fn (Blueprint $t) => $t->integer('c')->autoIncrement(),
            'charset()' => fn (Blueprint $t) => $t->string('c')->charset('utf8mb4'),
            'collation()' => fn (Blueprint $t) => $t->string('c')->collation('utf8mb4_general_ci'),
            'storedAs()' => fn (Blueprint $t) => $t->integer('c')->storedAs('anchor + 1'),
            'virtualAs()' => fn (Blueprint $t) => $t->integer('c')->virtualAs('anchor + 1'),
            'composite primary' => function (Blueprint $t) {
                $t->integer('c');
                $t->primary(['anchor', 'c']);
            },
            'composite unique' => function (Blueprint $t) {
                $t->integer('c');
                $t->unique(['anchor', 'c']);
            },
            'fulltext index' => fn (Blueprint $t) => $t->fullText('c'),
            'spatialIndex' => fn (Blueprint $t) => $t->spatialIndex('c'),
        ];
        foreach ($mods as $name => $build) {
            $r->add('Eloquent', 'schema:modifier', "modifier $name", function () use ($name, $build) {
                $schema = self::db()->getSchemaBuilder();
                $tn = Support::name('el');
                try {
                    $schema->create($tn, function (Blueprint $t) use ($build, $name) {
                        $t->integer('anchor');
                        // give a string column for fulltext/spatial cases
                        if (str_contains($name, 'fulltext') || str_contains($name, 'spatial')) {
                            $t->integer('c2');
                        }
                        $build($t);
                    });
                    Support::assert($schema->hasTable($tn), 'not created');
                    return "ok $name";
                } finally {
                    $schema->dropIfExists($tn);
                }
            });
        }
    }

    private static function schemaOperations(Runner $r): void
    {
        $ops = [
            'create + hasTable' => function () {
                $s = self::db()->getSchemaBuilder();
                $tn = Support::name('el');
                try {
                    $s->create($tn, fn (Blueprint $t) => $t->id());
                    Support::assert($s->hasTable($tn), 'hasTable false');
                    return 'ok';
                } finally {
                    $s->dropIfExists($tn);
                }
            },
            'hasColumn' => function () {
                $s = self::db()->getSchemaBuilder();
                $tn = Support::name('el');
                try {
                    $s->create($tn, function (Blueprint $t) {
                        $t->id();
                        $t->string('email');
                    });
                    Support::assert($s->hasColumn($tn, 'email'), 'hasColumn false');
                    return 'ok';
                } finally {
                    $s->dropIfExists($tn);
                }
            },
            'getColumnListing' => function () {
                $s = self::db()->getSchemaBuilder();
                $tn = Support::name('el');
                try {
                    $s->create($tn, function (Blueprint $t) {
                        $t->id();
                        $t->string('email');
                    });
                    $cols = $s->getColumnListing($tn);
                    Support::assert(in_array('email', $cols, true), 'column listing missing email');
                    return implode(',', $cols);
                } finally {
                    $s->dropIfExists($tn);
                }
            },
            'rename table' => function () {
                $s = self::db()->getSchemaBuilder();
                $a = Support::name('el');
                $b = Support::name('el');
                try {
                    $s->create($a, fn (Blueprint $t) => $t->id());
                    $s->rename($a, $b);
                    Support::assert($s->hasTable($b), 'rename target missing');
                    return 'renamed';
                } finally {
                    $s->dropIfExists($a);
                    $s->dropIfExists($b);
                }
            },
            'add column (alter)' => function () {
                $s = self::db()->getSchemaBuilder();
                $tn = Support::name('el');
                try {
                    $s->create($tn, fn (Blueprint $t) => $t->id());
                    $s->table($tn, fn (Blueprint $t) => $t->string('added')->nullable());
                    Support::assert($s->hasColumn($tn, 'added'), 'added column missing');
                    return 'added';
                } finally {
                    $s->dropIfExists($tn);
                }
            },
            'add column after() (alter)' => function () {
                $s = self::db()->getSchemaBuilder();
                $tn = Support::name('el');
                try {
                    $s->create($tn, function (Blueprint $t) {
                        $t->id();
                        $t->integer('anchor');
                    });
                    $s->table($tn, fn (Blueprint $t) => $t->integer('mid')->nullable()->after('anchor'));
                    Support::assert($s->hasColumn($tn, 'mid'), 'after column missing');
                    return 'added after';
                } finally {
                    $s->dropIfExists($tn);
                }
            },
            'add column first() (alter)' => function () {
                $s = self::db()->getSchemaBuilder();
                $tn = Support::name('el');
                try {
                    $s->create($tn, fn (Blueprint $t) => $t->id());
                    $s->table($tn, fn (Blueprint $t) => $t->integer('lead')->nullable()->first());
                    Support::assert($s->hasColumn($tn, 'lead'), 'first column missing');
                    return 'added first';
                } finally {
                    $s->dropIfExists($tn);
                }
            },
            'drop column (alter)' => function () {
                $s = self::db()->getSchemaBuilder();
                $tn = Support::name('el');
                try {
                    $s->create($tn, function (Blueprint $t) {
                        $t->id();
                        $t->string('gone')->nullable();
                    });
                    $s->table($tn, fn (Blueprint $t) => $t->dropColumn('gone'));
                    Support::assert(!$s->hasColumn($tn, 'gone'), 'column not dropped');
                    return 'dropped';
                } finally {
                    $s->dropIfExists($tn);
                }
            },
            'rename column (alter)' => function () {
                $s = self::db()->getSchemaBuilder();
                $tn = Support::name('el');
                try {
                    $s->create($tn, function (Blueprint $t) {
                        $t->id();
                        $t->string('oldn')->nullable();
                    });
                    $s->table($tn, fn (Blueprint $t) => $t->renameColumn('oldn', 'newn'));
                    Support::assert($s->hasColumn($tn, 'newn'), 'rename column failed');
                    return 'renamed col';
                } finally {
                    $s->dropIfExists($tn);
                }
            },
            'change column type (alter)' => function () {
                $s = self::db()->getSchemaBuilder();
                $tn = Support::name('el');
                try {
                    $s->create($tn, function (Blueprint $t) {
                        $t->id();
                        $t->string('c', 50)->nullable();
                    });
                    $s->table($tn, fn (Blueprint $t) => $t->text('c')->nullable()->change());
                    return 'changed';
                } finally {
                    $s->dropIfExists($tn);
                }
            },
            'add index (alter)' => function () {
                $s = self::db()->getSchemaBuilder();
                $tn = Support::name('el');
                try {
                    $s->create($tn, function (Blueprint $t) {
                        $t->id();
                        $t->string('c')->nullable();
                    });
                    $s->table($tn, fn (Blueprint $t) => $t->index('c'));
                    return 'indexed';
                } finally {
                    $s->dropIfExists($tn);
                }
            },
            'drop index (alter)' => function () {
                $s = self::db()->getSchemaBuilder();
                $tn = Support::name('el');
                try {
                    $s->create($tn, function (Blueprint $t) {
                        $t->id();
                        $t->string('c')->nullable()->index('ix_c');
                    });
                    $s->table($tn, fn (Blueprint $t) => $t->dropIndex('ix_c'));
                    return 'dropped index';
                } finally {
                    $s->dropIfExists($tn);
                }
            },
            'foreign key constraint' => function () {
                $s = self::db()->getSchemaBuilder();
                $p = Support::name('elp');
                $c = Support::name('elc');
                try {
                    $s->create($p, fn (Blueprint $t) => $t->id());
                    $s->create($c, function (Blueprint $t) use ($p) {
                        $t->id();
                        $t->foreignId('p_id')->constrained($p);
                    });
                    return 'fk created';
                } finally {
                    $s->dropIfExists($c);
                    $s->dropIfExists($p);
                }
            },
        ];
        foreach ($ops as $name => $fn) {
            $r->add('Eloquent', 'schema:operation', $name, $fn);
        }
    }

    // ----------------------------------------------------------- query builder
    private static function withQb(Runner $r, string $name, callable $fn): void
    {
        $r->add('Eloquent', 'query-builder', $name, function () use ($fn) {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('qb');
            $s->create($tn, function (Blueprint $t) {
                $t->id();
                $t->string('name', 50);
                $t->integer('age');
                $t->string('city', 50)->nullable();
                $t->decimal('score', 8, 2)->default(0);
            });
            try {
                $db->table($tn)->insert([
                    ['name' => 'alice', 'age' => 30, 'city' => 'NY', 'score' => 9.5],
                    ['name' => 'bob', 'age' => 25, 'city' => 'LA', 'score' => 7.0],
                    ['name' => 'carol', 'age' => 40, 'city' => 'NY', 'score' => 8.0],
                    ['name' => 'dave', 'age' => 35, 'city' => null, 'score' => 6.5],
                ]);
                return $fn($db, $tn);
            } finally {
                $s->dropIfExists($tn);
            }
        });
    }

    private static function queryBuilder(Runner $r): void
    {
        self::withQb($r, 'insert + count', function (Connection $db, string $t) {
            Support::assertEquals(4, $db->table($t)->count(), 'count');
            return 'ok';
        });
        self::withQb($r, 'insertGetId', function (Connection $db, string $t) {
            $id = $db->table($t)->insertGetId(['name' => 'eve', 'age' => 22]);
            Support::assert($id > 0, 'no id returned');
            return "id=$id";
        });
        self::withQb($r, 'insertOrIgnore', function (Connection $db, string $t) {
            $db->table($t)->insertOrIgnore([['id' => 1, 'name' => 'dup', 'age' => 1]]);
            return 'ok';
        });
        self::withQb($r, 'upsert', function (Connection $db, string $t) {
            $db->table($t)->upsert(
                [['id' => 1, 'name' => 'alice2', 'age' => 31]],
                ['id'],
                ['name', 'age']
            );
            Support::assertEquals('alice2', $db->table($t)->where('id', 1)->value('name'), 'upsert update');
            return 'ok';
        });
        self::withQb($r, 'where + first', function (Connection $db, string $t) {
            $row = $db->table($t)->where('name', 'bob')->first();
            Support::assertEquals(25, $row->age, 'where first');
            return 'ok';
        });
        self::withQb($r, 'whereIn', function (Connection $db, string $t) {
            Support::assertEquals(2, $db->table($t)->whereIn('name', ['alice', 'bob'])->count(), 'whereIn');
            return 'ok';
        });
        self::withQb($r, 'whereBetween', function (Connection $db, string $t) {
            Support::assertEquals(2, $db->table($t)->whereBetween('age', [30, 36])->count(), 'whereBetween');
            return 'ok';
        });
        self::withQb($r, 'whereNull', function (Connection $db, string $t) {
            Support::assertEquals(1, $db->table($t)->whereNull('city')->count(), 'whereNull');
            return 'ok';
        });
        self::withQb($r, 'orWhere', function (Connection $db, string $t) {
            Support::assertEquals(2, $db->table($t)->where('age', 30)->orWhere('age', 25)->count(), 'orWhere');
            return 'ok';
        });
        self::withQb($r, 'whereColumn', function (Connection $db, string $t) {
            $db->table($t)->whereColumn('age', '>', 'id')->get();
            return 'ok';
        });
        self::withQb($r, 'orderBy + limit', function (Connection $db, string $t) {
            $row = $db->table($t)->orderBy('age', 'desc')->limit(1)->first();
            Support::assertEquals('carol', $row->name, 'orderBy');
            return 'ok';
        });
        self::withQb($r, 'distinct', function (Connection $db, string $t) {
            $cities = $db->table($t)->whereNotNull('city')->distinct()->pluck('city');
            Support::assertEquals(2, count($cities), 'distinct count');
            return 'ok';
        });
        self::withQb($r, 'groupBy + havingRaw', function (Connection $db, string $t) {
            $rows = $db->table($t)->select('city', $db->raw('COUNT(*) as c'))
                ->whereNotNull('city')->groupBy('city')->havingRaw('COUNT(*) > 1')->get();
            Support::assertEquals(1, count($rows), 'groupBy/having');
            return 'ok';
        });
        self::withQb($r, 'aggregate sum/avg/min/max', function (Connection $db, string $t) {
            Support::assertEquals(130, (int) $db->table($t)->sum('age'), 'sum');
            Support::assertEquals(40, (int) $db->table($t)->max('age'), 'max');
            Support::assertEquals(25, (int) $db->table($t)->min('age'), 'min');
            return 'ok';
        });
        self::withQb($r, 'update', function (Connection $db, string $t) {
            $n = $db->table($t)->where('name', 'bob')->update(['age' => 99]);
            Support::assertEquals(1, $n, 'update affected');
            return 'ok';
        });
        self::withQb($r, 'increment', function (Connection $db, string $t) {
            $db->table($t)->where('name', 'bob')->increment('age', 5);
            Support::assertEquals(30, $db->table($t)->where('name', 'bob')->value('age'), 'increment');
            return 'ok';
        });
        self::withQb($r, 'delete', function (Connection $db, string $t) {
            $n = $db->table($t)->where('name', 'dave')->delete();
            Support::assertEquals(1, $n, 'delete affected');
            return 'ok';
        });
        self::withQb($r, 'exists', function (Connection $db, string $t) {
            Support::assert($db->table($t)->where('name', 'alice')->exists(), 'exists');
            return 'ok';
        });
        self::withQb($r, 'pluck key=>value', function (Connection $db, string $t) {
            $map = $db->table($t)->pluck('name', 'id');
            Support::assert(count($map) === 4, 'pluck count');
            return 'ok';
        });
        self::withQb($r, 'join (self via derived)', function (Connection $db, string $t) {
            $rows = $db->table($t . ' as a')->join($t . ' as b', 'a.city', '=', 'b.city')
                ->whereColumn('a.id', '<', 'b.id')->get();
            return 'rows=' . count($rows);
        });
        self::withQb($r, 'union', function (Connection $db, string $t) {
            $q1 = $db->table($t)->where('age', '<', 30);
            $rows = $db->table($t)->where('age', '>', 35)->union($q1)->get();
            return 'rows=' . count($rows);
        });
        self::withQb($r, 'whereExists subquery', function (Connection $db, string $t) {
            $rows = $db->table($t . ' as a')->whereExists(function ($q) use ($t) {
                $q->select($q->raw(1))->from($t . ' as b')->whereColumn('b.city', 'a.city')->whereColumn('b.id', '<>', 'a.id');
            })->get();
            return 'rows=' . count($rows);
        });
        self::withQb($r, 'paginate', function (Connection $db, string $t) {
            $p = $db->table($t)->orderBy('id')->paginate(2);
            Support::assertEquals(4, $p->total(), 'paginate total');
            return 'ok';
        });
        self::withQb($r, 'chunk', function (Connection $db, string $t) {
            $seen = 0;
            $db->table($t)->orderBy('id')->chunk(2, function ($rows) use (&$seen) {
                $seen += count($rows);
            });
            Support::assertEquals(4, $seen, 'chunk total');
            return 'ok';
        });
        self::withQb($r, 'whereRaw + selectRaw', function (Connection $db, string $t) {
            $rows = $db->table($t)->selectRaw('age * 2 as d')->whereRaw('age > ?', [30])->get();
            return 'rows=' . count($rows);
        });
        self::withQb($r, 'json where (->)', function (Connection $db, string $t) {
            // add a json column, set it, query into it
            $db->getSchemaBuilder()->table($t, fn (Blueprint $b) => $b->json('doc')->nullable());
            $db->table($t)->where('name', 'alice')->update(['doc' => json_encode(['k' => 5])]);
            $row = $db->table($t)->where('doc->k', 5)->first();
            Support::assert($row !== null, 'json arrow where matched nothing');
            return 'ok';
        });
    }

    // ----------------------------------------------------------------- models
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

    private static function modelCrud(Runner $r): void
    {
        $cases = [
            'Model::create + find' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Ann', 'email' => 'ann@x.io']);
                $found = MoUser::find($u->id);
                Support::assertEquals('Ann', $found->name, 'find name');
                return "id={$u->id}";
            },
            'Model timestamps set' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Ts']);
                Support::assert($u->created_at !== null, 'created_at not set');
                return 'ok';
            },
            'Model update + save' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Up']);
                $u->name = 'Updated';
                $u->save();
                Support::assertEquals('Updated', MoUser::find($u->id)->name, 'updated');
                return 'ok';
            },
            'Model delete' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Del']);
                $id = $u->id;
                $u->delete();
                Support::assert(MoUser::find($id) === null, 'still exists');
                return 'ok';
            },
            'Model array cast (json)' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'J', 'meta' => ['a' => 1, 'b' => [2, 3]]]);
                $reloaded = MoUser::find($u->id);
                Support::assertEquals(1, $reloaded->meta['a'], 'json cast roundtrip');
                return 'ok';
            },
            'Model boolean cast' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'B', 'active' => true]);
                Support::assert(MoUser::find($u->id)->active === true, 'boolean cast');
                return 'ok';
            },
            'Model decimal cast' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'D', 'score' => 12.5]);
                Support::assertEquals('12.50', (string) MoUser::find($u->id)->score, 'decimal cast');
                return 'ok';
            },
            'Model where + get collection' => function () {
                self::buildModelSchema();
                MoUser::create(['name' => 'A']);
                MoUser::create(['name' => 'B']);
                Support::assertEquals(2, MoUser::query()->count(), 'count');
                return 'ok';
            },
            'firstOrCreate' => function () {
                self::buildModelSchema();
                MoUser::firstOrCreate(['email' => 'u@x.io'], ['name' => 'U']);
                MoUser::firstOrCreate(['email' => 'u@x.io'], ['name' => 'U2']);
                Support::assertEquals(1, MoUser::where('email', 'u@x.io')->count(), 'firstOrCreate dup');
                return 'ok';
            },
            'updateOrCreate' => function () {
                self::buildModelSchema();
                MoUser::updateOrCreate(['email' => 'z@x.io'], ['name' => 'First']);
                MoUser::updateOrCreate(['email' => 'z@x.io'], ['name' => 'Second']);
                Support::assertEquals('Second', MoUser::where('email', 'z@x.io')->first()->name, 'updateOrCreate');
                return 'ok';
            },
            'Model increment' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Inc', 'score' => 1]);
                $u->increment('score', 4);
                Support::assertEquals('5.00', (string) MoUser::find($u->id)->score, 'increment');
                return 'ok';
            },
        ];
        foreach ($cases as $name => $fn) {
            $r->add('Eloquent', 'model:crud', $name, $fn);
        }
    }

    private static function relationships(Runner $r): void
    {
        $cases = [
            'hasMany' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Owner']);
                $u->posts()->create(['title' => 'P1']);
                $u->posts()->create(['title' => 'P2']);
                Support::assertEquals(2, MoUser::find($u->id)->posts()->count(), 'hasMany count');
                return 'ok';
            },
            'belongsTo' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Owner']);
                $p = MoPost::create(['title' => 'X', 'user_id' => $u->id]);
                Support::assertEquals('Owner', MoPost::find($p->id)->user->name, 'belongsTo');
                return 'ok';
            },
            'hasOne' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Owner']);
                $u->profile()->create(['bio' => 'hello']);
                Support::assertEquals('hello', MoUser::find($u->id)->profile->bio, 'hasOne');
                return 'ok';
            },
            'belongsToMany (pivot)' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Owner']);
                $r1 = MoRole::create(['name' => 'admin']);
                $r2 = MoRole::create(['name' => 'editor']);
                $u->roles()->attach([$r1->id, $r2->id]);
                Support::assertEquals(2, MoUser::find($u->id)->roles()->count(), 'belongsToMany');
                return 'ok';
            },
            'belongsToMany detach/sync' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Owner']);
                $r1 = MoRole::create(['name' => 'admin']);
                $r2 = MoRole::create(['name' => 'editor']);
                $u->roles()->attach($r1->id);
                $u->roles()->sync([$r2->id]);
                Support::assertEquals(1, MoUser::find($u->id)->roles()->count(), 'sync');
                return 'ok';
            },
            'morphMany / morphTo' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Owner']);
                $u->comments()->create(['body' => 'c1']);
                $c = MoComment::first();
                Support::assertEquals('Owner', $c->commentable->name, 'morphTo back');
                return 'ok';
            },
            'eager load with()' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Owner']);
                $u->posts()->create(['title' => 'P1']);
                $loaded = MoUser::with('posts')->find($u->id);
                Support::assertEquals(1, $loaded->posts->count(), 'eager load');
                return 'ok';
            },
            'withCount()' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Owner']);
                $u->posts()->create(['title' => 'P1']);
                $u->posts()->create(['title' => 'P2']);
                $loaded = MoUser::withCount('posts')->find($u->id);
                Support::assertEquals(2, $loaded->posts_count, 'withCount');
                return 'ok';
            },
            'whereHas()' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Owner']);
                $u->posts()->create(['title' => 'P1']);
                MoUser::create(['name' => 'NoPosts']);
                Support::assertEquals(1, MoUser::whereHas('posts')->count(), 'whereHas');
                return 'ok';
            },
            'has() with count' => function () {
                self::buildModelSchema();
                $u = MoUser::create(['name' => 'Owner']);
                $u->posts()->create(['title' => 'P1']);
                $u->posts()->create(['title' => 'P2']);
                Support::assertEquals(1, MoUser::has('posts', '>=', 2)->count(), 'has >= 2');
                return 'ok';
            },
        ];
        foreach ($cases as $name => $fn) {
            $r->add('Eloquent', 'relationship', $name, $fn);
        }
    }

    private static function transactions(Runner $r): void
    {
        $r->add('Eloquent', 'transaction', 'DB::transaction commit', function () {
            self::buildModelSchema();
            $db = self::db();
            $db->transaction(function () {
                MoUser::create(['name' => 'TxA']);
                MoUser::create(['name' => 'TxB']);
            });
            Support::assertEquals(2, MoUser::count(), 'tx commit');
            return 'ok';
        });
        $r->add('Eloquent', 'transaction', 'manual rollback', function () {
            self::buildModelSchema();
            $db = self::db();
            $db->beginTransaction();
            MoUser::create(['name' => 'Rb']);
            $db->rollBack();
            Support::assertEquals(0, MoUser::count(), 'rollback');
            return 'ok';
        });
        $r->add('Eloquent', 'transaction', 'nested transaction (savepoint)', function () {
            self::buildModelSchema();
            $db = self::db();
            $db->transaction(function () use ($db) {
                MoUser::create(['name' => 'Outer']);
                try {
                    $db->transaction(function () {
                        MoUser::create(['name' => 'Inner']);
                        throw new \RuntimeException('rollback inner');
                    });
                } catch (\RuntimeException) {
                    // inner savepoint should roll back, outer continues
                }
            });
            // With working savepoints: Outer persists, Inner does not => 1 row.
            Support::assertEquals(1, MoUser::count(), 'nested savepoint semantics');
            return 'ok';
        });
        $r->add('Eloquent', 'transaction', 'transaction retry (deadlock attempts param)', function () {
            self::buildModelSchema();
            $db = self::db();
            $db->transaction(function () {
                MoUser::create(['name' => 'Retry']);
            }, 3);
            Support::assertEquals(1, MoUser::count(), 'retry param');
            return 'ok';
        });
    }

    private static function aggregatesAndPaging(Runner $r): void
    {
        $r->add('Eloquent', 'misc', 'cursorPaginate', function () {
            self::buildModelSchema();
            for ($i = 0; $i < 5; $i++) {
                MoUser::create(['name' => 'U' . $i]);
            }
            $page = MoUser::orderBy('id')->cursorPaginate(2);
            Support::assertEquals(2, $page->count(), 'cursor page size');
            return 'ok';
        });
        $r->add('Eloquent', 'misc', 'lazy()', function () {
            self::buildModelSchema();
            for ($i = 0; $i < 5; $i++) {
                MoUser::create(['name' => 'U' . $i]);
            }
            $n = 0;
            foreach (MoUser::lazy(2) as $_) {
                $n++;
            }
            Support::assertEquals(5, $n, 'lazy count');
            return 'ok';
        });
        $r->add('Eloquent', 'misc', 'whereJsonContains', function () {
            self::buildModelSchema();
            MoUser::create(['name' => 'J', 'meta' => ['roles' => ['a', 'b']]]);
            $n = MoUser::whereJsonContains('meta->roles', 'a')->count();
            Support::assertEquals(1, $n, 'whereJsonContains');
            return 'ok';
        });
        $r->add('Eloquent', 'misc', 'pluck + chunkById', function () {
            self::buildModelSchema();
            for ($i = 0; $i < 6; $i++) {
                MoUser::create(['name' => 'U' . $i]);
            }
            $seen = 0;
            MoUser::chunkById(2, function ($rows) use (&$seen) {
                $seen += $rows->count();
            });
            Support::assertEquals(6, $seen, 'chunkById');
            return 'ok';
        });
    }
}
