/**
 * Paso 2 del pago en modo «bricks»: formulario de Mercado Pago embebido.
 *
 * El monto que se muestra viene del pedido guardado; el servidor lo vuelve a
 * fijar al cobrar (src/Payment/CheckoutService::payWithBricks).
 *
 * @see https://www.mercadopago.com.mx/developers/es/docs/checkout-bricks/payment-brick/default-rendering
 */

const SDK_URL = 'https://sdk.mercadopago.com/js/v2';
const SDK_TIMEOUT_MS = 15000;

const container = document.querySelector('[data-payment-brick]');
const loading = document.querySelector('[data-brick-loading]');
const errorBox = document.querySelector('[data-brick-error]');
const errorText = document.querySelector('[data-brick-error-text]');

function showError(message) {
  if (loading) loading.hidden = true;
  if (errorText && message) errorText.textContent = message;
  if (errorBox) {
    errorBox.hidden = false;
    errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
}

function loadSdk() {
  return new Promise((resolve, reject) => {
    if (window.MercadoPago) return resolve(window.MercadoPago);
    const script = document.createElement('script');
    script.src = SDK_URL;
    script.async = true;
    const timer = setTimeout(() => reject(new Error('timeout')), SDK_TIMEOUT_MS);
    script.onload = () => {
      clearTimeout(timer);
      resolve(window.MercadoPago);
    };
    script.onerror = () => {
      clearTimeout(timer);
      reject(new Error('load'));
    };
    document.head.append(script);
  });
}

async function submitPayment(formData) {
  const { endpoint, folio, token, csrf } = container.dataset;
  const response = await fetch(endpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
    credentials: 'same-origin',
    body: JSON.stringify({ folio, token, formData }),
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.error ?? 'No se pudo procesar el pago. No se te cobró.');
  return data;
}

async function init() {
  if (!container) return;
  const { publicKey, amount, preferenceId, email, maxInstallments } = container.dataset;

  if (!publicKey) {
    showError('El pago en línea no está disponible en este momento. Escríbenos por WhatsApp y lo resolvemos.');
    return;
  }

  let MercadoPago;
  try {
    MercadoPago = await loadSdk();
  } catch {
    showError('No se pudo cargar el formulario de Mercado Pago. Revisa tu conexión y recarga la página.');
    return;
  }

  const mp = new MercadoPago(publicKey, { locale: 'es-MX' });
  const paymentMethods = { creditCard: 'all', debitCard: 'all', ticket: 'all', maxInstallments: Number(maxInstallments) || 1 };
  if (preferenceId) paymentMethods.mercadoPago = 'all';

  await mp.bricks().create('payment', container.id, {
    initialization: {
      amount: Number(amount),
      ...(preferenceId ? { preferenceId } : {}),
      payer: { email },
    },
    customization: {
      paymentMethods,
      visual: { style: { theme: 'default' } },
    },
    callbacks: {
      onReady: () => {
        if (loading) loading.hidden = true;
      },
      onSubmit: ({ selectedPaymentMethod, formData }) => {
        // Pago con cuenta de Mercado Pago: el propio Brick redirige con la preferencia.
        if (selectedPaymentMethod === 'wallet_purchase') return Promise.resolve();

        if (errorBox) errorBox.hidden = true;
        return submitPayment(formData)
          .then((result) => {
            window.location.assign(result.redirect);
          })
          .catch((error) => {
            showError(error.message);
            // Rechazar le indica al Brick que el envío falló y reactiva el formulario.
            throw error;
          });
      },
      onError: (error) => {
        // Los errores de validación de campos los muestra el propio Brick.
        if (error?.type === 'critical') {
          showError('El formulario de pago tuvo un problema. Recarga la página e intenta de nuevo.');
        }
      },
    },
  });
}

init();
