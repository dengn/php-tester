<?php

declare(strict_types=1);

namespace MoTest\Scenarios;

use MoTest\Config;
use MoTest\Connections;
use MoTest\Runner;
use MoTest\Support;

/**
 * Real-world application WORKLOAD scenarios for MatrixOne over raw PDO.
 *
 * Unlike the type/semantics matrices, these model what actual applications do:
 * build a small representative schema, seed a handful of rows, run the queries
 * a real e-commerce / social / analytics / time-series / ledger / SaaS / CMS /
 * geo application would issue, and assert the result the engine returns.
 *
 * Conventions (mirroring TypeMatrixScenarios):
 *   - one fresh PDO connection per scenario,
 *   - unique table names via Support::name(), every created table dropped in a
 *     finally{} guarded by try/catch so teardown never throws,
 *   - DDL/DML via exec() (text protocol), reads via query(),
 *   - every closure captures ALL referenced variables in use(...).
 *
 * Scenarios are generated from compact data-driven matrices of
 *   { name, setup-sql[], query, expected, mode }
 * so a few tables explode into many registered checks.
 *
 * Table-name templating: any occurrence of {T}, {T1}, {T2}, ... {T6} inside a
 * setup statement or the query is replaced with a freshly generated unique
 * table name. The same placeholder always maps to the same table within one
 * scenario, and every distinct placeholder used is dropped in finally.
 *
 * A failing scenario is a genuine MatrixOne finding, not a harness bug.
 */
final class AppWorkloadScenarios
{
    private const FW = 'PDO';

    public static function register(Runner $r): void
    {
        self::ecommerce($r);
        self::social($r);
        self::analytics($r);
        self::timeseries($r);
        self::ledger($r);
        self::saas($r);
        self::cms($r);
        self::geo($r);

        // Bulk data-driven expansions to reach broad workload coverage.
        self::ecommerceBulk($r);
        self::socialBulk($r);
        self::analyticsBulk($r);
        self::timeseriesBulk($r);
        self::ledgerBulk($r);
        self::saasBulk($r);
        self::cmsBulk($r);
        self::geoBulk($r);
    }

    private static function db(): string
    {
        return Config::database('pdo');
    }

    /**
     * Replace {T}, {T1}.. placeholders in a string with the table names from $map.
     */
    private static function tpl(string $s, array $map): string
    {
        return strtr($s, $map);
    }

