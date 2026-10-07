/**
 * Cotizador de la página de inicio.
 */
import { readCatalog, lineFromSelection, deliveryOption, addLine, setPreferences, money } from './cart-store.js';

const form = document.querySelector('[data-quote]');
const catalog = readCatalog();

if (form && catalog) {
  const qtyOutput = form.querySelector('[data-qty]');
  const stepButtons = form.querySelectorAll('[data-qty-step]');
  const insurance = form.querySelector('[data-insurance]');
  const insuranceHint = form.querySelector('[data-insurance-hint]');
  const totalNode = form.querySelector('[data-quote-total]');
  const breakdownNode = form.querySelector('[data-quote-breakdown]');
  const feedback = form.querySelector('[data-quote-feedback]');
  const max = catalog.max_knives_per_line;
  let qty = 1;

  const selection = () => {
    const data = new FormData(form);
    return {
      removal: data.get('removal') ?? 'ninguna',
      extras: data.getAll('extras'),
      delivery: deliveryOption(catalog, data.get('delivery')),
    };
  };

  const update = () => {
    const { removal, extras, delivery } = selection();
    const line = lineFromSelection(catalog, removal, extras, qty);

    // El seguro solo existe con paquetería.
    const insurable = delivery?.insurable === true;
    insurance.disabled = !insurable;
    if (!insurable) insurance.checked = false;
    insuranceHint.hidden = insurable;

    const deliveryPrice = delivery?.price ?? 0;
    const insurancePrice = insurance.checked ? catalog.insurance.price : 0;
    const total = line.unitPrice * qty + deliveryPrice + insurancePrice;

    totalNode.textContent = money(total);
    const parts = [`${qty} × ${money(line.unitPrice)}`];
    parts.push(deliveryPrice === 0 ? 'entrega sin costo' : `entrega ${money(deliveryPrice)}`);
    if (insurancePrice) parts.push(`seguro ${money(insurancePrice)}`);
    breakdownNode.textContent = parts.join(' + ');

    qtyOutput.textContent = String(qty);
    stepButtons.forEach((button) => {
      const step = Number(button.dataset.qtyStep);
      button.disabled = (step < 0 && qty <= 1) || (step > 0 && qty >= max);
    });
  };

  stepButtons.forEach((button) =>
    button.addEventListener('click', () => {
      qty = Math.min(max, Math.max(1, qty + Number(button.dataset.qtyStep)));
      update();
    }),
  );

  form.addEventListener('change', () => {
    feedback.textContent = '';
    update();
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const { removal, extras, delivery } = selection();
    addLine(lineFromSelection(catalog, removal, extras, qty));
    setPreferences(delivery?.id ?? null, insurance.checked);
    feedback.textContent = `Agregado al carrito: ${qty} ${qty === 1 ? 'cuchillo' : 'cuchillos'}.`;
    // site.js escucha este evento y abre el carrito (no se importa site.js aquí:
    // el layout lo carga con ?v= y un segundo import lo ejecutaría dos veces).
    window.dispatchEvent(new CustomEvent('cart:open'));
  });

  update();
}
