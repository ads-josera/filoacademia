<?php
/**
 * Página de la academia (cursos). Las reservas son por WhatsApp.
 *
 * @var FiloAcademia\Support\View $v
 * @var array<string, mixed> $business
 */
$courses = [
    [
        'tag' => 'curso I',
        'title' => 'Afilado japonés',
        'text' => 'Piedras de agua, ángulos, rebaba y asentado. Sales afilando tu propio cuchillo y sabiendo mantenerlo.',
        'meta' => '4 hrs · principiante · cupo 6',
        'cta' => 'Reservar lugar',
        'message' => 'Hola, quiero reservar el Curso I de afilado',
    ],
    [
        'tag' => 'curso II',
        'title' => 'Reparación y perfilado',
        'text' => 'Puntas rotas, mellas, óxido y geometría de la hoja. Para quien ya afila y quiere aprender a reparar.',
        'meta' => '6 hrs · intermedio · cupo 4',
        'cta' => 'Reservar lugar',
        'message' => 'Hola, quiero reservar el Curso II de reparación',
    ],
    [
        'tag' => 'sesión privada',
        'title' => 'Cocinas profesionales',
        'text' => 'Capacitamos a tu brigada de restaurante o escuela en sus instalaciones, con su propio equipo.',
        'meta' => 'a demanda · tu brigada',
        'cta' => 'Agendar plática',
        'message' => 'Hola, me interesa una sesión privada para mi cocina',
    ],
];
?>
<section class="section dark" aria-labelledby="academia-title">
  <div class="wrap">
    <a href="<?= $v->e($v->url('/')) ?>" class="back-link">← Volver al inicio</a>
    <p class="eyebrow"><span class="eyebrow__jp" lang="ja">学院</span> Academia</p>
    <h1 class="section-title" id="academia-title">Aprende el arte del filo.</h1>
    <p class="section-lead">Cursos presenciales de afilado japonés en nuestro taller de CDMX. Grupos pequeños; piedras y cuchillos de práctica incluidos.</p>
    <figure class="academy-media">
      <?= $v->render('partials/picture', ['name' => 'academia-piedra', 'alt' => 'Cuchillo japonés sobre piedra de agua', 'width' => 1280, 'height' => 720, 'eager' => true]) ?>
      <figcaption>Estación de afilado · piedra de agua</figcaption>
    </figure>
    <ul class="courses">
      <?php foreach ($courses as $course): ?>
        <li class="course">
          <p class="course__tag"><?= $v->e($course['tag']) ?></p>
          <h2 class="course__title"><?= $v->e($course['title']) ?></h2>
          <p><?= $v->e($course['text']) ?></p>
          <p class="course__meta"><?= $v->e($course['meta']) ?></p>
          <a class="course__link" href="https://wa.me/<?= $v->e($business['whatsapp']) ?>?text=<?= rawurlencode($course['message']) ?>" rel="noopener"><?= $v->e($course['cta']) ?> por WhatsApp →</a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
