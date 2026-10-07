<?php

declare(strict_types=1);

namespace FiloAcademia\Persistence;

use PDO;

/**
 * Conexión PDO y esquema. Soporta SQLite (por defecto) y MySQL/MariaDB.
 *
 * El esquema es idempotente: `migrate()` se puede ejecutar cuantas veces se
 * quiera (lo hace bin/migrate.php en cada despliegue).
 */
final class Database
{
    public static function connect(string $dsn, ?string $username = null, ?string $password = null): PDO
    {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        if (self::driver($pdo) === 'sqlite') {
            // Espera en lugar de fallar si el webhook y el cliente escriben a la vez.
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        return $pdo;
    }

    public static function migrate(PDO $pdo): void
    {
        $sqlite = self::driver($pdo) === 'sqlite';
        $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $datetime = $sqlite ? 'TEXT' : 'DATETIME';
        $suffix = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS orders (
                id {$id},
                folio VARCHAR(16) NOT NULL UNIQUE,
                access_token CHAR(64) NOT NULL,
                status VARCHAR(20) NOT NULL,
                customer_name VARCHAR(120) NOT NULL,
                customer_email VARCHAR(190) NOT NULL,
                customer_phone VARCHAR(30) NOT NULL,
                customer_address TEXT NULL,
                customer_notes TEXT NULL,
                lines_json TEXT NOT NULL,
                delivery_id VARCHAR(30) NOT NULL,
                delivery_name VARCHAR(120) NOT NULL,
                services_amount INT NOT NULL,
                delivery_amount INT NOT NULL,
                insurance_amount INT NOT NULL,
                total INT NOT NULL,
                currency CHAR(3) NOT NULL,
                checkout_mode VARCHAR(10) NOT NULL,
                mp_preference_id VARCHAR(80) NULL,
                mp_init_point TEXT NULL,
                mp_payment_id VARCHAR(32) NULL,
                mp_status VARCHAR(30) NULL,
                mp_status_detail VARCHAR(80) NULL,
                mp_payment_method VARCHAR(40) NULL,
                mp_ticket_url TEXT NULL,
                payment_attempts INT NOT NULL DEFAULT 0,
                ip_hash CHAR(64) NOT NULL,
                paid_at {$datetime} NULL,
                customer_notified_at {$datetime} NULL,
                admin_notified_at {$datetime} NULL,
                created_at {$datetime} NOT NULL,
                updated_at {$datetime} NOT NULL
            ){$suffix}
            SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS webhook_events (
                id {$id},
                request_id VARCHAR(80) NULL,
                event_type VARCHAR(40) NULL,
                resource_id VARCHAR(40) NULL,
                signature_valid INT NOT NULL,
                result VARCHAR(255) NOT NULL,
                created_at {$datetime} NOT NULL
            ){$suffix}
            SQL);

        self::createIndex($pdo, 'idx_orders_ip_created', 'orders', 'ip_hash, created_at');
        self::createIndex($pdo, 'idx_orders_payment', 'orders', 'mp_payment_id');
    }

    public static function driver(PDO $pdo): string
    {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private static function createIndex(PDO $pdo, string $name, string $table, string $columns): void
    {
        if (self::driver($pdo) === 'sqlite') {
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$name} ON {$table} ({$columns})");

            return;
        }

        // MySQL no admite IF NOT EXISTS en índices: se consulta antes.
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?'
        );
        $statement->execute([$table, $name]);
        if ((int) $statement->fetchColumn() === 0) {
            $pdo->exec("CREATE INDEX {$name} ON {$table} ({$columns})");
        }
    }
}
