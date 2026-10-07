<?php

declare(strict_types=1);

namespace FiloAcademia\Mail;

use FiloAcademia\Order\Order;
use FiloAcademia\Order\OrderRepository;
use FiloAcademia\Support\Logger;
use FiloAcademia\Support\View;

/**
 * Avisos de pago aprobado: uno al cliente y otro al taller.
 *
 * Cada aviso se envía UNA sola vez aunque el pago se confirme por varias vías
 * (webhook, regreso del cliente, reintentos de Mercado Pago). Si el envío
 * falla, la marca se libera y el siguiente aviso de Mercado Pago lo reintenta.
 */
final class OrderNotifier
{
    /** @param list<string> $adminRecipients */
    public function __construct(
        private readonly Mailer $mailer,
        private readonly OrderRepository $orders,
        private readonly View $view,
        private readonly Logger $logger,
        private readonly array $adminRecipients,
        /** A dónde llegan las respuestas del cliente («Responde a este correo»). */
        private readonly ?string $customerReplyTo = null,
    ) {
    }

    public function notifyPaid(Order $order): void
    {
        $this->sendOnce($order, 'customer', fn (): Email => new Email(
            to: [$order->customerEmail],
            subject: sprintf('Pago recibido · pedido %s', $order->folio),
            html: $this->view->render('emails/customer-paid', ['order' => $order]),
            text: $this->view->render('emails/customer-paid-text', ['order' => $order]),
            replyTo: $this->customerReplyTo,
        ));

        if ($this->adminRecipients === []) {
            $this->logger->warning('Sin destinatarios de administración: no se avisó del pago.', ['folio' => $order->folio]);

            return;
        }

        $this->sendOnce($order, 'admin', fn (): Email => new Email(
            to: $this->adminRecipients,
            subject: sprintf('Nuevo pago · %s · %s · %s', $order->folio, $this->view->money($order->total), $order->customerName),
            html: $this->view->render('emails/admin-paid', ['order' => $order]),
            text: $this->view->render('emails/admin-paid-text', ['order' => $order]),
            replyTo: $order->customerEmail,
        ));
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
