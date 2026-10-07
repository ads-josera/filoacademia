/**
 * Paso 3 (/pagar/resultado): vacía el carrito cuando el pedido ya quedó
 * pagado (o con ficha OXXO) y refresca la página mientras el pago está en
 * revisión.
 */
import { clearCart } from './cart-store.js';

const hero = document.querySelector('.status-hero');
const MAX_REFRESHES = 20;
const counterKey = `filo.refresh.${location.search}`;

if (hero?.hasAttribute('data-clear-cart')) {
  clearCart();
}

const seconds = Number(hero?.dataset.autoRefresh ?? 0);
if (seconds > 0) {
  let count = 0;
  try {
    count = Number(sessionStorage.getItem(counterKey) ?? 0);
    sessionStorage.setItem(counterKey, String(count + 1));
  } catch {
    /* sin almacenamiento: se refresca igual, con el límite de la sesión */
  }
  if (count < MAX_REFRESHES) {
    setTimeout(() => window.location.reload(), seconds * 1000);
  }
}
