<?php

declare(strict_types=1);

namespace FiloAcademia\Payment;

/**
 * Regla de negocio que impide cobrar. El mensaje es apto para el cliente.
 */
final class CheckoutException extends \DomainException
{
}
