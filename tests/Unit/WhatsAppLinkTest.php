<?php

declare(strict_types=1);

namespace FiloAcademia\Tests\Unit;

use FiloAcademia\Order\Order;
use FiloAcademia\Order\OrderStatus;
use FiloAcademia\Support\Urls;
use FiloAcademia\Support\View;
use PHPUnit\Framework\TestCase;

final class WhatsAppLinkTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->view = new View($root . '/templates', $root . '/public', new Urls('https://filoacademia.test'), [
            'business' => ['whatsapp' => '52 55 4258 8200'],
        ]);
    }

    private function order(): Order
    {
        return new Order(
            id: 1, folio: 'HF-481804', accessToken: str_repeat('a', 64), status: OrderStatus::Pending,
            customerName: 'Ana Prueba', customerEmail: 'ana@ejemplo.mx', customerPhone: '5512345678',
            customerAddress: 'Calle 1', customerNotes: null,
            lines: [['removal' => 'remocion-2mm', 'extras' => ['mellas'], 'name' => 'Afilado + Remoción de 2 mm + Mellas y muescas', 'unit_price' => 990, 'qty' => 3]],
            deliveryId: 'nacional', deliveryName: 'Paquetería fuera de CDMX', servicesAmount: 2970,
            deliveryAmount: 680, insuranceAmount: 190, total: 3840, currency: 'MXN', checkoutMode: 'pro',
            mpPreferenceId: null, mpInitPoint: null, mpPaymentId: null, mpStatus: null, mpStatusDetail: null,
            mpPaymentMethod: null, mpTicketUrl: null, paymentAttempts: 0, paidAt: null, createdAt: '2026-10-07 10:00:00',
        );
    }

    public function testElMensajeLlevaLosDatosDelPedido(): void
    {
        $message = $this->view->whatsappOrderMessage($this->order(), 'Hola, tengo un problema para pagar.');

        self::assertStringStartsWith('Hola, tengo un problema para pagar.', $message);
        self::assertStringContainsString('Pedido: HF-481804', $message);
        self::assertStringContainsString('Afilado + Remoción de 2 mm + Mellas y muescas × 3 — $2,970', $message);
        self::assertStringContainsString('Seguro de paquetería — $190', $message);
        self::assertStringContainsString('Total: $3,840 MXN', $message);
        self::assertStringContainsString('Nombre: Ana Prueba', $message);
        self::assertStringNotContainsString("\n\n\n", $message);
    }

    public function testElEnlaceAbreEnPestanaNuevaConElMensaje(): void
    {
        $html = $this->view->render('partials/whatsapp-link', ['label' => 'Escribir por WhatsApp', 'text' => 'Hola & adiós']);

        self::assertStringContainsString('target="_blank"', $html);
        self::assertStringContainsString('rel="noopener"', $html);
        self::assertStringContainsString('href="https://wa.me/525542588200?text=Hola%20%26%20adi%C3%B3s"', $html);
        self::assertStringContainsString('se abre en una pestaña nueva', $html);
    }

    public function testFormatosDeCorreo(): void
    {
        self::assertSame('Jose', $this->view->firstName('jose luis pérez'));
        self::assertSame('Ánimas', $this->view->firstName('  ánimas'));
        self::assertSame('Visa', $this->view->paymentMethodLabel('visa'));
        self::assertSame('Efectivo en OXXO', $this->view->paymentMethodLabel('oxxo'));
        self::assertSame('Bank Transfer', $this->view->paymentMethodLabel('bank_transfer'));
        self::assertSame('7 oct 2026, 11:00', $this->view->dateTime('2026-10-07 11:00:05'));
        self::assertSame('—', $this->view->dateTime(null));
        self::assertSame('https://www.google.com/maps/search/?api=1&query=Eje%201%20Norte%20%2356%2C%20CDMX', $this->view->mapsUrl("Eje 1 Norte #56,\n  CDMX"));
    }

    public function testSinMensajeEsElEnlaceSimple(): void
    {
        self::assertSame('https://wa.me/525542588200', $this->view->whatsappUrl());
        self::assertSame('https://wa.me/525542588200', $this->view->whatsappUrl('   '));
    }
}
