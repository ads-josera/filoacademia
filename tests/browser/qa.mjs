/**
 * Recorrido de QA en navegador: el flujo completo como cliente + revisión de
 * geometría (desbordes, scroll horizontal) en varios anchos.
 *
 *   php -S localhost:8000 -t public bin/dev-router.php
 *   node <skill browser-automation>/browser.mjs http://localhost:8000/ --script tests/browser/qa.mjs
 *
 * No toca Mercado Pago: en local (sin llaves) el paso 2 debe mostrar el
 * estado de error de conexión, que también es una pantalla a revisar.
 */

const WIDTHS = [1440, 1280, 1024, 820, 390];

async function geometry(page, label) {
  return page.evaluate((label) => {
    const out = [];
    if (document.documentElement.scrollWidth > window.innerWidth + 2) {
      out.push(`${label}: la página se desplaza en horizontal (${document.documentElement.scrollWidth}px > ${window.innerWidth}px)`);
    }
    for (const el of document.querySelectorAll('main *')) {
      const r = el.getBoundingClientRect();
      if (!r.width || r.right <= window.innerWidth + 2) continue;
      let clipped = false;
      for (let p = el.parentElement; p; p = p.parentElement) {
        const ox = getComputedStyle(p).overflowX;
        if (ox !== 'visible') { clipped = true; break; }
      }
      if (!clipped) out.push(`${label}: ${el.tagName.toLowerCase()}.${el.className} se sale ${Math.round(r.right - window.innerWidth)}px`);
    }
    return out.slice(0, 10);
  }, label);
}

