<?php
/**
 * Página de inicio.
 *
 * @var FiloAcademia\Support\View $v
 * @var FiloAcademia\Catalog\Catalog $catalogModel
 * @var array<string, mixed> $business
 */
$base = $catalogModel->base();
$insurance = $catalogModel->insurance();
$defaultDelivery = 'cdmx';
// Ejemplo de la pista del paso 2, calculado del catálogo para que nunca desfase.
$exampleRemoval = $catalogModel->findRemoval('remocion-2mm');
$exampleExtra = $catalogModel->findExtra('punta');
?>
<section class="hero" aria-labelledby="hero-title">
  <div class="hero__copy">
    <p class="hero__kicker"><span class="hanko hanko--sm" aria-hidden="true"><span>英</span><span>雄</span></span> Filo Academia · Ciudad de México</p>
    <h1 class="hero__title" id="hero-title">Afilamos y reparamos <em>tus cuchillos</em>.</h1>
    <p class="hero__lead">Japoneses, de marca prestigiada, o ese cuchillo que heredaste y no quieres confiarle a nadie. A mano, con piedras de agua. Lo recogemos y te lo devolvemos con filo.</p>
    <div class="hero__actions">
      <a href="#cotizar" class="btn">Cotizar mi servicio</a>
      <a href="#precios" class="btn btn--ghost">Ver precios</a>
    </div>
    <p class="hero__note">Cada pieza se revisa y se cotiza antes de tocarla. Sin sorpresas.</p>
    <span class="hero__vertical" lang="ja" aria-hidden="true">包丁研ぎ ・ CDMX</span>
  </div>
  <div class="hero__media">
    <?= $v->render('partials/picture', ['name' => 'hero-cuchillos', 'alt' => 'Cuchillos damasco japoneses junto a rack magnético de nogal', 'width' => 1100, 'height' => 1100, 'eager' => true]) ?>
  </div>
</section>

