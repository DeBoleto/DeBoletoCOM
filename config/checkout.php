<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Retención de asientos
    |--------------------------------------------------------------------------
    |
    | Minutos que un asiento elegido en el mapa queda apartado para la sesión
    | antes de liberarse. El mismo tiempo se muestra como cuenta regresiva.
    |
    */

    'hold_minutes' => 7,

    /*
    |--------------------------------------------------------------------------
    | Límites de la compra
    |--------------------------------------------------------------------------
    */

    'max_tickets' => 10,

    // Eventos con un máximo propio de boletos por compra.
    'max_tickets_events' => [
        1204 => 3,
        821 => 1,
    ],

    // Eventos en los que cada cliente solo puede comprar una vez.
    'single_purchase_events' => [1086],

    /*
    |--------------------------------------------------------------------------
    | Cargos adicionales
    |--------------------------------------------------------------------------
    */

    // Cargo fijo por envío digital de cada compra (eventos_compras.total_envio).
    'delivery_fee' => 15,

    // Ticket Defender: porcentaje sobre (precio + cargo por servicio) de cada boleto pagado.
    'defender_rate' => 0.08,

    // Meses sin intereses: tasa de la pasarela por plazo y cargo fijo por operación.
    'installments' => [
        'min_total' => 300,
        'fixed_fee' => 3,
        'plans' => [
            3 => 9,
            6 => 12,
        ],
    ],

    // Horas que tiene el cliente para pagar una transferencia SPEI.
    'transfer_hours' => 6,

    // Minutos que una compra con tarjeta puede esperar la autenticación 3D Secure.
    'card_payment_minutes' => 30,

    /*
    |--------------------------------------------------------------------------
    | Reglas por evento heredadas de deboletox
    |--------------------------------------------------------------------------
    */

    'specials' => [
        // Por defecto el tipo de boleto especial solo se ofrece en cada segundo boleto.
        'every_ticket_events' => [1058, 1064, 1065, 1100, 1111, 1113, 1116],
        // Eventos que no ofrecen tipos de boleto especiales en línea.
        'disabled_events' => [1133],
    ],

    // Nombre del producto adicional que se ofrece con el cupo de "estacionamiento".
    'parking_labels' => [
        848 => 'Meet & Greet',
    ],

    'parking_label' => 'Estacionamiento',

    // Páginas legales que se enlazan en el paso de pago.
    'terms_url' => env('CHECKOUT_TERMS_URL'),
    'privacy_url' => env('CHECKOUT_PRIVACY_URL'),

    /*
    |--------------------------------------------------------------------------
    | Cuentas de clientes
    |--------------------------------------------------------------------------
    */

    'customer_type' => 5,

    // Dominios de correo aceptados al registrarse con contraseña.
    'allowed_email_domains' => [
        'gmail.com', 'hotmail.com', 'outlook.com', 'outlook.es', 'icloud.com',
        'live.com.mx', 'live.com', 'yahoo.com', 'yahoo.com.mx', 'pemex.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Liga MX: aviso de compra para eventos de la categoría deportiva
    |--------------------------------------------------------------------------
    */

    'ligamx' => [
        'category' => 1,
        'home_team' => 'Olmecas',
        'stadium' => 'Estadio Centenario 27 de Febrero',
        'state' => 'TABASCO',
        'city' => 'Villahermosa',
    ],

];
