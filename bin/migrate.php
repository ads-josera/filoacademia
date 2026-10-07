<?php

/**
 * Crea o actualiza las tablas. Seguro de ejecutar en cada despliegue.
 *
 *   php bin/migrate.php
 */

declare(strict_types=1);

use FiloAcademia\Persistence\Database;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

/** @var FiloAcademia\App $app */
$app = require dirname(__DIR__) . '/bootstrap.php';

Database::migrate($app->pdo());

echo 'Base de datos lista (' . Database::driver($app->pdo()) . ').' . PHP_EOL;
