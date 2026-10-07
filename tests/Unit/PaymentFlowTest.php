<?php

declare(strict_types=1);

namespace FiloAcademia\Tests\Unit;

use FiloAcademia\Catalog\Catalog;
use FiloAcademia\Catalog\QuoteCalculator;
use FiloAcademia\Mail\Email;
use FiloAcademia\Mail\MailException;
use FiloAcademia\Mail\Mailer;
use FiloAcademia\Mail\OrderNotifier;
use FiloAcademia\Order\Order;
use FiloAcademia\Order\OrderRepository;
use FiloAcademia\Order\OrderStatus;
use FiloAcademia\Order\PaymentSnapshot;
use FiloAcademia\Payment\CheckoutException;
use FiloAcademia\Payment\CheckoutService;
use FiloAcademia\Payment\CheckoutSession;
use FiloAcademia\Payment\PaymentGateway;
use FiloAcademia\Payment\PaymentSyncService;
use FiloAcademia\Persistence\Database;
use FiloAcademia\Support\Logger;
use FiloAcademia\Support\Urls;
use FiloAcademia\Support\View;
use PHPUnit\Framework\TestCase;

/**
 * Flujo de cobro completo contra SQLite en memoria, con Mercado Pago y el
 * correo sustituidos por dobles de prueba.
 */
final class PaymentFlowTest extends TestCase
{
    private OrderRepository $orders;
    private FakeGateway $gateway;
    private RecordingMailer $mailer;
    private CheckoutService $checkout;
    private PaymentSyncService $sync;
    private string $logDir;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $pdo = Database::connect('sqlite::memory:');
        Database::migrate($pdo);

        $this->logDir = sys_get_temp_dir() . '/filo-test-' . bin2hex(random_bytes(4));
        mkdir($this->logDir);
        $logger = new Logger($this->logDir);

        $this->orders = new OrderRepository($pdo, static fn (): \DateTimeImmutable => new \DateTimeImmutable());
        $this->gateway = new FakeGateway();
        $this->mailer = new RecordingMailer();
        $urls = new Urls('https://filoacademia.test');
        $view = new View($root . '/templates', $root . '/public', $urls, ['business' => require $root . '/config/business.php']);
        $notifier = new OrderNotifier($this->mailer, $this->orders, $view, $logger, ['taller@ejemplo.mx'], 'hola@ejemplo.mx');

