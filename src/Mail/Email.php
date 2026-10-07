<?php

declare(strict_types=1);

namespace FiloAcademia\Mail;

final class Email
{
    /** @param list<string> $to */
    public function __construct(
        public readonly array $to,
        public readonly string $subject,
        public readonly string $html,
        public readonly string $text,
        public readonly ?string $replyTo = null,
    ) {
    }
}
