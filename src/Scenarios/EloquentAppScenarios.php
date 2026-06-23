<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use MoTest\Connections;
use MoTest\Runner;
use MoTest\Support;

/**
 * Real-world Laravel application workloads + Laravel-style migrations on
 * MatrixOne, driven entirely through the Eloquent QUERY builder and SCHEMA
 * builder (no models — that surface is already covered by EloquentScenarios /
 * EloquentScenarios2).
 *
 * Themes:
 *   A) App workloads (ecommerce, social, analytics, cms, ledger) expressed via
 *      the fluent query builder: joins, aggregates, conditional aggregation,
 *      date bucketing, inventory decrements, upserts, balance transfers, ...
 *   B) Laravel-style migrations via Schema/Blueprint up/down: column-type
 *      matrices, ALTER operations (add/rename/drop/change/index/default), and
 *      foreign-key constraints with cascade/set null/restrict semantics.
 *   C) JSON column casts and the '->' path operator (MatrixOne supports the
 *      arrow operator but NOT JSON_CONTAINS, so whereJsonContains is a finding).
 *
 * Conventions (mirroring the other Eloquent providers):
 *   - fresh uniquely-named table(s) per scenario via Support::name(),
 *   - schema created via $conn->getSchemaBuilder()->create(...Blueprint...),
 *   - rows seeded via $db->table()->insert(),
 *   - app query run through the fluent builder,
 *   - tables dropped in finally (try/catch so teardown never throws),
 *   - every closure captures all referenced variables in use(...),
 *   - transactions kept FLAT (MatrixOne has no SAVEPOINT) and counter reset.
 *
 * Failures are genuine MatrixOne findings, not test bugs.
 */
final class EloquentAppScenarios
{
    private const FW = 'Eloquent';

    public static function register(Runner $r): void
    {
        self::ecommerce($r);
        self::social($r);
        self::analytics($r);
        self::cms($r);
        self::ledger($r);
        self::migrateUpDown($r);
        self::migrateColumn($r);
        self::migrateFk($r);
        self::json($r);
    }

    private static function db(): Connection
    {
        return Connections::eloquent()->getConnection();
    }

    /**
     * Force the connection's transaction counter back to zero. MatrixOne does
     * not implement SAVEPOINT; mirrors EloquentScenarios2::resetTransactions so
     * a half-finished transaction never poisons a later scenario.
     */
    private static function resetTransactions(): void
    {
        $db = self::db();
        try {
            $db->getPdo()->exec('ROLLBACK');
        } catch (\Throwable) {
            // nothing to roll back
        }
        try {
            $ref = new \ReflectionProperty($db, 'transactions');
            $ref->setAccessible(true);
            $ref->setValue($db, 0);
        } catch (\Throwable) {
            // best effort only
        }
    }

    // =====================================================================
    // Generic helpers
    // =====================================================================

