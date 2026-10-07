<?php

declare(strict_types=1);

namespace FiloAcademia\Payment;

/**
 * Fallo al hablar con el proveedor de pagos. El mensaje es técnico (va al
 * log); al cliente se le muestra un texto genérico.
 */
final class PaymentGatewayException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
