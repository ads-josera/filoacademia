<?php

declare(strict_types=1);

namespace FiloAcademia\Mail;

interface Mailer
{
    /** @throws MailException Si el correo no se pudo entregar al servidor de salida. */
    public function send(Email $email): void;
}
