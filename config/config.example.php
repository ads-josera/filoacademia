<?php

/**
 * Configuración de entorno de HERO · Filo Academia.
 *
 * Copia este archivo como `config/config.php` y llena los valores reales.
 * `config/config.php` está fuera de git y fuera de public_html: es el ÚNICO
 * lugar donde viven llaves y contraseñas. Nunca las pongas en otro archivo.
 */

declare(strict_types=1);

return [
    'app' => [
        // local | production. En production los errores no se muestran.
        'env' => 'local',
        // URL pública sin barra final. Se usa en correos y en las URLs que
        // recibe Mercado Pago (retorno y webhook), así que debe ser la real.
        'url' => 'http://localhost:8000',
        'timezone' => 'America/Mexico_City',
    ],

    'database' => [
        // SQLite por defecto: un archivo dentro de storage/, sin servidor.
        // Para MySQL: 'mysql:host=localhost;dbname=usuario_filo;charset=utf8mb4'
        'dsn' => 'sqlite:' . dirname(__DIR__) . '/storage/database.sqlite',
        'username' => null,
        'password' => null,
    ],

    'mercadopago' => [
        // pro    → el cliente paga en la ventana segura de Mercado Pago.
        // bricks → el formulario de pago aparece dentro de nuestra página.
        'checkout_mode' => 'pro',

        // Qué juego de llaves usa el sitio: test (pagos ficticios) | production
        // (cobra de verdad). Los dos juegos pueden estar guardados a la vez:
        // pasar a producción es cambiar esta palabra.
        'mode' => 'test',
        'test' => [
            'public_key' => '',
            'access_token' => '',
        ],
        'production' => [
            'public_key' => '',
            'access_token' => '',
        ],

        // Panel de Mercado Pago → Tus integraciones → Webhooks → «Clave secreta».
        // Sin ella, el webhook rechaza todas las notificaciones.
        'webhook_secret' => '',
        // Texto que aparece en el estado de cuenta de la tarjeta (máx. 22).
        'statement_descriptor' => 'HERO FILO ACADEMIA',
        'max_installments' => 1,
    ],

    'mail' => [
        // smtp → envía de verdad (cuenta de correo de cPanel).
        // log  → guarda cada correo en storage/mail/ sin enviarlo (desarrollo sin DDEV).
        'transport' => 'log',
        'host' => 'mail.tudominio.mx',
        'port' => 465,
        // ssl (puerto 465) | tls (puerto 587) | none (solo Mailpit de DDEV:
        // host 'localhost', puerto 1025, username '' → https://filoacademia.ddev.site:8026)
        'encryption' => 'ssl',
        'username' => 'pagos@tudominio.mx',
        'password' => '',
        'from_email' => 'pagos@tudominio.mx',
        'from_name' => 'HERO · Filo Academia',
        // Quién recibe el aviso de cada pago. Se admite más de uno.
        'admin_recipients' => ['hola@herofilo.mx'],
    ],
];
