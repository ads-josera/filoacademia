/**
 * Genera el PDF de la guía para el cliente a partir del HTML (tamaño carta).
 *   PDF_OUT=/ruta/guia.pdf node <skill browser-automation>/browser.mjs file://<ruta>/guia-credenciales-mercadopago.html --script docs/cliente/pdf.mjs
 */
export default async function run(page) {
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(500);
  await page.emulateMedia({ media: 'print' });
  await page.pdf({
    path: process.env.PDF_OUT,
    format: 'Letter',
    printBackground: true,
    preferCSSPageSize: true,
    displayHeaderFooter: true,
    headerTemplate: '<span></span>',
    footerTemplate: '<div style="width:100%;font-size:8px;color:#68635a;text-align:center;font-family:Arial,sans-serif;">Página <span class="pageNumber"></span> de <span class="totalPages"></span></div>',
    margin: { top: '18mm', bottom: '20mm', left: '18mm', right: '18mm' },
  });
  return 'ok';
}
