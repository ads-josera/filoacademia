/**
 * Carrito en el navegador (localStorage) y cálculo de precios para mostrar.
 *
 * Los precios aquí son SOLO de presentación: el servidor recalcula todo con
 * config/catalog.php al crear el pedido. Si cambias una regla de precio en
 * src/Catalog/QuoteCalculator.php, refléjala en lineFromSelection().
 */

const KEY = 'filo.cart.v1';
const EVENT = 'cart:change';

const empty = () => ({ lines: [], delivery: null, insurance: false });

/** localStorage puede no existir (modo privado, bloqueado): el sitio sigue funcionando en memoria. */
let memory = empty();

export function readCart() {
  try {
    const raw = window.localStorage.getItem(KEY);
    if (!raw) return memory;
    const data = JSON.parse(raw);
    if (!data || !Array.isArray(data.lines)) return empty();
    return {
      lines: data.lines.filter((l) => l && typeof l.removal === 'string' && Array.isArray(l.extras) && Number.isInteger(l.qty)),
      delivery: typeof data.delivery === 'string' ? data.delivery : null,
      insurance: data.insurance === true,
    };
  } catch {
    return memory;
  }
}

function writeCart(cart) {
  memory = cart;
  try {
    window.localStorage.setItem(KEY, JSON.stringify(cart));
  } catch {
    /* sin almacenamiento: queda en memoria para esta página */
  }
  window.dispatchEvent(new CustomEvent(EVENT, { detail: cart }));
}

export function onCartChange(callback) {
  window.addEventListener(EVENT, (event) => callback(event.detail));
  // Cambios hechos en otra pestaña.
  window.addEventListener('storage', (event) => {
    if (event.key === KEY) callback(readCart());
  });
}

export function addLine(line) {
  const cart = readCart();
  cart.lines.push(line);
  writeCart(cart);
}

export function removeLine(index) {
  const cart = readCart();
  cart.lines.splice(index, 1);
  writeCart(cart);
}

export function setPreferences(delivery, insurance) {
  const cart = readCart();
  writeCart({ ...cart, delivery, insurance });
}

export function clearCart() {
  writeCart(empty());
}

/** Catálogo que el servidor incrusta en la página (#catalog-data). */
export function readCatalog() {
  const node = document.getElementById('catalog-data');
  if (!node) return null;
  try {
    return JSON.parse(node.textContent);
  } catch {
    return null;
  }
}

/**
 * Línea de carrito a partir de lo elegido. Guarda nombre y precio unitario
 * para poder pintar el carrito en páginas sin catálogo.
 */
export function lineFromSelection(catalog, removalId, extraIds, qty) {
  const removal = catalog.removal.find((o) => o.id === removalId) ?? catalog.removal[0];
  const extras = catalog.extras.filter((e) => extraIds.includes(e.id));
  const unitPrice = catalog.base.price + removal.price + extras.reduce((sum, e) => sum + e.price, 0);
  const name = [catalog.base.short, removal.price > 0 ? removal.name : null, ...extras.map((e) => e.name)]
    .filter(Boolean)
    .join(' + ');

  return { removal: removal.id, extras: extras.map((e) => e.id), qty, name, unitPrice };
}

export function deliveryOption(catalog, id) {
  return catalog.delivery.find((o) => o.id === id && o.available) ?? null;
}

export function linesTotal(lines) {
  return lines.reduce((sum, line) => sum + line.unitPrice * line.qty, 0);
}

const formatter = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 0 });
export const money = (amount) => `$${formatter.format(amount)}`;

/** Solo lo que el servidor necesita: identificadores y cantidad. */
export function serializeForServer(cart) {
  return JSON.stringify(cart.lines.map(({ removal, extras, qty }) => ({ removal, extras, qty })));
}
