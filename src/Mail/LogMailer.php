<?php

declare(strict_types=1);

namespace FiloAcademia\Mail;

/**
 * Para desarrollo: en lugar de enviar, guarda cada correo como .html en
 * storage/mail/ para poder abrirlo en el navegador y revisarlo.
 */
final class LogMailer implements Mailer
{
    public function __construct(private readonly string $directory)
    {
    }

    public function send(Email $email): void
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($email->subject)) ?? 'correo';
        $file = sprintf('%s/%s-%s-%s.html', $this->directory, date('Ymd-His'), bin2hex(random_bytes(3)), trim($slug, '-'));

        $header = sprintf(
            "<!--\nPara: %s\nResponder a: %s\nAsunto: %s\n-->\n",
            implode(', ', $email->to),
            $email->replyTo ?? '—',
            $email->subject,
        );

        // Las imágenes cid: se convierten en data: para que el .html se vea completo.
        $html = $email->html;
        foreach ($email->inlineImages as $cid => $path) {
            $data = 'data:' . (mime_content_type($path) ?: 'image/png') . ';base64,' . base64_encode((string) file_get_contents($path));
            $html = str_replace('cid:' . $cid, $data, $html);
        }

        if (file_put_contents($file, $header . $html) === false) {
            throw new MailException('No se pudo escribir ' . $file);
        }
    }
}
