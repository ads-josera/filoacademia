<?php

declare(strict_types=1);

namespace FiloAcademia\Mail;

final class Email
{
    /**
     * @param list<string> $to
     * @param array<string, string> $inlineImages cid => ruta del archivo. El HTML
     *        las usa como <img src="cid:logo">: viajan dentro del correo y no
     *        dependen de que el sitio esté en línea ni de que el cliente de
     *        correo descargue imágenes remotas.
     */
    public function __construct(
        public readonly array $to,
        public readonly string $subject,
        public readonly string $html,
        public readonly string $text,
        public readonly ?string $replyTo = null,
        public readonly array $inlineImages = [],
    ) {
    }
}
