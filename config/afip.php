<?php

return [

    'cuit' => env('AFIP_CUIT'),

    // homologacion|produccion — cambiar de ambiente es una decisión de
    // deploy, no algo editable desde la pantalla de "Datos de la empresa".
    'environment' => env('AFIP_ENV', 'homologacion'),

    'cert_path' => storage_path('afip/certificado.crt'),
    'key_path' => storage_path('afip/privada.key'),

    'wsaa_url' => env('AFIP_ENV', 'homologacion') === 'produccion'
        ? 'https://wsaa.afip.gov.ar/ws/services/LoginCms'
        : 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms',

    'wsfe_url' => env('AFIP_ENV', 'homologacion') === 'produccion'
        ? 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL'
        : 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL',

    // Margen de seguridad antes de que expire el token de WSAA (AFIP los
    // emite con ~12hs de validez) para evitar pedir CAE con un token que
    // vence a mitad de la llamada.
    'token_cache_buffer_minutes' => 10,

];
