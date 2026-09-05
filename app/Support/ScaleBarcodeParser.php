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
     *                                                   tiene el formato/checksum esperado, o si la báscula está desactivada.
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

        if (! self::checksumValido($code)) {
            return null;
        }

        $offset = $prefixLen;
        $sku = substr($code, $offset, $codeDigits);
        $offset += $codeDigits;
        $weightGrams = (int) substr($code, $offset, $weightDigits);

        return ['sku' => $sku, 'weightKg' => $weightGrams / 1000];
    }

    /**
     * Checksum estándar EAN-13: dígitos impares (1ro, 3ro...) peso 1, pares
     * peso 3, sobre los primeros 12 dígitos; el 13vo tiene que coincidir.
     */
    private static function checksumValido(string $code): bool
    {
        if (strlen($code) !== 13) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $code[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        return $checkDigit === (int) $code[12];
    }
}
