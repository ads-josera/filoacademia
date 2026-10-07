<?php

declare(strict_types=1);

namespace FiloAcademia\Payment;

use FiloAcademia\Order\Order;
use FiloAcademia\Order\PaymentSnapshot;

/**
 * Contrato con el proveedor de pagos.
 *
 * El resto de la aplicación habla con esta interfaz, no con Mercado Pago: si
 * mañana se agrega o cambia el proveedor, solo cambia la implementación.
 */
interface PaymentGateway
{
    /**
     * Prepara el cobro del pedido en el proveedor (Checkout Pro: preferencia).
     *
     * @throws PaymentGatewayException
     */
    public function createCheckout(Order $order): CheckoutSession;

    /**
     * Cobra directamente con los datos que entregó el formulario embebido (Bricks).
     *
     * @param array<string, mixed> $paymentData Datos del formulario ya filtrados.
     * @throws PaymentGatewayException
     */
    public function createPayment(Order $order, array $paymentData, string $idempotencyKey): PaymentSnapshot;

    /** @throws PaymentGatewayException */
    public function getPayment(string $paymentId): PaymentSnapshot;

    /**
     * Pagos asociados a un folio, del más antiguo al más reciente. Respaldo para
     * cuando no llegó el webhook ni el cliente volvió al sitio.
     *
     * @return list<PaymentSnapshot>
     * @throws PaymentGatewayException
     */
    public function findPaymentsByReference(string $folio): array;
}
