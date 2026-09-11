<?php

namespace App\Support;

use App\Models\CompanySettings;

/**
 * Códigos de barras de balanza (EAN-13 de "peso variable"): prefijo fijo
 * (típicamente 20-29) + código interno del producto + peso en gramos +
 * dígito verificador, con los tamaños de cada tramo configurables en
 * Configuración de Empresa porque cada marca de balanza los arma distinto
 * (2-5-5, 2-4-6, etc.) — siempre suman 13 dígitos en total.
 */
class ScaleBarcodeParser
{
    /**
     * @return array{sku: string, weightKg: float}|null null si el código no
     *                                                  tiene el formato/checksum esperado, o si la báscula está desactivada.
     */
    public static function parse(string $code, CompanySettings $settings): ?array
    {
        if (! $settings->barcode_scale_enabled || ! $settings->barcode_scale_prefix) {
            return null;
        }

        if (! ctype_digit($code) || ! str_starts_with($code, $settings->barcode_scale_prefix)) {
            return null;
        }

        $prefixLen = strlen($settings->barcode_scale_prefix);
        $codeDigits = $settings->barcode_scale_code_digits;
        $weightDigits = $settings->barcode_scale_weight_digits;
        $expectedLength = $prefixLen + $codeDigits + $weightDigits + 1; // +1 dígito verificador

        if (strlen($code) !== $expectedLength) {
            return null;
        }

        if (! Ean13::isValid($code)) {
            return null;
        }

        $offset = $prefixLen;
        $sku = substr($code, $offset, $codeDigits);
        $offset += $codeDigits;
        $weightGrams = (int) substr($code, $offset, $weightDigits);

        return ['sku' => $sku, 'weightKg' => $weightGrams / 1000];
    }
}
