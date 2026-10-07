<?php

/**
 * Catálogo de precios: la ÚNICA fuente de verdad.
 *
 * De aquí salen la lista de precios, el cotizador (se entrega al navegador
 * como JSON) y el cálculo con el que se cobra. El servidor nunca confía en un
 * monto que venga del navegador: recalcula todo con estos valores.
 *
 * Montos en pesos MXN enteros. Los `id` son estables: se guardan en los
 * pedidos, así que no se renombran; para retirar una opción, se marca
 * 'available' => false.
 */

declare(strict_types=1);

return [
    'currency' => 'MXN',
    'max_knives_per_line' => 20,

    'base' => [
        'id' => 'afilado',
        'name' => 'Afilado a mano con piedras de agua',
        'short' => 'Afilado',
        'price' => 250,
    ],

    // Una sola opción por cuchillo.
    'removal' => [
        ['id' => 'ninguna', 'name' => 'Sin reparación', 'detail' => 'solo afilado', 'price' => 0],
        ['id' => 'remocion-1mm', 'name' => 'Remoción de 1 mm', 'detail' => 'reparación de la hoja', 'price' => 290],
        ['id' => 'remocion-2mm', 'name' => 'Remoción de 2 mm', 'detail' => 'reparación de la hoja', 'price' => 450],
        ['id' => 'remocion-3mm', 'name' => 'Remoción de 3 mm', 'detail' => 'reparación de la hoja', 'price' => 550],
    ],

    // Combinables entre sí y con la remoción.
    'extras' => [
        ['id' => 'punta', 'name' => 'Punta rota o doblada', 'price' => 300],
        ['id' => 'mellas', 'name' => 'Mellas y muescas', 'price' => 290],
        ['id' => 'oxido', 'name' => 'Eliminación de óxido', 'price' => 180],
    ],

    // Se elige UNA vez por pedido, no por cuchillo ni por línea del carrito.
    'delivery' => [
        [
            'id' => 'taller',
            'name' => 'En el taller',
            'long' => 'Entrega y recolección en el taller',
            'detail' => 'Eje 1 Norte #56 L17, Col. Morelos',
            'price' => 0,
            'needs_address' => false,
            'insurable' => false,
            'available' => true,
        ],
        [
            'id' => 'cdmx',
            'name' => 'CDMX',
            'long' => 'Recolección y entrega a domicilio en CDMX',
            'detail' => 'recolección y entrega a domicilio',
            'price' => 290,
            'needs_address' => true,
            'insurable' => false,
            'available' => true,
        ],
        [
            'id' => 'nacional',
            'name' => 'Fuera de CDMX',
            'long' => 'Paquetería fuera de CDMX · ida y vuelta (1 kg)',
            'detail' => 'paquetería, ida y vuelta',
            'price' => 680,
            'needs_address' => true,
            // El seguro es del transportista (Estafeta, DHL, Paquetexpress):
            // solo existe cuando el cuchillo viaja por paquetería.
            'insurable' => true,
            'available' => true,
        ],
        [
            'id' => 'internacional',
            'name' => 'Internacional',
            'long' => 'Envíos internacionales',
            'detail' => 'no disponible por el momento',
            'price' => 0,
            'needs_address' => true,
            'insurable' => false,
            'available' => false,
        ],
    ],

    'insurance' => [
        'id' => 'seguro',
        'name' => 'Seguro de paquetería',
        'detail' => 'Cobertura hasta $5,000 MXN por pérdida o daño en el traslado, conforme a las condiciones del transportista.',
        'price' => 190,
    ],
];
