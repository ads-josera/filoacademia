<?php

declare(strict_types=1);

namespace FiloAcademia\Mail;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Envío por SMTP autenticado (cuenta de correo de cPanel).
 *
 * Se prefiere SMTP sobre mail() porque llega mejor a la bandeja de entrada
 * (SPF/DKIM del dominio) y porque los errores se pueden detectar y reintentar.
 */
final class SmtpMailer implements Mailer
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption,
        private readonly string $username,
        private readonly string $password,
        private readonly string $fromEmail,
        private readonly string $fromName,
    ) {
    }

    public function send(Email $email): void
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->Port = $this->port;
            $mail->SMTPAuth = true;
            $mail->Username = $this->username;
            $mail->Password = $this->password;
            $mail->SMTPSecure = $this->encryption === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $mail->Timeout = 20;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;

            $mail->setFrom($this->fromEmail, $this->fromName);
            foreach ($email->to as $address) {
                $mail->addAddress($address);
            }
            if ($email->replyTo !== null) {
                $mail->addReplyTo($email->replyTo);
            }

            $mail->isHTML(true);
            $mail->Subject = $email->subject;
            $mail->Body = $email->html;
            $mail->AltBody = $email->text;

            $mail->send();
        } catch (PHPMailerException $exception) {
            throw new MailException('SMTP: ' . $mail->ErrorInfo, 0, $exception);
        }
    }
}
