<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tiendanube / Nuvemshop
    |--------------------------------------------------------------------------
    |
    | El Store ID y el Access Token se cargan desde la pantalla de
    | configuración (se guardan en company_settings), no acá. Este archivo
    | sólo tiene lo que no cambia entre tiendas.
    |
    | La API exige un User-Agent que identifique a la app con un email de
    | contacto; si no, rechaza las llamadas.
    |
    */

    'base_url' => env('TIENDANUBE_BASE_URL', 'https://api.tiendanube.com/v1'),

    'user_agent' => env('TIENDANUBE_USER_AGENT', 'Sistema Facturacion (soporte@localhost)'),

    // Cuántos registros pedir por página al listar productos/pedidos.
    'per_page' => (int) env('TIENDANUBE_PER_PAGE', 50),

    // Tope de páginas por importación (red de seguridad para no colgarse).
    'max_pages' => (int) env('TIENDANUBE_MAX_PAGES', 100),

];