        $this->sync = new PaymentSyncService($this->gateway, $this->orders, $notifier, $logger);
        $this->checkout = new CheckoutService(
            new QuoteCalculator(Catalog::fromFile($root . '/config/catalog.php')),
            $this->orders,
            $this->gateway,
            $this->sync,
            $logger,
            CheckoutService::MODE_BRICKS,
            1,
        );
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->logDir . '/*') ?: []);
        rmdir($this->logDir);
    }

    private function placeOrder(array $overrides = []): Order
    {
        [$order, $errors] = $this->checkout->placeOrder($overrides + [
            'cart' => json_encode([['removal' => 'remocion-2mm', 'extras' => ['punta'], 'qty' => 2]]),
            'delivery' => 'nacional',
            'insurance' => '1',
            'name' => 'Ana Prueba',
            'email' => 'Ana@Ejemplo.mx',
            'phone' => '55 1234 5678',
            'address' => 'Av. Reforma 123, Col. Juárez, CP 06600, CDMX',
        ], '203.0.113.' . random_int(1, 254));

        self::assertSame([], $errors);
        self::assertInstanceOf(Order::class, $order);

        return $order;
    }

    private function payment(Order $order, string $status, string $id = '1001', ?float $amount = null, string $currency = 'MXN'): PaymentSnapshot
    {
        return new PaymentSnapshot($id, $status, 'accredited', $order->folio, $amount ?? (float) $order->total, $currency, 'visa', null);
    }

    public function testElPedidoGuardaElTotalCalculadoPorElServidor(): void
    {
        $order = $this->placeOrder();

        // (250 + 450 + 300) × 2 + 680 + 190
        self::assertSame(2870, $order->total);
        self::assertSame(OrderStatus::Pending, $order->status);
        self::assertSame('ana@ejemplo.mx', $order->customerEmail);
        self::assertMatchesRegularExpression('/^HF-\d{6}$/', $order->folio);
        self::assertSame(64, strlen($order->accessToken));
    }

    public function testDatosInvalidosNoCreanPedido(): void
    {
        [$order, $errors] = $this->checkout->placeOrder([
            'cart' => json_encode([['removal' => 'ninguna', 'extras' => [], 'qty' => 1]]),
            'delivery' => 'cdmx',
            'name' => 'A',
            'email' => 'no-es-correo',
            'phone' => '123',
            'address' => '',
        ], '203.0.113.9');

        self::assertNull($order);
        self::assertSame(['name', 'email', 'phone', 'address'], array_keys($errors));
    }

    public function testRecogerEnTallerNoExigeDireccion(): void
    {
        $order = $this->placeOrder(['delivery' => 'taller', 'insurance' => '1', 'address' => '']);

        self::assertNull($order->customerAddress);
        self::assertSame(0, $order->insuranceAmount);
    }

    public function testLimiteDePedidosPorIp(): void
    {
        $input = [
            'cart' => json_encode([['removal' => 'ninguna', 'extras' => [], 'qty' => 1]]),
            'delivery' => 'taller',
            'name' => 'Ana Prueba',
            'email' => 'ana@ejemplo.mx',
            'phone' => '5512345678',
        ];
        for ($i = 0; $i < 8; $i++) {
            self::assertNotNull($this->checkout->placeOrder($input, '198.51.100.7')[0]);
        }

        [$order, $errors] = $this->checkout->placeOrder($input, '198.51.100.7');
        self::assertNull($order);
        self::assertArrayHasKey('form', $errors);
    }

    public function testPagoAprobadoAvisaAClienteYTallerUnaSolaVez(): void
    {
        $order = $this->placeOrder();
        $payment = $this->payment($order, 'approved');

        // Llega por el webhook, por el regreso del cliente y por un reintento.
        $this->sync->apply($payment);
        $this->sync->apply($payment);
        $updated = $this->sync->apply($payment);

        self::assertSame(OrderStatus::Approved, $updated->status);
        self::assertNotNull($updated->paidAt);
        self::assertCount(2, $this->mailer->sent);
        self::assertSame(['ana@ejemplo.mx'], $this->mailer->sent[0]->to);
        self::assertStringContainsString($order->folio, $this->mailer->sent[0]->subject);
        self::assertSame('hola@ejemplo.mx', $this->mailer->sent[0]->replyTo);
        self::assertSame(['taller@ejemplo.mx'], $this->mailer->sent[1]->to);
        self::assertSame('ana@ejemplo.mx', $this->mailer->sent[1]->replyTo);
        self::assertStringContainsString('$2,870', $this->mailer->sent[1]->html);
    }

    public function testPagoPendienteNoEnviaCorreos(): void
    {
        $order = $this->placeOrder();
        $updated = $this->sync->apply($this->payment($order, 'pending'));

        self::assertSame(OrderStatus::Pending, $updated->status);
        self::assertSame([], $this->mailer->sent);
    }

    public function testMontoDistintoNoMarcaElPedidoComoPagado(): void
    {
        $order = $this->placeOrder();
        $updated = $this->sync->apply($this->payment($order, 'approved', amount: 1.0));

        self::assertSame(OrderStatus::Pending, $updated->status);
        self::assertSame([], $this->mailer->sent);
    }

    public function testMonedaDistintaNoMarcaElPedidoComoPagado(): void
    {
        $order = $this->placeOrder();
        $updated = $this->sync->apply($this->payment($order, 'approved', currency: 'USD'));

        self::assertSame(OrderStatus::Pending, $updated->status);
    }

    public function testUnPagoDeOtroPedidoSeIgnoraEnLaPaginaDeResultado(): void
    {
        $mine = $this->placeOrder();
        $other = $this->placeOrder();

        self::assertNull($this->sync->apply($this->payment($other, 'approved'), $mine->folio));
        self::assertSame(OrderStatus::Pending, $this->orders->findById($other->id)->status);
    }

    public function testUnRechazoTardioNoDeshaceUnPagoAprobado(): void
    {
        $order = $this->placeOrder();
        $this->sync->apply($this->payment($order, 'approved', id: '2002'));
        $after = $this->sync->apply($this->payment($order, 'rejected', id: '2001'));

        self::assertSame(OrderStatus::Approved, $after->status);
        self::assertSame('2002', $after->mpPaymentId);
    }

    public function testElReembolsoDelMismoPagoSiSeRefleja(): void
    {
        $order = $this->placeOrder();
        $this->sync->apply($this->payment($order, 'approved', id: '3003'));
        $after = $this->sync->apply($this->payment($order, 'refunded', id: '3003'));

        self::assertSame(OrderStatus::Refunded, $after->status);
    }

    public function testSiFallaElCorreoSeReintentaConElSiguienteAviso(): void
    {
        $order = $this->placeOrder();
        $payment = $this->payment($order, 'approved');

        $this->mailer->failNext = 2;
        $this->sync->apply($payment);
        self::assertSame([], $this->mailer->sent);

        $this->sync->apply($payment);
        self::assertCount(2, $this->mailer->sent);
    }

    public function testBricksCobraElMontoDelPedidoYNoElDelNavegador(): void
    {
        $order = $this->placeOrder();
        $this->gateway->nextStatus = 'approved';

        $this->checkout->payWithBricks($order, [
            'token' => 'tok_123',
            'payment_method_id' => 'visa',
            'installments' => 12,
            'transaction_amount' => 1,
            'external_reference' => 'HF-000000',
            'payer' => ['email' => 'ana@ejemplo.mx'],
        ]);

        $call = $this->gateway->paymentCalls[0];
        self::assertArrayNotHasKey('transaction_amount', $call['data']);
        self::assertArrayNotHasKey('external_reference', $call['data']);
        self::assertSame(1, $call['data']['installments'], 'Las cuotas se limitan a la configuración.');
        self::assertSame($order->folio . '-1', $call['idempotencyKey']);
        self::assertSame(OrderStatus::Approved, $this->orders->findById($order->id)->status);
    }

    public function testBricksSinCorreoDelPagadorUsaElDelPedido(): void
    {
        $order = $this->placeOrder();
        $this->checkout->payWithBricks($order, ['payment_method_id' => 'oxxo', 'payer' => []]);

        self::assertSame('ana@ejemplo.mx', $this->gateway->paymentCalls[0]['data']['payer']['email']);
    }

    public function testBricksLimitaLosIntentos(): void
    {
        $order = $this->placeOrder();
        $this->gateway->nextStatus = 'rejected';
        for ($i = 0; $i < 6; $i++) {
            $this->checkout->payWithBricks($this->orders->findById($order->id), ['token' => 't']);
        }

        $this->expectException(CheckoutException::class);
        $this->checkout->payWithBricks($this->orders->findById($order->id), ['token' => 't']);
    }

    public function testNoSeCobraUnPedidoYaPagado(): void
    {
        $order = $this->placeOrder();
        $this->sync->apply($this->payment($order, 'approved'));

        $this->expectException(CheckoutException::class);
        $this->checkout->payWithBricks($this->orders->findById($order->id), ['token' => 't']);
    }

    public function testLaPreferenciaSeCreaUnaSolaVez(): void
    {
        $order = $this->placeOrder();
        $first = $this->checkout->prepareCheckout($order);
        $second = $this->checkout->prepareCheckout($first);

        self::assertSame(1, $this->gateway->checkoutCalls);
        self::assertSame('pref-1', $second->mpPreferenceId);
    }
}