export default async function run(page) {
  const report = { issues: [], steps: [] };
  const base = new URL(page.url()).origin;
  const step = (msg) => report.steps.push(msg);

  // 1. Inicio en todos los anchos
  for (const width of WIDTHS) {
    await page.setViewportSize({ width, height: 900 });
    for (const path of ['/', '/academia', '/pagar/']) {
      await page.goto(base + path);
      report.issues.push(...(await geometry(page, `${path} @${width}`)));
    }
  }
  await page.setViewportSize({ width: 1280, height: 900 });

  // 2. Cotizador: total inicial (afilado 250 + CDMX 290)
  await page.goto(base + '/');
  await page.evaluate(() => localStorage.clear());
  await page.reload();
  const initial = await page.locator('[data-quote-total]').innerText();
  step(`total inicial: ${initial}`);
  if (initial !== '$540') report.issues.push(`total inicial esperado $540, se ve ${initial}`);

  // Remoción 2 mm + punta, 2 cuchillos, paquetería + seguro
  await page.locator('label.choice:has(input[value="remocion-2mm"])').click();
  await page.locator('label.choice:has(input[value="punta"])').click();
  await page.locator('[data-qty-step="1"]').click();
  const insuranceDisabledCdmx = await page.locator('[data-insurance]').isDisabled();
  if (!insuranceDisabledCdmx) report.issues.push('el seguro debería estar desactivado con entrega CDMX');
  await page.locator('label.choice:has(input[value="nacional"])').click();
  await page.locator('[data-insurance-step] label.option-row').click();
  const quoted = await page.locator('[data-quote-total]').innerText();
  // (250+450+300)*2 + 680 + 190 = 2870
  step(`total cotizado: ${quoted}`);
  if (quoted !== '$2,870') report.issues.push(`total esperado $2,870, se ve ${quoted}`);

  // Agregar al carrito → se abre el carrito
  await page.locator('[data-quote] button[type="submit"]').click();
  const drawerOpen = await page.locator('dialog[data-cart]').evaluate((d) => d.open);
  if (!drawerOpen) report.issues.push('el carrito no se abrió al agregar');
  const count = await page.locator('[data-cart-count]').innerText();
  step(`contador carrito: ${count}`);
  if (count !== '1') report.issues.push(`contador esperado 1, se ve ${count}`);
  const cartTotal = await page.locator('[data-cart-total]').innerText();
  step(`total en carrito (solo servicios): ${cartTotal}`);

  // Esc cierra el carrito (lo da <dialog>)
  await page.keyboard.press('Escape');
  const closed = await page.locator('dialog[data-cart]').evaluate((d) => !d.open);
  if (!closed) report.issues.push('Esc no cierra el carrito');

  // 3. Paso 1: datos
  await page.goto(base + '/pagar/');
  const sumTotal = await page.locator('[data-sum-total]').innerText();
  step(`total en /pagar: ${sumTotal}`);
  if (sumTotal !== '$2,870 MXN') report.issues.push(`total en /pagar esperado $2,870 MXN, se ve ${sumTotal}`);
  const nacionalChecked = await page.locator('input[name="delivery"][value="nacional"]').isChecked();
  if (!nacionalChecked) report.issues.push('la entrega elegida en el cotizador no llegó a /pagar');

  // Envío vacío: la validación del navegador lo detiene (sigue en /pagar)
  await page.locator('[data-checkout-submit]').click();
  if (!page.url().endsWith('/pagar/')) report.issues.push('el formulario vacío se envió');

  // Errores del servidor: correo inválido que el navegador deja pasar → 422 con resumen
  await page.locator('#field-name').fill('Ana Prueba');
  await page.locator('#field-email').fill('ana@ejemplo');
  await page.locator('#field-phone').fill('55 1234 5678');
  await page.locator('#field-address').fill('Av. Reforma 123, Col. Juárez, CP 06600, CDMX');
  await page.locator('[data-checkout-submit]').click();
  await page.waitForLoadState('domcontentloaded');
  const hasSummary = await page.locator('[data-error-summary]').count();
  step(`resumen de errores del servidor: ${hasSummary ? 'sí' : 'no'}`);
  if (!hasSummary) report.issues.push('no se mostró el resumen de errores del servidor');
  const keptName = await page.locator('#field-name').inputValue();
  if (keptName !== 'Ana Prueba') report.issues.push('se perdió lo escrito tras el error');

  // Envío correcto
  await page.locator('#field-email').fill('ana@ejemplo.mx');
  await page.locator('[data-checkout-submit]').click();
  await page.waitForURL(/\/pagar\/pedido\?/);
  step(`paso 2: ${new URL(page.url()).pathname}`);
  const heading = await page.locator('h1').innerText();
  step(`título paso 2: ${heading}`);
  const payState = await page.evaluate(() => ({
    gatewayError: !!document.querySelector('.alert--danger'),
    payButton: !!document.querySelector('[data-pay-redirect]'),
    total: document.querySelector('.summary__row--total span:last-child')?.textContent,
  }));
  step(`estado paso 2: ${JSON.stringify(payState)}`);
  if (payState.total !== '$2,870 MXN') report.issues.push(`total del pedido guardado: ${payState.total}`);
  report.issues.push(...(await geometry(page, 'pedido @1280')));
  await page.setViewportSize({ width: 390, height: 900 });
  report.issues.push(...(await geometry(page, 'pedido @390')));

  // 4. Enlace manipulado: sin token no hay datos
  const folio = new URL(page.url()).searchParams.get('folio');
  const payToken = new URL(page.url()).searchParams.get('t');
  const resp = await page.goto(`${base}/pagar/resultado?folio=${folio}&t=falso`);
  step(`resultado con token falso: HTTP ${resp.status()}`);
  if (resp.status() !== 404) report.issues.push('el resultado se mostró con un token falso');

  // 5. Resultado (pedido sin pagar): separación entre botones y paneles, y
  //    paneles alineados arriba. Ya falló una vez: los botones tocaban la caja.
  for (const width of [1280, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(`${base}/pagar/resultado?folio=${folio}&t=${payToken}`);
    const layout = await page.evaluate(() => {
      const actions = document.querySelector('.status-hero__actions').getBoundingClientRect();
      const panels = [...document.querySelectorAll('.checkout__grid > .panel')].map((p) => p.getBoundingClientRect());
      return { gap: Math.round(panels[0].top - actions.bottom), tops: panels.map((p) => Math.round(p.top)), lefts: panels.map((p) => Math.round(p.left)) };
    });
    step(`resultado @${width}: separación ${layout.gap}px, paneles arriba ${layout.tops.join('/')}`);
    if (layout.gap < 32) report.issues.push(`resultado @${width}: botones a ${layout.gap}px de los paneles (mínimo 32)`);
    const sideBySide = layout.lefts[0] !== layout.lefts[1];
    if (sideBySide && layout.tops[0] !== layout.tops[1]) report.issues.push(`resultado @${width}: paneles lado a lado desalineados (${layout.tops.join(' vs ')})`);
    report.issues.push(...(await geometry(page, `resultado @${width}`)));
  }

  report.folio = folio;
  return report;
}
