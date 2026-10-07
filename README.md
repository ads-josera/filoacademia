# HERO · Filo Academia

Sitio de HERO · Filo Academia (afilado y reparación de cuchillos, CDMX) con
cotizador, carrito y **pago en línea con Mercado Pago**, más correos de
confirmación al cliente y al taller.

Reconstrucción en PHP del HTML original del cliente, con el mismo diseño y
contenido, pero con cobro real, precios validados en el servidor y
accesibilidad medida.

## Requisitos

| | |
|---|---|
| PHP | 8.3 o superior (producción: **8.4**) con `pdo_sqlite`, `curl`, `mbstring`, `openssl` |
| Composer | 2.x |
| Servidor | Apache o LiteSpeed con `mod_rewrite` (cPanel), HTTPS obligatorio |
| Base de datos | SQLite (por defecto, sin servidor) o MySQL/MariaDB |
| Correo | Una cuenta SMTP (p. ej. `pagos@dominio` en cPanel) |
| Mercado Pago | Cuenta de vendedor + aplicación en el panel de desarrolladores |

## Arranque local

```bash
composer install
cp config/config.example.php config/config.php   # modo local: correos a storage/mail/
php bin/migrate.php
php -S localhost:8000 -t public bin/dev-router.php
```

Abre <http://localhost:8000>. Sin llaves de Mercado Pago, el paso de pago muestra
el estado «No pudimos conectar con Mercado Pago»: es lo esperado.

## Pruebas

```bash
composer test                     # 46 pruebas: precios, firma del webhook, flujo de cobro, correos
node <skill browser-automation>/browser.mjs http://localhost:8000/ --script tests/browser/qa.mjs
```

## Documentación

| Documento | Para qué |
|---|---|
| [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) | Estructura de carpetas, capas, decisiones y diseño |
| [docs/FLUJO-DE-PAGO.md](docs/FLUJO-DE-PAGO.md) | Cómo funciona el cobro de punta a punta, estados y seguridad |
| [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md) | Manual de instalación en cPanel, con los comandos agrupados |
| [docs/OPERACION.md](docs/OPERACION.md) | Día a día: cambiar precios, revisar pedidos, bitácoras, problemas comunes |

## Autoría

José Raúl Perea · [ads@josera.com.mx](mailto:ads@josera.com.mx)
