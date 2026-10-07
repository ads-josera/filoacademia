<?php

declare(strict_types=1);

namespace FiloAcademia\Http;

use FiloAcademia\App;
use FiloAcademia\Order\Order;
use FiloAcademia\Order\OrderStatus;
use FiloAcademia\Payment\CheckoutException;
use FiloAcademia\Payment\PaymentGatewayException;
use FiloAcademia\Support\Session;

/**
 * Las tres pantallas del pago y el endpoint de cobro de Bricks.
 *
 *   GET/POST /pagar/            datos del cliente → crea el pedido
 *   GET      /pagar/pedido      cobra el pedido (Checkout Pro o Bricks)
 *   GET      /pagar/resultado   estado del pago (aquí regresa Mercado Pago)
 *   POST     /api/pago          cobro con Bricks (JSON)
 *
 * Las pantallas de un pedido exigen folio + token: el folio solo no basta
 * para ver datos personales.
 */
final class CheckoutController
{
    public function __construct(private readonly App $app)
    {
        Session::start($app->isHttps());
    }

    public function form(): never
    {
        $errors = [];
        $old = [];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $old = array_map(static fn ($value): string => is_string($value) ? $value : '', $_POST);

            if (!Session::verifyCsrf($_POST['csrf'] ?? null)) {
                $errors['form'] = 'Tu sesión expiró. Revisa los datos y vuelve a enviar.';
            } else {
                [$order, $errors] = $this->app->checkout()->placeOrder($_POST, $this->app->clientIp());
                if ($order !== null) {
                    Response::redirect($this->app->urls()->payStep($order));
                }
            }
        }

        $catalog = $this->app->catalog();

        Response::html($this->app->view()->page('pages/pagar', [
            'title' => 'Datos de recolección · HERO Filo Academia',
            'description' => 'Completa tus datos para pagar tu servicio de afilado.',
            'active' => 'pagar',
            'noindex' => true,
            'catalogModel' => $catalog,
            'catalog' => $catalog->toPublicArray(),
            'scripts' => ['checkout'],
            'errors' => $errors,
            'old' => $old,
            'csrf' => Session::csrfToken(),
        ]), $errors === [] ? 200 : 422);
    }

    public function payStep(): never
    {
        $order = $this->orderFromQuery();

        // Ya pagado o en revisión: no se ofrece pagar otra vez.
        if (!$order->status->acceptsPayment() || ($order->status === OrderStatus::Pending && $order->mpTicketUrl !== null)) {
            Response::redirect($this->app->urls()->paymentResult($order, false));
        }

        $gatewayError = null;
        try {
            $order = $this->app->checkout()->prepareCheckout($order);
        } catch (PaymentGatewayException $exception) {
            $this->app->logger()->error('No se pudo preparar el cobro.', ['folio' => $order->folio, 'error' => $exception]);
            $gatewayError = 'gateway';
        }

        $mode = $this->app->checkout()->mode();

        Response::html($this->app->view()->page('pages/pedido', [
            'title' => 'Pago del pedido ' . $order->folio . ' · HERO Filo Academia',
            'description' => 'Paga tu pedido de forma segura con Mercado Pago.',
            'active' => 'pagar',
            'noindex' => true,
            'order' => $order,
            'mode' => $mode,
            'publicKey' => $this->app->config->string('mercadopago.public_key'),
            'maxInstallments' => max(1, (int) $this->app->config->get('mercadopago.max_installments', 1)),
            'csrf' => Session::csrfToken(),
            'gatewayError' => $gatewayError,
            'retry' => in_array($order->status, [OrderStatus::Rejected, OrderStatus::Cancelled], true),
            'scripts' => $mode === 'bricks' && $gatewayError === null ? ['bricks'] : [],
        ]));
    }

    public function result(): never
    {
        $order = $this->orderFromQuery();
        $syncFailed = false;

        // Mercado Pago añade payment_id al regresar. Se consulta su API (no se
        // cree el parámetro) y solo se acepta si el pago es de este pedido.
        $paymentId = (string) ($_GET['payment_id'] ?? $_GET['collection_id'] ?? '');
        if ($paymentId === '' && $order->mpPaymentId !== null && in_array($order->status, [OrderStatus::Pending, OrderStatus::InProcess], true)) {
            // Recarga mientras se espera (auto-actualización): se consulta de nuevo
            // el último pago. Los estados finales no se vuelven a consultar.
            $paymentId = $order->mpPaymentId;
        }

        if ($paymentId !== '' && ctype_digit($paymentId)) {
            try {
                $order = $this->app->paymentSync()->syncById($paymentId, $order->folio) ?? $order;
            } catch (PaymentGatewayException $exception) {
                $syncFailed = true;
                $this->app->logger()->error('No se pudo consultar el pago al volver.', [
                    'folio' => $order->folio,
                    'payment_id' => $paymentId,
                    'error' => $exception,
                ]);
            }
        }

        Response::html($this->app->view()->page('pages/resultado', [
            'title' => 'Pedido ' . $order->folio . ' · HERO Filo Academia',
            'description' => 'Estado del pago de tu pedido.',
            'active' => 'pagar',
            'noindex' => true,
            'order' => $order,
            'syncFailed' => $syncFailed,
            'scripts' => ['result'],
        ]));
    }

    public function bricksPayment(): never
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::json(['error' => 'Método no permitido.'], 405);
        }

        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input)) {
            Response::json(['error' => 'Solicitud no válida.'], 400);
        }

        if (!Session::verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            Response::json(['error' => 'Tu sesión expiró. Recarga la página e intenta de nuevo.'], 419);
        }

        $order = $this->app->orders()->findByFolio((string) ($input['folio'] ?? ''));
        if ($order === null || !$order->isAccessibleWith((string) ($input['token'] ?? ''))) {
            Response::json(['error' => 'No encontramos el pedido.'], 404);
        }

        $formData = is_array($input['formData'] ?? null) ? $input['formData'] : [];

        try {
            $payment = $this->app->checkout()->payWithBricks($order, $formData);
        } catch (CheckoutException $exception) {
            Response::json(['error' => $exception->getMessage()], 409);
        } catch (PaymentGatewayException $exception) {
            $this->app->logger()->error('Cobro con Bricks fallido.', [
                'folio' => $order->folio,
                'http_status' => $exception->httpStatus,
                'error' => $exception,
            ]);
            // 4xx de Mercado Pago = datos de pago rechazados (no es un fallo nuestro).
            $message = $exception->httpStatus !== null && $exception->httpStatus < 500
                ? 'Mercado Pago no aceptó los datos de pago. Revisa la información o usa otro medio. No se te cobró.'
                : 'No pudimos conectar con Mercado Pago. No se te cobró; intenta de nuevo en unos momentos.';
            Response::json(['error' => $message], 502);
        }

        Response::json([
            'status' => $payment->status,
            'redirect' => $this->app->urls()->paymentResult($order, false),
        ]);
    }

    private function orderFromQuery(): Order
    {
        $order = $this->app->orders()->findByFolio((string) ($_GET['folio'] ?? ''));

        if ($order === null || !$order->isAccessibleWith((string) ($_GET['t'] ?? ''))) {
            (new PageController($this->app))->error(
                404,
                'No encontramos tu pedido',
                'El enlace está incompleto o ya no es válido. Si pagaste, revisa el correo de confirmación o escríbenos con tu folio.',
            );
        }

        return $order;
    }
}
