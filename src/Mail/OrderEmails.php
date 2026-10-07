<?php

declare(strict_types=1);

namespace FiloAcademia\Mail;

use FiloAcademia\Order\Order;
use FiloAcademia\Support\View;

/**
 * Arma los correos de un pedido. Único lugar donde se construyen: lo usan el
 * aviso automático (OrderNotifier) y la vista previa (bin/mail-preview.php),
 * así lo que se revisa es exactamente lo que se envía.
 */
final class OrderEmails
{
    /** @param list<string> $adminRecipients */
    public function __construct(
        private readonly View $view,
        private readonly string $assetsDir,
        private readonly array $adminRecipients,
        /** A dónde llegan las respuestas del cliente («Responde a este correo»). */
        private readonly ?string $customerReplyTo = null,
    ) {
    }

    /** @return list<string> */
    public function adminRecipients(): array
    {
        return $this->adminRecipients;
    }

    public function customerPaid(Order $order, string $subjectPrefix = ''): Email
    {
        return new Email(
            to: [$order->customerEmail],
            subject: $subjectPrefix . sprintf('Pago recibido · pedido %s', $order->folio),
            html: $this->view->render('emails/customer-paid', ['order' => $order]),
            text: $this->view->render('emails/customer-paid-text', ['order' => $order]),
            replyTo: $this->customerReplyTo,
            inlineImages: $this->images(),
        );
    }

    /** @param list<string>|null $to Destinatarios distintos a los configurados (vista previa). */
    public function adminPaid(Order $order, string $subjectPrefix = '', ?array $to = null): Email
    {
        return new Email(
            to: $to ?? $this->adminRecipients,
            subject: $subjectPrefix . sprintf('Nuevo pago · %s · %s · %s', $order->folio, $this->view->money($order->total), $order->customerName),
            html: $this->view->render('emails/admin-paid', ['order' => $order]),
            text: $this->view->render('emails/admin-paid-text', ['order' => $order]),
            replyTo: $order->customerEmail,
            inlineImages: $this->images(),
        );
    }

    /** @return array<string, string> */
    private function images(): array
    {
        return ['logo' => $this->assetsDir . '/logo.png'];
    }
}
