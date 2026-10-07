/**
 * QA visual de TODAS las pantallas y sus estados, en varios anchos.
 *
 *   SCREENS_FILE=pantallas.json SHOT_DIR=/tmp/capturas \
 *   node <skill browser-automation>/browser.mjs https://filoacademia.ddev.site/ --script tests/browser/screens.mjs
 *
 * pantallas.json = {"nombre": "url", ...}. Un nombre que contiene «con-carrito»
 * carga la página con un carrito de ejemplo.
 *
 * Revisa en cada ancho:
 *  - desplazamiento horizontal de la página y elementos que se salen;
 *  - cajas encimadas (bordes, fondos, botones que se pisan entre sí);
 *  - botones pegados a una caja (menos de 8 px), que es como se coló el
 *    defecto de la página de resultado;
 *  - palabras partidas en dos renglones;
 *  - botones demasiado pequeños para el dedo en móvil.
 */

import { readFileSync } from 'node:fs';

const WIDTHS = [1440, 1280, 1024, 820, 390];
const SHOT_WIDTHS = [1280, 390];

const SAMPLE_CART = {
  lines: [
    { removal: 'remocion-2mm', extras: ['punta', 'mellas'], qty: 3, name: 'Afilado + Remoción de 2 mm + Punta rota o doblada + Mellas y muescas', unitPrice: 1290 },
    { removal: 'ninguna', extras: [], qty: 1, name: 'Afilado', unitPrice: 250 },
  ],
  delivery: 'nacional',
  insurance: true,
};

function inspect() {
  const out = [];
  const vw = window.innerWidth;
  const visible = (el) => {
    const s = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none' && Number(s.opacity) > 0;
  };
  const inFixed = (el) => {
    for (let p = el; p; p = p.parentElement) {
      const pos = getComputedStyle(p).position;
      if (pos === 'fixed' || pos === 'sticky' || p.tagName === 'DIALOG') return true;
    }
    return false;
  };
  const name = (el) => `${el.tagName.toLowerCase()}${el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : ''}`;

  if (document.documentElement.scrollWidth > vw + 1) out.push(`la página se desplaza en horizontal (${document.documentElement.scrollWidth}px)`);

  const main = document.querySelector('main');
  for (const el of main.querySelectorAll('*')) {
    if (!visible(el)) continue;
    const r = el.getBoundingClientRect();
    if (r.right <= vw + 1 && r.left >= -1) continue;
    let clipped = false;
    for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) {
      if (getComputedStyle(p).overflowX !== 'visible') { clipped = true; break; }
    }
    if (!clipped) out.push(`se sale de la pantalla: ${name(el)}`);
  }

  // Cajas: lo que se ve como un objeto (borde, fondo propio, sombra) y los botones.
  const boxes = [...main.querySelectorAll('*')].filter((el) => {
    if (!visible(el) || inFixed(el)) return false;
    const s = getComputedStyle(el);
    const bordered = ['Top', 'Right', 'Bottom', 'Left'].filter((side) => parseFloat(s[`border${side}Width`]) > 0 && s[`border${side}Style`] !== 'none').length >= 3;
    const filled = s.backgroundColor !== 'rgba(0, 0, 0, 0)' && el.tagName !== 'SECTION' && el.tagName !== 'MAIN';
    return el.classList.contains('btn') || ((bordered || filled || s.boxShadow !== 'none') && el.getBoundingClientRect().height > 24);
  });
  const related = (a, b) => a.contains(b) || b.contains(a);
  for (let i = 0; i < boxes.length; i++) {
    for (let j = i + 1; j < boxes.length; j++) {
      const a = boxes[i], b = boxes[j];
      if (related(a, b)) continue;
      const ra = a.getBoundingClientRect(), rb = b.getBoundingClientRect();
      const w = Math.min(ra.right, rb.right) - Math.max(ra.left, rb.left);
      const h = Math.min(ra.bottom, rb.bottom) - Math.max(ra.top, rb.top);
      if (w > 2 && h > 2) out.push(`encimados: ${name(a)} y ${name(b)} (${Math.round(w)}×${Math.round(h)}px)`);
      // Un botón pegado arriba o abajo de otra caja que no es su hermano de fila.
      const isBtn = a.classList.contains('btn') || b.classList.contains('btn');
      if (isBtn && w > 2 && h <= 2) {
        const gapV = Math.max(rb.top - ra.bottom, ra.top - rb.bottom);
        const sameRow = Math.abs(ra.top - rb.top) < 4;
        if (!sameRow && gapV >= -2 && gapV < 8) out.push(`pegados (${Math.round(gapV)}px): ${name(a)} y ${name(b)}`);
      }
    }
  }

  // Palabras partidas en dos renglones.
  const walker = document.createTreeWalker(main, NodeFilter.SHOW_TEXT);
  while (walker.nextNode()) {
    const node = walker.currentNode;
    if (!node.parentElement || !visible(node.parentElement)) continue;
    for (const m of (node.nodeValue || '').matchAll(/[^\s]{4,}/g)) {
      const range = document.createRange();
      range.setStart(node, m.index);
      range.setEnd(node, m.index + m[0].length);
      const lines = new Set([...range.getClientRects()].filter((r) => r.width).map((r) => Math.round(r.top)));
      if (lines.size > 1 && !/https?:\/\/|www\.|@/.test(m[0])) out.push(`palabra partida: «${m[0]}»`);
    }
  }

  // Botones pequeños para el dedo (solo móvil).
  if (vw <= 480) {
    for (const el of main.querySelectorAll('button, .btn, input[type="submit"]')) {
      if (!visible(el)) continue;
      const r = el.getBoundingClientRect();
      if (r.height < 40 && !el.classList.contains('link-button')) out.push(`botón pequeño (${Math.round(r.height)}px de alto): ${name(el)} «${el.textContent.trim().slice(0, 30)}»`);
    }
  }

  return [...new Set(out)];
}

export default async function run(page) {
  const screens = JSON.parse(readFileSync(process.env.SCREENS_FILE, 'utf8'));
  const shotDir = process.env.SHOT_DIR;
  const report = {};

  await page.addInitScript((cart) => {
    try {
      if (location.hash === '#cart') localStorage.setItem('filo.cart.v1', JSON.stringify(cart));
      else if (location.pathname.startsWith('/pagar/') && !location.search) localStorage.removeItem('filo.cart.v1');
    } catch {}
  }, SAMPLE_CART);

  for (const [screen, url] of Object.entries(screens)) {
    for (const width of WIDTHS) {
      await page.setViewportSize({ width, height: 900 });
      // about:blank antes: de /pagar/ a /pagar/#cart el navegador no recargaría.
      await page.goto('about:blank');
      const response = await page.goto(url, { waitUntil: 'networkidle' });
      await page.waitForTimeout(300);
      const issues = await page.evaluate(inspect);
      if (issues.length) report[`${screen} @${width}`] = issues;
      if (shotDir && SHOT_WIDTHS.includes(width)) {
        await page.screenshot({ path: `${shotDir}/${screen}-${width}.png`, fullPage: true, timeout: 120000 });
      }
      if (width === WIDTHS[0]) report[`${screen} (HTTP)`] = response.status();
    }
  }
  return report;
}
