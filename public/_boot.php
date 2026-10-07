<?php

/**
 * Localiza la aplicación desde cualquier punto de entrada de public/.
 *
 * Caso normal: public/ está dentro del proyecto (o public_html es un enlace
 * simbólico a él) → la aplicación está en el directorio padre.
 * Caso alterno: public_html es una COPIA de public/ → el despliegue crea
 * public_html/app-root.local.php con `return '/home/usuario/filoacademia';`.
 * Ver docs/DESPLIEGUE.md.
 */

declare(strict_types=1);

$filoRoot = is_file(__DIR__ . '/app-root.local.php')
    ? require __DIR__ . '/app-root.local.php'
    : dirname(__DIR__);

return require $filoRoot . '/bootstrap.php';
