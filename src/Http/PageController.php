<?php

declare(strict_types=1);

namespace FiloAcademia\Http;

use FiloAcademia\App;

/**
 * Páginas informativas (sin estado).
 */
final class PageController
{
    public function __construct(private readonly App $app)
    {
    }

    public function home(): never
    {
        $catalog = $this->app->catalog();

        Response::html($this->app->view()->page('pages/home', [
            'title' => 'HERO · Filo Academia — Afilado de cuchillos de cocina · CDMX',
            'description' => 'El especialista en afilar y reparar cuchillos: japoneses, de marca prestigiada o con valor sentimental. A mano, con piedras de agua. Recolección en CDMX y toda la República.',
            'active' => 'home',
            'catalogModel' => $catalog,
            'catalog' => $catalog->toPublicArray(),
            'scripts' => ['quote'],
        ]), 200, true);
    }

    public function academia(): never
    {
        Response::html($this->app->view()->page('pages/academia', [
            'title' => 'Academia de afilado japonés · HERO Filo Academia',
            'description' => 'Cursos presenciales de afilado japonés en CDMX: piedras de agua, reparación y perfilado, y capacitación para cocinas profesionales.',
            'active' => 'academia',
        ]), 200, true);
    }

    public function notFound(): never
    {
        $this->error(404, 'No encontramos esta página', 'Puede que el enlace esté incompleto o que la página ya no exista.');
    }

    public function error(int $status, string $heading, string $message): never
    {
        Response::html($this->app->view()->page('pages/error', [
            'title' => $heading . ' · HERO Filo Academia',
            'description' => $message,
            'active' => '',
            'noindex' => true,
            'heading' => $heading,
            'message' => $message,
        ]), $status);
    }
}