<section class="section section--alt" id="cotizar" aria-labelledby="cotizar-title">
  <div class="wrap">
    <p class="eyebrow"><span class="eyebrow__jp" lang="ja">見積</span> Cotizador</p>
    <h2 class="section-title" id="cotizar-title">Cotiza tu servicio en 30 segundos.</h2>
    <p class="section-lead">El afilado es el servicio básico; si tu hoja tiene daños, suma la reparación que corresponda.</p>

    <form class="quote-card" data-quote novalidate>
      <div class="quote-card__body">

        <div class="quote-step">
          <p class="quote-step__label"><b>1</b> Servicio básico · incluido en toda cotización</p>
          <div class="option-row option-row--fixed">
            <span class="option-row__box" aria-hidden="true">✓</span>
            <span class="option-row__text"><b><?= $v->e($base['name']) ?></b><small>Por cuchillo, sin importar gama ni marca.</small></span>
            <span class="option-row__price num"><?= $v->money($base['price']) ?></span>
          </div>
        </div>

        <div class="quote-step">
          <p class="quote-step__label"><b>2</b> Reparación de la hoja · se suma al afilado</p>
          <fieldset class="choice-group">
            <legend class="choice-group__legend">Remoción de material · una sola opción, o ninguna</legend>
            <div class="choices">
              <?php foreach ($catalogModel->removalOptions() as $option): ?>
                <label class="choice">
                  <input type="radio" name="removal" value="<?= $v->e($option['id']) ?>"<?= $option['price'] === 0 ? ' checked' : '' ?>>
                  <span class="choice__card">
                    <span class="choice__title"><?= $v->e($option['price'] === 0 ? $option['name'] : str_replace('Remoción de', 'Remoción', $option['name'])) ?></span>
                    <span class="choice__detail"><?= $v->e($option['detail']) ?></span>
                    <span class="choice__price num"><?= $option['price'] === 0 ? '—' : '+' . $v->money($option['price']) ?></span>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <fieldset class="choice-group">
            <legend class="choice-group__legend">Otros daños · combinables entre sí y con la remoción</legend>
            <div class="choices choices--3">
              <?php foreach ($catalogModel->extras() as $extra): ?>
                <label class="choice choice--check">
                  <input type="checkbox" name="extras" value="<?= $v->e($extra['id']) ?>">
                  <span class="choice__card">
                    <span class="choice__title"><?= $v->e($extra['name']) ?></span>
                    <span class="choice__price num">+<?= $v->money($extra['price']) ?></span>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
            <?php if ($exampleRemoval !== null && $exampleExtra !== null): ?>
              <p class="quote-step__hint num">Ejemplo: afilado + remoción de 2 mm + punta rota = <?= (int) $base['price'] ?> + <?= (int) $exampleRemoval['price'] ?> + <?= (int) $exampleExtra['price'] ?> = <?= $v->money($base['price'] + $exampleRemoval['price'] + $exampleExtra['price']) ?> por cuchillo.</p>
            <?php endif; ?>
          </fieldset>
        </div>

        <div class="quote-step">
          <p class="quote-step__label" id="qty-label"><b>3</b> Cantidad de cuchillos</p>
          <div class="stepper" role="group" aria-labelledby="qty-label">
            <button type="button" data-qty-step="-1" aria-label="Un cuchillo menos">−</button>
            <output name="qty" data-qty aria-live="polite">1</output>
            <button type="button" data-qty-step="1" aria-label="Un cuchillo más">+</button>
          </div>
          <p class="quote-step__hint">Cada pieza se cotiza por separado; no hay descuentos por juegos completos. Máximo <?= (int) $catalogModel->maxKnivesPerLine() ?> por línea.</p>
        </div>

        <div class="quote-step">
          <p class="quote-step__label"><b>4</b> Recepción y entrega · una vez por pedido</p>
          <fieldset class="choice-group">
            <legend class="visually-hidden">Recepción y entrega</legend>
            <div class="choices">
              <?php foreach ($catalogModel->deliveryOptions() as $option): ?>
                <label class="choice">
                  <input type="radio" name="delivery" value="<?= $v->e($option['id']) ?>"<?= $option['id'] === $defaultDelivery ? ' checked' : '' ?><?= $option['available'] ? '' : ' disabled' ?>>
                  <span class="choice__card">
                    <span class="choice__title"><?= $v->e($option['name']) ?></span>
                    <span class="choice__detail"><?= $v->e($option['detail']) ?></span>
                    <span class="choice__price num"><?= !$option['available'] ? '—' : ($option['price'] === 0 ? 'Sin costo' : $v->money($option['price'])) ?></span>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
        </div>

        <div class="quote-step" data-insurance-step>
          <p class="quote-step__label"><b>5</b> Opcional · solo con paquetería</p>
          <label class="option-row">
            <input type="checkbox" name="insurance" value="1" data-insurance>
            <span class="option-row__box" aria-hidden="true"></span>
            <span class="option-row__text"><b><?= $v->e($insurance['name']) ?></b><small><?= $v->e($insurance['detail']) ?></small></span>
            <span class="option-row__price num">+<?= $v->money($insurance['price']) ?></span>
          </label>
          <p class="quote-step__hint" data-insurance-hint hidden>El seguro aplica cuando el cuchillo viaja por paquetería (fuera de CDMX).</p>
        </div>

      </div>
      <div class="quote-bar">
        <div>
          <p class="quote-bar__label">Total estimado</p>
          <p class="quote-bar__amount"><span data-quote-total>—</span> MXN</p>
          <p class="quote-bar__breakdown num" data-quote-breakdown></p>
        </div>
        <div>
          <button type="submit" class="btn btn--light">Agregar al carrito</button>
          <p class="quote-feedback" data-quote-feedback role="status"></p>
        </div>
      </div>
      <noscript><p class="alert alert--warning quote-card__noscript">Para cotizar y pagar en línea activa JavaScript, o escríbenos por WhatsApp al <?= $v->e($business['phone_display']) ?>.</p></noscript>
    </form>
    <p class="quote-note">Precios de referencia; el total final se confirma al revisar el cuchillo en el taller, antes de intervenirlo. Traerlo y recogerlo en el taller no tiene costo.</p>
  </div>
