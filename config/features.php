<?php

/**
 * Módulos que se pueden apagar por instalación (vía .env), sin bifurcar el
 * código: cada cliente que corre esta misma app (pos-tiendanube, deco-hogar,
 * etc.) prende o apaga lo que no usa. Todo default en true para no romper
 * ninguna instalación existente que no declare estas variables.
 */
return [
    'multisucursal' => env('FEATURE_MULTISUCURSAL', true),
    'tiendanube' => env('FEATURE_TIENDANUBE', true),
    'invoices_manual_create' => env('FEATURE_INVOICES_MANUAL_CREATE', true),
    'product_batches' => env('FEATURE_PRODUCT_BATCHES', true),
    'price_lists' => env('FEATURE_PRICE_LISTS', true),
    'stock_transfers' => env('FEATURE_STOCK_TRANSFERS', true),
    'vencimientos_finanzas' => env('FEATURE_VENCIMIENTOS_FINANZAS', true),
    'sell_by_weight' => env('FEATURE_SELL_BY_WEIGHT', true),
    'historical_sales' => env('FEATURE_HISTORICAL_SALES', true),

    // Al revés que las anteriores: default false para no cambiarle a nadie
    // la validación de clientes que ya tiene (email obligatorio, celular
    // opcional). DECO-HOGAR la prende porque sus clientes no siempre tienen
    // email pero sí celular (contacto real es por WhatsApp).
    'client_phone_required' => env('FEATURE_CLIENT_PHONE_REQUIRED', false),
];
