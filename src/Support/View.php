<?php

declare(strict_types=1);

namespace FiloAcademia\Support;

/**
 * Plantillas PHP planas (templates/). Cada plantilla recibe `$v` (esta clase)
 * y sus datos como variables.
 *
 * Regla: todo texto variable se imprime con $v->e(). Lo único que se imprime
 * sin escapar es HTML que ya generó otra plantilla.
 */
final class View
{
    /** @param array<string, mixed> $shared Datos disponibles en todas las plantillas. */
    public function __construct(
        private readonly string $templatesDir,
        private readonly string $publicDir,
        private readonly Urls $urls,
        private array $shared = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string
    {
        $file = $this->templatesDir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \InvalidArgumentException('Plantilla inexistente: ' . $template);
        }

        $render = function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            $v = $this;
            ob_start();
            try {
                require $__file;

                return (string) ob_get_clean();
            } catch (\Throwable $exception) {
                ob_end_clean();
                throw $exception;
            }
        };

        return $render($file, $data + $this->shared);
    }

    /**
     * Renderiza una página completa dentro del layout.
     *
     * @param array<string, mixed> $data
     */
    public function page(string $template, array $data = []): string
    {
        return $this->render('layout', $data + ['content' => $this->render($template, $data)]);
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** $1,250 (sin decimales: todos los precios son en pesos enteros). */
    public function money(int|float $amount): string
    {
        return '$' . number_format((float) $amount, 0, '.', ',');
    }

    public function url(string $path = '/'): string
    {
        return $this->urls->path($path);
    }

    /** Ruta de un recurso con versión por fecha de modificación (evita caché vieja). */
    public function asset(string $path): string
    {
        $file = $this->publicDir . '/assets/' . ltrim($path, '/');
        $version = is_file($file) ? (string) filemtime($file) : '0';

        return $this->urls->path('/assets/' . ltrim($path, '/')) . '?v=' . $version;
    }

    /** JSON seguro para incrustar en <script type="application/json">. */
    public function json(mixed $data): string
    {
        return json_encode(
            $data,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * URL de WhatsApp del taller con un mensaje opcional. Los enlaces se pintan
     * con el parcial partials/whatsapp-link (pestaña nueva, texto accesible).
     */
    public function whatsappUrl(?string $text = null): string
    {
        $url = 'https://wa.me/' . preg_replace('/\D+/', '', (string) ($this->shared['business']['whatsapp'] ?? ''));

        return $text !== null && trim($text) !== '' ? $url . '?text=' . rawurlencode(trim($text)) : $url;
    }

    /** Mensaje de WhatsApp con el detalle del pedido (templates/whatsapp/order-message.php). */
    public function whatsappOrderMessage(\FiloAcademia\Order\Order $order, string $intro): string
    {
        // Las líneas vacías de la plantilla se compactan: WhatsApp las respeta tal cual.
        $text = $this->render('whatsapp/order-message', ['order' => $order, 'intro' => $intro]);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    public function urls(): Urls
    {
        return $this->urls;
    }
}
