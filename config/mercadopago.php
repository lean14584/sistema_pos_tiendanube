<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mercado Pago — Cobro con QR (modelo "QR Pedido" / Instore integrado)
    |--------------------------------------------------------------------------
    |
    | El QR es FIJO (se imprime una vez y se pega en la pared). Está atado a
    | una "caja" (POS) que se crea con `php artisan mp:setup`. Cuando se cobra
    | una factura, el sistema le "empuja" el monto a esa caja por API y el
    | cliente ve el importe ya cargado al escanear el QR de la pared.
    |
    | Con el ACCESS TOKEN de TEST no se mueve plata real: se prueba con las
    | cuentas de comprador/vendedor de prueba de tu panel de Mercado Pago.
    |
    */

    // Access Token de la aplicación (TEST-... para pruebas, APP_USR-... en producción)
    'access_token' => env('MP_ACCESS_TOKEN'),

    // Base de la API de Mercado Pago
    'base_url' => env('MP_BASE_URL', 'https://api.mercadopago.com'),

    // Identificadores propios de la sucursal y la caja (los elegís vos)
    'store_external_id' => env('MP_STORE_EXTERNAL_ID', 'SUC001'),
    'pos_external_id' => env('MP_POS_EXTERNAL_ID', 'CAJA001'),

    // Nombre visible de la sucursal / caja
    'store_name' => env('MP_STORE_NAME', 'Sucursal Principal'),
    'pos_name' => env('MP_POS_NAME', 'Caja 1'),

    // Dirección de la sucursal (Mercado Pago valida provincia/ciudad reales).
    'store_street' => env('MP_STORE_STREET', 'Av. Corrientes'),
    'store_number' => env('MP_STORE_NUMBER', '1000'),
    'store_city' => env('MP_STORE_CITY', 'Balvanera'),
    'store_state' => env('MP_STORE_STATE', 'Capital Federal'),
    'store_lat' => (float) env('MP_STORE_LAT', -34.6037),
    'store_lng' => (float) env('MP_STORE_LNG', -58.3816),

    // Rubro (MCC). 621102 = comercio general. Ver tabla de categorías de MP.
    'category' => (int) env('MP_POS_CATEGORY', 621102),

    // URL pública que Mercado Pago llama cuando cambia el estado del pago.
    // Opcional: si no la ponés, el sistema igual detecta el pago por polling.
    'notification_url' => env('MP_NOTIFICATION_URL'),

    // Cada cuántos segundos la pantalla le pregunta a MP si ya pagaron.
    'poll_seconds' => (int) env('MP_POLL_SECONDS', 3),

];
