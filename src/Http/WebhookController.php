<?php

declare(strict_types=1);

namespace FiloAcademia\Http;

use FiloAcademia\App;
use FiloAcademia\Payment\PaymentGatewayException;

/**
 * Notificaciones de Mercado Pago (POST /webhooks/mercadopago.php).
 *
 * Códigos de respuesta y su efecto en Mercado Pago:
 *   200 → recibido; no reintenta.
 *   401 → firma inválida; se registra y se ignora.
 *   500 → fallo temporal (la API no respondió): Mercado Pago reintenta.
 *   Un pago inexistente (404 de la API) responde 200: reintentar no sirve.
 */
final class WebhookController
{
    public function __construct(private readonly App $app)
    {
    }

    public function mercadoPago(): never
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::text('Método no permitido.', 405);
        }

        $body = json_decode((string) file_get_contents('php://input'), true);
        $body = is_array($body) ? $body : [];

        // data.id y type llegan en la URL (y en el cuerpo). La firma se calcula
        // con el de la URL, como indica la documentación.
        $dataId = (string) ($_GET['data_id'] ?? $_GET['data.id'] ?? $_GET['id'] ?? ($body['data']['id'] ?? ''));
        $type = (string) ($_GET['type'] ?? $_GET['topic'] ?? ($body['type'] ?? ''));
        $requestId = (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
        $signature = (string) ($_SERVER['HTTP_X_SIGNATURE'] ?? '');

        $orders = $this->app->orders();
        $valid = $this->app->webhookSignature()->isValid($signature, $requestId, $dataId);

        if (!$valid) {
            $orders->logWebhook($requestId, $type, $dataId, false, 'firma inválida');
            $this->app->logger()->warning('Webhook con firma inválida.', ['type' => $type, 'data_id' => $dataId]);
            Response::text('Firma inválida.', 401);
        }

        if ($type !== 'payment' || !ctype_digit($dataId)) {
            $orders->logWebhook($requestId, $type, $dataId, true, 'ignorado: tipo no manejado');
            Response::text('OK');
        }

        try {
            $order = $this->app->paymentSync()->syncById($dataId);
        } catch (PaymentGatewayException $exception) {
            // 404: el pago no existe (p. ej. «Simular» del panel). Reintentar no
            // lo hará existir, así que se responde 200 para cortar los reintentos.
            if ($exception->httpStatus === 404) {
                $orders->logWebhook($requestId, $type, $dataId, true, 'pago inexistente en Mercado Pago');
                Response::text('OK');
            }
            $orders->logWebhook($requestId, $type, $dataId, true, 'error: ' . $exception->getMessage());
            $this->app->logger()->error('Webhook: no se pudo consultar el pago.', ['payment_id' => $dataId, 'error' => $exception]);
            Response::text('Reintentar.', 500);
        }

        $orders->logWebhook(
            $requestId,
            $type,
            $dataId,
            true,
            $order !== null ? sprintf('pedido %s → %s', $order->folio, $order->status->value) : 'pago sin pedido',
        );

        Response::text('OK');
    }
}
