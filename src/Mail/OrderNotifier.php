<?php

declare(strict_types=1);

namespace FiloAcademia\Mail;

use FiloAcademia\Order\Order;
use FiloAcademia\Order\OrderRepository;
use FiloAcademia\Support\Logger;

/**
 * Avisos de pago aprobado: uno al cliente y otro al taller.
 *
 * Cada aviso se envía UNA sola vez aunque el pago se confirme por varias vías
 * (webhook, regreso del cliente, reintentos de Mercado Pago). Si el envío
 * falla, la marca se libera y el siguiente aviso de Mercado Pago lo reintenta.
 */
final class OrderNotifier
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly OrderRepository $orders,
        private readonly OrderEmails $emails,
        private readonly Logger $logger,
    ) {
    }

    public function notifyPaid(Order $order): void
    {
        $this->sendOnce($order, 'customer', fn (): Email => $this->emails->customerPaid($order));

        if ($this->emails->adminRecipients() === []) {
            $this->logger->warning('Sin destinatarios de administración: no se avisó del pago.', ['folio' => $order->folio]);

            return;
        }

        $this->sendOnce($order, 'admin', fn (): Email => $this->emails->adminPaid($order));
    }

    /** @param 'customer'|'admin' $recipient */
    private function sendOnce(Order $order, string $recipient, \Closure $build): void
    {
        if (!$this->orders->claimNotification($order->id, $recipient)) {
            return;
        }

        try {
            $this->mailer->send($build());
            $this->logger->info('Aviso de pago enviado.', ['folio' => $order->folio, 'to' => $recipient]);
        } catch (\Throwable $exception) {
            $this->orders->releaseNotification($order->id, $recipient);
            $this->logger->error('No se pudo enviar el aviso de pago.', [
                'folio' => $order->folio,
                'to' => $recipient,
                'error' => $exception,
            ]);
        }
    }
}