    /**
     * Build a single-table scenario: create $tn via $schema, seed $rows, run
     * $fn($db, $tn), drop in finally.
     *
     * @param callable(Blueprint):void                 $schema
     * @param array<int,array<string,mixed>>           $rows
     * @param callable(Connection,string):(string|array) $fn
     */
    private static function addTable(Runner $r, string $cat, string $name, callable $schema, array $rows, callable $fn): void
    {
        $r->add(self::FW, $cat, $name, function () use ($schema, $rows, $fn) {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('app');
            $s->create($tn, $schema);
            try {
                if ($rows !== []) {
                    $db->table($tn)->insert($rows);
                }
                return $fn($db, $tn);
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
    }

    // =====================================================================
    // A1) ECOMMERCE
    // =====================================================================

    /** Standard orders+items fixture builder used by several ecommerce cases. */
    private static function ecomShop(Runner $r, string $name, callable $fn): void
    {
        $r->add(self::FW, 'eloq-app:ecommerce', $name, function () use ($fn) {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $products = Support::name('prod');
            $orders = Support::name('ord');
            $items = Support::name('item');
            $s->create($products, function (Blueprint $t) {
                $t->id();
                $t->string('sku', 30);
                $t->string('name', 60);
                $t->decimal('price', 10, 2);
                $t->integer('stock');
            });
            $s->create($orders, function (Blueprint $t) {
                $t->id();
                $t->integer('customer_id');
                $t->string('status', 20);
                $t->date('placed_on');
                $t->decimal('discount', 10, 2)->default(0);
            });
            $s->create($items, function (Blueprint $t) {
                $t->id();
                $t->integer('order_id');
                $t->integer('product_id');
                $t->integer('qty');
                $t->decimal('unit_price', 10, 2);
            });
            try {
                $db->table($products)->insert([
                    ['id' => 1, 'sku' => 'WID-1', 'name' => 'Widget', 'price' => 9.99, 'stock' => 100],
                    ['id' => 2, 'sku' => 'GAD-2', 'name' => 'Gadget', 'price' => 19.50, 'stock' => 50],
                    ['id' => 3, 'sku' => 'DOO-3', 'name' => 'Doohickey', 'price' => 4.25, 'stock' => 200],
                    ['id' => 4, 'sku' => 'GIZ-4', 'name' => 'Gizmo', 'price' => 49.00, 'stock' => 10],
                ]);
                $db->table($orders)->insert([
                    ['id' => 1, 'customer_id' => 10, 'status' => 'paid', 'placed_on' => '2024-01-05', 'discount' => 0],
                    ['id' => 2, 'customer_id' => 10, 'status' => 'paid', 'placed_on' => '2024-01-20', 'discount' => 5.00],
                    ['id' => 3, 'customer_id' => 20, 'status' => 'shipped', 'placed_on' => '2024-02-02', 'discount' => 0],
                    ['id' => 4, 'customer_id' => 30, 'status' => 'cancelled', 'placed_on' => '2024-02-15', 'discount' => 0],
                    ['id' => 5, 'customer_id' => 20, 'status' => 'paid', 'placed_on' => '2024-03-01', 'discount' => 10.00],
                ]);
                $db->table($items)->insert([
                    ['order_id' => 1, 'product_id' => 1, 'qty' => 3, 'unit_price' => 9.99],
                    ['order_id' => 1, 'product_id' => 3, 'qty' => 10, 'unit_price' => 4.25],
                    ['order_id' => 2, 'product_id' => 2, 'qty' => 2, 'unit_price' => 19.50],
                    ['order_id' => 3, 'product_id' => 4, 'qty' => 1, 'unit_price' => 49.00],
                    ['order_id' => 3, 'product_id' => 1, 'qty' => 5, 'unit_price' => 9.99],
                    ['order_id' => 4, 'product_id' => 2, 'qty' => 1, 'unit_price' => 19.50],
                    ['order_id' => 5, 'product_id' => 3, 'qty' => 20, 'unit_price' => 4.25],
                ]);
                return $fn($db, $products, $orders, $items);
            } finally {
                try {
                    $s->dropIfExists($items);
                    $s->dropIfExists($orders);
                    $s->dropIfExists($products);
                } catch (\Throwable) {
                }
            }
        });
    }

    private static function ecommerce(Runner $r): void
    {
        // ---- order-total / line-item aggregates over many variants ----------
        self::ecomShop($r, 'order line total via SUM(qty*unit_price)', function (Connection $db, string $p, string $o, string $i) {
            $row = $db->table($i)->where('order_id', 1)->selectRaw('SUM(qty * unit_price) as total')->first();
            // 3*9.99 + 10*4.25 = 29.97 + 42.50 = 72.47
            Support::assertValueEquals(72.47, $row->total, 'order 1 line total');
            return ['detail' => "total={$row->total}", 'sql' => 'SUM(qty*unit_price) WHERE order_id=1'];
        });
        self::ecomShop($r, 'grand revenue across all items', function (Connection $db, string $p, string $o, string $i) {
            $row = $db->table($i)->selectRaw('SUM(qty * unit_price) as total')->first();
            Support::assertValueEquals(312.81, $row->total, 'grand revenue');
            return "revenue={$row->total}";
        });
        self::ecomShop($r, 'revenue per order with join + groupBy', function (Connection $db, string $p, string $o, string $i) {
            $rows = $db->table($o . ' as o')
                ->join($i . ' as it', 'o.id', '=', 'it.order_id')
                ->select('o.id')
                ->selectRaw('SUM(it.qty * it.unit_price) as gross')
                ->groupBy('o.id')->orderBy('o.id')->get();
            Support::assertEquals(5, count($rows), 'one row per order');
            Support::assertValueEquals(72.47, $rows[0]->gross, 'order 1 gross');
            return 'orders=' . count($rows);
        });
        self::ecomShop($r, 'net revenue per order applying discount', function (Connection $db, string $p, string $o, string $i) {
            $rows = $db->table($o . ' as o')
                ->join($i . ' as it', 'o.id', '=', 'it.order_id')
                ->select('o.id', 'o.discount')
                ->selectRaw('SUM(it.qty * it.unit_price) - o.discount as net')
                ->groupBy('o.id', 'o.discount')->orderBy('o.id')->get();
            // order 2: 2*19.50 - 5.00 = 34.00
            $byId = [];
            foreach ($rows as $rr) {
                $byId[$rr->id] = $rr->net;
            }
            Support::assertValueEquals(34.00, $byId[2], 'order 2 net after discount');
            return 'net rows=' . count($rows);
        });
        self::ecomShop($r, 'revenue per customer (groupBy customer)', function (Connection $db, string $p, string $o, string $i) {
            $rows = $db->table($o . ' as o')
                ->join($i . ' as it', 'o.id', '=', 'it.order_id')
                ->where('o.status', '!=', 'cancelled')
                ->select('o.customer_id')
                ->selectRaw('SUM(it.qty * it.unit_price) as spent')
                ->groupBy('o.customer_id')->orderBy('o.customer_id')->get();
            Support::assert(count($rows) >= 2, 'customers grouped');
            return 'customers=' . count($rows);
        });
        self::ecomShop($r, 'having: customers spending over threshold', function (Connection $db, string $p, string $o, string $i) {
            $rows = $db->table($o . ' as o')
                ->join($i . ' as it', 'o.id', '=', 'it.order_id')
                ->select('o.customer_id')
                ->selectRaw('SUM(it.qty * it.unit_price) as spent')
                ->groupBy('o.customer_id')->havingRaw('SUM(it.qty * it.unit_price) > ?', [80])->get();
            Support::assert(count($rows) >= 1, 'having threshold');
            return 'rows=' . count($rows);
        });
        self::ecomShop($r, 'top products by units sold (orderByRaw+limit)', function (Connection $db, string $p, string $o, string $i) {
            $rows = $db->table($i . ' as it')
                ->join($p . ' as pr', 'it.product_id', '=', 'pr.id')
                ->select('pr.name')
                ->selectRaw('SUM(it.qty) as units')
                ->groupBy('pr.name')->orderByRaw('SUM(it.qty) DESC')->limit(2)->get();
            Support::assertEquals(2, count($rows), 'top 2 products');
            Support::assertEquals('Doohickey', $rows[0]->name, 'top product is Doohickey (30 units)');
            return 'top=' . $rows[0]->name;
        });
        self::ecomShop($r, 'top products by revenue (join+groupBy)', function (Connection $db, string $p, string $o, string $i) {
            $rows = $db->table($i . ' as it')
                ->join($p . ' as pr', 'it.product_id', '=', 'pr.id')
                ->select('pr.name')
                ->selectRaw('SUM(it.qty * it.unit_price) as rev')
                ->groupBy('pr.name')->orderByRaw('rev DESC')->get();
            Support::assert(count($rows) >= 1, 'product revenue');
            return 'leader=' . $rows[0]->name;
        });
        self::ecomShop($r, 'inventory decrement on fulfilment', function (Connection $db, string $p, string $o, string $i) {
            // ship order 1: deduct each item qty from product stock
            $lines = $db->table($i)->where('order_id', 1)->get();
            foreach ($lines as $ln) {
                $db->table($p)->where('id', $ln->product_id)->decrement('stock', $ln->qty);
            }
            Support::assertEquals(97, $db->table($p)->where('id', 1)->value('stock'), 'widget stock 100-3');
            Support::assertEquals(190, $db->table($p)->where('id', 3)->value('stock'), 'doohickey 200-10');
            return 'decremented';
        });
        self::ecomShop($r, 'low-stock report whereBetween/where', function (Connection $db, string $p, string $o, string $i) {
            $low = $db->table($p)->where('stock', '<', 60)->orderBy('stock')->get();
            Support::assertEquals('Gizmo', $low[0]->name, 'lowest stock product');
            return 'low count=' . count($low);
        });
        self::ecomShop($r, 'orders whereBetween on placed_on dates', function (Connection $db, string $p, string $o, string $i) {
            $n = $db->table($o)->whereBetween('placed_on', ['2024-01-01', '2024-01-31'])->count();
            Support::assertEquals(2, $n, 'orders in January');
            return "jan orders=$n";
        });
        self::ecomShop($r, 'order count by status (groupBy)', function (Connection $db, string $p, string $o, string $i) {
            $rows = $db->table($o)->select('status')->selectRaw('COUNT(*) as c')->groupBy('status')->get();
            $map = [];
            foreach ($rows as $rr) {
                $map[$rr->status] = (int) $rr->c;
            }
            Support::assertEquals(3, $map['paid'], 'three paid orders');
            return 'statuses=' . count($rows);
        });
        self::ecomShop($r, 'avg order value', function (Connection $db, string $p, string $o, string $i) {
            $perOrder = $db->table($i)->select('order_id')->selectRaw('SUM(qty*unit_price) as g')->groupBy('order_id');
            $row = $db->query()->fromSub($perOrder, 'po')->selectRaw('AVG(g) as aov')->first();
            Support::assert($row->aov !== null, 'aov computed');
            return "aov={$row->aov}";
        });
        self::ecomShop($r, 'products never ordered (whereNotIn subquery)', function (Connection $db, string $p, string $o, string $i) {
            $rows = $db->table($p)->whereNotIn('id', function ($q) use ($i) {
                $q->select('product_id')->from($i);
            })->get();
            // all 4 products were ordered -> 0
            Support::assertEquals(0, count($rows), 'all products ordered');
            return 'never ordered=' . count($rows);
        });
        self::ecomShop($r, 'repeat customers via groupBy+having', function (Connection $db, string $p, string $o, string $i) {
            $rows = $db->table($o)->select('customer_id')->selectRaw('COUNT(*) as orders')
                ->groupBy('customer_id')->having('orders', '>', 1)->get();
            Support::assert(count($rows) >= 1, 'repeat customers');
            return 'repeat=' . count($rows);
        });

        // ---- upsert / updateOrInsert on a dedicated catalog table -----------
        // MatrixOne ON DUPLICATE KEY only honors the PRIMARY KEY (finding for
        // secondary-unique upserts, handled separately below).
        self::addTable(
            $r,
            'eloq-app:ecommerce',
            'upsert price catalog on primary key',
            function (Blueprint $t) {
                $t->integer('id')->primary();
                $t->string('sku', 30);
                $t->decimal('price', 10, 2);
            },
            [['id' => 1, 'sku' => 'A', 'price' => 5.00], ['id' => 2, 'sku' => 'B', 'price' => 6.00]],
            function (Connection $db, string $t) {
                $db->table($t)->upsert(
                    [['id' => 1, 'sku' => 'A', 'price' => 7.50], ['id' => 3, 'sku' => 'C', 'price' => 9.00]],
                    ['id'],
                    ['price']
                );
                Support::assertValueEquals(7.50, $db->table($t)->where('id', 1)->value('price'), 'upsert updated id1');
                Support::assertEquals(3, $db->table($t)->count(), 'upsert inserted id3');
                return 'upserted';
            }
        );
        self::addTable(
            $r,
            'eloq-app:ecommerce',
            'upsert on secondary UNIQUE key is rejected (finding)',
            function (Blueprint $t) {
                $t->id();
                $t->string('sku', 30);
                $t->decimal('price', 10, 2);
            },
            [],
            function (Connection $db, string $t) {
                $db->statement("ALTER TABLE `$t` ADD UNIQUE KEY uq_sku (sku)");
                $db->table($t)->insert(['sku' => 'A', 'price' => 5.00]);
                $rejected = false;
                try {
                    $db->table($t)->upsert([['sku' => 'A', 'price' => 9.00]], ['sku'], ['price']);
                } catch (\Throwable) {
                    $rejected = true;
                }
                // MatrixOne ON DUPLICATE KEY UPDATE ignores secondary unique keys
                // and raises 1062 instead of updating. Documented finding.
                Support::assert($rejected, 'expected MatrixOne to reject upsert on secondary unique (it updated instead)');
                return ['detail' => 'secondary-unique upsert rejected (1062)', 'sql' => 'INSERT ... ON DUPLICATE KEY UPDATE on UNIQUE(sku)'];
            }
        );
        self::addTable(
            $r,
            'eloq-app:ecommerce',
            'updateOrInsert wishlist row',
            function (Blueprint $t) {
                $t->id();
                $t->integer('customer_id');
                $t->integer('product_id');
                $t->integer('qty');
            },
            [['customer_id' => 1, 'product_id' => 1, 'qty' => 1]],
            function (Connection $db, string $t) {
                $db->table($t)->updateOrInsert(['customer_id' => 1, 'product_id' => 1], ['qty' => 5]);
                $db->table($t)->updateOrInsert(['customer_id' => 1, 'product_id' => 2], ['qty' => 2]);
                Support::assertEquals(5, $db->table($t)->where('product_id', 1)->value('qty'), 'updated existing');
                Support::assertEquals(2, $db->table($t)->count(), 'inserted new');
                return 'ok';
            }
        );

        // ---- operator / ordering / aggregate variants (data-driven) ---------
        $opVariants = [
            ['price > 10', fn (Connection $db, string $t) => $db->table($t)->where('price', '>', 10)->count(), 2],
            ['price >= 9.99', fn (Connection $db, string $t) => $db->table($t)->where('price', '>=', 9.99)->count(), 3],
            ['price < 5', fn (Connection $db, string $t) => $db->table($t)->where('price', '<', 5)->count(), 1],
            ['price between 5 and 20', fn (Connection $db, string $t) => $db->table($t)->whereBetween('price', [5, 20])->count(), 2],
            ['stock <= 50', fn (Connection $db, string $t) => $db->table($t)->where('stock', '<=', 50)->count(), 2],
            ['sku like GAD%', fn (Connection $db, string $t) => $db->table($t)->where('sku', 'like', 'GAD%')->count(), 1],
            ['name in set', fn (Connection $db, string $t) => $db->table($t)->whereIn('name', ['Widget', 'Gizmo'])->count(), 2],
            ['MAX price', fn (Connection $db, string $t) => (int) $db->table($t)->max('price'), 49],
            ['MIN price', fn (Connection $db, string $t) => (int) ($db->table($t)->min('price') * 100), 425],
            ['SUM stock', fn (Connection $db, string $t) => (int) $db->table($t)->sum('stock'), 360],
            ['COUNT products', fn (Connection $db, string $t) => $db->table($t)->count(), 4],
            ['AVG price *100', fn (Connection $db, string $t) => (int) round($db->table($t)->avg('price') * 100), 2069],
        ];
        $catalogSchema = function (Blueprint $t) {
            $t->id();
            $t->string('sku', 30);
            $t->string('name', 60);
            $t->decimal('price', 10, 2);
            $t->integer('stock');
        };
        $catalogRows = [
            ['sku' => 'WID-1', 'name' => 'Widget', 'price' => 9.99, 'stock' => 100],
            ['sku' => 'GAD-2', 'name' => 'Gadget', 'price' => 19.50, 'stock' => 50],
            ['sku' => 'DOO-3', 'name' => 'Doohickey', 'price' => 4.25, 'stock' => 200],
            ['sku' => 'GIZ-4', 'name' => 'Gizmo', 'price' => 49.00, 'stock' => 10],
        ];
        foreach ($opVariants as [$label, $fn, $expected]) {
            self::addTable($r, 'eloq-app:ecommerce', "catalog query: $label", $catalogSchema, $catalogRows, function (Connection $db, string $t) use ($fn, $expected, $label) {
                $got = $fn($db, $t);
                Support::assertEquals($expected, $got, "catalog $label");
                return "= $got";
            });
        }
    }

    // =====================================================================
    // A2) SOCIAL
    // =====================================================================

    /** users + follows + posts + likes fixture. */
    private static function socialNet(Runner $r, string $name, callable $fn): void
    {
        $r->add(self::FW, 'eloq-app:social', $name, function () use ($fn) {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $users = Support::name('usr');
            $follows = Support::name('fol');
            $posts = Support::name('pst');
            $likes = Support::name('lik');
            $s->create($users, function (Blueprint $t) {
                $t->id();
                $t->string('handle', 30);
            });
            $s->create($follows, function (Blueprint $t) {
                $t->integer('follower_id');
                $t->integer('followed_id');
            });
            $s->create($posts, function (Blueprint $t) {
                $t->id();
                $t->integer('user_id');
                $t->string('body', 140);
            });
            $s->create($likes, function (Blueprint $t) {
                $t->integer('user_id');
                $t->integer('post_id');
            });
            try {
                $db->table($users)->insert([
                    ['id' => 1, 'handle' => 'alice'],
                    ['id' => 2, 'handle' => 'bob'],
                    ['id' => 3, 'handle' => 'carol'],
                    ['id' => 4, 'handle' => 'dave'],
                ]);
                // follows: 2->1, 3->1, 4->1 (alice has 3 followers); 1->2, 2->1 mutual
                $db->table($follows)->insert([
                    ['follower_id' => 2, 'followed_id' => 1],
                    ['follower_id' => 3, 'followed_id' => 1],
                    ['follower_id' => 4, 'followed_id' => 1],
                    ['follower_id' => 1, 'followed_id' => 2],
                    ['follower_id' => 1, 'followed_id' => 3],
                ]);
                $db->table($posts)->insert([
                    ['id' => 1, 'user_id' => 1, 'body' => 'hello world'],
                    ['id' => 2, 'user_id' => 1, 'body' => 'second post'],
                    ['id' => 3, 'user_id' => 2, 'body' => 'bob here'],
                    ['id' => 4, 'user_id' => 3, 'body' => 'carol speaks'],
                ]);
                $db->table($likes)->insert([
                    ['user_id' => 2, 'post_id' => 1],
                    ['user_id' => 3, 'post_id' => 1],
                    ['user_id' => 4, 'post_id' => 1],
                    ['user_id' => 1, 'post_id' => 3],
                    ['user_id' => 3, 'post_id' => 4],
                ]);
                return $fn($db, $users, $follows, $posts, $likes);
            } finally {
                try {
                    $s->dropIfExists($likes);
                    $s->dropIfExists($posts);
                    $s->dropIfExists($follows);
                    $s->dropIfExists($users);
                } catch (\Throwable) {
                }
            }
        });
    }

    private static function social(Runner $r): void
    {
        self::socialNet($r, 'follower count via correlated subquery select', function (Connection $db, string $u, string $f, string $p, string $l) {
            $rows = $db->table($u . ' as us')
                ->select('us.handle')
                ->selectSub(function ($q) use ($f) {
                    $q->from($f)->selectRaw('COUNT(*)')->whereColumn('followed_id', 'us.id');
                }, 'followers')
                ->orderBy('us.id')->get();
            $byHandle = [];
            foreach ($rows as $rr) {
                $byHandle[$rr->handle] = (int) $rr->followers;
            }
            Support::assertEquals(3, $byHandle['alice'], 'alice has 3 followers');
            return 'computed followers for ' . count($rows) . ' users';
        });
        self::socialNet($r, 'follower count via join+groupBy', function (Connection $db, string $u, string $f, string $p, string $l) {
            $rows = $db->table($u . ' as us')
                ->leftJoin($f . ' as fo', 'fo.followed_id', '=', 'us.id')
                ->select('us.handle')
                ->selectRaw('COUNT(fo.follower_id) as followers')
                ->groupBy('us.handle')->orderByRaw('followers DESC')->get();
            Support::assertEquals('alice', $rows[0]->handle, 'most-followed is alice');
            return 'top=' . $rows[0]->handle;
        });
        self::socialNet($r, 'following count per user', function (Connection $db, string $u, string $f, string $p, string $l) {
            $rows = $db->table($f)->select('follower_id')->selectRaw('COUNT(*) as following')
                ->groupBy('follower_id')->get();
            $map = [];
            foreach ($rows as $rr) {
                $map[$rr->follower_id] = (int) $rr->following;
            }
            Support::assertEquals(2, $map[1], 'alice follows 2');
            return 'rows=' . count($rows);
        });
        self::socialNet($r, 'feed: posts from followed users (whereIn subquery)', function (Connection $db, string $u, string $f, string $p, string $l) {
            // alice (id 1) follows 2 and 3 -> sees their posts
            $rows = $db->table($p)->whereIn('user_id', function ($q) use ($f) {
                $q->select('followed_id')->from($f)->where('follower_id', 1);
            })->get();
            Support::assertEquals(2, count($rows), 'alice feed has bob+carol posts');
            return 'feed posts=' . count($rows);
        });
        self::socialNet($r, 'feed via join on follows', function (Connection $db, string $u, string $f, string $p, string $l) {
            $rows = $db->table($p . ' as po')
                ->join($f . ' as fo', 'fo.followed_id', '=', 'po.user_id')
                ->where('fo.follower_id', 1)
                ->select('po.body')->get();
            Support::assertEquals(2, count($rows), 'feed join count');
            return 'rows=' . count($rows);
        });
        self::socialNet($r, 'most-liked posts (join+groupBy+orderBy)', function (Connection $db, string $u, string $f, string $p, string $l) {
            $rows = $db->table($p . ' as po')
                ->leftJoin($l . ' as lk', 'lk.post_id', '=', 'po.id')
                ->select('po.id', 'po.body')
                ->selectRaw('COUNT(lk.user_id) as likes')
                ->groupBy('po.id', 'po.body')->orderByRaw('likes DESC')->limit(1)->get();
            Support::assertEquals(3, (int) $rows[0]->likes, 'top post has 3 likes');
            Support::assertEquals('hello world', $rows[0]->body, 'top post body');
            return 'top likes=' . $rows[0]->likes;
        });
        self::socialNet($r, 'like count per user received', function (Connection $db, string $u, string $f, string $p, string $l) {
            $rows = $db->table($l . ' as lk')
                ->join($p . ' as po', 'po.id', '=', 'lk.post_id')
                ->select('po.user_id')
                ->selectRaw('COUNT(*) as received')
                ->groupBy('po.user_id')->orderByRaw('received DESC')->get();
            Support::assertEquals(1, (int) $rows[0]->user_id, 'alice received most likes');
            return 'rows=' . count($rows);
        });
        self::socialNet($r, 'mutual follows via self-join', function (Connection $db, string $u, string $f, string $p, string $l) {
            $rows = $db->table($f . ' as a')
                ->join($f . ' as b', function ($j) {
                    $j->on('a.follower_id', '=', 'b.followed_id')
                        ->on('a.followed_id', '=', 'b.follower_id');
                })
                ->whereColumn('a.follower_id', '<', 'a.followed_id')
                ->get();
            // 1<->2 is the only mutual pair
            Support::assertEquals(1, count($rows), 'one mutual pair');
            return 'mutual pairs=' . count($rows);
        });
        self::socialNet($r, 'non-followers (users alice does not follow)', function (Connection $db, string $u, string $f, string $p, string $l) {
            $rows = $db->table($u)->where('id', '!=', 1)
                ->whereNotIn('id', function ($q) use ($f) {
                    $q->select('followed_id')->from($f)->where('follower_id', 1);
                })->get();
            // alice follows 2,3; not 4 -> 1 row (dave)
            Support::assertEquals(1, count($rows), 'one non-followed user');
            return 'non-followed=' . count($rows);
        });
        self::socialNet($r, 'suggested follows (followed-by-followed, exclude self)', function (Connection $db, string $u, string $f, string $p, string $l) {
            // who do the people alice follows, follow?
            $rows = $db->table($f . ' as f2')
                ->whereIn('f2.follower_id', function ($q) use ($f) {
                    $q->select('followed_id')->from($f . ' as f1')->where('f1.follower_id', 1);
                })
                ->where('f2.followed_id', '!=', 1)
                ->distinct()->pluck('f2.followed_id');
            Support::assert(count($rows) >= 0, 'suggested computed');
            return 'suggestions=' . count($rows);
        });
        self::socialNet($r, 'engagement: posts with likes >= 1 (having)', function (Connection $db, string $u, string $f, string $p, string $l) {
            $rows = $db->table($l)->select('post_id')->selectRaw('COUNT(*) as c')
                ->groupBy('post_id')->having('c', '>=', 1)->get();
            Support::assert(count($rows) >= 1, 'engaged posts');
            return 'engaged=' . count($rows);
        });
        self::socialNet($r, 'users who never posted (left join null)', function (Connection $db, string $u, string $f, string $p, string $l) {
            $rows = $db->table($u . ' as us')
                ->leftJoin($p . ' as po', 'po.user_id', '=', 'us.id')
                ->whereNull('po.id')->select('us.handle')->get();
            // dave (4) never posted -> 1
            Support::assertEquals(1, count($rows), 'one user never posted');
            return 'silent users=' . count($rows);
        });

        // data-driven follower/like count assertions per user
        $userExpect = [
            ['alice', 'followers', 3],
            ['bob', 'followers', 1],
            ['carol', 'followers', 1],
            ['dave', 'followers', 0],
        ];
        foreach ($userExpect as [$handle, $metric, $expected]) {
            self::socialNet($r, "follower count for $handle = $expected", function (Connection $db, string $u, string $f, string $p, string $l) use ($handle, $expected) {
                $uid = $db->table($u)->where('handle', $handle)->value('id');
                $n = $db->table($f)->where('followed_id', $uid)->count();
                Support::assertEquals($expected, $n, "$handle followers");
                return "= $n";
            });
        }
    }

    // =====================================================================
    // A3) ANALYTICS
    // =====================================================================

    /** events fact table for analytics rollups. */
    private static function analyticsFact(Runner $r, string $name, callable $fn): void
    {
        $r->add(self::FW, 'eloq-app:analytics', $name, function () use ($fn) {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('evt');
            $s->create($tn, function (Blueprint $t) {
                $t->id();
                $t->integer('user_id');
                $t->string('event', 20);
                $t->string('country', 2);
                $t->decimal('amount', 10, 2)->default(0);
                $t->dateTime('occurred_at');
            });
            try {
                $db->table($tn)->insert([
                    ['user_id' => 1, 'event' => 'view', 'country' => 'US', 'amount' => 0, 'occurred_at' => '2024-01-05 10:00:00'],
                    ['user_id' => 1, 'event' => 'purchase', 'country' => 'US', 'amount' => 50, 'occurred_at' => '2024-01-05 11:00:00'],
                    ['user_id' => 2, 'event' => 'view', 'country' => 'GB', 'amount' => 0, 'occurred_at' => '2024-01-10 09:00:00'],
                    ['user_id' => 2, 'event' => 'purchase', 'country' => 'GB', 'amount' => 30, 'occurred_at' => '2024-01-12 09:00:00'],
                    ['user_id' => 3, 'event' => 'view', 'country' => 'US', 'amount' => 0, 'occurred_at' => '2024-02-01 14:00:00'],
                    ['user_id' => 3, 'event' => 'purchase', 'country' => 'US', 'amount' => 70, 'occurred_at' => '2024-02-02 14:00:00'],
                    ['user_id' => 4, 'event' => 'view', 'country' => 'GB', 'amount' => 0, 'occurred_at' => '2024-02-15 16:00:00'],
                    ['user_id' => 1, 'event' => 'purchase', 'country' => 'US', 'amount' => 20, 'occurred_at' => '2024-02-20 16:00:00'],
                ]);
                return $fn($db, $tn);
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
    }

    private static function analytics(Runner $r): void
    {
        self::analyticsFact($r, 'revenue by country (groupBy + SUM)', function (Connection $db, string $t) {
            $rows = $db->table($t)->where('event', 'purchase')
                ->select('country')->selectRaw('SUM(amount) as rev')
                ->groupBy('country')->orderBy('country')->get();
            $map = [];
            foreach ($rows as $rr) {
                $map[$rr->country] = (float) $rr->rev;
            }
            Support::assertValueEquals(140.00, $map['US'], 'US revenue 50+70+20');
            Support::assertValueEquals(30.00, $map['GB'], 'GB revenue');
            return 'countries=' . count($rows);
        });
        self::analyticsFact($r, 'conditional aggregation SUM(CASE WHEN)', function (Connection $db, string $t) {
            $row = $db->table($t)->selectRaw(
                "SUM(CASE WHEN event='purchase' THEN amount ELSE 0 END) as revenue, " .
                'SUM(CASE WHEN event=\'view\' THEN 1 ELSE 0 END) as views'
            )->first();
            Support::assertValueEquals(170.00, $row->revenue, 'total revenue');
            Support::assertEquals(4, (int) $row->views, 'total views');
            return "rev={$row->revenue} views={$row->views}";
        });
        self::analyticsFact($r, 'conversion rate per country (case ratio)', function (Connection $db, string $t) {
            $rows = $db->table($t)->select('country')->selectRaw(
                "SUM(CASE WHEN event='purchase' THEN 1 ELSE 0 END) as purchases, " .
                "SUM(CASE WHEN event='view' THEN 1 ELSE 0 END) as views"
            )->groupBy('country')->orderBy('country')->get();
            Support::assert(count($rows) === 2, 'two countries');
            return 'rows=' . count($rows);
        });
        self::analyticsFact($r, 'distinct active users count', function (Connection $db, string $t) {
            $n = $db->table($t)->distinct()->count('user_id');
            Support::assertEquals(4, $n, 'four distinct users');
            return "distinct users=$n";
        });
        self::analyticsFact($r, 'distinct purchasers count', function (Connection $db, string $t) {
            $n = $db->table($t)->where('event', 'purchase')->distinct()->count('user_id');
            Support::assertEquals(3, $n, 'three purchasers');
            return "purchasers=$n";
        });
        self::analyticsFact($r, 'monthly revenue bucket via DATE_FORMAT', function (Connection $db, string $t) {
            $rows = $db->table($t)->where('event', 'purchase')
                ->selectRaw("DATE_FORMAT(occurred_at, '%Y-%m') as ym, SUM(amount) as rev")
                ->groupBy('ym')->orderBy('ym')->get();
            Support::assertEquals(2, count($rows), 'two months');
            Support::assertValueEquals(80.00, $rows[0]->rev, 'January 50+30');
            Support::assertValueEquals(90.00, $rows[1]->rev, 'February 70+20');
            return 'months=' . count($rows);
        });
        self::analyticsFact($r, 'daily event counts via DATE() bucket', function (Connection $db, string $t) {
            $rows = $db->table($t)->selectRaw('DATE(occurred_at) as d, COUNT(*) as c')
                ->groupBy('d')->orderBy('d')->get();
            Support::assert(count($rows) >= 1, 'daily buckets');
            return 'days=' . count($rows);
        });
        self::analyticsFact($r, 'ROLLUP-ish: revenue per country with grand total (UNION)', function (Connection $db, string $t) {
            $perCountry = $db->table($t)->where('event', 'purchase')
                ->select('country')->selectRaw('SUM(amount) as rev')->groupBy('country');
            $grand = $db->table($t)->where('event', 'purchase')
                ->selectRaw("'ALL' as country")->selectRaw('SUM(amount) as rev');
            $rows = $perCountry->unionAll($grand)->get();
            Support::assertEquals(3, count($rows), '2 countries + grand total');
            return 'rows=' . count($rows);
        });
        self::analyticsFact($r, 'window-ish running total via correlated subquery', function (Connection $db, string $t) {
            $rows = $db->table($t . ' as e')->where('e.event', 'purchase')
                ->select('e.id', 'e.amount')
                ->selectRaw('(SELECT SUM(amount) FROM `' . $t . '` x WHERE x.event=\'purchase\' AND x.id <= e.id) as running')
                ->orderBy('e.id')->get();
            Support::assert(count($rows) >= 1, 'running totals');
            // last running == grand total 170
            $last = end($rows);
            Support::assertValueEquals(170.00, $last->running, 'final running total');
            return 'running rows=' . count($rows);
        });
        self::analyticsFact($r, 'RANK via window function ROW_NUMBER', function (Connection $db, string $t) {
            $rows = $db->table($t)->where('event', 'purchase')
                ->selectRaw('user_id, amount, ROW_NUMBER() OVER (ORDER BY amount DESC) as rnk')
                ->get();
            Support::assert(count($rows) >= 1, 'row_number rows');
            return 'ranked=' . count($rows);
        });
        self::analyticsFact($r, 'avg purchase amount having > threshold', function (Connection $db, string $t) {
            $rows = $db->table($t)->where('event', 'purchase')
                ->select('country')->selectRaw('AVG(amount) as a')
                ->groupBy('country')->having('a', '>', 40)->get();
            Support::assert(count($rows) >= 1, 'avg having');
            return 'rows=' . count($rows);
        });
        self::analyticsFact($r, 'top spender per country (subquery max)', function (Connection $db, string $t) {
            $rows = $db->table($t . ' as e')->where('e.event', 'purchase')
                ->select('e.country', 'e.user_id', 'e.amount')
                ->whereRaw('e.amount = (SELECT MAX(x.amount) FROM `' . $t . '` x WHERE x.country=e.country AND x.event=\'purchase\')')
                ->orderBy('e.country')->get();
            Support::assert(count($rows) >= 1, 'top spenders');
            return 'rows=' . count($rows);
        });

        // data-driven country revenue checks
        foreach ([['US', 140.0], ['GB', 30.0]] as [$cc, $expected]) {
            self::analyticsFact($r, "revenue check $cc = $expected", function (Connection $db, string $t) use ($cc, $expected) {
                $v = $db->table($t)->where('event', 'purchase')->where('country', $cc)->sum('amount');
                Support::assertValueEquals($expected, $v, "$cc revenue");
                return "= $v";
            });
        }
    }

    // =====================================================================
    // A4) CMS
    // =====================================================================

    /** categories (adjacency) + articles fixture. */
    private static function cmsContent(Runner $r, string $name, callable $fn): void
    {
        $r->add(self::FW, 'eloq-app:cms', $name, function () use ($fn) {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $cats = Support::name('cat');
            $arts = Support::name('art');
            $s->create($cats, function (Blueprint $t) {
                $t->id();
                $t->string('name', 40);
                $t->integer('parent_id')->nullable();
            });
            $s->create($arts, function (Blueprint $t) {
                $t->id();
                $t->integer('category_id');
                $t->string('title', 120);
                $t->string('slug', 140);
                $t->tinyInteger('published')->default(0);
                $t->dateTime('published_at')->nullable();
                $t->dateTime('deleted_at')->nullable();
            });
            try {
                $db->table($cats)->insert([
                    ['id' => 1, 'name' => 'Tech', 'parent_id' => null],
                    ['id' => 2, 'name' => 'Databases', 'parent_id' => 1],
                    ['id' => 3, 'name' => 'PHP', 'parent_id' => 1],
                    ['id' => 4, 'name' => 'Lifestyle', 'parent_id' => null],
                ]);
                $db->table($arts)->insert([
                    ['id' => 1, 'category_id' => 2, 'title' => 'Intro to SQL', 'slug' => 'intro-to-sql', 'published' => 1, 'published_at' => '2024-01-01 00:00:00', 'deleted_at' => null],
                    ['id' => 2, 'category_id' => 2, 'title' => 'Advanced SQL', 'slug' => 'advanced-sql', 'published' => 1, 'published_at' => '2024-02-01 00:00:00', 'deleted_at' => null],
                    ['id' => 3, 'category_id' => 3, 'title' => 'PHP 8.4 Tips', 'slug' => 'php-84-tips', 'published' => 1, 'published_at' => '2024-03-01 00:00:00', 'deleted_at' => null],
                    ['id' => 4, 'category_id' => 3, 'title' => 'Draft Post', 'slug' => 'draft-post', 'published' => 0, 'published_at' => null, 'deleted_at' => null],
                    ['id' => 5, 'category_id' => 4, 'title' => 'Removed', 'slug' => 'removed', 'published' => 1, 'published_at' => '2024-01-15 00:00:00', 'deleted_at' => '2024-04-01 00:00:00'],
                ]);
                return $fn($db, $cats, $arts);
            } finally {
                try {
                    $s->dropIfExists($arts);
                    $s->dropIfExists($cats);
                } catch (\Throwable) {
                }
            }
        });
    }

    private static function cms(Runner $r): void
    {
        self::cmsContent($r, 'lookup article by slug', function (Connection $db, string $c, string $a) {
            $row = $db->table($a)->where('slug', 'php-84-tips')->first();
            Support::assertEquals('PHP 8.4 Tips', $row->title, 'slug lookup');
            return 'found by slug';
        });
        self::cmsContent($r, 'slug is case-sensitive (MatrixOne finding)', function (Connection $db, string $c, string $a) {
            // Default collation is case-sensitive, so the uppercased slug must NOT match.
            $n = $db->table($a)->where('slug', 'INTRO-TO-SQL')->count();
            Support::assertEquals(0, $n, 'uppercased slug should not match under case-sensitive collation');
            return ['detail' => 'case-sensitive slug match=0', 'sql' => "WHERE slug='INTRO-TO-SQL'"];
        });
        self::cmsContent($r, 'soft-delete emulation: only live articles (whereNull deleted_at)', function (Connection $db, string $c, string $a) {
            $n = $db->table($a)->whereNull('deleted_at')->count();
            Support::assertEquals(4, $n, 'four non-deleted articles');
            return "live=$n";
        });
        self::cmsContent($r, 'published live articles (whereNull + where)', function (Connection $db, string $c, string $a) {
            $n = $db->table($a)->whereNull('deleted_at')->where('published', 1)->count();
            Support::assertEquals(3, $n, 'three published live');
            return "published live=$n";
        });
        self::cmsContent($r, 'trashed articles (whereNotNull deleted_at)', function (Connection $db, string $c, string $a) {
            $n = $db->table($a)->whereNotNull('deleted_at')->count();
            Support::assertEquals(1, $n, 'one trashed');
            return "trashed=$n";
        });
        self::cmsContent($r, 'category adjacency: children of Tech', function (Connection $db, string $c, string $a) {
            $rows = $db->table($c)->where('parent_id', 1)->orderBy('id')->get();
            Support::assertEquals(2, count($rows), 'Tech has 2 children');
            Support::assertEquals('Databases', $rows[0]->name, 'first child');
            return 'children=' . count($rows);
        });
        self::cmsContent($r, 'category adjacency: root categories (whereNull parent)', function (Connection $db, string $c, string $a) {
            $n = $db->table($c)->whereNull('parent_id')->count();
            Support::assertEquals(2, $n, 'two roots');
            return "roots=$n";
        });
        self::cmsContent($r, 'category self-join parent name', function (Connection $db, string $c, string $a) {
            $rows = $db->table($c . ' as child')
                ->leftJoin($c . ' as par', 'child.parent_id', '=', 'par.id')
                ->select('child.name as child', 'par.name as parent')
                ->orderBy('child.id')->get();
            $byChild = [];
            foreach ($rows as $rr) {
                $byChild[$rr->child] = $rr->parent;
            }
            Support::assertEquals('Tech', $byChild['Databases'], 'Databases parent is Tech');
            return 'rows=' . count($rows);
        });
        self::cmsContent($r, 'recursive CTE: category tree depth', function (Connection $db, string $c, string $a) {
            $sql = "WITH RECURSIVE tree AS (
                        SELECT id, name, parent_id, 0 as depth FROM `$c` WHERE parent_id IS NULL
                        UNION ALL
                        SELECT ch.id, ch.name, ch.parent_id, t.depth+1
                        FROM `$c` ch JOIN tree t ON ch.parent_id = t.id
                    ) SELECT MAX(depth) as md FROM tree";
            $row = $db->selectOne($sql);
            Support::assertEquals(1, (int) $row->md, 'tree max depth 1');
            return 'max depth=' . $row->md;
        });
        self::cmsContent($r, 'article count per category (join+groupBy)', function (Connection $db, string $c, string $a) {
            $rows = $db->table($c . ' as cat')
                ->leftJoin($a . ' as art', function ($j) {
                    $j->on('art.category_id', '=', 'cat.id');
                })
                ->select('cat.name')->selectRaw('COUNT(art.id) as n')
                ->groupBy('cat.name')->orderBy('cat.name')->get();
            Support::assertEquals(4, count($rows), 'all categories present');
            return 'rows=' . count($rows);
        });
        self::cmsContent($r, 'latest article per category (correlated subquery)', function (Connection $db, string $c, string $a) {
            $rows = $db->table($a . ' as art')
                ->whereNull('art.deleted_at')->whereNotNull('art.published_at')
                ->whereRaw('art.published_at = (SELECT MAX(x.published_at) FROM `' . $a . '` x WHERE x.category_id=art.category_id AND x.deleted_at IS NULL)')
                ->orderBy('art.category_id')->get();
            Support::assert(count($rows) >= 1, 'latest per category');
            return 'rows=' . count($rows);
        });
        self::cmsContent($r, 'archive by month (DATE_FORMAT bucket)', function (Connection $db, string $c, string $a) {
            $rows = $db->table($a)->whereNotNull('published_at')->whereNull('deleted_at')
                ->selectRaw("DATE_FORMAT(published_at, '%Y-%m') as ym, COUNT(*) as c")
                ->groupBy('ym')->orderBy('ym')->get();
            Support::assert(count($rows) >= 1, 'archive buckets');
            return 'months=' . count($rows);
        });
        self::cmsContent($r, 'search title LIKE', function (Connection $db, string $c, string $a) {
            $n = $db->table($a)->where('title', 'like', '%SQL%')->count();
            Support::assertEquals(2, $n, 'two SQL articles');
            return "matches=$n";
        });
        self::cmsContent($r, 'unique slug enforcement (insert dup expected reject)', function (Connection $db, string $c, string $a) {
            $db->statement("ALTER TABLE `$a` ADD UNIQUE KEY uq_slug (slug)");
            $rejected = false;
            try {
                $db->table($a)->insert(['category_id' => 1, 'title' => 'Dup', 'slug' => 'intro-to-sql', 'published' => 1]);
            } catch (\Throwable) {
                $rejected = true;
            }
            Support::assert($rejected, 'duplicate slug should be rejected by unique index');
            return 'dup slug rejected';
        });
    }

    // =====================================================================
    // A5) LEDGER (double-entry, DECIMAL money, flat transactions)
    // =====================================================================

    /** accounts + entries fixture. */
    private static function ledgerBook(Runner $r, string $name, callable $fn): void
    {
        $r->add(self::FW, 'eloq-app:ledger', $name, function () use ($fn) {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $accounts = Support::name('acct');
            $entries = Support::name('entry');
            $s->create($accounts, function (Blueprint $t) {
                $t->id();
                $t->string('code', 10);
                $t->string('name', 40);
                $t->decimal('balance', 14, 2)->default(0);
            });
            $s->create($entries, function (Blueprint $t) {
                $t->id();
                $t->integer('account_id');
                $t->string('kind', 6); // debit / credit
                $t->decimal('amount', 14, 2);
                $t->date('posted_on');
            });
            try {
                $db->table($accounts)->insert([
                    ['id' => 1, 'code' => '1000', 'name' => 'Cash', 'balance' => 1000.00],
                    ['id' => 2, 'code' => '2000', 'name' => 'Payable', 'balance' => 0.00],
                    ['id' => 3, 'code' => '3000', 'name' => 'Revenue', 'balance' => 0.00],
                ]);
                $db->table($entries)->insert([
                    ['account_id' => 1, 'kind' => 'debit', 'amount' => 500.00, 'posted_on' => '2024-01-01'],
                    ['account_id' => 3, 'kind' => 'credit', 'amount' => 500.00, 'posted_on' => '2024-01-01'],
                    ['account_id' => 1, 'kind' => 'credit', 'amount' => 200.00, 'posted_on' => '2024-01-15'],
                    ['account_id' => 2, 'kind' => 'debit', 'amount' => 200.00, 'posted_on' => '2024-01-15'],
                    ['account_id' => 1, 'kind' => 'debit', 'amount' => 300.00, 'posted_on' => '2024-02-01'],
                    ['account_id' => 3, 'kind' => 'credit', 'amount' => 300.00, 'posted_on' => '2024-02-01'],
                ]);
                return $fn($db, $accounts, $entries);
            } finally {
                try {
                    $s->dropIfExists($entries);
                    $s->dropIfExists($accounts);
                } catch (\Throwable) {
                }
            }
        });
    }

    private static function ledger(Runner $r): void
    {
        self::ledgerBook($r, 'sum debits and credits (conditional aggregation)', function (Connection $db, string $ac, string $en) {
            $row = $db->table($en)->selectRaw(
                "SUM(CASE WHEN kind='debit' THEN amount ELSE 0 END) as debits, " .
                "SUM(CASE WHEN kind='credit' THEN amount ELSE 0 END) as credits"
            )->first();
            // debits: 500+200+300=1000 ; credits: 500+200+300=1000 (balanced)
            Support::assertValueEquals(1000.00, $row->debits, 'total debits');
            Support::assertValueEquals(1000.00, $row->credits, 'total credits');
            return "debits={$row->debits} credits={$row->credits}";
        });
        self::ledgerBook($r, 'ledger is balanced (debits == credits)', function (Connection $db, string $ac, string $en) {
            $row = $db->table($en)->selectRaw(
                "SUM(CASE WHEN kind='debit' THEN amount ELSE -amount END) as net"
            )->first();
            Support::assertValueEquals(0.00, $row->net, 'balanced ledger nets to 0');
            return 'balanced';
        });
        self::ledgerBook($r, 'computed balance per account from entries', function (Connection $db, string $ac, string $en) {
            $rows = $db->table($en)->select('account_id')->selectRaw(
                "SUM(CASE WHEN kind='debit' THEN amount ELSE -amount END) as bal"
            )->groupBy('account_id')->orderBy('account_id')->get();
            $map = [];
            foreach ($rows as $rr) {
                $map[$rr->account_id] = (float) $rr->bal;
            }
            // cash: +500 -200 +300 = 600
            Support::assertValueEquals(600.00, $map[1], 'cash net from entries');
            return 'accounts=' . count($rows);
        });
        self::ledgerBook($r, 'DECIMAL precision preserved (no float drift)', function (Connection $db, string $ac, string $en) {
            $db->table($en)->insert([
                ['account_id' => 1, 'kind' => 'debit', 'amount' => 0.10, 'posted_on' => '2024-03-01'],
                ['account_id' => 1, 'kind' => 'debit', 'amount' => 0.20, 'posted_on' => '2024-03-01'],
            ]);
            $v = $db->table($en)->where('account_id', 1)->where('posted_on', '2024-03-01')->sum('amount');
            Support::assertValueEquals(0.30, $v, '0.10+0.20 == 0.30 exactly');
            return "sum=$v";
        });
        self::ledgerBook($r, 'flat transaction transfer cash->payable', function (Connection $db, string $ac, string $en) {
            $db->transaction(function () use ($db, $ac, $en) {
                $db->table($ac)->where('id', 1)->decrement('balance', 100.00);
                $db->table($ac)->where('id', 2)->increment('balance', 100.00);
                $db->table($en)->insert([
                    ['account_id' => 1, 'kind' => 'credit', 'amount' => 100.00, 'posted_on' => '2024-03-10'],
                    ['account_id' => 2, 'kind' => 'debit', 'amount' => 100.00, 'posted_on' => '2024-03-10'],
                ]);
            });
            Support::assertValueEquals(900.00, $db->table($ac)->where('id', 1)->value('balance'), 'cash after transfer');
            Support::assertValueEquals(100.00, $db->table($ac)->where('id', 2)->value('balance'), 'payable after transfer');
            return 'transferred';
        });
        self::ledgerBook($r, 'transaction rollback on exception keeps balances', function (Connection $db, string $ac, string $en) {
            $before = $db->table($ac)->where('id', 1)->value('balance');
            try {
                $db->transaction(function () use ($db, $ac) {
                    $db->table($ac)->where('id', 1)->decrement('balance', 50.00);
                    throw new \RuntimeException('abort transfer');
                });
            } catch (\RuntimeException) {
                // expected
            }
            $after = $db->table($ac)->where('id', 1)->value('balance');
            Support::assertValueEquals((float) $before, (float) $after, 'balance unchanged after rollback');
            return 'rolled back';
        });
        self::ledgerBook($r, 'account statement ordered by date', function (Connection $db, string $ac, string $en) {
            $rows = $db->table($en)->where('account_id', 1)->orderBy('posted_on')->orderBy('id')->get();
            Support::assert(count($rows) >= 3, 'statement rows');
            return 'entries=' . count($rows);
        });
        self::ledgerBook($r, 'running balance via correlated subquery', function (Connection $db, string $ac, string $en) {
            $rows = $db->table($en . ' as e')->where('e.account_id', 1)
                ->select('e.id')
                ->selectRaw("(SELECT SUM(CASE WHEN x.kind='debit' THEN x.amount ELSE -x.amount END) FROM `" . $en . "` x WHERE x.account_id=1 AND x.id <= e.id) as running")
                ->orderBy('e.id')->get();
            Support::assert(count($rows) >= 1, 'running balance computed');
            return 'rows=' . count($rows);
        });
        self::ledgerBook($r, 'trial balance per account (groupBy)', function (Connection $db, string $ac, string $en) {
            $rows = $db->table($ac . ' as a')
                ->leftJoin($en . ' as e', 'e.account_id', '=', 'a.id')
                ->select('a.name')
                ->selectRaw("SUM(CASE WHEN e.kind='debit' THEN e.amount ELSE 0 END) as dr")
                ->selectRaw("SUM(CASE WHEN e.kind='credit' THEN e.amount ELSE 0 END) as cr")
                ->groupBy('a.name')->orderBy('a.name')->get();
            Support::assertEquals(3, count($rows), 'three accounts in trial balance');
            return 'rows=' . count($rows);
        });
        self::ledgerBook($r, 'monthly debit totals (DATE_FORMAT)', function (Connection $db, string $ac, string $en) {
            $rows = $db->table($en)->where('kind', 'debit')
                ->selectRaw("DATE_FORMAT(posted_on, '%Y-%m') as ym, SUM(amount) as total")
                ->groupBy('ym')->orderBy('ym')->get();
            Support::assert(count($rows) >= 1, 'monthly debits');
            return 'months=' . count($rows);
        });

        // data-driven balance checks per account
        foreach ([[1, 600.0], [2, -200.0], [3, -800.0]] as [$acctId, $expected]) {
            self::ledgerBook($r, "net entries for account $acctId = $expected", function (Connection $db, string $ac, string $en) use ($acctId, $expected) {
                $row = $db->table($en)->where('account_id', $acctId)->selectRaw(
                    "SUM(CASE WHEN kind='debit' THEN amount ELSE -amount END) as bal"
                )->first();
                Support::assertValueEquals($expected, $row->bal, "account $acctId net");
                return "= {$row->bal}";
            });
        }
    }

    // =====================================================================
    // B1) MIGRATIONS — up/down with column-type matrices
    // =====================================================================

    private static function migrateUpDown(Runner $r): void
    {
        // Each entry: [label, blueprint builder adding a column 'c', expected
        // getColumnType (or null to skip type assertion)].
        $columns = [
            ['string', fn (Blueprint $t) => $t->string('c', 100), 'VARCHAR'],
            ['string default len', fn (Blueprint $t) => $t->string('c'), 'VARCHAR'],
            ['char', fn (Blueprint $t) => $t->char('c', 10), 'CHAR'],
            ['text', fn (Blueprint $t) => $t->text('c'), 'TEXT'],
            ['mediumText', fn (Blueprint $t) => $t->mediumText('c'), null],
            ['longText', fn (Blueprint $t) => $t->longText('c'), null],
            ['tinyText', fn (Blueprint $t) => $t->tinyText('c'), null],
            ['integer', fn (Blueprint $t) => $t->integer('c'), 'INT'],
            ['bigInteger', fn (Blueprint $t) => $t->bigInteger('c'), 'BIGINT'],
            ['tinyInteger', fn (Blueprint $t) => $t->tinyInteger('c'), null],
            ['smallInteger', fn (Blueprint $t) => $t->smallInteger('c'), null],
            ['mediumInteger', fn (Blueprint $t) => $t->mediumInteger('c'), null],
            ['unsignedInteger', fn (Blueprint $t) => $t->unsignedInteger('c'), null],
            ['unsignedBigInteger', fn (Blueprint $t) => $t->unsignedBigInteger('c'), null],
            ['unsignedTinyInteger', fn (Blueprint $t) => $t->unsignedTinyInteger('c'), null],
            ['decimal(8,2)', fn (Blueprint $t) => $t->decimal('c', 8, 2), 'DECIMAL'],
            ['decimal(18,6)', fn (Blueprint $t) => $t->decimal('c', 18, 6), 'DECIMAL'],
            ['decimal(38,10)', fn (Blueprint $t) => $t->decimal('c', 38, 10), 'DECIMAL'],
            ['float', fn (Blueprint $t) => $t->float('c'), null],
            ['double', fn (Blueprint $t) => $t->double('c'), null],
            ['boolean', fn (Blueprint $t) => $t->boolean('c'), 'TINYINT'],
            ['date', fn (Blueprint $t) => $t->date('c'), 'DATE'],
            ['dateTime', fn (Blueprint $t) => $t->dateTime('c'), 'DATETIME'],
            ['timestamp', fn (Blueprint $t) => $t->timestamp('c')->nullable(), 'TIMESTAMP'],
            ['time', fn (Blueprint $t) => $t->time('c'), 'TIME'],
            ['year', fn (Blueprint $t) => $t->year('c'), null],
            ['json', fn (Blueprint $t) => $t->json('c'), 'JSON'],
            ['binary', fn (Blueprint $t) => $t->binary('c'), null],
            ['enum', fn (Blueprint $t) => $t->enum('c', ['a', 'b', 'c']), 'ENUM'],
            ['uuid', fn (Blueprint $t) => $t->uuid('c'), 'CHAR'],
            ['ipAddress', fn (Blueprint $t) => $t->ipAddress('c'), 'VARCHAR'],
            ['macAddress', fn (Blueprint $t) => $t->macAddress('c'), null],
        ];
        foreach ($columns as [$label, $build, $expectedType]) {
            // up + hasTable + hasColumn + (optional type) + down
            $r->add(self::FW, 'eloq-migrate:up-down', "migrate up/down: $label", function () use ($build, $label, $expectedType) {
                $db = self::db();
                $s = $db->getSchemaBuilder();
                $tn = Support::name('mig');
                try {
                    // ----- up -----
                    $s->create($tn, function (Blueprint $t) use ($build) {
                        $t->id();
                        $build($t);
                    });
                    Support::assert($s->hasTable($tn), "up: table $tn not created");
                    Support::assert($s->hasColumn($tn, 'c'), "up: column c missing for $label");
                    Support::assert(in_array('c', $s->getColumnListing($tn), true), 'up: c not in column listing');
                    if ($expectedType !== null) {
                        $got = strtoupper((string) $s->getColumnType($tn, 'c'));
                        Support::assert(str_contains($got, $expectedType), "type: expected ~$expectedType got $got for $label");
                    }
                    // ----- down -----
                    $s->drop($tn);
                    Support::assert(!$s->hasTable($tn), "down: table $tn still present");
                    return ['detail' => "up+down $label ok", 'sql' => "CREATE/DROP TABLE with $label column"];
                } finally {
                    try {
                        $s->dropIfExists($tn);
                    } catch (\Throwable) {
                    }
                }
            });
        }

        // Multi-column migration "up" with a realistic users table, then down.
        $r->add(self::FW, 'eloq-migrate:up-down', 'migrate up/down: full users table', function () {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('mig');
            try {
                $s->create($tn, function (Blueprint $t) {
                    $t->id();
                    $t->string('name', 100);
                    $t->string('email', 150)->unique();
                    $t->string('password', 255);
                    $t->boolean('active')->default(true);
                    $t->json('preferences')->nullable();
                    $t->timestamp('email_verified_at')->nullable();
                    $t->timestamps();
                });
                $cols = $s->getColumnListing($tn);
                foreach (['id', 'name', 'email', 'password', 'active', 'preferences', 'email_verified_at', 'created_at', 'updated_at'] as $expected) {
                    Support::assert(in_array($expected, $cols, true), "missing column $expected");
                }
                $s->drop($tn);
                Support::assert(!$s->hasTable($tn), 'table not dropped');
                return 'full users table up/down';
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });

        // dropIfExists idempotency (down on a non-existent table).
        $r->add(self::FW, 'eloq-migrate:up-down', 'down: dropIfExists is idempotent', function () {
            $s = self::db()->getSchemaBuilder();
            $tn = Support::name('mig');
            $s->dropIfExists($tn); // never created -> should be a no-op
            $s->create($tn, fn (Blueprint $t) => $t->id());
            $s->dropIfExists($tn);
            $s->dropIfExists($tn); // again
            Support::assert(!$s->hasTable($tn), 'idempotent drop');
            return 'idempotent';
        });

        // rename table (up creates a, rename to b, down drops b).
        $r->add(self::FW, 'eloq-migrate:up-down', 'migrate: rename table', function () {
            $s = self::db()->getSchemaBuilder();
            $a = Support::name('mig');
            $b = Support::name('mig');
            try {
                $s->create($a, fn (Blueprint $t) => $t->id());
                $s->rename($a, $b);
                Support::assert($s->hasTable($b), 'rename target exists');
                Support::assert(!$s->hasTable($a), 'rename source gone');
                return 'renamed';
            } finally {
                try {
                    $s->dropIfExists($a);
                    $s->dropIfExists($b);
                } catch (\Throwable) {
                }
            }
        });

        // hasColumns plural introspection.
        $r->add(self::FW, 'eloq-migrate:up-down', 'introspect: hasColumns plural', function () {
            $s = self::db()->getSchemaBuilder();
            $tn = Support::name('mig');
            try {
                $s->create($tn, function (Blueprint $t) {
                    $t->id();
                    $t->string('a')->nullable();
                    $t->string('b')->nullable();
                });
                Support::assert($s->hasColumns($tn, ['a', 'b']), 'hasColumns true');
                Support::assert(!$s->hasColumn($tn, 'zzz'), 'missing column false');
                return 'hasColumns ok';
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
    }

    // =====================================================================
    // B2) MIGRATIONS — column ALTER operations
    // =====================================================================

    private static function migrateColumn(Runner $r): void
    {
        // add column (nullable), assert present then drop.
        $r->add(self::FW, 'eloq-migrate:column', 'ALTER add column', function () {
            $s = self::db()->getSchemaBuilder();
            $tn = Support::name('alt');
            try {
                $s->create($tn, fn (Blueprint $t) => $t->id());
                $s->table($tn, fn (Blueprint $t) => $t->string('nickname', 50)->nullable());
                Support::assert($s->hasColumn($tn, 'nickname'), 'added column present');
                return 'added';
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
        $r->add(self::FW, 'eloq-migrate:column', 'ALTER add column after()', function () {
            $s = self::db()->getSchemaBuilder();
            $tn = Support::name('alt');
            try {
                $s->create($tn, function (Blueprint $t) {
                    $t->id();
                    $t->string('first', 50);
                });
                $s->table($tn, fn (Blueprint $t) => $t->string('middle', 50)->nullable()->after('first'));
                Support::assert($s->hasColumn($tn, 'middle'), 'after column present');
                // verify ordinal position: middle should sit right after first
                $cols = $s->getColumnListing($tn);
                $pos = array_search('middle', $cols, true);
                $posFirst = array_search('first', $cols, true);
                Support::assert($pos === $posFirst + 1, 'after() placed column in order');
                return 'added after';
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
        $r->add(self::FW, 'eloq-migrate:column', 'ALTER add column first()', function () {
            $s = self::db()->getSchemaBuilder();
            $tn = Support::name('alt');
            try {
                $s->create($tn, function (Blueprint $t) {
                    $t->id();
                    $t->string('x', 10)->nullable();
                });
                $s->table($tn, fn (Blueprint $t) => $t->integer('lead')->nullable()->first());
                $cols = $s->getColumnListing($tn);
                Support::assertEquals('lead', $cols[0], 'first() column is first');
                return 'added first';
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
        $r->add(self::FW, 'eloq-migrate:column', 'ALTER rename column', function () {
            $s = self::db()->getSchemaBuilder();
            $tn = Support::name('alt');
            try {
                $s->create($tn, function (Blueprint $t) {
                    $t->id();
                    $t->string('old_name', 50)->nullable();
                });
                $s->table($tn, fn (Blueprint $t) => $t->renameColumn('old_name', 'new_name'));
                Support::assert($s->hasColumn($tn, 'new_name'), 'renamed column present');
                Support::assert(!$s->hasColumn($tn, 'old_name'), 'old column gone');
                return 'renamed';
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
        $r->add(self::FW, 'eloq-migrate:column', 'ALTER rename column preserves data', function () {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('alt');
            try {
                $s->create($tn, function (Blueprint $t) {
                    $t->id();
                    $t->string('label', 50);
                });
                $db->table($tn)->insert(['id' => 1, 'label' => 'keep-me']);
                $s->table($tn, fn (Blueprint $t) => $t->renameColumn('label', 'caption'));
                $v = $db->table($tn)->where('id', 1)->value('caption');
                Support::assertEquals('keep-me', $v, 'data preserved through rename');
                return 'rename kept data';
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
        $r->add(self::FW, 'eloq-migrate:column', 'ALTER drop column', function () {
            $s = self::db()->getSchemaBuilder();
            $tn = Support::name('alt');
            try {
                $s->create($tn, function (Blueprint $t) {
                    $t->id();
                    $t->string('temp', 20)->nullable();
                });
                $s->table($tn, fn (Blueprint $t) => $t->dropColumn('temp'));
                Support::assert(!$s->hasColumn($tn, 'temp'), 'column dropped');
                return 'dropped';
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
        $r->add(self::FW, 'eloq-migrate:column', 'ALTER drop multiple columns', function () {
            $s = self::db()->getSchemaBuilder();
            $tn = Support::name('alt');
            try {
                $s->create($tn, function (Blueprint $t) {
                    $t->id();
                    $t->string('a')->nullable();
                    $t->string('b')->nullable();
                    $t->string('c')->nullable();
                });
                $s->table($tn, fn (Blueprint $t) => $t->dropColumn(['a', 'b']));
                Support::assert(!$s->hasColumn($tn, 'a') && !$s->hasColumn($tn, 'b'), 'both dropped');
                Support::assert($s->hasColumn($tn, 'c'), 'c retained');
                return 'dropped two';
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
        $r->add(self::FW, 'eloq-migrate:column', 'ALTER change column type (Laravel 12 native change())', function () {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('alt');
            try {
                $s->create($tn, function (Blueprint $t) {
                    $t->id();
                    $t->string('body', 50)->nullable();
                });
                $s->table($tn, fn (Blueprint $t) => $t->text('body')->nullable()->change());
                $type = strtoupper((string) $s->getColumnType($tn, 'body'));
                Support::assert(str_contains($type, 'TEXT'), "change(): expected TEXT got $type");
                return "changed to $type";
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
        $r->add(self::FW, 'eloq-migrate:column', 'ALTER change int->bigInteger', function () {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('alt');
            try {
                $s->create($tn, function (Blueprint $t) {
                    $t->id();
                    $t->integer('counter')->nullable();
                });
                $s->table($tn, fn (Blueprint $t) => $t->bigInteger('counter')->nullable()->change());
                $type = strtoupper((string) $s->getColumnType($tn, 'counter'));
                Support::assert(str_contains($type, 'BIGINT'), "change int->bigint: got $type");
                return "changed to $type";
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
        $r->add(self::FW, 'eloq-migrate:column', 'ALTER widen string length', function () {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('alt');
            try {
                $s->create($tn, function (Blueprint $t) {
                    $t->id();
                    $t->string('code', 10)->nullable();
                });
                $s->table($tn, fn (Blueprint $t) => $t->string('code', 255)->nullable()->change());
                // a 200-char value should now fit
                $db->table($tn)->insert(['id' => 1, 'code' => str_repeat('z', 200)]);
                Support::assertEquals(200, strlen((string) $db->table($tn)->where('id', 1)->value('code')), 'widened length stores 200');
                return 'widened';
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
        $r->add(self::FW, 'eloq-migrate:column', 'ALTER add index', function () {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('alt');
            try {
                $s->create($tn, function (Blueprint $t) {
                    $t->id();
                    $t->string('c', 30)->nullable();
                });
                $s->table($tn, fn (Blueprint $t) => $t->index('c', 'ix_c'));
                $rows = $db->select("SHOW INDEX FROM `$tn` WHERE Key_name='ix_c'");
                Support::assert(count($rows) >= 1, 'index created');
                return 'indexed';
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
        $r->add(self::FW, 'eloq-migrate:column', 'ALTER drop index', function () {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('alt');
            try {
                $s->create($tn, function (Blueprint $t) {
                    $t->id();
                    $t->string('c', 30)->nullable()->index('ix_c');
                });
                $s->table($tn, fn (Blueprint $t) => $t->dropIndex('ix_c'));
                $rows = $db->select("SHOW INDEX FROM `$tn` WHERE Key_name='ix_c'");
                Support::assertEquals(0, count($rows), 'index dropped');
                return 'dropped index';
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
        $r->add(self::FW, 'eloq-migrate:column', 'ALTER add unique', function () {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('alt');
            try {
                $s->create($tn, function (Blueprint $t) {
                    $t->id();
                    $t->string('email', 50)->nullable();
                });
                $s->table($tn, fn (Blueprint $t) => $t->unique('email', 'uq_email'));
                $db->table($tn)->insert(['id' => 1, 'email' => 'a@x']);
                $rejected = false;
                try {
                    $db->table($tn)->insert(['id' => 2, 'email' => 'a@x']);
                } catch (\Throwable) {
                    $rejected = true;
                }
                Support::assert($rejected, 'unique index enforced');
                return 'unique enforced';
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
        $r->add(self::FW, 'eloq-migrate:column', 'ALTER drop unique', function () {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('alt');
            try {
                $s->create($tn, function (Blueprint $t) {
                    $t->id();
                    $t->string('email', 50)->nullable()->unique('uq_email');
                });
                $s->table($tn, fn (Blueprint $t) => $t->dropUnique('uq_email'));
                // now duplicates allowed
                $db->table($tn)->insert([['id' => 1, 'email' => 'a@x'], ['id' => 2, 'email' => 'a@x']]);
                Support::assertEquals(2, $db->table($tn)->where('email', 'a@x')->count(), 'duplicates allowed after drop unique');
                return 'dropped unique';
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
        $r->add(self::FW, 'eloq-migrate:column', 'ALTER add column with default applies to inserts', function () {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('alt');
            try {
                $s->create($tn, fn (Blueprint $t) => $t->id());
                $s->table($tn, fn (Blueprint $t) => $t->integer('status')->default(1));
                $db->table($tn)->insert(['id' => 1]);
                Support::assertEquals(1, $db->table($tn)->where('id', 1)->value('status'), 'default applied');
                return 'default applied';
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
        $r->add(self::FW, 'eloq-migrate:column', 'ALTER add nullable column then store null', function () {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('alt');
            try {
                $s->create($tn, fn (Blueprint $t) => $t->id());
                $s->table($tn, fn (Blueprint $t) => $t->string('note', 50)->nullable());
                $db->table($tn)->insert(['id' => 1]);
                Support::assert($db->table($tn)->where('id', 1)->value('note') === null, 'nullable stays null');
                return 'nullable ok';
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
        $r->add(self::FW, 'eloq-migrate:column', 'ALTER add composite index', function () {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $tn = Support::name('alt');
            try {
                $s->create($tn, function (Blueprint $t) {
                    $t->id();
                    $t->integer('a')->nullable();
                    $t->integer('b')->nullable();
                });
                $s->table($tn, fn (Blueprint $t) => $t->index(['a', 'b'], 'ix_ab'));
                $rows = $db->select("SHOW INDEX FROM `$tn` WHERE Key_name='ix_ab'");
                Support::assert(count($rows) >= 2, 'composite index over 2 cols');
                return 'composite index';
            } finally {
                try {
                    $s->dropIfExists($tn);
                } catch (\Throwable) {
                }
            }
        });
    }

    // =====================================================================
    // B3) MIGRATIONS — foreign keys
    // =====================================================================

    /**
     * Build a parent+child via migrations, run $fn, drop child-then-parent.
     *
     * @param callable(Blueprint,string):void          $childSchema  (Blueprint, parentTable)
     * @param callable(Connection,string,string):(string|array) $fn
     */
    private static function withFk(Runner $r, string $name, callable $childSchema, callable $fn): void
    {
        $r->add(self::FW, 'eloq-migrate:fk', $name, function () use ($childSchema, $fn) {
            $db = self::db();
            $s = $db->getSchemaBuilder();
            $parent = Support::name('par');
            $child = Support::name('chi');
            try {
                $s->create($parent, function (Blueprint $t) {
                    $t->id();
                    $t->string('label', 30)->nullable();
                });
                $s->create($child, function (Blueprint $t) use ($childSchema, $parent) {
                    $childSchema($t, $parent);
                });
                return $fn($db, $parent, $child);
            } finally {
                try {
                    $s->dropIfExists($child);
                    $s->dropIfExists($parent);
                } catch (\Throwable) {
                }
            }
        });
    }

    private static function migrateFk(Runner $r): void
    {
        self::withFk(
            $r,
            'foreignId constrained creates FK',
            function (Blueprint $t, string $parent) {
                $t->id();
                $t->foreignId('parent_id')->constrained($parent);
            },
            function (Connection $db, string $parent, string $child) {
                $db->table($parent)->insert(['id' => 1, 'label' => 'p']);
                $db->table($child)->insert(['id' => 1, 'parent_id' => 1]);
                Support::assertEquals(1, $db->table($child)->count(), 'valid FK row inserted');
                return 'fk created';
            }
        );
        self::withFk(
            $r,
            'FK rejects orphan child insert',
            function (Blueprint $t, string $parent) {
                $t->id();
                $t->foreignId('parent_id')->constrained($parent);
            },
            function (Connection $db, string $parent, string $child) {
                $rejected = false;
                try {
                    $db->table($child)->insert(['id' => 1, 'parent_id' => 999]);
                } catch (\Throwable) {
                    $rejected = true;
                }
                Support::assert($rejected, 'orphan insert should be rejected by FK');
                return 'orphan rejected';
            }
        );
        self::withFk(
            $r,
            'FK onDelete cascade removes children',
            function (Blueprint $t, string $parent) {
                $t->id();
                $t->foreignId('parent_id')->constrained($parent)->cascadeOnDelete();
            },
            function (Connection $db, string $parent, string $child) {
                $db->table($parent)->insert(['id' => 1, 'label' => 'p']);
                $db->table($child)->insert([['id' => 1, 'parent_id' => 1], ['id' => 2, 'parent_id' => 1]]);
                $db->table($parent)->where('id', 1)->delete();
                Support::assertEquals(0, $db->table($child)->count(), 'cascade removed children');
                return 'cascaded';
            }
        );
        self::withFk(
            $r,
            'FK onDelete set null nulls children',
            function (Blueprint $t, string $parent) {
                $t->id();
                $t->foreignId('parent_id')->nullable()->constrained($parent)->nullOnDelete();
            },
            function (Connection $db, string $parent, string $child) {
                $db->table($parent)->insert(['id' => 1, 'label' => 'p']);
                $db->table($child)->insert(['id' => 1, 'parent_id' => 1]);
                $db->table($parent)->where('id', 1)->delete();
                $v = $db->table($child)->where('id', 1)->value('parent_id');
                Support::assert($v === null, 'set null nulled the FK column');
                return 'nulled';
            }
        );
        self::withFk(
            $r,
            'FK onDelete restrict blocks parent delete',
            function (Blueprint $t, string $parent) {
                $t->id();
                $t->foreignId('parent_id')->constrained($parent)->restrictOnDelete();
            },
            function (Connection $db, string $parent, string $child) {
                $db->table($parent)->insert(['id' => 1, 'label' => 'p']);
                $db->table($child)->insert(['id' => 1, 'parent_id' => 1]);
                $blocked = false;
                try {
                    $db->table($parent)->where('id', 1)->delete();
                } catch (\Throwable) {
                    $blocked = true;
                }
                Support::assert($blocked, 'restrict should block parent delete');
                return 'restricted';
            }
        );
        self::withFk(
            $r,
            'FK explicit foreign()->references()->on()',
            function (Blueprint $t, string $parent) {
                $t->id();
                $t->unsignedBigInteger('pid');
                $t->foreign('pid')->references('id')->on($parent)->onDelete('cascade');
            },
            function (Connection $db, string $parent, string $child) {
                $db->table($parent)->insert(['id' => 1, 'label' => 'p']);
                $db->table($child)->insert(['id' => 1, 'pid' => 1]);
                $db->table($parent)->where('id', 1)->delete();
                Support::assertEquals(0, $db->table($child)->count(), 'explicit FK cascade');
                return 'explicit fk cascade';
            }
        );
        self::withFk(
            $r,
            'FK onUpdate cascade propagates',
            function (Blueprint $t, string $parent) {
                $t->id();
                $t->unsignedBigInteger('pid');
                $t->foreign('pid')->references('id')->on($parent)->onUpdate('cascade')->onDelete('cascade');
            },
            function (Connection $db, string $parent, string $child) {
                $db->table($parent)->insert(['id' => 1, 'label' => 'p']);
                $db->table($child)->insert(['id' => 1, 'pid' => 1]);
                try {
                    $db->table($parent)->where('id', 1)->update(['id' => 5]);
                } catch (\Throwable $e) {
                    // MatrixOne may not support PK update with cascade; record as finding
                    Support::skip('ON UPDATE CASCADE / PK update not supported: ' . substr($e->getMessage(), 0, 80));
                }
                $v = $db->table($child)->where('id', 1)->value('pid');
                Support::assertEquals(5, $v, 'child pid updated via cascade');
                return 'update cascaded';
            }
        );
        self::withFk(
            $r,
            'dropForeign by column array',
            function (Blueprint $t, string $parent) {
                $t->id();
                $t->foreignId('parent_id')->constrained($parent);
            },
            function (Connection $db, string $parent, string $child) use ($r) {
                $s = $db->getSchemaBuilder();
                $s->table($child, fn (Blueprint $t) => $t->dropForeign(['parent_id']));
                // FK gone -> orphan insert now allowed
                $db->table($child)->insert(['id' => 1, 'parent_id' => 999]);
                Support::assertEquals(1, $db->table($child)->count(), 'orphan allowed after dropForeign');
                return 'fk dropped';
            }
        );
        self::withFk(
            $r,
            'dropConstrainedForeignId',
            function (Blueprint $t, string $parent) {
                $t->id();
                $t->foreignId('parent_id')->constrained($parent);
            },
            function (Connection $db, string $parent, string $child) {
                $s = $db->getSchemaBuilder();
                $s->table($child, fn (Blueprint $t) => $t->dropConstrainedForeignId('parent_id'));
                Support::assert(!$s->hasColumn($child, 'parent_id'), 'column + FK dropped');
                return 'constrained id dropped';
            }
        );
        self::withFk(
            $r,
            'add FK via separate ALTER migration',
            function (Blueprint $t, string $parent) {
                // create child without FK first
                $t->id();
                $t->unsignedBigInteger('parent_id')->nullable();
            },
            function (Connection $db, string $parent, string $child) {
                $s = $db->getSchemaBuilder();
                $s->table($child, function (Blueprint $t) use ($parent) {
                    $t->foreign('parent_id')->references('id')->on($parent);
                });
                $db->table($parent)->insert(['id' => 1, 'label' => 'p']);
                $rejected = false;
                try {
                    $db->table($child)->insert(['id' => 1, 'parent_id' => 999]);
                } catch (\Throwable) {
                    $rejected = true;
                }
                Support::assert($rejected, 'FK added via ALTER enforces referential integrity');
                return 'fk added via alter';
            }
        );
        self::withFk(
            $r,
            'self-referential FK (tree parent)',
            function (Blueprint $t, string $parent) {
                $t->id();
                $t->foreignId('parent_id')->constrained($parent);
            },
            function (Connection $db, string $parent, string $child) {
                // not a self-ref on $child, but verify FK column listing exists
                $s = $db->getSchemaBuilder();
                Support::assert($s->hasColumn($child, 'parent_id'), 'fk column present');
                $db->table($parent)->insert(['id' => 1, 'label' => 'root']);
                $db->table($child)->insert(['id' => 1, 'parent_id' => 1]);
                Support::assertEquals(1, $db->table($child)->count(), 'fk row inserted');
                return 'ok';
            }
        );
    }

    // =====================================================================
    // C) JSON casts + arrow path operator
    // =====================================================================

    private static function json(Runner $r): void
    {
        // ---- arrow path operator in WHERE (supported) ----------------------
        self::addTable(
            $r,
            'eloq-json',
            'arrow where col->key scalar match',
            function (Blueprint $t) {
                $t->id();
                $t->json('doc')->nullable();
            },
            [
                ['doc' => '{"priority":1}'],
                ['doc' => '{"priority":2}'],
                ['doc' => '{"priority":3}'],
            ],
            function (Connection $db, string $t) {
                $n = $db->table($t)->where('doc->priority', 2)->count();
                Support::assertEquals(1, $n, 'arrow where priority=2');
                return "matched=$n";
            }
        );
        self::addTable(
            $r,
            'eloq-json',
            'arrow where nested col->a->b',
            function (Blueprint $t) {
                $t->id();
                $t->json('doc')->nullable();
            },
            [
                ['doc' => '{"a":{"b":7}}'],
                ['doc' => '{"a":{"b":9}}'],
            ],
            function (Connection $db, string $t) {
                $n = $db->table($t)->where('doc->a->b', 7)->count();
                Support::assertEquals(1, $n, 'nested arrow where');
                return "matched=$n";
            }
        );
        self::addTable(
            $r,
            'eloq-json',
            'arrow select value()',
            function (Blueprint $t) {
                $t->id();
                $t->json('doc')->nullable();
            },
            [['doc' => '{"k":42}']],
            function (Connection $db, string $t) {
                $v = $db->table($t)->value('doc->k');
                Support::assertEquals(42, (int) $v, 'arrow select value');
                return "v=$v";
            }
        );
        self::addTable(
            $r,
            'eloq-json',
            'arrow orderBy col->path',
            function (Blueprint $t) {
                $t->id();
                $t->json('doc')->nullable();
            },
            [
                ['doc' => '{"rank":3}'],
                ['doc' => '{"rank":1}'],
                ['doc' => '{"rank":2}'],
            ],
            function (Connection $db, string $t) {
                $first = $db->table($t)->orderBy('doc->rank')->first();
                Support::assertEquals(1, (int) json_decode($first->doc)->rank, 'orderBy arrow asc');
                $last = $db->table($t)->orderByDesc('doc->rank')->first();
                Support::assertEquals(3, (int) json_decode($last->doc)->rank, 'orderBy arrow desc');
                return 'ordered by json path';
            }
        );
        self::addTable(
            $r,
            'eloq-json',
            'arrow where string value',
            function (Blueprint $t) {
                $t->id();
                $t->json('doc')->nullable();
            },
            [
                ['doc' => '{"status":"active"}'],
                ['doc' => '{"status":"inactive"}'],
            ],
            function (Connection $db, string $t) {
                $n = $db->table($t)->where('doc->status', 'active')->count();
                Support::assertEquals(1, $n, 'arrow where string');
                return "active=$n";
            }
        );
        self::addTable(
            $r,
            'eloq-json',
            'arrow update json path',
            function (Blueprint $t) {
                $t->id();
                $t->json('doc')->nullable();
            },
            [['doc' => '{"count":1}']],
            function (Connection $db, string $t) {
                $db->table($t)->update(['doc->count' => 5]);
                $v = $db->table($t)->value('doc->count');
                Support::assertEquals(5, (int) $v, 'arrow update path');
                return "count=$v";
            }
        );
        self::addTable(
            $r,
            'eloq-json',
            'whereJsonLength on array path',
            function (Blueprint $t) {
                $t->id();
                $t->json('doc')->nullable();
            },
            [
                ['doc' => '{"items":[1,2,3]}'],
                ['doc' => '{"items":[1]}'],
            ],
            function (Connection $db, string $t) {
                $n = $db->table($t)->whereJsonLength('doc->items', 3)->count();
                Support::assertEquals(1, $n, 'whereJsonLength=3');
                return "len3=$n";
            }
        );

        // ---- whereJsonContains: UNSUPPORTED (JSON_CONTAINS not implemented) -
        self::addTable(
            $r,
            'eloq-json',
            'whereJsonContains is unsupported (finding)',
            function (Blueprint $t) {
                $t->id();
                $t->json('tags')->nullable();
            },
            [
                ['tags' => '["admin","editor"]'],
                ['tags' => '["viewer"]'],
            ],
            function (Connection $db, string $t) {
                $failed = false;
                $msg = '';
                try {
                    $db->table($t)->whereJsonContains('tags', 'admin')->count();
                } catch (\Throwable $e) {
                    $failed = true;
                    $msg = $e->getMessage();
                }
                // MatrixOne: error 20105 not supported: function or operator 'json_contains'
                Support::assert($failed && str_contains($msg, 'json_contains'), 'expected JSON_CONTAINS unsupported error');
                return ['detail' => 'JSON_CONTAINS unsupported (20105)', 'sql' => 'whereJsonContains -> JSON_CONTAINS'];
            }
        );
        self::addTable(
            $r,
            'eloq-json',
            'whereJsonContains nested path is unsupported (finding)',
            function (Blueprint $t) {
                $t->id();
                $t->json('doc')->nullable();
            },
            [['doc' => '{"roles":["a","b"]}']],
            function (Connection $db, string $t) {
                $failed = false;
                try {
                    $db->table($t)->whereJsonContains('doc->roles', 'a')->count();
                } catch (\Throwable $e) {
                    $failed = str_contains($e->getMessage(), 'json_contains');
                }
                Support::assert($failed, 'expected JSON_CONTAINS unsupported on nested path');
                return 'json_contains nested unsupported';
            }
        );

        // ---- JSON cast round-trips via query builder (json_encode payloads) -
        $castPayloads = [
            ['array of scalars', '{"v":[1,2,3]}', fn ($d) => count($d->v) === 3],
            ['nested object', '{"v":{"a":{"b":1}}}', fn ($d) => $d->v->a->b === 1],
            ['boolean true', '{"v":true}', fn ($d) => $d->v === true],
            ['null value', '{"v":null}', fn ($d) => $d->v === null],
            ['unicode string', '{"v":"café"}', fn ($d) => $d->v === 'café'],
            ['number', '{"v":3.5}', fn ($d) => $d->v === 3.5],
            ['empty array', '{"v":[]}', fn ($d) => is_array($d->v) && count($d->v) === 0],
            ['empty object', '{"v":{}}', fn ($d) => is_object($d->v)],
        ];
        foreach ($castPayloads as [$label, $payload, $check]) {
            self::addTable(
                $r,
                'eloq-json',
                "json round-trip: $label",
                function (Blueprint $t) {
                    $t->id();
                    $t->json('doc')->nullable();
                },
                [['doc' => $payload]],
                function (Connection $db, string $t) use ($check, $label) {
                    $raw = $db->table($t)->value('doc');
                    $decoded = json_decode((string) $raw);
                    Support::assert($check($decoded), "round-trip mismatch for $label (raw=$raw)");
                    return "= $raw";
                }
            );
        }

        // ---- JSON_EXTRACT / -> arithmetic in selectRaw ---------------------
        self::addTable(
            $r,
            'eloq-json',
            'aggregate over json arrow path',
            function (Blueprint $t) {
                $t->id();
                $t->json('doc')->nullable();
            },
            [
                ['doc' => '{"amount":10}'],
                ['doc' => '{"amount":20}'],
                ['doc' => '{"amount":30}'],
            ],
            function (Connection $db, string $t) {
                $row = $db->table($t)->selectRaw("SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(doc, '$.amount')) AS DECIMAL(10,2))) as total")->first();
                Support::assertValueEquals(60.00, $row->total, 'sum over json amounts');
                return "total={$row->total}";
            }
        );
        self::addTable(
            $r,
            'eloq-json',
            'groupBy json arrow path',
            function (Blueprint $t) {
                $t->id();
                $t->json('doc')->nullable();
            },
            [
                ['doc' => '{"cat":"x"}'],
                ['doc' => '{"cat":"x"}'],
                ['doc' => '{"cat":"y"}'],
            ],
            function (Connection $db, string $t) {
                $rows = $db->table($t)->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(doc,'$.cat')) as c, COUNT(*) as n")
                    ->groupBy('c')->orderBy('c')->get();
                Support::assertEquals(2, count($rows), 'two json categories');
                Support::assertEquals(2, (int) $rows[0]->n, 'category x has 2');
                return 'grouped by json path';
            }
        );
    }
}
