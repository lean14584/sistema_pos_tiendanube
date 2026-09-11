<?php

namespace App\Support;

/**
 * EAN13 a partir del campo "código" (sku) del producto, para poder
 * escanearlo en el POS en vez de tipearlo (ver Pos\Index::addByBarcode()).
 * Dos casos:
 * - El sku ya es un código real de fábrica de 13 dígitos con checksum
 *   válido: se usa tal cual, no se inventa nada.
 * - El sku es un código interno corto (ej. "9876"): se completa con ceros
 *   a la izquierda hasta 12 dígitos y se calcula el dígito verificador.
 *   No es un GTIN registrado, es solo para que el lector del POS lo pueda
 *   leer — Pos\Index::addByBarcode() sabe recuperar el sku original.
 */
class Ean13
{
    public static function fromSku(?string $sku): ?string
    {
        if ($sku === null || $sku === '' || ! ctype_digit($sku)) {
            return null;
        }

        if (strlen($sku) === 13) {
            return self::isValid($sku) ? $sku : null;
        }

        if (strlen($sku) > 12) {
            return null;
        }

        $base = str_pad($sku, 12, '0', STR_PAD_LEFT);

        return $base.self::checkDigit($base);
    }

    /**
     * Recupera el sku "corto" de un código escaneado con el esquema de
     * ceros a la izquierda de arriba. Null si el checksum no da (no es un
     * EAN13 válido, sea del esquema que sea).
     */
    public static function stripPadding(string $code): ?string
    {
        if (! self::isValid($code)) {
            return null;
        }

        return ltrim(substr($code, 0, 12), '0') ?: '0';
    }

    /** Checksum estándar EAN13: dígitos impares (1ro, 3ro...) peso 1, pares peso 3, sobre los primeros 12. */
    public static function checkDigit(string $twelveDigits): string
    {
        $sum = 0;

        foreach (str_split($twelveDigits) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return (string) ((10 - ($sum % 10)) % 10);
    }

    public static function isValid(string $code): bool
    {
        return strlen($code) === 13
            && ctype_digit($code)
            && self::checkDigit(substr($code, 0, 12)) === substr($code, 12, 1);
    }
}