</section>

<section class="section" id="precios" aria-labelledby="precios-title">
  <div class="wrap">
    <p class="eyebrow"><span class="eyebrow__jp" lang="ja">料金</span> Precios</p>
    <h2 class="section-title" id="precios-title">Lista de referencia.</h2>
    <p class="section-lead">El mismo precio aplica para cualquier gama o marca: lo que cambia es el trabajo sobre la hoja.</p>
    <div class="price-columns">
      <div>
        <div class="price-group">
          <h3 class="price-group__title">Servicio básico · por cuchillo</h3>
          <ul class="price-list">
            <li><span><?= $v->e($base['name']) ?></span><span class="price-list__dots" aria-hidden="true"></span><span class="price-list__value"><?= $v->money($base['price']) ?></span></li>
          </ul>
        </div>
        <div class="price-group">
          <h3 class="price-group__title">Reparación · se suma al afilado</h3>
          <ul class="price-list">
            <?php foreach ([...array_filter($catalogModel->removalOptions(), static fn (array $o): bool => $o['price'] > 0), ...$catalogModel->extras()] as $item): ?>
              <li><span><?= $v->e($item['name']) ?></span><span class="price-list__dots" aria-hidden="true"></span><span class="price-list__value">+<?= $v->money($item['price']) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <p class="price-small">La remoción de material es una sola opción por cuchillo; punta, mellas y óxido son combinables entre sí y con la remoción.</p>
        </div>
      </div>
      <div class="price-group">
        <h3 class="price-group__title">Recepción y entrega · por pedido</h3>
        <ul class="price-list">
          <?php foreach ($catalogModel->deliveryOptions() as $option): ?>
            <li>
              <span><?= $v->e($option['long']) ?></span><span class="price-list__dots" aria-hidden="true"></span>
              <?php if (!$option['available']): ?>
                <span class="price-list__value price-list__value--na">No disponibles por el momento</span>
              <?php elseif ($option['price'] === 0): ?>
                <span class="price-list__value price-list__value--free">Sin costo</span>
              <?php else: ?>
                <span class="price-list__value"><?= $v->money($option['price']) ?></span>
              <?php endif; ?>
            </li>
            <?php if ($option['insurable']): ?>
              <li><span><?= $v->e($insurance['name']) ?> hasta $5,000 MXN</span><span class="price-list__dots" aria-hidden="true"></span><span class="price-list__value"><?= $v->money($insurance['price']) ?></span></li>
            <?php endif; ?>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <p class="fine-print">Esta lista es una referencia: el precio final puede variar al examinar el cuchillo y se confirma contigo antes de cualquier trabajo. No hay descuentos por juegos completos: cada pieza se cotiza por separado. Los costos de paquetería y seguro son promedios estimados de tarifas Estafeta, DHL y Paquetexpress (paquete de 1 kg, ida y vuelta) e incluyen empaque protector y gestión. En zonas extendidas o rurales el costo de envío puede variar, y si tu localidad no cuenta con recolección de la paquetería, deberás llevar tu paquete a la sucursal más cercana.</p>
    <p class="fine-print"><b>Condiciones del seguro:</b> la cobertura hasta $5,000 MXN opera bajo las reglas de valor declarado del transportista (Estafeta, DHL, Paquetexpress). Aplica únicamente con el empaque protector de nuestro taller; requiere reportar el daño dentro de la ventana del transportista —típicamente 5 días hábiles por daño visible y 10 a 30 días por extravío, según la paquetería— y presentar comprobante de valor de la pieza. Excluye deterioro previo, oxidación o corrosión propia del acero y daños por empaque inadecuado. La indemnización es monetaria, sobre el valor declarado, y no cubre valor sentimental. Nosotros presentamos y damos seguimiento al reclamo por ti.</p>
  </div>
</section>

