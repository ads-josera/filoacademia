<?php

/**
 * Arranque común a todos los puntos de entrada (web y consola).
 * Devuelve la instancia de FiloAcademia\App.
 */

declare(strict_types=1);

use FiloAcademia\App;
use FiloAcademia\Support\Config;

$root = __DIR__;

if (!is_file($root . '/vendor/autoload.php')) {
    http_response_code(500);
    exit('Faltan las dependencias: ejecuta «composer install --no-dev» en ' . $root);
}
require $root . '/vendor/autoload.php';

if (!is_file($root . '/config/config.php')) {
    http_response_code(500);
    exit('Falta config/config.php: cópialo de config/config.example.php y llénalo.');
}

$config = new Config(require $root . '/config/config.php');

date_default_timezone_set($config->string('app.timezone', 'America/Mexico_City'));
mb_internal_encoding('UTF-8');

// En producción ningún error llega a la pantalla: va a storage/logs/php-errors.log.
ini_set('display_errors', $config->isProduction() ? '0' : '1');
ini_set('log_errors', '1');
ini_set('error_log', $root . '/storage/logs/php-errors.log');
error_reporting(E_ALL);

return new App($root, $config, require $root . '/config/business.php');
