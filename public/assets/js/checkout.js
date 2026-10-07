/**
 * Paso 1 del pago (/pagar): resumen del carrito y envío del formulario.
 */
import { readCart, readCatalog, deliveryOption, setPreferences, onCartChange, serializeForServer, linesTotal, money } from './cart-store.js';

const form = document.querySelector('[data-checkout-form]');
const emptyState = document.querySelector('[data-checkout-empty]');
const catalog = readCatalog();

if (form && catalog) {
  const cartInput = form.querySelector('[data-checkout-cart]');
  const linesNode = form.querySelector('[data-checkout-lines]');
  const addressField = form.querySelector('[data-address-field]');
  const addressInput = addressField.querySelector('textarea');
  const insurance = form.querySelector('[data-insurance]');
  const insuranceStep = form.querySelector('[data-insurance-step]');
  const submit = form.querySelector('[data-checkout-submit]');
  const deliveryInputs = [...form.querySelectorAll('input[name="delivery"]')];

  // Si no hay forma elegida (primera visita), se usa la del cotizador.
  if (!deliveryInputs.some((input) => input.checked)) {
    const preferred = readCart().delivery;
    const match = deliveryInputs.find((input) => input.value === preferred) ?? deliveryInputs[0];
    match.checked = true;
    insurance.checked = readCart().insurance;
  }

  const render = () => {
    const cart = readCart();
    const isEmpty = cart.lines.length === 0;
    form.hidden = isEmpty;
    emptyState.hidden = !isEmpty;
    if (isEmpty) return;

    const deliveryInput = deliveryInputs.find((input) => input.checked);
    const delivery = deliveryOption(catalog, deliveryInput?.value);
    const insurable = delivery?.insurable === true;
    const needsAddress = delivery?.needs_address === true;

    insuranceStep.hidden = !insurable;
    if (!insurable) insurance.checked = false;
    addressField.hidden = !needsAddress;
    addressInput.required = needsAddress;

    linesNode.replaceChildren(
      ...cart.lines.map((line) => {
        const item = document.createElement('li');
        const name = document.createElement('span');
        name.textContent = `${line.name}\u00a0×\u00a0${line.qty}`;
        const amount = document.createElement('span');
        amount.textContent = money(line.unitPrice * line.qty);
        item.append(name, amount);
        return item;
      }),
    );

    const services = linesTotal(cart.lines);
    const deliveryPrice = delivery?.price ?? 0;
    const insurancePrice = insurance.checked ? catalog.insurance.price : 0;

    form.querySelector('[data-sum-services]').textContent = money(services);
    form.querySelector('[data-sum-delivery]').textContent = deliveryPrice === 0 ? 'Sin costo' : money(deliveryPrice);
    form.querySelector('[data-sum-insurance-row]').hidden = insurancePrice === 0;
    form.querySelector('[data-sum-insurance]').textContent = money(insurancePrice);
    form.querySelector('[data-sum-total]').textContent = `${money(services + deliveryPrice + insurancePrice)} MXN`;
  };

  form.addEventListener('change', () => {
    const delivery = deliveryInputs.find((input) => input.checked)?.value ?? null;
    setPreferences(delivery, insurance.checked);
  });
  onCartChange(render);

  form.addEventListener('submit', (event) => {
    const cart = readCart();
    if (cart.lines.length === 0) {
      event.preventDefault();
      render();
      return;
    }
    if (!form.checkValidity()) {
      event.preventDefault();
      form.reportValidity();
      return;
    }
    cartInput.value = serializeForServer(cart);
    // Evita el doble envío mientras el servidor crea el pedido.
    submit.disabled = true;
    submit.textContent = 'Preparando tu pago…';
  });

  // Al volver con «atrás», el navegador puede restaurar el botón desactivado.
  window.addEventListener('pageshow', () => {
    submit.disabled = false;
    submit.textContent = 'Continuar al pago';
  });

  render();
  document.querySelector('[data-error-summary]')?.focus();
}
