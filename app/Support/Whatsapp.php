<?php

namespace App\Support;

class Whatsapp
{
    /**
     * Arma un enlace wa.me con el mensaje ya cargado. Devuelve null si el
     * teléfono está vacío o no tiene dígitos utilizables.
     *
     * El número se normaliza a solo dígitos y, si no trae código de país,
     * se le antepone 54 (Argentina). Los celulares deberían guardarse con
     * el 9 (por ej. 5493511234567) para que WhatsApp los reconozca.
     */
    public static function link(?string $phone, string $message = ''): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '' || $digits === null) {
            return null;
        }

        if (! str_starts_with($digits, '54')) {
            $digits = '54'.$digits;
        }

        $url = "https://wa.me/{$digits}";

        if (trim($message) !== '') {
            $url .= '?text='.rawurlencode($message);
        }

        return $url;
    }
}