<section class="section section--alt" id="como" aria-labelledby="como-title">
  <div class="wrap">
    <p class="eyebrow"><span class="eyebrow__jp" lang="ja">手順</span> Cómo funciona</p>
    <h2 class="section-title" id="como-title">Tres pasos. Nada más.</h2>
    <ol class="steps">
      <li class="step">
        <span class="step__kanji" lang="ja" aria-hidden="true">一</span>
        <h3 class="step__title">Nos lo haces llegar</h3>
        <p>Lo traes al taller sin costo, pasamos por él a tu domicilio en CDMX, o te enviamos guía de paquetería prepagada si estás en la República.</p>
      </li>
      <li class="step">
        <span class="step__kanji" lang="ja" aria-hidden="true">二</span>
        <h3 class="step__title">Lo afilamos</h3>
        <p>A mano, con piedras de agua. Si hay punta rota, mellas u óxido, lo reparamos. Te confirmamos el precio final al revisar la pieza, antes de tocarla.</p>
      </li>
      <li class="step">
        <span class="step__kanji" lang="ja" aria-hidden="true">三</span>
        <h3 class="step__title">Te lo devolvemos</h3>
        <p>Lo recoges en el taller o regresa a tu domicilio, con empaque protector para la hoja y seguro de envío si lo contrataste.</p>
      </li>
    </ol>
  </div>
</section>

<section class="section specialist" id="especialista" aria-labelledby="especialista-title">
  <span class="watermark" lang="ja" aria-hidden="true">研</span>
  <div class="wrap">
    <p class="eyebrow"><span class="eyebrow__jp" lang="ja">専門</span> El especialista</p>
    <h2 class="section-title" id="especialista-title">El lugar al que mandas tus cuchillos.</h2>
    <p class="section-lead">Conocer cada acero, cada geometría y cada filo: ese es nuestro oficio completo.</p>
    <div class="two-columns">
      <div>
        <h3 class="dash-list__title">Lo que recibimos</h3>
        <ul class="dash-list">
          <li><b>Japoneses:</b> gyuto, santoku, nakiri, yanagiba, deba… de doble o un solo bisel.</li>
          <li><b>Marcas prestigiadas:</b> Zwilling, Wüsthof, Global, Shun, MAC, Chroma y similares.</li>
          <li><b>Cuchillos con historia:</b> herencias, regalos, el primer cuchillo de la escuela. Los tratamos como lo que son.</li>
          <li><b>Todo cuchillo con filo que recuperar:</b> de cocina, de oficio o de familia.</li>
          <li><b>¿Algo distinto?</b> Navajas u otras herramientas de corte: escríbenos y revisamos el caso.</li>
        </ul>
      </div>
      <div>
        <h3 class="dash-list__title">Lo que hacemos en la hoja</h3>
        <ul class="dash-list">
          <li><b>Afilado a mano</b> con piedras de agua, respetando el ángulo y la geometría de cada uso.</li>
          <li><b>Reconstrucción de punta</b> rota o doblada, con reperfilado simétrico.</li>
          <li><b>Eliminación de mellas</b> y muescas del filo, sin sacrificar más acero del necesario.</li>
          <li><b>Limpieza de óxido</b> y oxidación, con acabado que protege la hoja.</li>
          <li><b>Diagnóstico honesto:</b> si tu cuchillo no necesita servicio, te lo decimos.</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<section class="section section--alt" id="showroom" aria-labelledby="showroom-title">
  <div class="wrap">
    <p class="eyebrow"><span class="eyebrow__jp" lang="ja">展示</span> Showroom</p>
    <h2 class="section-title" id="showroom-title">El taller.</h2>
    <div class="gallery">
      <figure>
        <?= $v->render('partials/picture', ['name' => 'taller-rack-magnetico', 'alt' => 'Cuchillos colgados en rack magnético', 'width' => 1400, 'height' => 1120]) ?>
        <figcaption>Rack magnético · pared del taller</figcaption>
      </figure>
      <figure>
        <?= $v->render('partials/picture', ['name' => 'taller-soporte-madera', 'alt' => 'Cuchillos en soporte de madera', 'width' => 479, 'height' => 640]) ?>
        <figcaption>Soporte de madera · exhibición</figcaption>
      </figure>
    </div>
  </div>
</section>
