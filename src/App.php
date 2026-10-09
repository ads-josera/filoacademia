<?php

declare(strict_types=1);

namespace FiloAcademia;

use FiloAcademia\Catalog\Catalog;
use FiloAcademia\Catalog\QuoteCalculator;
use FiloAcademia\Mail\LogMailer;
use FiloAcademia\Mail\Mailer;
use FiloAcademia\Mail\OrderEmails;
use FiloAcademia\Mail\OrderNotifier;
use FiloAcademia\Mail\SmtpMailer;
use FiloAcademia\Order\OrderRepository;
use FiloAcademia\Payment\CheckoutService;
use FiloAcademia\Payment\Http\CurlTransport;
use FiloAcademia\Payment\MercadoPago\MercadoPagoGateway;
use FiloAcademia\Payment\MercadoPago\WebhookSignature;
use FiloAcademia\Payment\PaymentGateway;
use FiloAcademia\Payment\PaymentSyncService;
use FiloAcademia\Persistence\Database;
use FiloAcademia\Support\Config;
use FiloAcademia\Support\Logger;
use FiloAcademia\Support\Urls;
use FiloAcademia\Support\View;
use PDO;

/**
 * Contenedor de servicios: el único lugar que sabe cómo se construye cada
 * pieza. Los puntos de entrada de public/ piden aquí lo que necesitan.
 *
 * Cada servicio se crea al pedirlo por primera vez, así una página que no
 * cobra nunca abre conexión con Mercado Pago ni con el SMTP.
 */
final class App
{
    /** @var array<string, object> */
    private array $instances = [];

    /** @param array<string, mixed> $business */
    public function __construct(
        public readonly string $rootDir,
        public readonly Config $config,
        public readonly array $business,
    ) {
    }

    public function pdo(): PDO
    {
        return $this->shared(PDO::class, fn (): PDO => Database::connect(
            $this->config->string('database.dsn'),
            $this->config->get('database.username'),
            $this->config->get('database.password'),
        ));
    }

    public function catalog(): Catalog
    {
        return $this->shared(Catalog::class, fn (): Catalog => Catalog::fromFile($this->rootDir . '/config/catalog.php'));
    }

    public function urls(): Urls
    {
        return $this->shared(Urls::class, fn (): Urls => new Urls($this->config->string('app.url')));
    }

    public function logger(): Logger
    {
        return $this->shared(Logger::class, fn (): Logger => new Logger($this->rootDir . '/storage/logs'));
    }

    public function view(): View
    {
        return $this->shared(View::class, fn (): View => new View(
            $this->rootDir . '/templates',
            $this->rootDir . '/public',
            $this->urls(),
            ['business' => $this->business],
        ));
    }

    public function orders(): OrderRepository
    {
        return $this->shared(OrderRepository::class, fn (): OrderRepository => new OrderRepository(
            $this->pdo(),
            static fn (): \DateTimeImmutable => new \DateTimeImmutable(),
        ));
    }

    public function paymentGateway(): PaymentGateway
    {
        return $this->shared(PaymentGateway::class, fn (): PaymentGateway => new MercadoPagoGateway(
            new CurlTransport(),
            $this->config->mercadoPagoCredential('access_token'),
            $this->urls(),
            mb_substr($this->config->string('mercadopago.statement_descriptor'), 0, 22),
            $this->maxInstallments(),
        ));
    }

    public function webhookSignature(): WebhookSignature
    {
        return new WebhookSignature($this->config->string('mercadopago.webhook_secret'));
    }

    public function mailer(): Mailer
    {
        return $this->shared(Mailer::class, fn (): Mailer => $this->config->get('mail.transport') === 'smtp'
            ? new SmtpMailer(
                $this->config->string('mail.host'),
                (int) $this->config->get('mail.port', 465),
                $this->config->string('mail.encryption', 'ssl'),
                $this->config->string('mail.username'),
                $this->config->string('mail.password'),
                $this->config->string('mail.from_email'),
                $this->config->string('mail.from_name'),
            )
            : new LogMailer($this->rootDir . '/storage/mail'));
    }

    public function orderEmails(): OrderEmails
    {
        return $this->shared(OrderEmails::class, fn (): OrderEmails => new OrderEmails(
            $this->view(),
            $this->rootDir . '/templates/emails/assets',
            array_values(array_filter((array) $this->config->get('mail.admin_recipients', []))),
            (string) $this->business['email'],
        ));
    }

    public function notifier(): OrderNotifier
    {
        return $this->shared(OrderNotifier::class, fn (): OrderNotifier => new OrderNotifier(
            $this->mailer(),
            $this->orders(),
            $this->orderEmails(),
            $this->logger(),
        ));
    }

    public function paymentSync(): PaymentSyncService
    {
        return $this->shared(PaymentSyncService::class, fn (): PaymentSyncService => new PaymentSyncService(
            $this->paymentGateway(),
            $this->orders(),
            $this->notifier(),
            $this->logger(),
        ));
    }

    public function checkout(): CheckoutService
    {
        return $this->shared(CheckoutService::class, fn (): CheckoutService => new CheckoutService(
            new QuoteCalculator($this->catalog()),
            $this->orders(),
            $this->paymentGateway(),
            $this->paymentSync(),
            $this->logger(),
            $this->config->string('mercadopago.checkout_mode', CheckoutService::MODE_PRO),
            $this->maxInstallments(),
        ));
    }

    public function isHttps(): bool
    {
        return str_starts_with($this->config->string('app.url'), 'https://');
    }

    /**
     * IP del cliente. Solo se usa (hasheada) para limitar abusos, así que no
     * se confía en cabeceras como X-Forwarded-For, que el cliente controla.
     */
    public function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    private function maxInstallments(): int
    {
        return max(1, (int) $this->config->get('mercadopago.max_installments', 1));
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @param \Closure(): T $factory
     * @return T
     */
    private function shared(string $id, \Closure $factory): object
    {
        return $this->instances[$id] ??= $factory();
    }
}