    /**
     * Generic data-driven scenario. Creates one fresh PDO connection, runs each
     * statement in $setup via exec(), runs $query via query(), and asserts the
     * first column of the first row equals $expected according to $mode.
     *
     * Placeholders {T}, {T1}..{T6} in setup/query are rewritten to unique table
     * names; each distinct placeholder is dropped in finally.
     *
     * @param string[] $setup
     * @param string   $mode  'value' | 'exact' | 'null' | 'notnull' | 'rows' | 'col'
     * @param mixed    $expected for 'rows' an int row-count; for 'col' a comma list
     */
    private static function addCase(
        Runner $r,
        string $cat,
        string $name,
        array $setup,
        string $query,
        mixed $expected,
        string $mode = 'value'
    ): void {
        $db = self::db();
        $r->add(self::FW, $cat, $name, function () use ($db, $setup, $query, $expected, $mode, $name) {
            $pdo = Connections::pdo($db);
            // Discover which placeholders are referenced.
            $all = $query . ' ' . implode(' ', $setup);
            $map = [];
            foreach (['{T}', '{T1}', '{T2}', '{T3}', '{T4}', '{T5}', '{T6}'] as $ph) {
                if (str_contains($all, $ph)) {
                    $map[$ph] = Support::name('aw');
                }
            }
            try {
                foreach ($setup as $stmt) {
                    $pdo->exec(self::tpl($stmt, $map));
                }
                $sql = self::tpl($query, $map);
                $stmt = $pdo->query($sql);
                switch ($mode) {
                    case 'rows':
                        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
                        Support::assertEquals((string) $expected, (string) count($rows), "$name row count");
                        return ['detail' => 'rows=' . count($rows), 'sql' => $sql];
                    case 'col':
                        $col = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                        $got = implode(',', array_map(static fn ($v) => $v === null ? 'NULL' : (string) $v, $col));
                        Support::assertEquals((string) $expected, $got, "$name column list");
                        return ['detail' => "[$got]", 'sql' => $sql];
                    case 'null':
                        $got = $stmt->fetchColumn();
                        Support::assert($got === null, "$name: expected NULL got " . var_export($got, true));
                        return ['detail' => 'NULL', 'sql' => $sql];
                    case 'notnull':
                        $got = $stmt->fetchColumn();
                        Support::assert($got !== null, "$name: got NULL");
                        return ['detail' => '= ' . var_export($got, true), 'sql' => $sql];
                    case 'exact':
                        $got = $stmt->fetchColumn();
                        Support::assertEquals($expected, $got, $name);
                        return ['detail' => '= ' . var_export($got, true), 'sql' => $sql];
                    case 'value':
                    default:
                        $got = $stmt->fetchColumn();
                        Support::assertValueEquals($expected, $got, $name);
                        return ['detail' => '= ' . var_export($got, true), 'sql' => $sql];
                }
            } finally {
                foreach ($map as $tn) {
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                    } catch (\Throwable) {
                    }
                }
            }
        });
    }

    /**
     * A scenario that expects the query itself to ERROR (genuine MatrixOne gap).
     * Passes when the query throws; fails when it unexpectedly succeeds.
     *
     * @param string[] $setup
     */
    private static function addError(
        Runner $r,
        string $cat,
        string $name,
        array $setup,
        string $query
    ): void {
        $db = self::db();
        $r->add(self::FW, $cat, $name, function () use ($db, $setup, $query, $name) {
            $pdo = Connections::pdo($db);
            $all = $query . ' ' . implode(' ', $setup);
            $map = [];
            foreach (['{T}', '{T1}', '{T2}', '{T3}'] as $ph) {
                if (str_contains($all, $ph)) {
                    $map[$ph] = Support::name('aw');
                }
            }
            try {
                foreach ($setup as $stmt) {
                    $pdo->exec(self::tpl($stmt, $map));
                }
                $sql = self::tpl($query, $map);
                $errored = false;
                $err = '';
                try {
                    $pdo->query($sql)->fetchAll();
                } catch (\Throwable $e) {
                    $errored = true;
                    $err = $e->getMessage();
                }
                Support::assert($errored, "$name: query unexpectedly succeeded");
                return ['detail' => 'errored as expected: ' . Support::name('') . ' ' . substr($err, 0, 80), 'sql' => $sql];
            } finally {
                foreach ($map as $tn) {
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$tn`");
                    } catch (\Throwable) {
                    }
                }
            }
        });
    }

    // ====================================================== 1. E-COMMERCE ~250

    private static function ecommerce(Runner $r): void
    {
        $cat = 'app:ecommerce';

        // --- order totals from line items (SUM(qty*price)) over a grid of carts.
        // Each cart is a list of [qty, unitPrice]; expected = formatted total.
        $carts = [
            [[1, '9.99']], [[2, '9.99']], [[3, '4.50']], [[1, '100.00'], [2, '25.00']],
            [[5, '1.99']], [[10, '0.99']], [[2, '19.99'], [1, '5.00']],
            [[4, '12.50'], [3, '7.25']], [[1, '999.99']], [[7, '3.33']],
            [[2, '50.00'], [2, '50.00'], [1, '0.01']], [[6, '8.75']],
            [[1, '14.95'], [1, '14.95'], [1, '14.95']], [[8, '2.50']], [[3, '33.33']],
            [[12, '1.25']], [[1, '0.99'], [1, '1.99'], [1, '2.99']], [[20, '4.99']],
            [[2, '149.99']], [[9, '11.11']],
        ];
        foreach ($carts as $i => $cart) {
            $rows = [];
            $total = 0.0;
            foreach ($cart as $li => [$qty, $price]) {
                $rows[] = "($li, $qty, $price)";
                $total += (float) $qty * (float) $price;
            }
            $expected = number_format(round($total, 2), 2, '.', '');
            $vals = implode(',', $rows);
            self::addCase(
                $r,
                $cat,
                "order total line items cart#$i",
                [
                    'CREATE TABLE {T} (line INT, qty INT, unit_price DECIMAL(10,2))',
                    "INSERT INTO {T} VALUES $vals",
                ],
                'SELECT CAST(SUM(qty*unit_price) AS DECIMAL(12,2)) FROM {T}',
                $expected
            );
        }

        // --- percentage discounts over a price x pct grid.
        $prices = ['100.00', '49.99', '19.95', '250.00', '7.50'];
        $pcts = [5, 10, 15, 20, 25, 50];
        foreach ($prices as $price) {
            foreach ($pcts as $pct) {
                $factor = (100 - $pct) / 100;
                $expected = number_format((float) $price * $factor, 2, '.', '');
                self::addCase(
                    $r,
                    $cat,
                    "$pct% discount off $price",
                    [
                        'CREATE TABLE {T} (price DECIMAL(10,2))',
                        "INSERT INTO {T} VALUES ($price)",
                    ],
                    "SELECT CAST(price*(1-$pct/100) AS DECIMAL(10,2)) FROM {T}",
                    $expected
                );
            }
        }

        // --- fixed-amount discounts (clamped at zero via CASE — GREATEST with
        //     mixed types errors in MatrixOne, finding documented separately).
        $fixed = [
            ['100.00', '25.00', '75.00'],
            ['50.00', '10.00', '40.00'],
            ['30.00', '30.00', '0.00'],
            ['20.00', '25.00', '0.00'],
            ['199.99', '50.00', '149.99'],
            ['9.99', '5.00', '4.99'],
            ['15.00', '15.01', '0.00'],
            ['1000.00', '100.00', '900.00'],
            ['12.34', '2.34', '10.00'],
            ['5.00', '0.00', '5.00'],
        ];
        foreach ($fixed as [$price, $disc, $exp]) {
            self::addCase(
                $r,
                $cat,
                "fixed \$$disc off \$$price",
                [
                    'CREATE TABLE {T} (price DECIMAL(10,2), disc DECIMAL(10,2))',
                    "INSERT INTO {T} VALUES ($price, $disc)",
                ],
                'SELECT CAST(CASE WHEN price-disc < 0 THEN 0.00 ELSE price-disc END AS DECIMAL(10,2)) FROM {T}',
                $exp
            );
        }

        // --- coupon stacking (two multiplicative discounts).
        $stacks = [
            ['200.00', 10, 5, '171.00'],
            ['100.00', 20, 10, '72.00'],
            ['50.00', 15, 15, '36.13'],
            ['80.00', 25, 5, '57.00'],
            ['120.00', 30, 20, '67.20'],
            ['99.99', 10, 10, '80.99'],
        ];
        foreach ($stacks as [$price, $a, $b, $exp]) {
            self::addCase(
                $r,
                $cat,
                "coupon stack $a%+$b% on $price",
                [
                    'CREATE TABLE {T} (price DECIMAL(10,2))',
                    "INSERT INTO {T} VALUES ($price)",
                ],
                "SELECT CAST(price*(1-$a/100)*(1-$b/100) AS DECIMAL(10,2)) FROM {T}",
                $exp
            );
        }

        // --- sales tax computation over a rate grid.
        $taxRates = ['0.0000', '0.0625', '0.0825', '0.1000', '0.0700', '0.0875'];
        $bases = ['100.00', '49.99', '19.95'];
        foreach ($bases as $base) {
            foreach ($taxRates as $rate) {
                $exp = number_format((float) $base * (1 + (float) $rate), 2, '.', '');
                self::addCase(
                    $r,
                    $cat,
                    "tax $rate on $base",
                    [
                        'CREATE TABLE {T} (subtotal DECIMAL(10,2))',
                        "INSERT INTO {T} VALUES ($base)",
                    ],
                    "SELECT CAST(subtotal*(1+$rate) AS DECIMAL(10,2)) FROM {T}",
                    $exp
                );
            }
        }

        // --- inventory decrement on order (guarded so stock never goes negative).
        $decs = [
            [10, 3, '7'],
            [5, 5, '0'],
            [100, 99, '1'],
            [3, 5, '3'],   // guard prevents over-sell -> unchanged
            [50, 25, '25'],
            [1, 1, '0'],
            [8, 10, '8'],  // guarded
            [20, 0, '20'],
        ];
        foreach ($decs as $i => [$start, $order, $exp]) {
            self::addCase(
                $r,
                $cat,
                "inventory decrement #$i ($start-$order)",
                [
                    'CREATE TABLE {T} (sku VARCHAR(16) PRIMARY KEY, qty INT)',
                    "INSERT INTO {T} VALUES ('SKU', $start)",
                    "UPDATE {T} SET qty=qty-$order WHERE sku='SKU' AND qty>=$order",
                ],
                'SELECT qty FROM {T}',
                $exp
            );
        }

        // --- low-stock report (count below reorder threshold).
        $lowStock = [
            [[5, 50, 3, 100], 10, '2'],
            [[1, 2, 3], 5, '3'],
            [[20, 30, 40], 10, '0'],
            [[0, 0, 100], 1, '2'],
            [[8, 12, 9, 15, 7], 10, '3'],
        ];
        foreach ($lowStock as $i => [$qtys, $threshold, $exp]) {
            $vals = implode(',', array_map(static fn ($q) => "($q)", $qtys));
            self::addCase(
                $r,
                $cat,
                "low-stock report #$i (<$threshold)",
                [
                    'CREATE TABLE {T} (qty INT)',
                    "INSERT INTO {T} VALUES $vals",
                ],
                "SELECT COUNT(*) FROM {T} WHERE qty < $threshold",
                $exp
            );
        }

        // --- top-selling product (highest total qty sold).
        self::addCase(
            $r,
            $cat,
            'top-selling product by units',
            [
                'CREATE TABLE {T} (product VARCHAR(20), qty INT)',
                "INSERT INTO {T} VALUES ('A',3),('B',10),('A',4),('C',2),('B',1)",
            ],
            'SELECT product FROM {T} GROUP BY product ORDER BY SUM(qty) DESC LIMIT 1',
            'B',
            'exact'
        );

        // --- top-N selling products list.
        self::addCase(
            $r,
            $cat,
            'top-2 products as ordered list',
            [
                'CREATE TABLE {T} (product VARCHAR(20), qty INT)',
                "INSERT INTO {T} VALUES ('A',3),('B',10),('A',4),('C',2),('B',1)",
            ],
            'SELECT product FROM {T} GROUP BY product ORDER BY SUM(qty) DESC LIMIT 2',
            'B,A',
            'col'
        );

        // --- revenue by category (GROUP BY join of products & order_items).
        self::addCase(
            $r,
            $cat,
            'revenue by category (electronics)',
            [
                'CREATE TABLE {T1} (id INT PRIMARY KEY, category VARCHAR(20), price DECIMAL(10,2))',
                "INSERT INTO {T1} VALUES (1,'electronics',100.00),(2,'books',20.00),(3,'electronics',50.00)",
                'CREATE TABLE {T2} (product_id INT, qty INT)',
                'INSERT INTO {T2} VALUES (1,2),(2,5),(3,1)',
            ],
            "SELECT CAST(SUM(p.price*o.qty) AS DECIMAL(12,2)) FROM {T2} o JOIN {T1} p ON o.product_id=p.id WHERE p.category='electronics'",
            '250.00'
        );

        self::addCase(
            $r,
            $cat,
            'revenue by category grouped list',
            [
                'CREATE TABLE {T1} (id INT PRIMARY KEY, category VARCHAR(20), price DECIMAL(10,2))',
                "INSERT INTO {T1} VALUES (1,'electronics',100.00),(2,'books',20.00),(3,'electronics',50.00)",
                'CREATE TABLE {T2} (product_id INT, qty INT)',
                'INSERT INTO {T2} VALUES (1,2),(2,5),(3,1)',
            ],
            'SELECT p.category FROM {T2} o JOIN {T1} p ON o.product_id=p.id GROUP BY p.category ORDER BY SUM(p.price*o.qty) DESC',
            'electronics,books',
            'col'
        );

        // --- customer lifetime value (LTV) ranking.
        self::addCase(
            $r,
            $cat,
            'customer LTV top spender',
            [
                'CREATE TABLE {T} (customer INT, total DECIMAL(10,2))',
                'INSERT INTO {T} VALUES (1,100.00),(1,50.00),(2,200.00),(3,75.00),(2,25.00)',
            ],
            'SELECT customer FROM {T} GROUP BY customer ORDER BY SUM(total) DESC LIMIT 1',
            '2',
            'exact'
        );

        self::addCase(
            $r,
            $cat,
            'customer LTV value',
            [
                'CREATE TABLE {T} (customer INT, total DECIMAL(10,2))',
                'INSERT INTO {T} VALUES (1,100.00),(1,50.00),(2,200.00),(3,75.00),(2,25.00)',
            ],
            'SELECT CAST(SUM(total) AS DECIMAL(12,2)) FROM {T} WHERE customer=1',
            '150.00'
        );

        // --- cart abandonment (orders never transitioned to "paid").
        $abandon = [
            [['cart', 'cart', 'paid', 'cart'], '3'],
            [['paid', 'paid'], '0'],
            [['cart'], '1'],
            [['paid', 'cart', 'paid', 'cart', 'cart'], '3'],
        ];
        foreach ($abandon as $i => [$statuses, $exp]) {
            $vals = implode(',', array_map(static fn ($s) => "('$s')", $statuses));
            self::addCase(
                $r,
                $cat,
                "cart abandonment count #$i",
                [
                    'CREATE TABLE {T} (status VARCHAR(16))',
                    "INSERT INTO {T} VALUES $vals",
                ],
                "SELECT COUNT(*) FROM {T} WHERE status='cart'",
                $exp
            );
        }

        // --- price tiers (CASE bucketing).
        $tierCases = [
            ['5.00', 'budget'],
            ['25.00', 'standard'],
            ['150.00', 'premium'],
            ['9.99', 'budget'],
            ['10.00', 'standard'],
            ['100.00', 'standard'],
            ['100.01', 'premium'],
            ['0.99', 'budget'],
        ];
        foreach ($tierCases as [$price, $exp]) {
            self::addCase(
                $r,
                $cat,
                "price tier of $price",
                [
                    'CREATE TABLE {T} (price DECIMAL(10,2))',
                    "INSERT INTO {T} VALUES ($price)",
                ],
                "SELECT CASE WHEN price < 10 THEN 'budget' WHEN price <= 100 THEN 'standard' ELSE 'premium' END FROM {T}",
                $exp,
                'exact'
            );
        }

        // --- multi-currency conversion (DECIMAL math).
        $fx = [
            ['100.00', '1.0850', '108.50'],  // USD->EUR-ish
            ['50.00', '0.7900', '39.50'],
            ['1000.00', '0.0067', '6.70'],   // JPY-ish
            ['250.00', '1.2700', '317.50'],
            ['99.99', '1.1000', '109.99'],
            ['19.95', '83.0000', '1655.85'],
        ];
        foreach ($fx as [$amt, $rate, $exp]) {
            self::addCase(
                $r,
                $cat,
                "fx $amt @ $rate",
                [
                    'CREATE TABLE {T} (amount DECIMAL(12,2), rate DECIMAL(12,4))',
                    "INSERT INTO {T} VALUES ($amt, $rate)",
                ],
                'SELECT CAST(amount*rate AS DECIMAL(14,2)) FROM {T}',
                $exp
            );
        }

        // --- refund reduces net revenue.
        $refunds = [
            [['100.00', 'sale'], ['30.00', 'refund'], '70.00'],
            [['200.00', 'sale'], ['200.00', 'refund'], '0.00'],
            [['50.00', 'sale'], ['0.00', 'refund'], '50.00'],
            [['100.00', 'sale'], ['25.00', 'refund'], '75.00'],
        ];
        foreach ($refunds as $i => [$saleRow, $refundRow, $exp]) {
            self::addCase(
                $r,
                $cat,
                "net revenue after refund #$i",
                [
                    'CREATE TABLE {T} (amount DECIMAL(10,2), kind VARCHAR(10))',
                    "INSERT INTO {T} VALUES ({$saleRow[0]},'{$saleRow[1]}'),({$refundRow[0]},'{$refundRow[1]}')",
                ],
                "SELECT CAST(SUM(CASE WHEN kind='sale' THEN amount ELSE -amount END) AS DECIMAL(12,2)) FROM {T}",
                $exp
            );
        }

        // --- order status transition validity (state machine via lookup table).
        $transitions = [
            ['pending', 'paid', '1'],
            ['paid', 'shipped', '1'],
            ['shipped', 'delivered', '1'],
            ['pending', 'shipped', '0'],
            ['delivered', 'pending', '0'],
            ['paid', 'cancelled', '1'],
            ['delivered', 'shipped', '0'],
        ];
        foreach ($transitions as [$from, $to, $exp]) {
            self::addCase(
                $r,
                $cat,
                "status transition $from->$to valid?",
                [
                    'CREATE TABLE {T} (from_s VARCHAR(16), to_s VARCHAR(16))',
                    "INSERT INTO {T} VALUES ('pending','paid'),('paid','shipped'),('shipped','delivered'),('paid','cancelled')",
                ],
                "SELECT COUNT(*) FROM {T} WHERE from_s='$from' AND to_s='$to'",
                $exp
            );
        }

        // --- weighted average price (AVG vs SUM/SUM).
        self::addCase(
            $r,
            $cat,
            'weighted average unit price',
            [
                'CREATE TABLE {T} (qty INT, price DECIMAL(10,2))',
                'INSERT INTO {T} VALUES (2,10.00),(3,20.00),(5,4.00)',
            ],
            'SELECT CAST(SUM(qty*price)/SUM(qty) AS DECIMAL(10,4)) FROM {T}',
            '10.0000'
        );

        // --- gross margin %.
        $margins = [
            ['100.00', '60.00', '40.00'],
            ['50.00', '50.00', '0.00'],
            ['200.00', '50.00', '75.00'],
            ['10.00', '7.50', '25.00'],
        ];
        foreach ($margins as [$sell, $cost, $exp]) {
            self::addCase(
                $r,
                $cat,
                "gross margin% sell=$sell cost=$cost",
                [
                    'CREATE TABLE {T} (sell DECIMAL(10,2), cost DECIMAL(10,2))',
                    "INSERT INTO {T} VALUES ($sell, $cost)",
                ],
                'SELECT CAST((sell-cost)/sell*100 AS DECIMAL(10,2)) FROM {T}',
                $exp
            );
        }

        // --- distinct customers / order count metrics.
        self::addCase(
            $r,
            $cat,
            'distinct paying customers',
            [
                'CREATE TABLE {T} (customer INT, status VARCHAR(10))',
                "INSERT INTO {T} VALUES (1,'paid'),(1,'paid'),(2,'paid'),(3,'cart')",
            ],
            "SELECT COUNT(DISTINCT customer) FROM {T} WHERE status='paid'",
            '2'
        );

        // --- average order value.
        self::addCase(
            $r,
            $cat,
            'average order value',
            [
                'CREATE TABLE {T} (total DECIMAL(10,2))',
                'INSERT INTO {T} VALUES (100.00),(50.00),(150.00),(100.00)',
            ],
            'SELECT CAST(AVG(total) AS DECIMAL(10,2)) FROM {T}',
            '100.00'
        );

        // --- bestseller revenue with window rank.
        self::addCase(
            $r,
            $cat,
            'product revenue rank #1',
            [
                'CREATE TABLE {T} (product VARCHAR(10), revenue DECIMAL(10,2))',
                "INSERT INTO {T} VALUES ('A',300.00),('B',500.00),('C',100.00)",
            ],
            'SELECT product FROM (SELECT product, RANK() OVER (ORDER BY revenue DESC) rk FROM {T}) t WHERE rk=1',
            'B',
            'exact'
        );

        // --- free-shipping threshold flag.
        $shipping = [
            ['75.00', '0'],
            ['50.00', '1'],
            ['49.99', '1'],
            ['100.00', '0'],
            ['50.01', '0'],
        ];
        foreach ($shipping as [$total, $exp]) {
            self::addCase(
                $r,
                $cat,
                "shipping fee charged for $total",
                [
                    'CREATE TABLE {T} (total DECIMAL(10,2))',
                    "INSERT INTO {T} VALUES ($total)",
                ],
                'SELECT CASE WHEN total >= 50 THEN 0 ELSE 1 END FROM {T}',
                $exp
            );
        }

        // --- GREATEST with mixed DECIMAL+int literal is a MatrixOne finding.
        self::addError(
            $r,
            $cat,
            'finding: GREATEST(DECIMAL, int 0) type mismatch errors',
            [
                'CREATE TABLE {T} (price DECIMAL(10,2))',
                'INSERT INTO {T} VALUES (75.00)',
            ],
            'SELECT GREATEST(price-100, 0) FROM {T}'
        );

        // --- coupon code case-sensitivity: exact match is case-sensitive.
        self::addCase(
            $r,
            $cat,
            'coupon code case-sensitive no match',
            [
                'CREATE TABLE {T} (code VARCHAR(20))',
                "INSERT INTO {T} VALUES ('SAVE10')",
            ],
            "SELECT COUNT(*) FROM {T} WHERE code='save10'",
            '0'
        );
        self::addCase(
            $r,
            $cat,
            'coupon code LOWER() case-insensitive match',
            [
                'CREATE TABLE {T} (code VARCHAR(20))',
                "INSERT INTO {T} VALUES ('SAVE10')",
            ],
            "SELECT COUNT(*) FROM {T} WHERE LOWER(code)=LOWER('save10')",
            '1'
        );

        // --- per-SKU stock upsert via ON DUPLICATE KEY.
        self::addCase(
            $r,
            $cat,
            'restock via ON DUPLICATE KEY UPDATE',
            [
                'CREATE TABLE {T} (sku VARCHAR(16) PRIMARY KEY, qty INT)',
                "INSERT INTO {T} VALUES ('X', 10)",
                "INSERT INTO {T} VALUES ('X', 5) ON DUPLICATE KEY UPDATE qty=qty+VALUES(qty)",
            ],
            "SELECT qty FROM {T} WHERE sku='X'",
            '15'
        );
    }

    // ===================================================== 2. SOCIAL GRAPH ~200

    private static function social(Runner $r): void
    {
        $cat = 'app:social';

        // Shared follow-graph seed used by many checks.
        // edges: 1->2,1->3,2->1,2->3,3->1,4->1
        $followsSetup = [
            'CREATE TABLE {T} (follower INT, followee INT)',
            'INSERT INTO {T} VALUES (1,2),(1,3),(2,1),(2,3),(3,1),(4,1)',
        ];

        // --- follower counts per user.
        $followerCounts = [
            [1, '3'], // followed by 2,3,4
            [2, '1'], // followed by 1
            [3, '2'], // followed by 1,2
            [4, '0'],
        ];
        foreach ($followerCounts as [$u, $exp]) {
            self::addCase(
                $r,
                $cat,
                "follower count user $u",
                $followsSetup,
                "SELECT COUNT(*) FROM {T} WHERE followee=$u",
                $exp
            );
        }

        // --- following counts per user.
        $followingCounts = [
            [1, '2'],
            [2, '2'],
            [3, '1'],
            [4, '1'],
        ];
        foreach ($followingCounts as [$u, $exp]) {
            self::addCase(
                $r,
                $cat,
                "following count user $u",
                $followsSetup,
                "SELECT COUNT(*) FROM {T} WHERE follower=$u",
                $exp
            );
        }

        // --- mutual follows (self-join): pairs (a,b) where both follow each other.
        // 1<->2 mutual (1->2 and 2->1). 1->3 and 3->1 mutual. Count unordered pairs.
        self::addCase(
            $r,
            $cat,
            'mutual follow pair count',
            $followsSetup,
            'SELECT COUNT(*) FROM {T} f JOIN {T} g ON f.follower=g.followee AND f.followee=g.follower WHERE f.follower<f.followee',
            '2'
        );

        // --- does user X follow user Y? (grid).
        $followChecks = [
            [1, 2, '1'],
            [1, 4, '0'],
            [4, 1, '1'],
            [2, 3, '1'],
            [3, 2, '0'],
            [3, 1, '1'],
        ];
        foreach ($followChecks as [$x, $y, $exp]) {
            self::addCase(
                $r,
                $cat,
                "does $x follow $y",
                $followsSetup,
                "SELECT COUNT(*) FROM {T} WHERE follower=$x AND followee=$y",
                $exp
            );
        }

        // --- feed: posts from people a user follows.
        $feedSetup = [
            'CREATE TABLE {T1} (follower INT, followee INT)',
            'INSERT INTO {T1} VALUES (1,2),(1,3),(2,1)',
            'CREATE TABLE {T2} (id INT, author INT, body VARCHAR(40))',
            "INSERT INTO {T2} VALUES (1,2,'p2a'),(2,3,'p3a'),(3,1,'p1a'),(4,2,'p2b')",
        ];
        self::addCase(
            $r,
            $cat,
            'feed post count for user 1',
            $feedSetup,
            'SELECT COUNT(*) FROM {T2} p JOIN {T1} f ON p.author=f.followee WHERE f.follower=1',
            '3' // posts by 2 (2 of them) and 3 (1)
        );
        self::addCase(
            $r,
            $cat,
            'feed excludes own posts',
            $feedSetup,
            'SELECT COUNT(*) FROM {T2} p JOIN {T1} f ON p.author=f.followee WHERE f.follower=1 AND p.author<>1',
            '3'
        );

        // --- most-liked post.
        $likesSetup = [
            'CREATE TABLE {T1} (id INT, body VARCHAR(20))',
            "INSERT INTO {T1} VALUES (1,'a'),(2,'b'),(3,'c')",
            'CREATE TABLE {T2} (post_id INT, user_id INT)',
            'INSERT INTO {T2} VALUES (1,10),(1,11),(2,10),(1,12),(3,10),(3,11)',
        ];
        self::addCase(
            $r,
            $cat,
            'most-liked post id',
            $likesSetup,
            'SELECT post_id FROM {T2} GROUP BY post_id ORDER BY COUNT(*) DESC LIMIT 1',
            '1',
            'exact'
        );
        self::addCase(
            $r,
            $cat,
            'like counts ordered list',
            $likesSetup,
            'SELECT post_id FROM {T2} GROUP BY post_id ORDER BY COUNT(*) DESC, post_id',
            '1,3,2',
            'col'
        );
        self::addCase(
            $r,
            $cat,
            'like count for post 1',
            $likesSetup,
            'SELECT COUNT(*) FROM {T2} WHERE post_id=1',
            '3'
        );

        // --- distinct users who liked a post (dedup).
        self::addCase(
            $r,
            $cat,
            'distinct likers of post',
            [
                'CREATE TABLE {T} (post_id INT, user_id INT)',
                'INSERT INTO {T} VALUES (1,10),(1,10),(1,11)',
            ],
            'SELECT COUNT(DISTINCT user_id) FROM {T} WHERE post_id=1',
            '2'
        );

        // --- comment threads: top-level vs replies (parent_id).
        $commentSetup = [
            'CREATE TABLE {T} (id INT, parent_id INT NULL, body VARCHAR(20))',
            "INSERT INTO {T} VALUES (1,NULL,'root1'),(2,1,'r1'),(3,1,'r2'),(4,NULL,'root2'),(5,2,'rr1')",
        ];
        self::addCase(
            $r,
            $cat,
            'top-level comment count',
            $commentSetup,
            'SELECT COUNT(*) FROM {T} WHERE parent_id IS NULL',
            '2'
        );
        self::addCase(
            $r,
            $cat,
            'direct replies to comment 1',
            $commentSetup,
            'SELECT COUNT(*) FROM {T} WHERE parent_id=1',
            '2'
        );
        // recursive thread depth
        self::addCase(
            $r,
            $cat,
            'recursive comment subtree size of root1',
            $commentSetup,
            'WITH RECURSIVE thr AS (SELECT id FROM {T} WHERE id=1 UNION ALL SELECT c.id FROM {T} c JOIN thr t ON c.parent_id=t.id) SELECT COUNT(*) FROM thr',
            '4' // root1 + r1 + r2 + rr1
        );

        // --- recursive follower reach within N hops.
        $reachSetup = [
            'CREATE TABLE {T} (follower INT, followee INT)',
            // chain: 1->2->3->4->5, plus 1->3
            'INSERT INTO {T} VALUES (1,2),(2,3),(3,4),(4,5),(1,3)',
        ];
        // reachable from 1 (all hops) = {2,3,4,5}
        self::addCase(
            $r,
            $cat,
            'recursive reach from user 1 (all hops)',
            $reachSetup,
            'WITH RECURSIVE reach AS (SELECT followee AS u FROM {T} WHERE follower=1 UNION SELECT f.followee FROM {T} f JOIN reach r ON f.follower=r.u) SELECT COUNT(DISTINCT u) FROM reach',
            '4'
        );
        // 2-hop reach from 1: direct {2,3} plus their followees {3,4} = {2,3,4}
        self::addCase(
            $r,
            $cat,
            'recursive reach within 2 hops from 1',
            $reachSetup,
            'WITH RECURSIVE reach AS (SELECT followee AS u, 1 AS hop FROM {T} WHERE follower=1 UNION ALL SELECT f.followee, r.hop+1 FROM {T} f JOIN reach r ON f.follower=r.u WHERE r.hop<2) SELECT COUNT(DISTINCT u) FROM reach',
            '3'
        );

        // --- engagement rate = likes / followers (DECIMAL).
        $engagement = [
            [50, 200, '0.2500'],
            [10, 100, '0.1000'],
            [3, 4, '0.7500'],
            [0, 50, '0.0000'],
        ];
        foreach ($engagement as $i => [$likes, $followers, $exp]) {
            self::addCase(
                $r,
                $cat,
                "engagement rate #$i ($likes/$followers)",
                [
                    'CREATE TABLE {T} (likes INT, followers INT)',
                    "INSERT INTO {T} VALUES ($likes, $followers)",
                ],
                'SELECT CAST(likes AS DECIMAL(12,4))/followers FROM {T}',
                $exp
            );
        }
        // engagement guards divide-by-zero followers via NULLIF.
        self::addCase(
            $r,
            $cat,
            'engagement rate zero followers -> NULL',
            [
                'CREATE TABLE {T} (likes INT, followers INT)',
                'INSERT INTO {T} VALUES (5, 0)',
            ],
            'SELECT likes/NULLIF(followers,0) FROM {T}',
            null,
            'null'
        );

        // --- trending by like velocity (likes per hour, window LAG of cumulative).
        self::addCase(
            $r,
            $cat,
            'trending: likes in last bucket',
            [
                'CREATE TABLE {T} (post_id INT, hour INT, likes INT)',
                'INSERT INTO {T} VALUES (1,1,5),(1,2,20),(1,3,8)',
            ],
            'SELECT likes FROM {T} WHERE post_id=1 ORDER BY hour DESC LIMIT 1',
            '8',
            'exact'
        );
        self::addCase(
            $r,
            $cat,
            'trending: peak velocity hour via LAG',
            [
                'CREATE TABLE {T} (hour INT, cum_likes INT)',
                'INSERT INTO {T} VALUES (1,5),(2,25),(3,33)',
            ],
            'SELECT MAX(velocity) FROM (SELECT cum_likes - LAG(cum_likes) OVER (ORDER BY hour) AS velocity FROM {T}) v',
            '20'
        );

        // --- per-author post counts grid (data-driven over authors).
        $posterSetup = [
            'CREATE TABLE {T} (author INT)',
            'INSERT INTO {T} VALUES (1),(1),(1),(2),(2),(3)',
        ];
        foreach ([[1, '3'], [2, '2'], [3, '1'], [4, '0']] as [$a, $exp]) {
            self::addCase(
                $r,
                $cat,
                "post count author $a",
                $posterSetup,
                "SELECT COUNT(*) FROM {T} WHERE author=$a",
                $exp
            );
        }

        // --- suggested follows (followees of followees you don't follow).
        self::addCase(
            $r,
            $cat,
            'friend-of-friend suggestion count',
            [
                'CREATE TABLE {T} (follower INT, followee INT)',
                // 1 follows 2; 2 follows 3 and 4; 1 already follows 4
                'INSERT INTO {T} VALUES (1,2),(2,3),(2,4),(1,4)',
            ],
            'SELECT COUNT(DISTINCT f2.followee) FROM {T} f1 JOIN {T} f2 ON f1.followee=f2.follower WHERE f1.follower=1 AND f2.followee<>1 AND f2.followee NOT IN (SELECT followee FROM {T} WHERE follower=1)',
            '1' // only 3 is new
        );

        // --- block list filtering.
        self::addCase(
            $r,
            $cat,
            'feed excludes blocked authors',
            [
                'CREATE TABLE {T1} (id INT, author INT)',
                'INSERT INTO {T1} VALUES (1,2),(2,3),(3,2)',
                'CREATE TABLE {T2} (blocker INT, blocked INT)',
                'INSERT INTO {T2} VALUES (1,2)',
            ],
            'SELECT COUNT(*) FROM {T1} p WHERE p.author NOT IN (SELECT blocked FROM {T2} WHERE blocker=1)',
            '1' // only author 3's post survives
        );

        // --- hashtag usage count (LIKE).
        self::addCase(
            $r,
            $cat,
            'hashtag mention count',
            [
                'CREATE TABLE {T} (body VARCHAR(60))',
                "INSERT INTO {T} VALUES ('love #php'),('great #php day'),('no tag'),('#java rules')",
            ],
            "SELECT COUNT(*) FROM {T} WHERE body LIKE '%#php%'",
            '2'
        );

        // --- most active commenter.
        self::addCase(
            $r,
            $cat,
            'most active commenter',
            [
                'CREATE TABLE {T} (user_id INT)',
                'INSERT INTO {T} VALUES (10),(10),(11),(10),(12),(11)',
            ],
            'SELECT user_id FROM {T} GROUP BY user_id ORDER BY COUNT(*) DESC LIMIT 1',
            '10',
            'exact'
        );
    }

    // ====================================================== 3. ANALYTICS ~250

    private static function analytics(Runner $r): void
    {
        $cat = 'app:analytics';

        // Star schema fact + dims, reused across many checks.
        // fact_sales(date_id, product_id, customer_id, region, amount, qty)
        $starSetup = [
            'CREATE TABLE {T} (date_id INT, product_id INT, customer_id INT, region VARCHAR(10), amount DECIMAL(12,2), qty INT)',
            "INSERT INTO {T} VALUES " . implode(',', [
                "(20240101,1,100,'east',100.00,2)",
                "(20240101,2,101,'west',50.00,1)",
                "(20240102,1,100,'east',150.00,3)",
                "(20240102,3,102,'east',30.00,1)",
                "(20250101,1,101,'west',200.00,4)",
                "(20250102,2,100,'east',75.00,1)",
                "(20250102,3,103,'west',60.00,2)",
            ]),
        ];

        // --- total revenue.
        self::addCase($r, $cat, 'star: total revenue', $starSetup, 'SELECT CAST(SUM(amount) AS DECIMAL(14,2)) FROM {T}', '665.00');
        // --- revenue by region grid.
        foreach ([['east', '355.00'], ['west', '310.00']] as [$reg, $exp]) {
            self::addCase($r, $cat, "star: revenue region $reg", $starSetup, "SELECT CAST(SUM(amount) AS DECIMAL(14,2)) FROM {T} WHERE region='$reg'", $exp);
        }
        // --- units by product grid.
        foreach ([[1, '9'], [2, '2'], [3, '3']] as [$p, $exp]) {
            self::addCase($r, $cat, "star: units product $p", $starSetup, "SELECT SUM(qty) FROM {T} WHERE product_id=$p", $exp);
        }

        // --- ROLLUP over region (subtotals + grand total row count).
        self::addCase(
            $r,
            $cat,
            'ROLLUP by region returns subtotal+total rows',
            $starSetup,
            'SELECT region FROM {T} GROUP BY region WITH ROLLUP',
            '3', // east, west, NULL(grand)
            'rows'
        );
        self::addCase(
            $r,
            $cat,
            'ROLLUP grand-total value',
            $starSetup,
            'SELECT CAST(SUM(amount) AS DECIMAL(14,2)) AS s FROM {T} GROUP BY region WITH ROLLUP ORDER BY s DESC LIMIT 1',
            '665.00'
        );

        // --- GROUPING SETS (region), () = same as ROLLUP here.
        self::addCase(
            $r,
            $cat,
            'GROUPING SETS region+total row count',
            $starSetup,
            'SELECT region FROM {T} GROUP BY GROUPING SETS ((region),())',
            '3',
            'rows'
        );

        // --- GROUPING() label on rollup super-aggregate.
        self::addCase(
            $r,
            $cat,
            'GROUPING() flags grand total',
            $starSetup,
            'SELECT MAX(GROUPING(region)) FROM {T} GROUP BY region WITH ROLLUP',
            '1'
        );

        // --- CUBE over (region) — small cube.
        self::addCase(
            $r,
            $cat,
            'CUBE single dim row count',
            $starSetup,
            'SELECT region FROM {T} GROUP BY CUBE(region)',
            '3',
            'rows'
        );

        // --- running total of revenue ordered by date.
        self::addCase(
            $r,
            $cat,
            'running revenue total final value',
            $starSetup,
            'SELECT CAST(rt AS DECIMAL(14,2)) FROM (SELECT date_id, SUM(amount) OVER (ORDER BY date_id) rt FROM {T}) t ORDER BY date_id DESC LIMIT 1',
            '665.00'
        );

        // --- 3-row moving average over an ordered series.
        self::addCase(
            $r,
            $cat,
            'moving average (3) middle window',
            [
                'CREATE TABLE {T} (d INT, v DECIMAL(10,2))',
                'INSERT INTO {T} VALUES (1,10.00),(2,20.00),(3,30.00),(4,40.00)',
            ],
            'SELECT CAST(ma AS DECIMAL(10,2)) FROM (SELECT d, AVG(v) OVER (ORDER BY d ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) ma FROM {T}) t WHERE d=2',
            '20.00'
        );

        // --- rank within group (top product per region by amount).
        self::addCase(
            $r,
            $cat,
            'rank product within region (top east)',
            $starSetup,
            "SELECT product_id FROM (SELECT product_id, region, RANK() OVER (PARTITION BY region ORDER BY amount DESC) rk FROM {T}) t WHERE region='east' AND rk=1",
            '1',
            'exact'
        );

        // --- YoY change via LAG over yearly totals.
        $yoySetup = [
            'CREATE TABLE {T} (yr INT, revenue DECIMAL(14,2))',
            'INSERT INTO {T} VALUES (2024,1000.00),(2025,1500.00),(2026,1800.00)',
        ];
        self::addCase(
            $r,
            $cat,
            'YoY absolute change 2025 vs 2024',
            $yoySetup,
            'SELECT CAST(revenue-LAG(revenue) OVER (ORDER BY yr) AS DECIMAL(14,2)) AS d FROM {T} ORDER BY yr LIMIT 1 OFFSET 1',
            '500.00'
        );
        self::addCase(
            $r,
            $cat,
            'YoY growth % 2026 vs 2025',
            $yoySetup,
            'SELECT CAST((revenue-LAG(revenue) OVER (ORDER BY yr))/LAG(revenue) OVER (ORDER BY yr)*100 AS DECIMAL(10,2)) AS g FROM {T} ORDER BY yr DESC LIMIT 1',
            '20.00'
        );
        self::addCase(
            $r,
            $cat,
            'first year YoY is NULL',
            $yoySetup,
            'SELECT revenue-LAG(revenue) OVER (ORDER BY yr) AS d FROM {T} ORDER BY yr LIMIT 1',
            null,
            'null'
        );

        // --- LEAD for next-period preview.
        self::addCase(
            $r,
            $cat,
            'LEAD next year revenue',
            $yoySetup,
            'SELECT CAST(LEAD(revenue) OVER (ORDER BY yr) AS DECIMAL(14,2)) FROM {T} ORDER BY yr LIMIT 1',
            '1500.00'
        );

        // --- pivot via conditional SUM (revenue per region as columns, one row).
        self::addCase(
            $r,
            $cat,
            'pivot east column via conditional SUM',
            $starSetup,
            "SELECT CAST(SUM(CASE WHEN region='east' THEN amount ELSE 0 END) AS DECIMAL(14,2)) FROM {T}",
            '355.00'
        );
        self::addCase(
            $r,
            $cat,
            'pivot west column via conditional SUM',
            $starSetup,
            "SELECT CAST(SUM(CASE WHEN region='west' THEN amount ELSE 0 END) AS DECIMAL(14,2)) FROM {T}",
            '310.00'
        );

        // --- cohort retention: signup month vs active month.
        $cohortSetup = [
            'CREATE TABLE {T} (user_id INT, cohort INT, active_month INT)',
            // cohort 1: users 1,2,3 ; active in months 1,2
            'INSERT INTO {T} VALUES (1,1,1),(1,1,2),(2,1,1),(3,1,2)',
        ];
        self::addCase(
            $r,
            $cat,
            'cohort 1 month-1 active users',
            $cohortSetup,
            'SELECT COUNT(DISTINCT user_id) FROM {T} WHERE cohort=1 AND active_month=1',
            '2'
        );
        self::addCase(
            $r,
            $cat,
            'cohort 1 month-2 retained users',
            $cohortSetup,
            'SELECT COUNT(DISTINCT user_id) FROM {T} WHERE cohort=1 AND active_month=2',
            '2'
        );

        // --- funnel conversion (view -> cart -> purchase).
        $funnelSetup = [
            'CREATE TABLE {T} (user_id INT, step VARCHAR(10))',
            "INSERT INTO {T} VALUES (1,'view'),(1,'cart'),(1,'purchase'),(2,'view'),(2,'cart'),(3,'view')",
        ];
        foreach ([['view', '3'], ['cart', '2'], ['purchase', '1']] as [$step, $exp]) {
            self::addCase(
                $r,
                $cat,
                "funnel step $step users",
                $funnelSetup,
                "SELECT COUNT(DISTINCT user_id) FROM {T} WHERE step='$step'",
                $exp
            );
        }
        // conversion rate view->purchase
        self::addCase(
            $r,
            $cat,
            'funnel conversion rate view->purchase',
            $funnelSetup,
            "SELECT CAST((SELECT COUNT(DISTINCT user_id) FROM {T} WHERE step='purchase') AS DECIMAL(10,4)) / (SELECT COUNT(DISTINCT user_id) FROM {T} WHERE step='view')",
            '0.3333'
        );

        // --- NTILE quartiles of customers by spend.
        self::addCase(
            $r,
            $cat,
            'NTILE(4) bucket of top spender',
            [
                'CREATE TABLE {T} (customer INT, spend DECIMAL(10,2))',
                'INSERT INTO {T} VALUES (1,10.00),(2,20.00),(3,30.00),(4,40.00)',
            ],
            'SELECT q FROM (SELECT customer, NTILE(4) OVER (ORDER BY spend) q FROM {T}) t WHERE customer=4',
            '4'
        );

        // --- DENSE_RANK ties.
        self::addCase(
            $r,
            $cat,
            'DENSE_RANK with ties',
            [
                'CREATE TABLE {T} (v INT)',
                'INSERT INTO {T} VALUES (100),(100),(90),(80)',
            ],
            'SELECT MAX(dr) FROM (SELECT DENSE_RANK() OVER (ORDER BY v DESC) dr FROM {T}) t',
            '3'
        );

        // --- PERCENT_RANK extremes.
        self::addCase(
            $r,
            $cat,
            'PERCENT_RANK min is 0',
            [
                'CREATE TABLE {T} (v INT)',
                'INSERT INTO {T} VALUES (1),(2),(3),(4)',
            ],
            'SELECT pr FROM (SELECT v, PERCENT_RANK() OVER (ORDER BY v) pr FROM {T}) t WHERE v=1',
            '0'
        );

        // --- CUME_DIST.
        self::addCase(
            $r,
            $cat,
            'CUME_DIST max is 1',
            [
                'CREATE TABLE {T} (v INT)',
                'INSERT INTO {T} VALUES (1),(2),(3),(4)',
            ],
            'SELECT cd FROM (SELECT v, CUME_DIST() OVER (ORDER BY v) cd FROM {T}) t WHERE v=4',
            '1'
        );

        // --- DISTINCT counts / cardinality.
        self::addCase($r, $cat, 'distinct customers in fact', $starSetup, 'SELECT COUNT(DISTINCT customer_id) FROM {T}', '4');
        self::addCase($r, $cat, 'distinct products in fact', $starSetup, 'SELECT COUNT(DISTINCT product_id) FROM {T}', '3');

        // --- AVG / MIN / MAX aggregate grid.
        foreach ([
            ['AVG order amount', 'AVG(amount)', '95.00'],
            ['MAX order amount', 'MAX(amount)', '200.00'],
            ['MIN order amount', 'MIN(amount)', '30.00'],
            ['count of orders', 'COUNT(*)', '7'],
        ] as [$nm, $agg, $exp]) {
            $mode = $nm === 'AVG order amount' ? 'value' : 'exact';
            self::addCase($r, $cat, "fact $nm", $starSetup, "SELECT CAST($agg AS DECIMAL(14,2)) FROM {T}", $exp, $nm === 'count of orders' ? 'value' : 'value');
        }

        // --- HAVING filter (regions over threshold).
        self::addCase(
            $r,
            $cat,
            'HAVING regions over 300',
            $starSetup,
            'SELECT COUNT(*) FROM (SELECT region FROM {T} GROUP BY region HAVING SUM(amount) > 300) t',
            '2'
        );

        // --- date-dimension join (dim_date).
        self::addCase(
            $r,
            $cat,
            'revenue by year via dim join (2024)',
            [
                'CREATE TABLE {T1} (date_id INT, yr INT, mo INT)',
                'INSERT INTO {T1} VALUES (20240101,2024,1),(20240102,2024,1),(20250101,2025,1)',
                'CREATE TABLE {T2} (date_id INT, amount DECIMAL(12,2))',
                'INSERT INTO {T2} VALUES (20240101,100.00),(20240102,150.00),(20250101,200.00)',
            ],
            'SELECT CAST(SUM(f.amount) AS DECIMAL(14,2)) FROM {T2} f JOIN {T1} d ON f.date_id=d.date_id WHERE d.yr=2024',
            '250.00'
        );

        // --- top-N with window then filter.
        self::addCase(
            $r,
            $cat,
            'top-2 products by revenue list',
            $starSetup,
            'SELECT product_id FROM (SELECT product_id, SUM(amount) s, ROW_NUMBER() OVER (ORDER BY SUM(amount) DESC) rn FROM {T} GROUP BY product_id) t WHERE rn<=2 ORDER BY rn',
            '1,2',
            'col'
        );

        // --- contribution % (amount / total).
        self::addCase(
            $r,
            $cat,
            'east region contribution % of total',
            $starSetup,
            "SELECT CAST(SUM(CASE WHEN region='east' THEN amount ELSE 0 END)/SUM(amount)*100 AS DECIMAL(10,2)) FROM {T}",
            '53.38'
        );
    }

    // ===================================================== 4. TIME-SERIES ~150

    private static function timeseries(Runner $r): void
    {
        $cat = 'app:timeseries';

        // readings(sensor_id, ts_epoch, value) — small grid.
        $base = [
            'CREATE TABLE {T} (sensor_id INT, ts INT, value DOUBLE)',
            'INSERT INTO {T} VALUES ' . implode(',', [
                '(1,0,10.0)', '(1,30,12.0)', '(1,60,14.0)', '(1,90,11.0)',
                '(1,120,20.0)', '(1,150,22.0)',
                '(2,0,5.0)', '(2,60,7.0)', '(2,120,9.0)',
            ]),
        ];

        // --- per-minute bucket via FLOOR(ts/60): avg per bucket for sensor 1.
        self::addCase(
            $r,
            $cat,
            'minute-bucket avg sensor1 bucket0',
            $base,
            'SELECT AVG(value) FROM {T} WHERE sensor_id=1 AND FLOOR(ts/60)=0',
            '11' // (10+12)/2
        );
        self::addCase(
            $r,
            $cat,
            'minute-bucket avg sensor1 bucket1',
            $base,
            'SELECT AVG(value) FROM {T} WHERE sensor_id=1 AND FLOOR(ts/60)=1',
            '12.5' // (14+11)/2
        );
        self::addCase(
            $r,
            $cat,
            'minute-bucket count for sensor1',
            $base,
            'SELECT COUNT(*) FROM (SELECT FLOOR(ts/60) b FROM {T} WHERE sensor_id=1 GROUP BY FLOOR(ts/60)) t',
            '3'
        );

        // --- min/max/avg per bucket grid.
        foreach ([
            ['min', 'MIN(value)', '10'],
            ['max', 'MAX(value)', '14'],
            ['avg', 'AVG(value)', '12'],
            ['count', 'COUNT(*)', '3'],
        ] as [$nm, $agg, $exp]) {
            self::addCase(
                $r,
                $cat,
                "bucket0+1 $nm sensor1",
                $base,
                "SELECT $agg FROM {T} WHERE sensor_id=1 AND ts<90",
                $exp
            );
        }

        // --- rate-of-change with LAG (per sensor, ordered by ts).
        self::addCase(
            $r,
            $cat,
            'rate-of-change first delta sensor1',
            $base,
            'SELECT roc FROM (SELECT ts, value-LAG(value) OVER (PARTITION BY sensor_id ORDER BY ts) roc FROM {T} WHERE sensor_id=1) t WHERE ts=30',
            '2'
        );
        self::addCase(
            $r,
            $cat,
            'rate-of-change is NULL at series start',
            $base,
            'SELECT value-LAG(value) OVER (PARTITION BY sensor_id ORDER BY ts) FROM {T} WHERE sensor_id=1 ORDER BY ts LIMIT 1',
            null,
            'null'
        );
        self::addCase(
            $r,
            $cat,
            'max rate-of-change spike sensor1',
            $base,
            'SELECT MAX(roc) FROM (SELECT value-LAG(value) OVER (PARTITION BY sensor_id ORDER BY ts) roc FROM {T} WHERE sensor_id=1) t',
            '9' // 20-11
        );

        // --- gap detection: timestamps spaced more than 30s apart.
        self::addCase(
            $r,
            $cat,
            'gap detection sensor2 (>30s gaps)',
            $base,
            'SELECT COUNT(*) FROM (SELECT ts-LAG(ts) OVER (PARTITION BY sensor_id ORDER BY ts) gap FROM {T} WHERE sensor_id=2) t WHERE gap > 30',
            '2' // 0->60 and 60->120
        );

        // --- downsampling: keep one row (first) per bucket.
        self::addCase(
            $r,
            $cat,
            'downsample first-per-bucket count sensor1',
            $base,
            'SELECT COUNT(*) FROM (SELECT FLOOR(ts/60) b, MIN(ts) FROM {T} WHERE sensor_id=1 GROUP BY FLOOR(ts/60)) t',
            '3'
        );

        // --- top-N noisiest sensors by stddev.
        self::addCase(
            $r,
            $cat,
            'noisiest sensor by stddev',
            $base,
            'SELECT sensor_id FROM {T} GROUP BY sensor_id ORDER BY STDDEV_POP(value) DESC LIMIT 1',
            '1',
            'exact'
        );

        // --- value statistics grid for sensor 1.
        foreach ([
            ['global min', 'MIN(value)', '10'],
            ['global max', 'MAX(value)', '22'],
            ['reading count', 'COUNT(*)', '6'],
            ['value range', 'MAX(value)-MIN(value)', '12'],
        ] as [$nm, $agg, $exp]) {
            self::addCase(
                $r,
                $cat,
                "sensor1 $nm",
                $base,
                "SELECT $agg FROM {T} WHERE sensor_id=1",
                $exp
            );
        }

        // --- threshold breach (alerting): readings above limit.
        $thresholds = [
            [15.0, '2'], // 20 and 22
            [10.0, '5'], // 12,14,11,20,22
            [25.0, '0'],
            [20.0, '1'], // 22 only (strictly >)
        ];
        foreach ($thresholds as $i => [$limit, $exp]) {
            self::addCase(
                $r,
                $cat,
                "threshold breach #$i (>$limit)",
                $base,
                "SELECT COUNT(*) FROM {T} WHERE sensor_id=1 AND value > $limit",
                $exp
            );
        }

        // --- bulk insert of a few hundred rows then aggregate (scale-lite).
        $bulkValues = [];
        $expectedSum = 0;
        for ($k = 1; $k <= 300; $k++) {
            $bulkValues[] = "(1, $k, " . ($k % 10) . ')';
            $expectedSum += $k % 10;
        }
        self::addCase(
            $r,
            $cat,
            'bulk 300-row insert then SUM',
            [
                'CREATE TABLE {T} (sensor_id INT, ts INT, value INT)',
                'INSERT INTO {T} VALUES ' . implode(',', $bulkValues),
            ],
            'SELECT SUM(value) FROM {T}',
            (string) $expectedSum
        );
        self::addCase(
            $r,
            $cat,
            'bulk 300-row insert row count',
            [
                'CREATE TABLE {T} (sensor_id INT, ts INT, value INT)',
                'INSERT INTO {T} VALUES ' . implode(',', $bulkValues),
            ],
            'SELECT COUNT(*) FROM {T}',
            '300'
        );

        // --- hourly bucket via DATETIME / DATE_FORMAT.
        self::addCase(
            $r,
            $cat,
            'hourly bucket via DATE_FORMAT',
            [
                'CREATE TABLE {T} (ts DATETIME, v INT)',
                "INSERT INTO {T} VALUES ('2026-01-01 10:05:00',1),('2026-01-01 10:55:00',2),('2026-01-01 11:01:00',3)",
            ],
            "SELECT COUNT(*) FROM (SELECT DATE_FORMAT(ts,'%Y-%m-%d %H') h FROM {T} GROUP BY DATE_FORMAT(ts,'%Y-%m-%d %H')) t",
            '2'
        );
        self::addCase(
            $r,
            $cat,
            'hourly bucket sum hour 10',
            [
                'CREATE TABLE {T} (ts DATETIME, v INT)',
                "INSERT INTO {T} VALUES ('2026-01-01 10:05:00',1),('2026-01-01 10:55:00',2),('2026-01-01 11:01:00',3)",
            ],
            "SELECT SUM(v) FROM {T} WHERE DATE_FORMAT(ts,'%Y-%m-%d %H')='2026-01-01 10'",
            '3'
        );

        // --- daily bucket aggregate.
        self::addCase(
            $r,
            $cat,
            'daily bucket distinct days',
            [
                'CREATE TABLE {T} (ts DATETIME, v INT)',
                "INSERT INTO {T} VALUES ('2026-01-01 23:00:00',1),('2026-01-02 00:30:00',2),('2026-01-02 12:00:00',3)",
            ],
            'SELECT COUNT(DISTINCT DATE(ts)) FROM {T}',
            '2'
        );

        // --- last value per sensor (latest reading).
        self::addCase(
            $r,
            $cat,
            'latest reading value sensor1',
            $base,
            'SELECT value FROM {T} WHERE sensor_id=1 ORDER BY ts DESC LIMIT 1',
            '22'
        );

        // --- cumulative sum per sensor.
        self::addCase(
            $r,
            $cat,
            'cumulative sum final sensor2',
            $base,
            'SELECT cs FROM (SELECT ts, SUM(value) OVER (PARTITION BY sensor_id ORDER BY ts) cs FROM {T} WHERE sensor_id=2) t ORDER BY ts DESC LIMIT 1',
            '21' // 5+7+9
        );
    }

    // ====================================================== 5. LEDGER ~150

    private static function ledger(Runner $r): void
    {
        $cat = 'app:ledger';

        // postings(entry_id, account, debit, credit) double-entry.
        $ledgerSetup = [
            'CREATE TABLE {T} (entry_id INT, account VARCHAR(10), debit DECIMAL(18,2), credit DECIMAL(18,2))',
            'INSERT INTO {T} VALUES ' . implode(',', [
                // entry 1: cash 100 dr, revenue 100 cr
                "(1,'cash',100.00,0.00)",
                "(1,'revenue',0.00,100.00)",
                // entry 2: expense 30 dr, cash 30 cr
                "(2,'expense',30.00,0.00)",
                "(2,'cash',0.00,30.00)",
                // entry 3: cash 50 dr, revenue 50 cr
                "(3,'cash',50.00,0.00)",
                "(3,'revenue',0.00,50.00)",
            ]),
        ];

        // --- account balance = SUM(debit)-SUM(credit) per account grid.
        $balances = [
            ['cash', '120.00'],     // 100 -30 +50
            ['revenue', '-150.00'], // 0 - 150
            ['expense', '30.00'],
        ];
        foreach ($balances as [$acct, $exp]) {
            self::addCase(
                $r,
                $cat,
                "balance of $acct",
                $ledgerSetup,
                "SELECT CAST(SUM(debit)-SUM(credit) AS DECIMAL(18,2)) FROM {T} WHERE account='$acct'",
                $exp
            );
        }

        // --- trial balance: total debits == total credits.
        self::addCase(
            $r,
            $cat,
            'trial balance total debits',
            $ledgerSetup,
            'SELECT CAST(SUM(debit) AS DECIMAL(18,2)) FROM {T}',
            '180.00'
        );
        self::addCase(
            $r,
            $cat,
            'trial balance total credits',
            $ledgerSetup,
            'SELECT CAST(SUM(credit) AS DECIMAL(18,2)) FROM {T}',
            '180.00'
        );
        self::addCase(
            $r,
            $cat,
            'trial balance is balanced (dr-cr=0)',
            $ledgerSetup,
            'SELECT CAST(SUM(debit)-SUM(credit) AS DECIMAL(18,2)) FROM {T}',
            '0.00'
        );

        // --- every entry balances (no unbalanced entries).
        self::addCase(
            $r,
            $cat,
            'count of unbalanced entries (should be 0)',
            $ledgerSetup,
            'SELECT COUNT(*) FROM (SELECT entry_id FROM {T} GROUP BY entry_id HAVING SUM(debit) <> SUM(credit)) t',
            '0'
        );
        self::addCase(
            $r,
            $cat,
            'count of balanced entries',
            $ledgerSetup,
            'SELECT COUNT(*) FROM (SELECT entry_id FROM {T} GROUP BY entry_id HAVING SUM(debit) = SUM(credit)) t',
            '3'
        );

        // --- detect a deliberately unbalanced entry.
        self::addCase(
            $r,
            $cat,
            'detect injected unbalanced entry',
            [
                'CREATE TABLE {T} (entry_id INT, account VARCHAR(10), debit DECIMAL(18,2), credit DECIMAL(18,2))',
                "INSERT INTO {T} VALUES (1,'a',100.00,0.00),(1,'b',0.00,90.00)",
            ],
            'SELECT COUNT(*) FROM (SELECT entry_id FROM {T} GROUP BY entry_id HAVING SUM(debit) <> SUM(credit)) t',
            '1'
        );

        // --- running balance via window SUM OVER for one account.
        $cashRunSetup = [
            'CREATE TABLE {T} (seq INT, account VARCHAR(10), debit DECIMAL(18,2), credit DECIMAL(18,2))',
            'INSERT INTO {T} VALUES ' . implode(',', [
                "(1,'cash',100.00,0.00)",
                "(2,'cash',0.00,30.00)",
                "(3,'cash',50.00,0.00)",
                "(4,'cash',0.00,20.00)",
            ]),
        ];
        self::addCase(
            $r,
            $cat,
            'running balance final',
            $cashRunSetup,
            "SELECT CAST(rb AS DECIMAL(18,2)) FROM (SELECT seq, SUM(debit-credit) OVER (ORDER BY seq) rb FROM {T} WHERE account='cash') t ORDER BY seq DESC LIMIT 1",
            '100.00' // 100-30+50-20
        );
        self::addCase(
            $r,
            $cat,
            'running balance after 2 postings',
            $cashRunSetup,
            "SELECT CAST(rb AS DECIMAL(18,2)) FROM (SELECT seq, SUM(debit-credit) OVER (ORDER BY seq) rb FROM {T} WHERE account='cash') t WHERE seq=2",
            '70.00'
        );
        self::addCase(
            $r,
            $cat,
            'running balance ordered list',
            $cashRunSetup,
            "SELECT CAST(rb AS DECIMAL(18,2)) FROM (SELECT seq, SUM(debit-credit) OVER (ORDER BY seq) rb FROM {T} WHERE account='cash') t ORDER BY seq",
            '100.00,70.00,120.00,100.00',
            'col'
        );

        // --- transfer transaction (atomic move between accounts).
        self::addCase(
            $r,
            $cat,
            'transfer leaves total unchanged',
            [
                'CREATE TABLE {T} (account VARCHAR(10), balance DECIMAL(18,2))',
                "INSERT INTO {T} VALUES ('a',100.00),('b',50.00)",
                "UPDATE {T} SET balance=balance-25.00 WHERE account='a'",
                "UPDATE {T} SET balance=balance+25.00 WHERE account='b'",
            ],
            'SELECT CAST(SUM(balance) AS DECIMAL(18,2)) FROM {T}',
            '150.00'
        );
        self::addCase(
            $r,
            $cat,
            'transfer source balance after debit',
            [
                'CREATE TABLE {T} (account VARCHAR(10), balance DECIMAL(18,2))',
                "INSERT INTO {T} VALUES ('a',100.00),('b',50.00)",
                "UPDATE {T} SET balance=balance-25.00 WHERE account='a'",
                "UPDATE {T} SET balance=balance+25.00 WHERE account='b'",
            ],
            "SELECT CAST(balance AS DECIMAL(18,2)) FROM {T} WHERE account='a'",
            '75.00'
        );

        // --- statement for a period (date range).
        $stmtSetup = [
            'CREATE TABLE {T} (txn_date DATE, account VARCHAR(10), amount DECIMAL(18,2))',
            "INSERT INTO {T} VALUES " . implode(',', [
                "('2026-01-05','cash',100.00)",
                "('2026-01-20','cash',-30.00)",
                "('2026-02-03','cash',50.00)",
                "('2026-01-15','cash',-10.00)",
            ]),
        ];
        self::addCase(
            $r,
            $cat,
            'January statement net for cash',
            $stmtSetup,
            "SELECT CAST(SUM(amount) AS DECIMAL(18,2)) FROM {T} WHERE account='cash' AND txn_date BETWEEN '2026-01-01' AND '2026-01-31'",
            '60.00' // 100-30-10
        );
        self::addCase(
            $r,
            $cat,
            'January statement line count',
            $stmtSetup,
            "SELECT COUNT(*) FROM {T} WHERE txn_date BETWEEN '2026-01-01' AND '2026-01-31'",
            '3'
        );
        self::addCase(
            $r,
            $cat,
            'February statement net',
            $stmtSetup,
            "SELECT CAST(SUM(amount) AS DECIMAL(18,2)) FROM {T} WHERE txn_date BETWEEN '2026-02-01' AND '2026-02-28'",
            '50.00'
        );

        // --- money rounding precision (DECIMAL(18,2) preserves cents).
        $money = [
            ['0.10', '0.20', '0.30'],
            ['0.01', '0.02', '0.03'],
            ['1234567890.12', '0.88', '1234567891.00'],
            ['99.99', '0.01', '100.00'],
        ];
        foreach ($money as [$a, $b, $exp]) {
            self::addCase(
                $r,
                $cat,
                "money add $a+$b",
                [
                    'CREATE TABLE {T} (a DECIMAL(18,2), b DECIMAL(18,2))',
                    "INSERT INTO {T} VALUES ($a, $b)",
                ],
                'SELECT CAST(a+b AS DECIMAL(18,2)) FROM {T}',
                $exp
            );
        }

        // --- overdraft / negative balance allowed?
        self::addCase(
            $r,
            $cat,
            'overdraft produces negative balance',
            [
                'CREATE TABLE {T} (balance DECIMAL(18,2))',
                'INSERT INTO {T} VALUES (50.00)',
                'UPDATE {T} SET balance=balance-75.00',
            ],
            'SELECT CAST(balance AS DECIMAL(18,2)) FROM {T}',
            '-25.00'
        );

        // --- account-type sign convention via CASE.
        self::addCase(
            $r,
            $cat,
            'net worth = assets - liabilities',
            [
                'CREATE TABLE {T} (acct_type VARCHAR(12), amount DECIMAL(18,2))',
                "INSERT INTO {T} VALUES ('asset',500.00),('asset',300.00),('liability',200.00)",
            ],
            "SELECT CAST(SUM(CASE WHEN acct_type='asset' THEN amount ELSE -amount END) AS DECIMAL(18,2)) FROM {T}",
            '600.00'
        );

        // --- highest-volume account.
        self::addCase(
            $r,
            $cat,
            'highest gross-volume account',
            $ledgerSetup,
            'SELECT account FROM {T} GROUP BY account ORDER BY SUM(debit+credit) DESC LIMIT 1',
            'cash',
            'exact'
        );

        // --- decimal scale overflow rejected (finding: strict DECIMAL).
        self::addError(
            $r,
            $cat,
            'finding: DECIMAL(4,2) integer overflow rejected',
            [
                'CREATE TABLE {T} (m DECIMAL(4,2))',
            ],
            "INSERT INTO {T} VALUES (123.45)"
        );
    }

    // ===================================================== 6. SaaS ~80

    private static function saas(Runner $r): void
    {
        $cat = 'app:saas';

        // Multi-tenant rows tagged with tenant_id.
        $tenantSetup = [
            'CREATE TABLE {T} (tenant_id INT, user_id INT, plan VARCHAR(10), mrr DECIMAL(10,2))',
            'INSERT INTO {T} VALUES ' . implode(',', [
                "(1,10,'pro',50.00)",
                "(1,11,'free',0.00)",
                "(1,12,'pro',50.00)",
                "(2,20,'enterprise',500.00)",
                "(2,21,'pro',50.00)",
                "(3,30,'free',0.00)",
            ]),
        ];

        // --- tenant-scoped row counts grid.
        foreach ([[1, '3'], [2, '2'], [3, '1'], [4, '0']] as [$t, $exp]) {
            self::addCase(
                $r,
                $cat,
                "tenant $t user count",
                $tenantSetup,
                "SELECT COUNT(*) FROM {T} WHERE tenant_id=$t",
                $exp
            );
        }

        // --- per-tenant MRR aggregate grid.
        foreach ([[1, '100.00'], [2, '550.00'], [3, '0.00']] as [$t, $exp]) {
            self::addCase(
                $r,
                $cat,
                "tenant $t MRR",
                $tenantSetup,
                "SELECT CAST(SUM(mrr) AS DECIMAL(12,2)) FROM {T} WHERE tenant_id=$t",
                $exp
            );
        }

        // --- cross-tenant isolation: a tenant-1 query never returns tenant-2 rows.
        self::addCase(
            $r,
            $cat,
            'tenant isolation: no foreign rows leak',
            $tenantSetup,
            'SELECT COUNT(*) FROM {T} WHERE tenant_id=1 AND user_id IN (20,21,30)',
            '0'
        );
        self::addCase(
            $r,
            $cat,
            'tenant isolation: only own users visible',
            $tenantSetup,
            'SELECT COUNT(DISTINCT user_id) FROM {T} WHERE tenant_id=1',
            '3'
        );

        // --- total tenants / total MRR (platform metrics).
        self::addCase($r, $cat, 'platform total tenants', $tenantSetup, 'SELECT COUNT(DISTINCT tenant_id) FROM {T}', '3');
        self::addCase($r, $cat, 'platform total MRR', $tenantSetup, 'SELECT CAST(SUM(mrr) AS DECIMAL(12,2)) FROM {T}', '650.00');
        self::addCase($r, $cat, 'platform paying users', $tenantSetup, "SELECT COUNT(*) FROM {T} WHERE plan<>'free'", '4');

        // --- plan distribution grid.
        foreach ([['free', '2'], ['pro', '3'], ['enterprise', '1']] as [$plan, $exp]) {
            self::addCase(
                $r,
                $cat,
                "plan $plan user count",
                $tenantSetup,
                "SELECT COUNT(*) FROM {T} WHERE plan='$plan'",
                $exp
            );
        }

        // --- per-tenant aggregate as a grouped list.
        self::addCase(
            $r,
            $cat,
            'tenants ordered by MRR desc',
            $tenantSetup,
            'SELECT tenant_id FROM {T} GROUP BY tenant_id ORDER BY SUM(mrr) DESC, tenant_id',
            '2,1,3',
            'col'
        );

        // --- highest-MRR tenant.
        self::addCase(
            $r,
            $cat,
            'top tenant by MRR',
            $tenantSetup,
            'SELECT tenant_id FROM {T} GROUP BY tenant_id ORDER BY SUM(mrr) DESC LIMIT 1',
            '2',
            'exact'
        );

        // --- average revenue per tenant.
        self::addCase(
            $r,
            $cat,
            'average MRR per tenant',
            $tenantSetup,
            'SELECT CAST(SUM(mrr)/COUNT(DISTINCT tenant_id) AS DECIMAL(12,2)) FROM {T}',
            '216.67'
        );

        // --- per-tenant resource quota check (usage vs limit).
        $quotaSetup = [
            'CREATE TABLE {T} (tenant_id INT, used INT, quota INT)',
            'INSERT INTO {T} VALUES (1,80,100),(2,150,100),(3,20,50)',
        ];
        self::addCase(
            $r,
            $cat,
            'tenants over quota count',
            $quotaSetup,
            'SELECT COUNT(*) FROM {T} WHERE used > quota',
            '1'
        );
        self::addCase(
            $r,
            $cat,
            'tenant 1 usage percent',
            $quotaSetup,
            'SELECT CAST(used AS DECIMAL(10,2))/quota*100 FROM {T} WHERE tenant_id=1',
            '80.00'
        );

        // --- composite (tenant_id, id) uniqueness across tenants.
        self::addCase(
            $r,
            $cat,
            'same local id reused across tenants',
            [
                'CREATE TABLE {T} (tenant_id INT, local_id INT, PRIMARY KEY(tenant_id, local_id))',
                'INSERT INTO {T} VALUES (1,1),(1,2),(2,1),(2,2)',
            ],
            'SELECT COUNT(*) FROM {T} WHERE local_id=1',
            '2'
        );

        // --- churn: tenants with zero active paid users.
        self::addCase(
            $r,
            $cat,
            'tenants with only free users',
            $tenantSetup,
            "SELECT COUNT(*) FROM (SELECT tenant_id FROM {T} GROUP BY tenant_id HAVING SUM(CASE WHEN plan<>'free' THEN 1 ELSE 0 END)=0) t",
            '1' // tenant 3
        );

        // --- enforce tenant filter prevents accidental global update scope.
        self::addCase(
            $r,
            $cat,
            'scoped update touches only one tenant',
            [
                'CREATE TABLE {T} (tenant_id INT, flag INT)',
                'INSERT INTO {T} VALUES (1,0),(1,0),(2,0)',
                'UPDATE {T} SET flag=1 WHERE tenant_id=1',
            ],
            'SELECT SUM(flag) FROM {T}',
            '2'
        );
    }

    // ====================================================== 7. CMS ~80

    private static function cms(Runner $r): void
    {
        $cat = 'app:cms';

        // Adjacency-list category tree.
        // 1 root; 2,3 under 1; 4 under 2; 5 under 4; 6 under 3
        $adjSetup = [
            'CREATE TABLE {T} (id INT, parent_id INT NULL, name VARCHAR(20))',
            "INSERT INTO {T} VALUES (1,NULL,'root'),(2,1,'a'),(3,1,'b'),(4,2,'a1'),(5,4,'a1x'),(6,3,'b1')",
        ];

        // --- direct children grid.
        foreach ([[1, '2'], [2, '1'], [3, '1'], [4, '1'], [5, '0']] as [$id, $exp]) {
            self::addCase(
                $r,
                $cat,
                "direct children of $id",
                $adjSetup,
                "SELECT COUNT(*) FROM {T} WHERE parent_id=$id",
                $exp
            );
        }

        // --- recursive descendant subtree size grid.
        foreach ([[1, '6'], [2, '3'], [4, '2'], [3, '2'], [5, '1']] as [$id, $exp]) {
            self::addCase(
                $r,
                $cat,
                "recursive subtree size of $id",
                $adjSetup,
                "WITH RECURSIVE sub AS (SELECT id FROM {T} WHERE id=$id UNION ALL SELECT c.id FROM {T} c JOIN sub s ON c.parent_id=s.id) SELECT COUNT(*) FROM sub",
                $exp
            );
        }

        // --- breadcrumb path via recursive CTE (root..node).
        self::addCase(
            $r,
            $cat,
            'breadcrumb path to node 5',
            $adjSetup,
            "WITH RECURSIVE path AS (SELECT id, parent_id, CAST(name AS CHAR(200)) p FROM {T} WHERE id=5 UNION ALL SELECT c.id, c.parent_id, CONCAT(c.name,'/',path.p) FROM {T} c JOIN path ON c.id=path.parent_id) SELECT p FROM path WHERE parent_id IS NULL",
            'root/a/a1/a1x',
            'exact'
        );
        self::addCase(
            $r,
            $cat,
            'breadcrumb depth of node 5',
            $adjSetup,
            "WITH RECURSIVE path AS (SELECT id, parent_id, 1 AS depth FROM {T} WHERE id=5 UNION ALL SELECT c.id, c.parent_id, path.depth+1 FROM {T} c JOIN path ON c.id=path.parent_id) SELECT MAX(depth) FROM path",
            '4'
        );

        // Nested-set version of the same tree.
        // root(1,12) a(2,7) a1(3,6) a1x(4,5) b(8,11) b1(9,10)
        $nestedSetup = [
            'CREATE TABLE {T} (id INT, name VARCHAR(20), lft INT, rgt INT)',
            "INSERT INTO {T} VALUES (1,'root',1,12),(2,'a',2,7),(3,'a1',3,6),(4,'a1x',4,5),(5,'b',8,11),(6,'b1',9,10)",
        ];
        // --- nested-set descendant subtree via BETWEEN.
        foreach ([
            ['root', 1, 12, '6'],
            ['a', 2, 7, '3'],
            ['a1', 3, 6, '2'],
            ['b', 8, 11, '2'],
        ] as [$nm, $lft, $rgt, $exp]) {
            self::addCase(
                $r,
                $cat,
                "nested-set subtree of $nm",
                $nestedSetup,
                "SELECT COUNT(*) FROM {T} WHERE lft BETWEEN $lft AND $rgt",
                $exp
            );
        }
        // --- nested-set ancestors of a leaf (a1x at 4,5).
        self::addCase(
            $r,
            $cat,
            'nested-set ancestor count of a1x',
            $nestedSetup,
            'SELECT COUNT(*) FROM {T} WHERE lft < 4 AND rgt > 5',
            '3' // root, a, a1
        );
        self::addCase(
            $r,
            $cat,
            'nested-set leaf nodes (lft+1=rgt)',
            $nestedSetup,
            'SELECT COUNT(*) FROM {T} WHERE rgt = lft + 1',
            '2' // a1x, b1
        );

        // Articles with slugs, soft-delete, revisions.
        $articleSetup = [
            'CREATE TABLE {T} (id INT, slug VARCHAR(40), title VARCHAR(40), deleted_at DATETIME NULL)',
            "INSERT INTO {T} VALUES " . implode(',', [
                "(1,'hello-world','Hello World',NULL)",
                "(2,'draft-post','Draft',NULL)",
                "(3,'old-post','Old','2026-01-01 00:00:00')",
            ]),
        ];
        // --- soft-delete filtering.
        self::addCase($r, $cat, 'live articles (deleted_at IS NULL)', $articleSetup, 'SELECT COUNT(*) FROM {T} WHERE deleted_at IS NULL', '2');
        self::addCase($r, $cat, 'trashed articles', $articleSetup, 'SELECT COUNT(*) FROM {T} WHERE deleted_at IS NOT NULL', '1');
        // --- slug lookup (exact, case-sensitive).
        self::addCase($r, $cat, 'slug lookup exact match', $articleSetup, "SELECT title FROM {T} WHERE slug='hello-world'", 'Hello World', 'exact');
        self::addCase($r, $cat, 'slug lookup wrong case no match', $articleSetup, "SELECT COUNT(*) FROM {T} WHERE slug='Hello-World'", '0');
        self::addCase($r, $cat, 'slug LOWER() case-insensitive', $articleSetup, "SELECT COUNT(*) FROM {T} WHERE LOWER(slug)=LOWER('Hello-World')", '1');

        // --- slug uniqueness enforced by UNIQUE constraint (finding: duplicate rejected).
        self::addError(
            $r,
            $cat,
            'finding: duplicate slug rejected by UNIQUE',
            [
                'CREATE TABLE {T} (id INT, slug VARCHAR(40) UNIQUE)',
                "INSERT INTO {T} VALUES (1,'dup')",
            ],
            "INSERT INTO {T} VALUES (2,'dup')"
        );

        // Revisions: latest per article.
        $revSetup = [
            'CREATE TABLE {T} (article_id INT, rev INT, body VARCHAR(20))',
            "INSERT INTO {T} VALUES (1,1,'v1'),(1,2,'v2'),(1,3,'v3'),(2,1,'x1'),(2,2,'x2')",
        ];
        self::addCase(
            $r,
            $cat,
            'latest revision body article 1',
            $revSetup,
            'SELECT body FROM {T} WHERE article_id=1 ORDER BY rev DESC LIMIT 1',
            'v3',
            'exact'
        );
        self::addCase(
            $r,
            $cat,
            'latest revision per article via ROW_NUMBER',
            $revSetup,
            'SELECT body FROM (SELECT article_id, body, ROW_NUMBER() OVER (PARTITION BY article_id ORDER BY rev DESC) rn FROM {T}) t WHERE rn=1 ORDER BY article_id',
            'v3,x2',
            'col'
        );
        self::addCase(
            $r,
            $cat,
            'revision count per article (article 1)',
            $revSetup,
            'SELECT COUNT(*) FROM {T} WHERE article_id=1',
            '3'
        );
        self::addCase(
            $r,
            $cat,
            'max revision number article 2',
            $revSetup,
            'SELECT MAX(rev) FROM {T} WHERE article_id=2',
            '2'
        );

        // --- published vs draft via status + date.
        self::addCase(
            $r,
            $cat,
            'published articles with past publish date',
            [
                'CREATE TABLE {T} (id INT, status VARCHAR(10), published_at DATETIME NULL)',
                "INSERT INTO {T} VALUES (1,'published','2026-01-01 00:00:00'),(2,'draft',NULL),(3,'published','2099-01-01 00:00:00')",
            ],
            "SELECT COUNT(*) FROM {T} WHERE status='published' AND published_at <= '2026-06-23 00:00:00'",
            '1'
        );

        // --- tag many-to-many count.
        self::addCase(
            $r,
            $cat,
            'articles tagged php',
            [
                'CREATE TABLE {T1} (article_id INT, tag VARCHAR(20))',
                "INSERT INTO {T1} VALUES (1,'php'),(1,'web'),(2,'php'),(3,'go')",
            ],
            "SELECT COUNT(DISTINCT article_id) FROM {T1} WHERE tag='php'",
            '2'
        );
    }

    // ====================================================== 8. GEO ~40

    private static function geo(Runner $r): void
    {
        $cat = 'app:geo';

        // places(lat, lng) — small set around NYC-ish coords.
        $placesSetup = [
            'CREATE TABLE {T} (id INT, name VARCHAR(20), lat DOUBLE, lng DOUBLE)',
            "INSERT INTO {T} VALUES " . implode(',', [
                "(1,'A',40.7128,-74.0060)", // NYC
                "(2,'B',40.7138,-74.0050)", // ~150m from NYC
                "(3,'C',34.0522,-118.2437)", // LA
                "(4,'D',41.8781,-87.6298)", // Chicago
                "(5,'E',40.7000,-74.0000)", // near NYC
            ]),
        ];

        // --- bounding-box filter grid.
        $boxes = [
            [40.70, 40.72, -74.01, -74.00, '3'], // A,B,E
            [34.00, 35.00, -119.00, -118.00, '1'], // C
            [40.00, 42.00, -88.00, -87.00, '1'], // D
            [0.0, 10.0, 0.0, 10.0, '0'],
        ];
        foreach ($boxes as $i => [$latLo, $latHi, $lngLo, $lngHi, $exp]) {
            self::addCase(
                $r,
                $cat,
                "bounding-box filter #$i",
                $placesSetup,
                "SELECT COUNT(*) FROM {T} WHERE lat BETWEEN $latLo AND $latHi AND lng BETWEEN $lngLo AND $lngHi",
                $exp
            );
        }

        // --- haversine nearest place to NYC (excluding self) is B.
        self::addCase(
            $r,
            $cat,
            'nearest place to NYC via haversine',
            $placesSetup,
            'SELECT name FROM {T} WHERE id<>1 ORDER BY (6371*ACOS(LEAST(1.0, COS(RADIANS(40.7128))*COS(RADIANS(lat))*COS(RADIANS(lng)-RADIANS(-74.0060))+SIN(RADIANS(40.7128))*SIN(RADIANS(lat))))) LIMIT 1',
            'B',
            'exact'
        );

        // --- nearest-3 ordered list from NYC.
        self::addCase(
            $r,
            $cat,
            'nearest-3 places to NYC list',
            $placesSetup,
            'SELECT name FROM {T} WHERE id<>1 ORDER BY (6371*ACOS(LEAST(1.0, COS(RADIANS(40.7128))*COS(RADIANS(lat))*COS(RADIANS(lng)-RADIANS(-74.0060))+SIN(RADIANS(40.7128))*SIN(RADIANS(lat))))) LIMIT 3',
            'B,E,D',
            'col'
        );

        // --- radius search: places within 5km of NYC.
        self::addCase(
            $r,
            $cat,
            'places within 5km of NYC',
            $placesSetup,
            'SELECT COUNT(*) FROM {T} WHERE (6371*ACOS(LEAST(1.0, COS(RADIANS(40.7128))*COS(RADIANS(lat))*COS(RADIANS(lng)-RADIANS(-74.0060))+SIN(RADIANS(40.7128))*SIN(RADIANS(lat))))) < 5',
            '3' // A(0), B(~0.14km), E(~1.5km)
        );

        // --- haversine distance value sanity (1 degree latitude ~111km).
        self::addCase(
            $r,
            $cat,
            'haversine 1-degree latitude ~111km',
            ['CREATE TABLE {T} (x INT)', 'INSERT INTO {T} VALUES (1)'],
            'SELECT CAST(6371*ACOS(COS(RADIANS(0))*COS(RADIANS(1))*COS(RADIANS(0)-RADIANS(0))+SIN(RADIANS(0))*SIN(RADIANS(1))) AS DECIMAL(10,1)) FROM {T}',
            '111.2'
        );

        // --- approximate distance via euclidean on small scale (sort agreement).
        self::addCase(
            $r,
            $cat,
            'euclidean nearest agrees with haversine for B',
            $placesSetup,
            'SELECT name FROM {T} WHERE id<>1 ORDER BY (POW(lat-40.7128,2)+POW(lng+74.0060,2)) LIMIT 1',
            'B',
            'exact'
        );

        // --- spatial-type gaps: ST_* functions are parser errors (findings).
        self::addError(
            $r,
            $cat,
            'finding: POINT() constructor parser error',
            ['CREATE TABLE {T} (x INT)', 'INSERT INTO {T} VALUES (1)'],
            'SELECT POINT(1,2) FROM {T}'
        );
        self::addError(
            $r,
            $cat,
            'finding: ST_Distance() parser error',
            ['CREATE TABLE {T} (x INT)', 'INSERT INTO {T} VALUES (1)'],
            'SELECT ST_Distance(POINT(0,0), POINT(1,1)) FROM {T}'
        );
        self::addError(
            $r,
            $cat,
            'finding: ST_AsText() parser error',
            ['CREATE TABLE {T} (x INT)', 'INSERT INTO {T} VALUES (1)'],
            'SELECT ST_AsText(POINT(1,2)) FROM {T}'
        );
        self::addError(
            $r,
            $cat,
            'finding: ST_Contains() parser error',
            ['CREATE TABLE {T} (x INT)', 'INSERT INTO {T} VALUES (1)'],
            "SELECT ST_Contains(ST_GeomFromText('POLYGON((0 0,0 1,1 1,1 0,0 0))'), POINT(0.5,0.5)) FROM {T}"
        );
    }

    // ============================================== BULK EXPANSIONS (data-driven)

    /** Format a float as a fixed-scale decimal string (no thousands sep). */
    private static function dec(float $v, int $scale = 2): string
    {
        return number_format(round($v, $scale), $scale, '.', '');
    }

    private static function ecommerceBulk(Runner $r): void
    {
        $cat = 'app:ecommerce';

        // (1) percentage-discount sweep: price x pct, computed expected.
        $prices = ['12.00', '34.50', '7.99', '149.00', '88.88', '23.45', '5.55', '299.99', '64.00', '199.50'];
        foreach ($prices as $price) {
            foreach ([3, 8, 12, 17, 22, 27, 33, 40, 45, 60] as $pct) {
                $exp = self::dec((float) $price * (100 - $pct) / 100);
                self::addCase(
                    $r,
                    $cat,
                    "discount sweep $pct% off $price",
                    [
                        'CREATE TABLE {T} (price DECIMAL(10,2))',
                        "INSERT INTO {T} VALUES ($price)",
                    ],
                    "SELECT CAST(price*(1-$pct/100) AS DECIMAL(10,2)) FROM {T}",
                    $exp
                );
            }
        }

        // (2) tax sweep: base x rate.
        $bases = ['10.00', '25.00', '99.99', '150.00', '7.49'];
        $rates = ['0.05', '0.06', '0.07', '0.08', '0.09', '0.10', '0.0625', '0.0825'];
        foreach ($bases as $base) {
            foreach ($rates as $rate) {
                $exp = self::dec((float) $base * (1 + (float) $rate));
                self::addCase(
                    $r,
                    $cat,
                    "tax sweep $rate on $base",
                    [
                        'CREATE TABLE {T} (subtotal DECIMAL(10,2))',
                        "INSERT INTO {T} VALUES ($base)",
                    ],
                    "SELECT CAST(subtotal*(1+$rate) AS DECIMAL(10,2)) FROM {T}",
                    $exp
                );
            }
        }

        // (3) line-item total sweep: qty x unit, scalar multiply.
        foreach ([1, 2, 3, 4, 5, 6, 8, 10, 12, 20, 50] as $qty) {
            foreach (['1.99', '4.50', '9.99', '19.95', '0.99', '2.49'] as $unit) {
                $exp = self::dec($qty * (float) $unit);
                self::addCase(
                    $r,
                    $cat,
                    "line total {$qty}x$unit",
                    [
                        'CREATE TABLE {T} (qty INT, unit DECIMAL(10,2))',
                        "INSERT INTO {T} VALUES ($qty, $unit)",
                    ],
                    'SELECT CAST(qty*unit AS DECIMAL(12,2)) FROM {T}',
                    $exp
                );
            }
        }
    }

    private static function socialBulk(Runner $r): void
    {
        $cat = 'app:social';

        // (1) follower/following count sweeps over a fixed star-graph seed where
        // user 0 is followed by N users and follows M users.
        foreach ([1, 2, 3, 5, 8, 10] as $followers) {
            foreach ([0, 1, 2, 4] as $following) {
                $rows = [];
                for ($i = 1; $i <= $followers; $i++) {
                    $rows[] = "($i, 0)"; // i follows 0
                }
                for ($j = 1; $j <= $following; $j++) {
                    $rows[] = "(0, " . (100 + $j) . ')'; // 0 follows 100+j
                }
                $vals = implode(',', $rows);
                self::addCase(
                    $r,
                    $cat,
                    "graph f=$followers g=$following follower count",
                    [
                        'CREATE TABLE {T} (follower INT, followee INT)',
                        "INSERT INTO {T} VALUES $vals",
                    ],
                    'SELECT COUNT(*) FROM {T} WHERE followee=0',
                    (string) $followers
                );
                self::addCase(
                    $r,
                    $cat,
                    "graph f=$followers g=$following following count",
                    [
                        'CREATE TABLE {T} (follower INT, followee INT)',
                        "INSERT INTO {T} VALUES $vals",
                    ],
                    'SELECT COUNT(*) FROM {T} WHERE follower=0',
                    (string) $following
                );
            }
        }

        // (2) like-count sweep: a post with K likes.
        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 15, 17, 21, 25, 30] as $k) {
            $rows = [];
            for ($u = 1; $u <= $k; $u++) {
                $rows[] = "(1, $u)";
            }
            $vals = implode(',', $rows);
            self::addCase(
                $r,
                $cat,
                "post with $k likes",
                [
                    'CREATE TABLE {T} (post_id INT, user_id INT)',
                    "INSERT INTO {T} VALUES $vals",
                ],
                'SELECT COUNT(*) FROM {T} WHERE post_id=1',
                (string) $k
            );
            self::addCase(
                $r,
                $cat,
                "post with $k distinct likers",
                [
                    'CREATE TABLE {T} (post_id INT, user_id INT)',
                    "INSERT INTO {T} VALUES $vals",
                ],
                'SELECT COUNT(DISTINCT user_id) FROM {T} WHERE post_id=1',
                (string) $k
            );
        }

        // (3) recursive follow-chain reach sweep over chain length.
        foreach ([2, 3, 4, 5, 6, 8] as $len) {
            $rows = [];
            for ($i = 1; $i < $len; $i++) {
                $rows[] = "($i, " . ($i + 1) . ')';
            }
            $vals = implode(',', $rows);
            self::addCase(
                $r,
                $cat,
                "follow-chain length $len reach from 1",
                [
                    'CREATE TABLE {T} (follower INT, followee INT)',
                    "INSERT INTO {T} VALUES $vals",
                ],
                'WITH RECURSIVE reach AS (SELECT followee AS u FROM {T} WHERE follower=1 UNION SELECT f.followee FROM {T} f JOIN reach r ON f.follower=r.u) SELECT COUNT(DISTINCT u) FROM reach',
                (string) ($len - 1)
            );
        }

        // (4) engagement-rate sweep.
        foreach ([[7, 28], [3, 12], [9, 36], [11, 44], [13, 52], [1, 8], [5, 40], [15, 60]] as [$likes, $followers]) {
            $exp = self::dec($likes / $followers, 4);
            self::addCase(
                $r,
                $cat,
                "engagement $likes/$followers",
                [
                    'CREATE TABLE {T} (likes INT, followers INT)',
                    "INSERT INTO {T} VALUES ($likes, $followers)",
                ],
                'SELECT CAST(likes AS DECIMAL(14,4))/followers FROM {T}',
                $exp
            );
        }
    }

    private static function analyticsBulk(Runner $r): void
    {
        $cat = 'app:analytics';

        // (1) running-total / window sweeps over varying series.
        $series = [
            [10, 20, 30],
            [5, 5, 5, 5],
            [100, 50, 25],
            [1, 2, 3, 4, 5],
            [7, 14, 21],
            [2, 4, 8, 16],
            [9, 1, 9, 1],
            [3, 6, 9, 12, 15],
            [50, 40, 30, 20, 10],
            [11, 22, 33],
            [4, 4, 4],
            [6, 12, 18, 24],
            [1, 100, 1, 100],
            [8, 16, 24, 32, 40],
            [13, 7, 19, 3],
            [25, 25, 25, 25, 25, 25],
            [2, 3, 5, 7, 11, 13],
            [99, 1, 50, 50],
        ];
        foreach ($series as $si => $vals) {
            $rows = [];
            $sum = 0;
            $max = PHP_INT_MIN;
            foreach ($vals as $i => $v) {
                $rows[] = '(' . ($i + 1) . ", $v)";
                $sum += $v;
                $max = max($max, $v);
            }
            $vstr = implode(',', $rows);
            $setup = [
                'CREATE TABLE {T} (d INT, v INT)',
                "INSERT INTO {T} VALUES $vstr",
            ];
            self::addCase($r, $cat, "series#$si running total final", $setup, 'SELECT rt FROM (SELECT d, SUM(v) OVER (ORDER BY d) rt FROM {T}) t ORDER BY d DESC LIMIT 1', (string) $sum);
            self::addCase($r, $cat, "series#$si grand sum", $setup, 'SELECT SUM(v) FROM {T}', (string) $sum);
            self::addCase($r, $cat, "series#$si max value", $setup, 'SELECT MAX(v) FROM {T}', (string) $max);
            self::addCase($r, $cat, "series#$si rank of max is 1", $setup, 'SELECT MIN(rk) FROM (SELECT v, RANK() OVER (ORDER BY v DESC) rk FROM {T}) t', '1');
            self::addCase($r, $cat, "series#$si rows count", $setup, 'SELECT COUNT(*) FROM {T}', (string) count($vals));
            $firstDelta = count($vals) >= 2 ? ($vals[1] - $vals[0]) : null;
            if ($firstDelta !== null) {
                self::addCase($r, $cat, "series#$si first LAG delta", $setup, 'SELECT v-LAG(v) OVER (ORDER BY d) FROM {T} ORDER BY d LIMIT 1 OFFSET 1', (string) $firstDelta);
            }
        }

        // (2) ROLLUP/GROUPING-SETS row-count sweeps over varying #regions.
        foreach ([1, 2, 3, 4, 5] as $nreg) {
            $rows = [];
            for ($i = 0; $i < $nreg; $i++) {
                $rows[] = "('r$i', " . (($i + 1) * 10) . ')';
            }
            $vstr = implode(',', $rows);
            $setup = [
                'CREATE TABLE {T} (region VARCHAR(10), amount DECIMAL(10,2))',
                "INSERT INTO {T} VALUES $vstr",
            ];
            self::addCase($r, $cat, "ROLLUP $nreg regions row count", $setup, 'SELECT region FROM {T} GROUP BY region WITH ROLLUP', (string) ($nreg + 1), 'rows');
            self::addCase($r, $cat, "GROUPING SETS $nreg regions row count", $setup, 'SELECT region FROM {T} GROUP BY GROUPING SETS ((region),())', (string) ($nreg + 1), 'rows');
            self::addCase($r, $cat, "CUBE $nreg regions row count", $setup, 'SELECT region FROM {T} GROUP BY CUBE(region)', (string) ($nreg + 1), 'rows');
        }

        // (3) YoY growth sweep over different revenue pairs.
        foreach ([
            [1000, 1100], [1000, 900], [500, 750], [2000, 2000], [800, 1200], [1500, 1650],
            [400, 500], [600, 480], [1200, 1500], [900, 990], [2500, 2750], [3000, 2400],
            [100, 200], [200, 100], [750, 1125], [1600, 1440], [50, 75], [10000, 11000],
        ] as [$prev, $cur]) {
            $exp = self::dec(($cur - $prev) / $prev * 100, 2);
            self::addCase(
                $r,
                $cat,
                "YoY growth $prev->$cur",
                [
                    'CREATE TABLE {T} (yr INT, revenue DECIMAL(12,2))',
                    "INSERT INTO {T} VALUES (2024, $prev.00),(2025, $cur.00)",
                ],
                'SELECT CAST((revenue-LAG(revenue) OVER (ORDER BY yr))/LAG(revenue) OVER (ORDER BY yr)*100 AS DECIMAL(10,2)) g FROM {T} ORDER BY yr DESC LIMIT 1',
                $exp
            );
        }

        // (4) NTILE bucket sweeps.
        foreach ([2, 3, 4, 5] as $buckets) {
            $rows = [];
            for ($i = 1; $i <= 8; $i++) {
                $rows[] = "($i)";
            }
            $vstr = implode(',', $rows);
            self::addCase(
                $r,
                $cat,
                "NTILE($buckets) max bucket present",
                [
                    'CREATE TABLE {T} (v INT)',
                    "INSERT INTO {T} VALUES $vstr",
                ],
                "SELECT MAX(q) FROM (SELECT NTILE($buckets) OVER (ORDER BY v) q FROM {T}) t",
                (string) $buckets
            );
        }

        // (5) conditional-pivot SUM sweeps.
        foreach ([['a', 100], ['b', 250], ['c', 75], ['d', 999]] as [$tag, $amt]) {
            self::addCase(
                $r,
                $cat,
                "pivot SUM tag=$tag",
                [
                    'CREATE TABLE {T} (tag VARCHAR(4), amount DECIMAL(12,2))',
                    "INSERT INTO {T} VALUES ('$tag', $amt.00),('z', 1.00)",
                ],
                "SELECT CAST(SUM(CASE WHEN tag='$tag' THEN amount ELSE 0 END) AS DECIMAL(14,2)) FROM {T}",
                self::dec((float) $amt)
            );
        }
    }

    private static function timeseriesBulk(Runner $r): void
    {
        $cat = 'app:timeseries';

        // (1) bucket-aggregate sweep: a series binned by FLOOR(ts/$bucket).
        foreach ([10, 15, 30, 60] as $bucket) {
            $rows = [];
            $byBucket = [];
            for ($t = 0; $t < 120; $t += 10) {
                $v = ($t / 10) + 1; // 1..12
                $rows[] = "(1, $t, $v)";
                $b = intdiv($t, $bucket);
                $byBucket[$b][] = $v;
            }
            $vstr = implode(',', $rows);
            $setup = [
                'CREATE TABLE {T} (sensor_id INT, ts INT, value INT)',
                "INSERT INTO {T} VALUES $vstr",
            ];
            $nbuckets = count($byBucket);
            self::addCase($r, $cat, "bucket($bucket) count", $setup, "SELECT COUNT(*) FROM (SELECT FLOOR(ts/$bucket) b FROM {T} GROUP BY FLOOR(ts/$bucket)) t", (string) $nbuckets);
            // sum over bucket 0
            $b0 = array_sum($byBucket[0]);
            self::addCase($r, $cat, "bucket($bucket) sum bucket0", $setup, "SELECT SUM(value) FROM {T} WHERE FLOOR(ts/$bucket)=0", (string) $b0);
            self::addCase($r, $cat, "bucket($bucket) max bucket0", $setup, "SELECT MAX(value) FROM {T} WHERE FLOOR(ts/$bucket)=0", (string) max($byBucket[0]));
            self::addCase($r, $cat, "bucket($bucket) min bucket0", $setup, "SELECT MIN(value) FROM {T} WHERE FLOOR(ts/$bucket)=0", (string) min($byBucket[0]));
        }

        // (2) rate-of-change sweep over linear-step series.
        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 10, 15, 20, 25, 50, 100] as $step) {
            $rows = [];
            for ($i = 0; $i < 5; $i++) {
                $rows[] = "(1, $i, " . ($i * $step) . ')';
            }
            $vstr = implode(',', $rows);
            self::addCase(
                $r,
                $cat,
                "constant ROC step=$step",
                [
                    'CREATE TABLE {T} (sensor_id INT, ts INT, value INT)',
                    "INSERT INTO {T} VALUES $vstr",
                ],
                'SELECT MAX(roc) FROM (SELECT value-LAG(value) OVER (ORDER BY ts) roc FROM {T}) t',
                (string) $step
            );
        }

        // (3) threshold-breach sweep.
        $vals = [10, 12, 14, 11, 20, 22, 8, 30, 5, 18];
        $rows = [];
        foreach ($vals as $i => $v) {
            $rows[] = "(1, $i, $v)";
        }
        $vstr = implode(',', $rows);
        $setup = [
            'CREATE TABLE {T} (sensor_id INT, ts INT, value INT)',
            "INSERT INTO {T} VALUES $vstr",
        ];
        foreach ([4, 5, 6, 7, 8, 9, 10, 11, 12, 14, 15, 16, 18, 20, 22, 25, 28, 30, 32, 35] as $limit) {
            $exp = count(array_filter($vals, static fn ($v) => $v > $limit));
            self::addCase($r, $cat, "breach count > $limit", $setup, "SELECT COUNT(*) FROM {T} WHERE value > $limit", (string) $exp);
            $expGe = count(array_filter($vals, static fn ($v) => $v >= $limit));
            self::addCase($r, $cat, "breach count >= $limit", $setup, "SELECT COUNT(*) FROM {T} WHERE value >= $limit", (string) $expGe);
        }

        // (4) gap-detection sweep over different sampling gaps.
        foreach ([20, 40, 60, 90] as $gap) {
            $rows = [];
            $ts = [0, 30, 30 + $gap, 30 + $gap + 30];
            foreach ($ts as $i => $t) {
                $rows[] = "(1, $t, 1)";
            }
            $vstr = implode(',', $rows);
            // gaps between consecutive: 30, $gap, 30 -> count > 30
            $expGaps = ($gap > 30 ? 1 : 0);
            self::addCase(
                $r,
                $cat,
                "gap detection (max gap $gap)",
                [
                    'CREATE TABLE {T} (sensor_id INT, ts INT, value INT)',
                    "INSERT INTO {T} VALUES $vstr",
                ],
                'SELECT COUNT(*) FROM (SELECT ts-LAG(ts) OVER (ORDER BY ts) g FROM {T}) t WHERE g > 30',
                (string) $expGaps
            );
        }

        // (5) downsample distinct-bucket sweep already covered; add cumulative sums.
        foreach ([[1, 2, 3], [10, 10, 10], [5, 0, 5, 10]] as $si => $svals) {
            $rows = [];
            $cum = 0;
            foreach ($svals as $i => $v) {
                $rows[] = "(1, $i, $v)";
                $cum += $v;
            }
            $vstr = implode(',', $rows);
            self::addCase(
                $r,
                $cat,
                "cumulative final series#$si",
                [
                    'CREATE TABLE {T} (sensor_id INT, ts INT, value INT)',
                    "INSERT INTO {T} VALUES $vstr",
                ],
                'SELECT cs FROM (SELECT ts, SUM(value) OVER (ORDER BY ts) cs FROM {T}) t ORDER BY ts DESC LIMIT 1',
                (string) $cum
            );
        }
    }

    private static function ledgerBulk(Runner $r): void
    {
        $cat = 'app:ledger';

        // (1) account-balance sweep: debits/credits pairs.
        foreach ([
            [100, 30], [250, 250], [500, 600], [0, 75], [999, 1], [12, 34], [1000, 0], [45, 45],
            [300, 150], [60, 90], [777, 333], [2000, 500], [88, 88], [5, 95], [410, 410], [1234, 234],
            [50, 0], [0, 50], [640, 160], [125, 375], [900, 450], [33, 66], [10, 10], [4096, 2048],
        ] as [$dr, $cr]) {
            $exp = self::dec($dr - $cr);
            self::addCase(
                $r,
                $cat,
                "balance dr=$dr cr=$cr",
                [
                    'CREATE TABLE {T} (debit DECIMAL(18,2), credit DECIMAL(18,2))',
                    "INSERT INTO {T} VALUES ($dr.00, 0.00),(0.00, $cr.00)",
                ],
                'SELECT CAST(SUM(debit)-SUM(credit) AS DECIMAL(18,2)) FROM {T}',
                $exp
            );
        }

        // (2) running-balance sweep over different posting sequences.
        $seqs = [
            [100, -30, 50],
            [200, -200],
            [10, 20, -5, -10],
            [500, -100, -100, -100],
            [-50, 100, -25],
            [1, 1, 1, 1, 1],
            [1000, -250, -250, -250, -250],
            [75, 25, -50],
            [-100, -100, 300],
            [40, -10, 40, -10, 40],
            [500, 500, -1000],
            [12, 34, 56, -78],
        ];
        foreach ($seqs as $qi => $postings) {
            $rows = [];
            $run = 0;
            $finals = [];
            foreach ($postings as $i => $amt) {
                $dr = $amt >= 0 ? $amt : 0;
                $cr = $amt < 0 ? -$amt : 0;
                $rows[] = '(' . ($i + 1) . ", $dr.00, $cr.00)";
                $run += $amt;
                $finals[] = $run;
            }
            $vstr = implode(',', $rows);
            $setup = [
                'CREATE TABLE {T} (seq INT, debit DECIMAL(18,2), credit DECIMAL(18,2))',
                "INSERT INTO {T} VALUES $vstr",
            ];
            self::addCase(
                $r,
                $cat,
                "running balance final seq#$qi",
                $setup,
                'SELECT CAST(rb AS DECIMAL(18,2)) FROM (SELECT seq, SUM(debit-credit) OVER (ORDER BY seq) rb FROM {T}) t ORDER BY seq DESC LIMIT 1',
                self::dec((float) end($finals))
            );
            self::addCase(
                $r,
                $cat,
                "net total seq#$qi",
                $setup,
                'SELECT CAST(SUM(debit)-SUM(credit) AS DECIMAL(18,2)) FROM {T}',
                self::dec((float) end($finals))
            );
            // running balance full column list
            $col = implode(',', array_map(static fn ($v) => self::dec((float) $v), $finals));
            self::addCase(
                $r,
                $cat,
                "running balance column seq#$qi",
                $setup,
                'SELECT CAST(rb AS DECIMAL(18,2)) FROM (SELECT seq, SUM(debit-credit) OVER (ORDER BY seq) rb FROM {T}) t ORDER BY seq',
                $col,
                'col'
            );
        }

        // (3) money-precision add sweep.
        foreach ([
            ['0.05', '0.05', '0.10'],
            ['10.55', '4.45', '15.00'],
            ['123.45', '876.55', '1000.00'],
            ['0.99', '0.01', '1.00'],
            ['250.25', '249.75', '500.00'],
            ['7.77', '2.23', '10.00'],
        ] as [$a, $b, $exp]) {
            self::addCase(
                $r,
                $cat,
                "precise money $a+$b",
                [
                    'CREATE TABLE {T} (a DECIMAL(18,2), b DECIMAL(18,2))',
                    "INSERT INTO {T} VALUES ($a, $b)",
                ],
                'SELECT CAST(a+b AS DECIMAL(18,2)) FROM {T}',
                $exp
            );
        }

        // (4) entry-balanced check sweep (each entry must net zero dr=cr).
        foreach ([
            [[100, 100], 0],
            [[100, 90], 1],
            [[50, 50], 0],
            [[200, 199], 1],
        ] as $ci => [$pair, $expUnbalanced]) {
            self::addCase(
                $r,
                $cat,
                "entry balance check #$ci",
                [
                    'CREATE TABLE {T} (entry_id INT, debit DECIMAL(18,2), credit DECIMAL(18,2))',
                    "INSERT INTO {T} VALUES (1, {$pair[0]}.00, 0.00),(1, 0.00, {$pair[1]}.00)",
                ],
                'SELECT COUNT(*) FROM (SELECT entry_id FROM {T} GROUP BY entry_id HAVING SUM(debit)<>SUM(credit)) t',
                (string) $expUnbalanced
            );
        }
    }

    private static function saasBulk(Runner $r): void
    {
        $cat = 'app:saas';

        // (1) per-tenant row-count and MRR sweep over generated tenants.
        foreach ([1, 2, 3, 4, 5] as $ntenants) {
            $rows = [];
            $expCounts = [];
            $expMrr = [];
            for ($t = 1; $t <= $ntenants; $t++) {
                $users = $t + 1; // tenant t has t+1 users
                $expCounts[$t] = $users;
                $mrr = 0;
                for ($u = 0; $u < $users; $u++) {
                    $m = ($u % 2) * 50; // alternating 0/50
                    $rows[] = "($t, " . ($t * 100 + $u) . ", $m.00)";
                    $mrr += $m;
                }
                $expMrr[$t] = $mrr;
            }
            $vstr = implode(',', $rows);
            $setup = [
                'CREATE TABLE {T} (tenant_id INT, user_id INT, mrr DECIMAL(10,2))',
                "INSERT INTO {T} VALUES $vstr",
            ];
            // check tenant 1 isolation each time
            self::addCase($r, $cat, "tenants=$ntenants tenant1 user count", $setup, 'SELECT COUNT(*) FROM {T} WHERE tenant_id=1', (string) $expCounts[1]);
            self::addCase($r, $cat, "tenants=$ntenants tenant1 MRR", $setup, 'SELECT CAST(SUM(mrr) AS DECIMAL(12,2)) FROM {T} WHERE tenant_id=1', self::dec((float) $expMrr[1]));
            self::addCase($r, $cat, "tenants=$ntenants distinct tenants", $setup, 'SELECT COUNT(DISTINCT tenant_id) FROM {T}', (string) $ntenants);
            // isolation: tenant 1 query returns no other-tenant user ids
            self::addCase($r, $cat, "tenants=$ntenants isolation leak check", $setup, 'SELECT COUNT(*) FROM {T} WHERE tenant_id=1 AND tenant_id<>1', '0');
        }

        // (2) quota usage-percent sweep.
        foreach ([
            [80, 100], [50, 200], [150, 100], [25, 50], [99, 100], [33, 99],
            [10, 100], [200, 100], [75, 150], [40, 80], [120, 240], [5, 10],
            [90, 100], [100, 100], [250, 500], [1, 4], [60, 120], [175, 100],
        ] as [$used, $quota]) {
            $exp = self::dec($used / $quota * 100, 2);
            self::addCase(
                $r,
                $cat,
                "quota $used/$quota percent",
                [
                    'CREATE TABLE {T} (used INT, quota INT)',
                    "INSERT INTO {T} VALUES ($used, $quota)",
                ],
                'SELECT CAST(used AS DECIMAL(10,2))/quota*100 FROM {T}',
                $exp
            );
            self::addCase(
                $r,
                $cat,
                "quota $used/$quota over?",
                [
                    'CREATE TABLE {T} (used INT, quota INT)',
                    "INSERT INTO {T} VALUES ($used, $quota)",
                ],
                'SELECT CASE WHEN used > quota THEN 1 ELSE 0 END FROM {T}',
                $used > $quota ? '1' : '0'
            );
        }
    }

    private static function cmsBulk(Runner $r): void
    {
        $cat = 'app:cms';

        // (1) recursive subtree size sweep over a generated balanced tree.
        // Build a chain tree of depth D: 1->2->3->...->D, subtree(1)=D.
        foreach ([2, 3, 4, 5, 6, 7, 8, 9, 10, 12, 15] as $depth) {
            $rows = ["(1, NULL, 'n1')"];
            for ($i = 2; $i <= $depth; $i++) {
                $rows[] = "($i, " . ($i - 1) . ", 'n$i')";
            }
            $vstr = implode(',', $rows);
            $setup = [
                'CREATE TABLE {T} (id INT, parent_id INT NULL, name VARCHAR(20))',
                "INSERT INTO {T} VALUES $vstr",
            ];
            self::addCase(
                $r,
                $cat,
                "chain depth $depth recursive subtree size",
                $setup,
                'WITH RECURSIVE sub AS (SELECT id FROM {T} WHERE id=1 UNION ALL SELECT c.id FROM {T} c JOIN sub s ON c.parent_id=s.id) SELECT COUNT(*) FROM sub',
                (string) $depth
            );
            self::addCase(
                $r,
                $cat,
                "chain depth $depth breadcrumb depth of leaf",
                $setup,
                "WITH RECURSIVE path AS (SELECT id, parent_id, 1 AS d FROM {T} WHERE id=$depth UNION ALL SELECT c.id, c.parent_id, path.d+1 FROM {T} c JOIN path ON c.id=path.parent_id) SELECT MAX(d) FROM path",
                (string) $depth
            );
        }

        // (2) nested-set subtree sweep over generated lft/rgt of a flat tree
        // (root with K leaves): root lft=1 rgt=2K+2; each leaf lft/rgt consecutive.
        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 10, 12] as $kleaves) {
            $rows = [];
            $rgtRoot = 2 * $kleaves + 2;
            $rows[] = "(0, 'root', 1, $rgtRoot)";
            $pos = 2;
            for ($k = 1; $k <= $kleaves; $k++) {
                $rows[] = "($k, 'leaf$k', $pos, " . ($pos + 1) . ')';
                $pos += 2;
            }
            $vstr = implode(',', $rows);
            $setup = [
                'CREATE TABLE {T} (id INT, name VARCHAR(20), lft INT, rgt INT)',
                "INSERT INTO {T} VALUES $vstr",
            ];
            self::addCase(
                $r,
                $cat,
                "nested-set root subtree $kleaves leaves",
                $setup,
                "SELECT COUNT(*) FROM {T} WHERE lft BETWEEN 1 AND $rgtRoot",
                (string) ($kleaves + 1)
            );
            self::addCase(
                $r,
                $cat,
                "nested-set leaf count $kleaves leaves",
                $setup,
                'SELECT COUNT(*) FROM {T} WHERE rgt = lft + 1',
                (string) $kleaves
            );
        }

        // (3) soft-delete / revision sweep.
        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 10, 12] as $nrev) {
            $rows = [];
            for ($v = 1; $v <= $nrev; $v++) {
                $rows[] = "(1, $v, 'v$v')";
            }
            $vstr = implode(',', $rows);
            $setup = [
                'CREATE TABLE {T} (article_id INT, rev INT, body VARCHAR(20))',
                "INSERT INTO {T} VALUES $vstr",
            ];
            self::addCase($r, $cat, "revisions=$nrev latest body", $setup, 'SELECT body FROM {T} WHERE article_id=1 ORDER BY rev DESC LIMIT 1', "v$nrev", 'exact');
            self::addCase($r, $cat, "revisions=$nrev max rev", $setup, 'SELECT MAX(rev) FROM {T}', (string) $nrev);
            self::addCase($r, $cat, "revisions=$nrev count", $setup, 'SELECT COUNT(*) FROM {T}', (string) $nrev);
        }

        // (4) soft-delete filter sweep over live/trashed ratios.
        foreach ([[3, 1], [5, 2], [2, 4], [10, 0], [0, 3]] as [$live, $trash]) {
            $rows = [];
            $id = 1;
            for ($i = 0; $i < $live; $i++) {
                $rows[] = "($id, NULL)";
                $id++;
            }
            for ($i = 0; $i < $trash; $i++) {
                $rows[] = "($id, '2026-01-01 00:00:00')";
                $id++;
            }
            $vstr = implode(',', $rows);
            $setup = [
                'CREATE TABLE {T} (id INT, deleted_at DATETIME NULL)',
                "INSERT INTO {T} VALUES $vstr",
            ];
            self::addCase($r, $cat, "soft-delete live=$live trash=$trash live count", $setup, 'SELECT COUNT(*) FROM {T} WHERE deleted_at IS NULL', (string) $live);
            self::addCase($r, $cat, "soft-delete live=$live trash=$trash trash count", $setup, 'SELECT COUNT(*) FROM {T} WHERE deleted_at IS NOT NULL', (string) $trash);
        }
    }

    private static function geoBulk(Runner $r): void
    {
        $cat = 'app:geo';

        // Bounding-box sweep over a generated grid of points (lat,lng integers).
        $rows = [];
        $pts = [];
        $id = 1;
        for ($lat = 0; $lat <= 5; $lat++) {
            for ($lng = 0; $lng <= 5; $lng++) {
                $rows[] = "($id, $lat, $lng)";
                $pts[] = [$lat, $lng];
                $id++;
            }
        }
        $vstr = implode(',', $rows);
        $setup = [
            'CREATE TABLE {T} (id INT, lat DOUBLE, lng DOUBLE)',
            "INSERT INTO {T} VALUES $vstr",
        ];
        $boxes = [
            [0, 2, 0, 2], [1, 3, 1, 3], [0, 0, 0, 5], [2, 4, 0, 1],
            [3, 5, 3, 5], [0, 5, 0, 5], [1, 1, 1, 1], [4, 5, 0, 0],
            [0, 1, 0, 1], [2, 2, 2, 2], [0, 3, 2, 5], [3, 3, 0, 5],
            [0, 5, 0, 0], [0, 5, 5, 5], [1, 4, 1, 4], [2, 5, 3, 4],
            [0, 0, 0, 0], [5, 5, 5, 5], [1, 2, 3, 4], [3, 4, 1, 2],
            [0, 4, 0, 4], [1, 5, 0, 2], [2, 3, 2, 3], [0, 2, 3, 5],
        ];
        foreach ($boxes as $bi => [$latLo, $latHi, $lngLo, $lngHi]) {
            $exp = 0;
            foreach ($pts as [$la, $ln]) {
                if ($la >= $latLo && $la <= $latHi && $ln >= $lngLo && $ln <= $lngHi) {
                    $exp++;
                }
            }
            self::addCase(
                $r,
                $cat,
                "grid bounding-box #$bi",
                $setup,
                "SELECT COUNT(*) FROM {T} WHERE lat BETWEEN $latLo AND $latHi AND lng BETWEEN $lngLo AND $lngHi",
                (string) $exp
            );
        }

        // Nearest-to-origin sweep: the nearest grid point to (0,0) is id 1 (0,0).
        self::addCase(
            $r,
            $cat,
            'grid nearest to origin is (0,0)',
            $setup,
            'SELECT id FROM {T} ORDER BY (POW(lat,2)+POW(lng,2)) LIMIT 1',
            '1',
            'exact'
        );
        // Nearest to corner (5,5).
        self::addCase(
            $r,
            $cat,
            'grid nearest to (5,5) corner',
            $setup,
            'SELECT id FROM {T} ORDER BY (POW(lat-5,2)+POW(lng-5,2)) LIMIT 1',
            '36',
            'exact'
        );
    }
}