final class FakeGateway implements PaymentGateway
{
    public int $checkoutCalls = 0;
    /** @var list<array{data: array<string, mixed>, idempotencyKey: string}> */
    public array $paymentCalls = [];
    public string $nextStatus = 'approved';

    public function createCheckout(Order $order): CheckoutSession
    {
        $this->checkoutCalls++;

        return new CheckoutSession('pref-' . $this->checkoutCalls, 'https://mp.test/pay/' . $this->checkoutCalls);
    }

    public function createPayment(Order $order, array $paymentData, string $idempotencyKey): PaymentSnapshot
    {
        $this->paymentCalls[] = ['data' => $paymentData, 'idempotencyKey' => $idempotencyKey];

        // Igual que Mercado Pago real: cobra lo que fija el gateway (el total del pedido).
        return new PaymentSnapshot((string) (5000 + count($this->paymentCalls)), $this->nextStatus, null, $order->folio, (float) $order->total, 'MXN', 'visa', null);
    }

    public function getPayment(string $paymentId): PaymentSnapshot
    {
        throw new \LogicException('No se usa en estas pruebas.');
    }
}

final class RecordingMailer implements Mailer
{
    /** @var list<Email> */
    public array $sent = [];
    public int $failNext = 0;

    public function send(Email $email): void
    {
        if ($this->failNext > 0) {
            $this->failNext--;
            throw new MailException('SMTP caído (simulado)');
        }
        $this->sent[] = $email;
    }
}
