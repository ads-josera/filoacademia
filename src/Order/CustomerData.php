<?php

declare(strict_types=1);

namespace FiloAcademia\Order;

/**
 * Datos de contacto del cliente, ya validados y normalizados.
 */
final class CustomerData
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
        public readonly ?string $address,
        public readonly ?string $notes,
    ) {
    }

    /**
     * @param array<string, mixed> $input Datos del formulario sin procesar.
     * @param bool $needsAddress La forma de entrega elegida exige dirección.
     * @return array{0: ?self, 1: array<string, string>} [datos, errores por campo]
     */
    public static function fromInput(array $input, bool $needsAddress): array
    {
        $clean = static fn (string $key): string => trim(preg_replace('/\s+/u', ' ', (string) ($input[$key] ?? '')) ?? '');

        $name = $clean('name');
        $email = mb_strtolower($clean('email'));
        $phone = $clean('phone');
        $address = trim((string) ($input['address'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));

        $errors = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors['name'] = 'Escribe tu nombre.';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 190) {
            $errors['email'] = 'Escribe un correo válido: ahí te llega la confirmación del pago.';
        }
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 10 || strlen($digits) > 13) {
            $errors['phone'] = 'Escribe un WhatsApp de 10 dígitos.';
        }
        if ($needsAddress && mb_strlen($address) < 10) {
            $errors['address'] = 'Escribe la dirección completa: calle, número, colonia, CP y ciudad.';
        }
        if (mb_strlen($address) > 500) {
            $errors['address'] = 'La dirección es demasiado larga (máx. 500 caracteres).';
        }
        if (mb_strlen($notes) > 500) {
            $errors['notes'] = 'Las notas son demasiado largas (máx. 500 caracteres).';
        }

        if ($errors !== []) {
            return [null, $errors];
        }

        return [new self($name, $email, $phone, $address !== '' ? $address : null, $notes !== '' ? $notes : null), []];
    }
}
