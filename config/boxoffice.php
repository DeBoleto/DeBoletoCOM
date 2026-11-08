<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Zona horaria del esquema legacy
    |--------------------------------------------------------------------------
    |
    | deboletox guarda todas las fechas en hora local de México. Las fechas que
    | escribe la taquilla y los filtros "del día" (corte de caja) usan esta zona
    | para no desfasarse respecto al sistema legacy que comparte la base.
    |
    */

    'timezone' => env('BOXOFFICE_TIMEZONE', 'America/Mexico_City'),

    /*
    |--------------------------------------------------------------------------
    | Privilegios
    |--------------------------------------------------------------------------
    */

    'privileges' => [
        'payments_report' => 62,
        'transfer_payment' => 63,
        'courtesies' => 39,
        'reports_admin' => 50,
        'whatsapp_report' => 64,
        'events_create' => 33,
    ],

    // Si es true, cada sección (primer segmento después de /boxoffice) exige
    // que el usuario tenga en su menú un privilegio con ese menu_url.
    'enforce_menu_privileges' => env('BOXOFFICE_ENFORCE_MENU_PRIVILEGES', true),

    // Secciones accesibles para cualquier usuario autenticado.
    'public_sections' => ['inicio'],

    /*
    |--------------------------------------------------------------------------
    | Venta
    |--------------------------------------------------------------------------
    */

    'max_tickets_per_sale' => 50,

    'allowed_discounts' => [5, 10, 15, 20, 25, 30, 35, 40, 45, 50],

    // Valor de eventos.promocion / eventos_localidades.promocion que significa 4x2.
    'four_for_two_code' => 42,

    // Cargo fijo de la pasarela que se suma a la comisión bancaria en venta web.
    'gateway_fixed_fee' => 2.5,

    'vat_rate' => 0.16,

    // Costo por compra enviada por WhatsApp (reporte de envíos).
    'whatsapp_fee' => 15,

    /*
    |--------------------------------------------------------------------------
    | Reglas específicas por evento (heredadas de deboletox)
    |--------------------------------------------------------------------------
    |
    | Comportamiento que el sistema legacy tenía escrito con ids de evento
    | dentro del flujo. Se conserva tal cual, pero aislado aquí.
    |
    */

    'rules' => [
        // Eventos que ignoran cualquier promoción o porcentaje de descuento del evento.
        'promotion_disabled_events' => [1000],

        // Venta sin mapa: el boleto "gratis" de la promoción se cobra a este precio.
        'promotion_paid_price' => [
            922 => 199,
        ],

        // El descuento manual se aplica sobre el precio ya rebajado por promoción.
        'discount_over_promoted_price_events' => [944],

        // Comisión de servicio = 10 % de (precio + comisión) en vez del porcentaje bancario.
        'ten_percent_service_fee' => [
            'events' => [675],
            'categories' => [4],
        ],

        // Venta sin mapa vendida por mesa (cada lugar se parte en N asientos).
        'race_table_events' => [1234],

        // Silla adicional opcional por mesa.
        'extra_chair' => [
            'events' => [924, 925],
            'localities' => [
                10111, 10112, 10114, 10115, 10135, 10136, 10137, 10138, 10139,
                10140, 10272, 10273, 10274, 10275, 10276, 10277, 11144, 11145,
            ],
            'price' => 1700,
            'commission' => 100,
            'seat_suffix' => '-5',
        ],

        // Eventos cuya compra con terminal no genera comisión bancaria.
        'card_fee_exempt_events' => [331],

        // A partir de este id de evento la comisión se toma de lo guardado en cada boleto;
        // antes se recalculaba con el tipo de comisión del evento.
        'stored_commission_since_event' => 840,

        // Reportes: eventos con el formato general "PP" (columnas de pendientes y por cobrar).
        'report_pending_columns_events' => [430, 369],

        // Reporte general: precios distintos tomados de boletos pendientes (estatus 4).
        'report_pending_prices_events' => [430],

        // Reportes: cortesías que se suman a la venta a $1.00 desde este id de localidad.
        'courtesy_valued_localities' => [890 => 10080],

        // Reportes: la comisión no se reduce por el % de descuento del boleto.
        'commission_discount_exempt' => ['events' => [866], 'categories' => [5]],

        // Excel de participantes de carreras: catálogos por evento.
        'race_registration' => [
            'default' => [
                'sizes' => ['Chica', 'Mediana', 'Grande', 'Extra Grande', ''],
                'blood' => ['A', 'B', 'AB', 'O+', 'O-'],
                'genders' => ['Masculino', 'Femenino', 'Omitido'],
            ],
            'events' => [
                723 => ['sizes' => ['XS', 'S', 'M', 'L', 'XL'], 'extra' => ['label' => 'Personaje', 'options' => ['Superman', 'Batman', 'Wonder Woman', 'Spiderman', 'Capitan América']]],
                1086 => [
                    'extra' => ['label' => 'SubCategoria', 'options' => ['A SINGLES', 'B SINGLES', 'C SINGLES', 'B DOBLES', 'C DOBLES', 'D DOBLES']],
                    'extra_fixed_outside' => ['localities' => [12008, 12009], 'option' => 4],
                    'partner' => true,
                ],
                1149 => ['sizes' => ['CHICA', 'MEDIANA', 'GRANDE', 'EXTRA GRANDE', '06 AÑOS', '08 AÑOS', '10 AÑOS', '12 AÑOS']],
                1157 => [
                    'sizes' => ['CHICA', 'MEDIANA', 'GRANDE', 'EXTRA GRANDE', 'XL', 'Extra chica', 'XXL (Doble Extragrande'],
                    'extra' => ['label' => 'Corte Playera', 'options' => ['Corte dama', 'Corte unisex']],
                    'extra_b' => 'EMPRESA/INSTITUCIÓN',
                ],
            ],
        ],

        // Corte por usuario: eventos cuya comisión se toma de lo guardado en cada boleto.
        'user_cut_stored_commission_events' => [840, 844, 854],

        // Corte por usuario: comisión fija de 10 por boleto con descuento.
        'discounted_fixed_commission' => [
            857 => 10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Formatos de impresión
    |--------------------------------------------------------------------------
    |
    | eventos.pk_boleto_formato_tipo (o usuarios.pk_formato_boleto, que tiene
    | prioridad) => vista Blade en resources/views/boxoffice/print/formats.
    |
    */

    'print_formats' => [
        1 => 'superior-blanco',
        2 => 'inferior-blanco',
        3 => 'impresion-godex',
        4 => 'inferior-blanco-qr',
        5 => 'olmecas-impresora-termica',
        6 => 'impresora-termica',
        7 => 'maya-barcode',
        9 => 'nuevo-formato',
        10 => 'olmecas-membretado-v2',
        11 => 'formato-qr-10',
        12 => 'ticket-ribetec-chico',
        13 => 'ticket-ribetec-chico-olmecas',
        14 => 'formato-14-bilbao',
        15 => 'navidad-membretado',
        16 => 'mini-ticket-barcode',
        17 => 'mini-ticket-barcode-mesa',
        18 => 'nano-ticket-barcode',
        19 => 'ticket-ribetec',
        20 => 'ticket-ribetec-chico-especial',
        21 => 'las-olmecas-maya-barcode',
        22 => 'ticket-ribetec-tickets',
    ],

    // Formatos sin id asignado en legacy; quedan disponibles para asignarse aquí.
    'unassigned_print_formats' => [
        'formato-10032023',
        'full-pass-qr',
        'inferior-blanco-cortesia',
        'nuevo-formato-qr',
        'olmecas-membretado',
        'qr-olmecas',
        'reposicion',
        'boleto-membretado-mark3',
    ],

    // Las cortesías usaban un mapeo propio para estos ids.
    'courtesy_print_formats' => [
        2 => 'inferior-blanco-cortesia',
        10 => 'qr-olmecas',
        12 => 'formato-10032023',
        13 => 'olmecas-membretado',
    ],

    // boletos_formato_tipo.vista (nombre legacy) => vista nueva.
    'legacy_format_views' => [
        'SuperiorBlanco' => 'superior-blanco', 'InferiorBlanco' => 'inferior-blanco',
        'ImpresionGodex' => 'impresion-godex', 'InferiorBlancoQR' => 'inferior-blanco-qr',
        'OlmecasImpresoraTermica' => 'olmecas-impresora-termica', 'ImpresoraTermica' => 'impresora-termica',
        'MayaBarcode' => 'maya-barcode', 'NuevoFormato' => 'nuevo-formato',
        'OlmecasMembretadoV2' => 'olmecas-membretado-v2', 'FormatoQR10' => 'formato-qr-10',
        'TicketRibetecChico' => 'ticket-ribetec-chico', 'TicketRibetecChicoOlmecas' => 'ticket-ribetec-chico-olmecas',
        'Formato14Bilbao' => 'formato-14-bilbao', 'MiniTciketBarcode' => 'mini-ticket-barcode',
        'MiniTciketBarcodeMesa' => 'mini-ticket-barcode-mesa', 'NanoTicketBarcode' => 'nano-ticket-barcode',
        'TicketRibetec' => 'ticket-ribetec', 'TicketRibetecChicoEspecial' => 'ticket-ribetec-chico-especial',
        'LasOlmecasMayaBarcode' => 'las-olmecas-maya-barcode', 'TicketRibetecTickets' => 'ticket-ribetec-tickets',
        'Formato10032023' => 'formato-10032023', 'FullPassQR' => 'full-pass-qr',
        'InferiorBlancoCortesia' => 'inferior-blanco-cortesia', 'NavidadMembretado' => 'navidad-membretado',
        'NuevoFormatoQR' => 'nuevo-formato-qr', 'OlmecasMembretado' => 'olmecas-membretado',
        'QROlmecas' => 'qr-olmecas', 'Reposicion' => 'reposicion', 'BoletoMembretadoMark3' => 'boleto-membretado-mark3',
    ],

    'default_print_format' => 'superior-blanco',

    // Cambio de boletos: categoría de evento destino permitida.
    'change_target_category' => 1,

    'print' => [
        // Localidades con leyenda/diseño especial en los formatos QR y Ribetec.
        'highlight_localities' => [3551, 3553, 3554, 3563, 3564, 3565],

        // Eventos que imprimen la leyenda adicional en los formatos membretados.
        'legend_events' => [504],
    ],

    /*
    |--------------------------------------------------------------------------
    | Administración: valores con los que legacy daba de alta un evento
    |--------------------------------------------------------------------------
    */

    'new_event' => [
        'status' => 3,
        'commission_type' => 2,
        'commission' => 30,
        'ticket_format' => 13,
        'category' => 0,
        // Usuarios que reciben automáticamente cada evento nuevo.
        'assigned_users' => [57, 1],
    ],

    // Disco y carpeta donde se guardan las imágenes subidas desde administración.
    'images_disk' => env('BOXOFFICE_IMAGES_DISK', 'public'),
    'event_images_dir' => 'images/eventos',
    'venue_images_dir' => 'images/escenarios',

    /*
    |--------------------------------------------------------------------------
    | Recursos públicos
    |--------------------------------------------------------------------------
    */

    'event_images_url' => env('BOXOFFICE_EVENT_IMAGES_URL', 'https://deboleto.com/images/eventos/'),

    'digital_ticket_url' => env('BOXOFFICE_DIGITAL_TICKET_URL', 'https://deboleto.net/boleto/digital/'),

    'bond_registration_url' => env('BOXOFFICE_BOND_REGISTRATION_URL', 'https://deboleto.net/perfil/registro/bonos/'),

];
