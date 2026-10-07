<?php

/**
 * Router para el servidor de desarrollo de PHP (imita public/.htaccess):
 *
 *   php -S localhost:8000 -t public bin/dev-router.php
 *
 * Solo para desarrollo local. En el servidor manda .htaccess.
 */

declare(strict_types=1);

$public = dirname(__DIR__) . '/public';
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (str_contains($path, '..') || preg_match('#/(_boot|app-root\.local)\.php$|/\.#', $path)) {
    http_response_code(403);
    exit('Prohibido');
}

$target = $public . $path;

if (is_file($target)) {
    // Archivos estáticos y .php existentes: los sirve el servidor integrado.
    return false;
}
if (is_dir($target) && is_file(rtrim($target, '/') . '/index.php')) {
    return false;
}

$withPhp = $public . rtrim($path, '/') . '.php';
if ($path !== '/' && is_file($withPhp)) {
    $_SERVER['SCRIPT_NAME'] = rtrim($path, '/') . '.php';
    require $withPhp;
    return true;
}

require $public . '/404.php';
