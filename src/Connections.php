<?php

declare(strict_types=1);

namespace MoTest;

use Cake\Database\Connection as CakeConnection;
use Cake\Database\Driver\Mysql as CakeMysql;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Builds raw PDO and ORM-specific connection objects, all pointed at the same
 * MatrixOne server but at framework-specific databases.
 */
final class Connections
{
    private static ?Capsule $capsule = null;
    /** @var array<string, DbalConnection> */
    private static array $dbal = [];
    /** @var array<string, EntityManagerInterface> */
    private static array $em = [];

    /** Raw PDO. $db null => no database selected (server scope). */
    public static function pdo(?string $db = null): \PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d', Config::host(), Config::port());
        if ($db !== null) {
            $dsn .= ';dbname=' . $db;
        }
        $dsn .= ';charset=utf8mb4';
        return new \PDO($dsn, Config::user(), Config::password(), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT => 15,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ]);
    }

    /** Create the per-framework databases (idempotent). */
    public static function bootstrapDatabases(array $frameworks): void
    {
        $pdo = self::pdo();
        foreach ($frameworks as $fw) {
            $db = Config::database($fw);
            $pdo->exec("DROP DATABASE IF EXISTS `$db`");
            $pdo->exec("CREATE DATABASE `$db`");
        }
    }

    public static function eloquent(): Capsule
    {
        if (self::$capsule === null) {
            $capsule = new Capsule();
            $capsule->addConnection([
                'driver' => 'mysql',
                'host' => Config::host(),
                'port' => Config::port(),
                'database' => Config::database('eloquent'),
                'username' => Config::user(),
                'password' => Config::password(),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_general_ci',
                'prefix' => '',
                // Intentionally left at Laravel's defaults (emulated prepares
                // ON) so results reflect a stock Eloquent application.
            ]);
            $capsule->setAsGlobal();
            $capsule->bootEloquent();
            self::$capsule = $capsule;
        }
        return self::$capsule;
    }

    public static function dbal(string $db): DbalConnection
    {
        if (!isset(self::$dbal[$db])) {
            self::$dbal[$db] = DriverManager::getConnection([
                'dbname' => $db,
                'user' => Config::user(),
                'password' => Config::password(),
                'host' => Config::host(),
                'port' => Config::port(),
                'driver' => 'pdo_mysql',
                'charset' => 'utf8mb4',
                'serverVersion' => Config::serverVersion(),
            ]);
        }
        return self::$dbal[$db];
    }

    public static function entityManager(string $db, array $entityPaths): EntityManagerInterface
    {
        $key = $db . '|' . implode(',', $entityPaths);
        if (!isset(self::$em[$key])) {
            $config = ORMSetup::createAttributeMetadataConfiguration(
                paths: $entityPaths,
                isDevMode: true,
            );
            $conn = DriverManager::getConnection([
                'dbname' => $db,
                'user' => Config::user(),
                'password' => Config::password(),
                'host' => Config::host(),
                'port' => Config::port(),
                'driver' => 'pdo_mysql',
                'charset' => 'utf8mb4',
                'serverVersion' => Config::serverVersion(),
            ], $config);
            self::$em[$key] = new EntityManager($conn, $config);
        }
        return self::$em[$key];
    }

    public static function cake(): CakeConnection
    {
        return new CakeConnection([
            'driver' => CakeMysql::class,
            'host' => Config::host(),
            'port' => Config::port(),
            'username' => Config::user(),
            'password' => Config::password(),
            'database' => Config::database('cake'),
            'encoding' => 'utf8mb4',
            'timezone' => 'UTC',
        ]);
    }
}
