/**
 * Comportamiento común a todas las páginas: menú móvil y carrito lateral.
 * Ambos son <dialog>: el navegador se encarga del foco, de Esc y de bloquear el fondo.
 */
import { readCart, removeLine, onCartChange, linesTotal, money } from './cart-store.js';

const menu = document.querySelector('[data-menu]');
const cartDialog = document.querySelector('[data-cart]');

/* ---------- Menú móvil ---------- */
document.querySelector('[data-menu-open]')?.addEventListener('click', () => menu?.showModal());
document.querySelector('[data-menu-close]')?.addEventListener('click', () => menu?.close());
menu?.querySelectorAll('[data-menu-link]').forEach((link) => link.addEventListener('click', () => menu.close()));

/* ---------- Carrito ---------- */
function openCart() {
  if (cartDialog && !cartDialog.open) cartDialog.showModal();
}

document.querySelector('[data-cart-open]')?.addEventListener('click', openCart);
window.addEventListener('cart:open', openCart);
document.querySelector('[data-cart-close]')?.addEventListener('click', () => cartDialog?.close());
// Clic en el fondo oscuro cierra el carrito.
cartDialog?.addEventListener('click', (event) => {
  if (event.target === cartDialog) cartDialog.close();
});

function renderCart(cart) {
  const count = cart.lines.length;
  const countNode = document.querySelector('[data-cart-count]');
  if (countNode) countNode.textContent = String(count);
  document.querySelector('[data-cart-open]')?.setAttribute(
    'aria-label',
    count === 0 ? 'Abrir carrito, vacío' : `Abrir carrito, ${count} ${count === 1 ? 'servicio' : 'servicios'}`,
  );

  const body = cartDialog?.querySelector('[data-cart-lines]');
  const foot = cartDialog?.querySelector('[data-cart-foot]');
  if (!body || !foot) return;

  body.replaceChildren();
  foot.hidden = count === 0;

  if (count === 0) {
    const emptyState = document.createElement('div');
    emptyState.className = 'empty-state';
    const text = document.createElement('p');
    text.textContent = 'Tu carrito está vacío. Usa el cotizador para agregar un servicio.';
    const link = document.createElement('a');
    link.className = 'btn btn--ghost';
    link.href = (document.querySelector('.logo')?.getAttribute('href') ?? '/') + '#cotizar';
    link.textContent = 'Ir al cotizador';
    link.addEventListener('click', () => cartDialog.close());
    emptyState.append(text, link);
    body.append(emptyState);
    return;
  }

  // textContent en todo: nada del almacenamiento se interpreta como HTML.
  cart.lines.forEach((line, index) => {
    const item = document.createElement('div');
    item.className = 'cart-line';

    const title = document.createElement('p');
    title.className = 'cart-line__title';
    title.textContent = `${line.name}\u00a0×\u00a0${line.qty}`;

    const meta = document.createElement('p');
    meta.className = 'cart-line__meta num';
    meta.textContent = `${money(line.unitPrice)} por cuchillo`;

    const row = document.createElement('div');
    row.className = 'cart-line__row';
    const price = document.createElement('span');
    price.className = 'cart-line__price';
    price.textContent = `${money(line.unitPrice * line.qty)} MXN`;
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'link-button';
    remove.textContent = 'Quitar';
    remove.setAttribute('aria-label', `Quitar ${line.name} × ${line.qty}`);
    remove.addEventListener('click', () => removeLine(index));
    row.append(price, remove);

    item.append(title, meta, row);
    body.append(item);
  });

  const total = cartDialog.querySelector('[data-cart-total]');
  if (total) total.textContent = `${money(linesTotal(cart.lines))} MXN`;
}

renderCart(readCart());
onCartChange(renderCart);
