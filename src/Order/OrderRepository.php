<?php

declare(strict_types=1);

namespace FiloAcademia\Order;

use FiloAcademia\Catalog\Quote;
use FiloAcademia\Catalog\QuoteLine;
use PDO;

/**
 * Lectura y escritura de pedidos.
 *
 * Las escrituras que deben ocurrir UNA sola vez (avisos por correo) se hacen
 * con un UPDATE condicional: si dos procesos llegan a la vez (el webhook y el
 * regreso del cliente), solo uno gana.
 */
final class OrderRepository
{
    public function __construct(private readonly PDO $pdo, private readonly \Closure $clock)
    {
    }

    public function create(Quote $quote, CustomerData $customer, string $checkoutMode, string $ipHash): Order
    {
        $now = $this->now();

        $statement = $this->pdo->prepare(
            'INSERT INTO orders (
                folio, access_token, status, customer_name, customer_email, customer_phone,
                customer_address, customer_notes, lines_json, delivery_id, delivery_name,
                services_amount, delivery_amount, insurance_amount, total, currency,
                checkout_mode, ip_hash, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        // El folio es corto para dictarlo por teléfono; por eso NO es la llave
        // de acceso: la página de resultado exige además el access_token.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $folio = 'HF-' . random_int(100000, 999999);
            try {
                $statement->execute([
                    $folio,
                    bin2hex(random_bytes(32)),
                    OrderStatus::Pending->value,
                    $customer->name,
                    $customer->email,
                    $customer->phone,
                    $quote->needsAddress ? $customer->address : null,
                    $customer->notes,
                    json_encode(
                        array_map(static fn (QuoteLine $line): array => $line->toArray(), $quote->lines),
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                    ),
                    $quote->deliveryId,
                    $quote->deliveryName,
                    $quote->servicesSubtotal(),
                    $quote->deliveryAmount,
                    $quote->insuranceAmount,
                    $quote->total(),
                    $quote->currency,
                    $checkoutMode,
                    $ipHash,
                    $now,
                    $now,
                ]);

                return $this->findById((int) $this->pdo->lastInsertId())
                    ?? throw new \RuntimeException('El pedido recién creado no se encontró.');
            } catch (\PDOException $exception) {
                if (!$this->isUniqueViolation($exception)) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('No se pudo generar un folio único.');
    }

    public function findById(int $id): ?Order
    {
        return $this->fetchOne('SELECT * FROM orders WHERE id = ?', [$id]);
    }

    public function findByFolio(string $folio): ?Order
    {
        return $this->fetchOne('SELECT * FROM orders WHERE folio = ?', [$folio]);
    }

    public function countRecentByIp(string $ipHash, int $seconds): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM orders WHERE ip_hash = ? AND created_at >= ?');
        $statement->execute([$ipHash, $this->now(-$seconds)]);

        return (int) $statement->fetchColumn();
    }

    public function savePreference(int $orderId, string $preferenceId, string $initPoint): void
    {
        $this->execute(
            'UPDATE orders SET mp_preference_id = ?, mp_init_point = ?, updated_at = ? WHERE id = ?',
            [$preferenceId, $initPoint, $this->now(), $orderId],
        );
    }

    /**
     * Reserva un intento de pago (Bricks). Devuelve el número de intento, que
     * forma parte de la llave de idempotencia, o null si se superó el límite.
     */
    public function reservePaymentAttempt(int $orderId, int $maxAttempts): ?int
    {
        $statement = $this->pdo->prepare(
            'UPDATE orders SET payment_attempts = payment_attempts + 1, updated_at = ?
             WHERE id = ? AND payment_attempts < ?'
        );
        $statement->execute([$this->now(), $orderId, $maxAttempts]);

        if ($statement->rowCount() !== 1) {
            return null;
        }

        return $this->findById($orderId)?->paymentAttempts;
    }

    public function applyPayment(int $orderId, PaymentSnapshot $payment): void
    {
        $status = OrderStatus::fromMercadoPago($payment->status);

        $this->execute(
            'UPDATE orders SET
                status = ?, mp_payment_id = ?, mp_status = ?, mp_status_detail = ?,
                mp_payment_method = ?, mp_ticket_url = COALESCE(?, mp_ticket_url),
                paid_at = COALESCE(paid_at, ?),
                updated_at = ?
             WHERE id = ?',
            [
                $status->value,
                $payment->id,
                $payment->status,
                $payment->statusDetail,
                $payment->paymentMethodId,
                $payment->ticketUrl,
                // Se decide en PHP: execute() envía todo como texto y en SQLite
                // la comparación '1' = 1 es falsa (paid_at nunca se guardaba).
                $status === OrderStatus::Approved ? $this->now() : null,
                $this->now(),
                $orderId,
            ],
        );
    }

    /**
     * Marca un aviso como enviado solo si nadie lo marcó antes.
     *
     * @param 'customer'|'admin' $recipient
     * @return bool true si este proceso ganó el derecho a enviar el aviso.
     */
    public function claimNotification(int $orderId, string $recipient): bool
    {
        $column = $this->notificationColumn($recipient);
        $statement = $this->pdo->prepare(
            "UPDATE orders SET {$column} = ? WHERE id = ? AND {$column} IS NULL"
        );
        $statement->execute([$this->now(), $orderId]);

        return $statement->rowCount() === 1;
    }

    /**
     * Libera la marca si el envío falló, para que el siguiente webhook lo reintente.
     *
     * @param 'customer'|'admin' $recipient
     */
    public function releaseNotification(int $orderId, string $recipient): void
    {
        $column = $this->notificationColumn($recipient);
        $this->execute("UPDATE orders SET {$column} = NULL WHERE id = ?", [$orderId]);
    }

    public function logWebhook(
        ?string $requestId,
        ?string $eventType,
        ?string $resourceId,
        bool $signatureValid,
        string $result,
    ): void {
        $this->execute(
            'INSERT INTO webhook_events (request_id, event_type, resource_id, signature_valid, result, created_at)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $requestId !== null ? mb_substr($requestId, 0, 80) : null,
                $eventType !== null ? mb_substr($eventType, 0, 40) : null,
                $resourceId !== null ? mb_substr($resourceId, 0, 40) : null,
                $signatureValid ? 1 : 0,
                mb_substr($result, 0, 255),
                $this->now(),
            ],
        );
    }

    private function notificationColumn(string $recipient): string
    {
        return match ($recipient) {
            'customer' => 'customer_notified_at',
            'admin' => 'admin_notified_at',
        };
    }

    /** @param list<mixed> $params */
    private function fetchOne(string $sql, array $params): ?Order
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return $row === false ? null : Order::fromRow($row);
    }

    /** @param list<mixed> $params */
    private function execute(string $sql, array $params): void
    {
        $this->pdo->prepare($sql)->execute($params);
    }

    private function now(int $offsetSeconds = 0): string
    {
        /** @var \DateTimeImmutable $now */
        $now = ($this->clock)();

        return $now->modify(sprintf('%+d seconds', $offsetSeconds))->format('Y-m-d H:i:s');
    }

    private function isUniqueViolation(\PDOException $exception): bool
    {
        // SQLite: 23000 / "UNIQUE constraint failed"; MySQL: 23000 / 1062.
        return $exception->getCode() === '23000' || str_contains($exception->getMessage(), 'UNIQUE');
    }
}
