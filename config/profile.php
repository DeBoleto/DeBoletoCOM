<?php

return [

    'purchases_limit' => (int) env('PROFILE_PURCHASES_LIMIT', 30),

    // Servicio que entrega el QR dinámico de acceso de cada boleto (GET /qr?boleto={id}).
    'dynamic_qr_url' => env('PROFILE_DYNAMIC_QR_URL', 'https://ticketsapp.jksolutions.site'),

    'dynamic_qr_seconds' => (int) env('PROFILE_DYNAMIC_QR_SECONDS', 300),

    'scan_poll_seconds' => (int) env('PROFILE_SCAN_POLL_SECONDS', 35),

    // Logos de patrocinadores al pie del boleto digital. `events` reemplaza la
    // lista para un evento: [pk_evento => [[...], ...]]; una lista vacía los oculta.
    'sponsors' => [
        'default' => [
            ['name' => 'Flor de Tabasco', 'image' => '/images/patrocinadores/flor-de-tabasco.png', 'url' => 'https://flordetabasco.com/'],
        ],
        'events' => [],
        'hide_on_races' => true,
    ],

];
