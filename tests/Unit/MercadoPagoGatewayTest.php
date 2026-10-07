<?php

declare(strict_types=1);

namespace FiloAcademia\Tests\Unit;

use FiloAcademia\Order\Order;
use FiloAcademia\Order\OrderStatus;
use FiloAcademia\Payment\Http\HttpTransport;
use FiloAcademia\Payment\MercadoPago\MercadoPagoGateway;
use FiloAcademia\Payment\PaymentGatewayException;
use FiloAcademia\Support\Urls;
use PHPUnit\Framework\TestCase;

final class MercadoPagoGatewayTest extends TestCase
{
    private function order(): Order
    {
        return new Order(
            id: 7, folio: 'HF-123456', accessToken: str_repeat('a', 64), status: OrderStatus::Pending,
            customerName: 'Ana Prueba', customerEmail: 'ana@ejemplo.mx', customerPhone: '5512345678',
            customerAddress: 'Calle 1', customerNotes: null,
            lines: [
                ['removal' => 'remocion-2mm', 'extras' => ['punta'], 'name' => 'Afilado + Remoción de 2 mm + Punta rota o doblada', 'unit_price' => 1000, 'qty' => 2],
                ['removal' => 'ninguna', 'extras' => [], 'name' => 'Afilado', 'unit_price' => 250, 'qty' => 1],
            ],
            deliveryId: 'nacional', deliveryName: 'Paquetería fuera de CDMX', servicesAmount: 2250,
            deliveryAmount: 680, insuranceAmount: 190, total: 3120, currency: 'MXN', checkoutMode: 'pro',
            mpPreferenceId: null, mpInitPoint: null, mpPaymentId: null, mpStatus: null, mpStatusDetail: null,
            mpPaymentMethod: null, mpTicketUrl: null, paymentAttempts: 0, paidAt: null, createdAt: '2026-10-07 10:00:00',
        );
    }

    private function gateway(RecordingTransport $transport, string $url = 'https://filoacademia.mx'): MercadoPagoGateway
    {
        return new MercadoPagoGateway($transport, 'TEST-token', new Urls($url), 'HERO FILO ACADEMIA', 1);
    }

    public function testLosConceptosDeLaPreferenciaSumanElTotalDelPedido(): void
    {
        $transport = new RecordingTransport(['status' => 201, 'body' => '{"id":"pref-1","init_point":"https://mp/p"}']);
        $session = $this->gateway($transport)->createCheckout($this->order());

        $payload = json_decode($transport->requests[0]['body'], true);
        $sum = array_sum(array_map(static fn (array $item): float => $item['unit_price'] * $item['quantity'], $payload['items']));

        self::assertSame(3120.0, $sum);
        self::assertSame('HF-123456', $payload['external_reference']);
        self::assertSame('approved', $payload['auto_return']);
        // El webhook es el del panel (firmado); notification_url lo anularía.
        self::assertArrayNotHasKey('notification_url', $payload);
        self::assertStringStartsWith('https://filoacademia.mx/pagar/resultado?folio=HF-123456&t=', $payload['back_urls']['success']);
        self::assertSame('Bearer TEST-token', $transport->requests[0]['headers']['Authorization']);
        self::assertSame('pref-1', $session->preferenceId);
    }

    public function testEnLocalNoSeEnviaAutoReturn(): void
    {
        $transport = new RecordingTransport(['status' => 201, 'body' => '{"id":"pref-1","init_point":"https://mp/p"}']);
        $this->gateway($transport, 'http://localhost:8000')->createCheckout($this->order());

        $payload = json_decode($transport->requests[0]['body'], true);
        self::assertArrayNotHasKey('auto_return', $payload);
    }

    public function testElCobroLlevaMontoDelPedidoEIdempotencia(): void
    {
        $transport = new RecordingTransport(['status' => 201, 'body' => '{"id":555,"status":"approved","external_reference":"HF-123456","transaction_amount":3120,"currency_id":"MXN"}']);
        $payment = $this->gateway($transport)->createPayment($this->order(), ['token' => 't', 'transaction_amount' => 1], 'HF-123456-1');

        $payload = json_decode($transport->requests[0]['body'], true);
        self::assertSame(3120, (int) $payload['transaction_amount']);
        self::assertSame('HF-123456-1', $transport->requests[0]['headers']['X-Idempotency-Key']);
        self::assertArrayNotHasKey('notification_url', $payload);
        self::assertSame('555', $payment->id);
    }

    public function testErrorDeMercadoPagoSeConvierteEnExcepcion(): void
    {
        $transport = new RecordingTransport(['status' => 400, 'body' => '{"message":"invalid token"}']);

        try {
            $this->gateway($transport)->getPayment('123');
            self::fail('Debió lanzar excepción.');
        } catch (PaymentGatewayException $exception) {
            self::assertSame(400, $exception->httpStatus);
            self::assertStringContainsString('invalid token', $exception->getMessage());
        }
    }

    public function testLaBusquedaPorFolioDescartaCoincidenciasInexactas(): void
    {
        $transport = new RecordingTransport(['status' => 200, 'body' => json_encode(['results' => [
            ['id' => 1, 'status' => 'rejected', 'external_reference' => 'HF-123456', 'transaction_amount' => 3120, 'currency_id' => 'MXN'],
            ['id' => 2, 'status' => 'approved', 'external_reference' => 'HF-1234567', 'transaction_amount' => 1, 'currency_id' => 'MXN'],
            ['id' => 3, 'status' => 'approved', 'external_reference' => 'HF-123456', 'transaction_amount' => 3120, 'currency_id' => 'MXN'],
        ]])]);
        $payments = $this->gateway($transport)->findPaymentsByReference('HF-123456');

        self::assertSame(['1', '3'], array_map(static fn ($p) => $p->id, $payments));
        self::assertStringContainsString('/v1/payments/search?external_reference=HF-123456', $transport->requests[0]['url']);
    }

    public function testRechazaIdentificadoresDePagoNoNumericos(): void
    {
        $this->expectException(PaymentGatewayException::class);
        $this->gateway(new RecordingTransport(['status' => 200, 'body' => '{}']))->getPayment('../v1/users/me');
    }

    public function testSinTokenNoSeHacePeticion(): void
    {
        $transport = new RecordingTransport(['status' => 200, 'body' => '{}']);
        $gateway = new MercadoPagoGateway($transport, '', new Urls('https://filoacademia.mx'), 'X', 1);

        try {
            $gateway->getPayment('1');
            self::fail('Debió lanzar excepción.');
        } catch (PaymentGatewayException) {
            self::assertSame([], $transport->requests);
        }
    }
}

final class RecordingTransport implements HttpTransport
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $requests = [];

    /** @param array{status: int, body: string} $response */
    public function __construct(private readonly array $response)
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body): array
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        return $this->response;
    }
}
