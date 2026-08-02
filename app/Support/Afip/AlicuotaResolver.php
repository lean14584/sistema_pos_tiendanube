<?php

namespace App\Support\Afip;

use DomainException;

/**
 * Código de 4 dígitos de la tabla "Alícuotas del IVA" que exige el Libro de
 * IVA Digital (RG 4597) — ver Libro-IVA-Digital-Tablas-del-Sistema.pdf,
 * publicado por AFIP. No inventar códigos nuevos acá: si aparece una tasa
 * que no está en esta tabla, es un dato mal cargado en la factura/compra,
 * no un caso a soportar.
 */
final class AlicuotaResolver
{
    private const CODIGOS = [
        '0.00' => '0003',
        '2.50' => '0009',
        '5.00' => '0008',
        '10.50' => '0004',
        '21.00' => '0005',
        '27.00' => '0006',
    ];

    public static function codigo(float $tasa): string
    {
        $key = number_format($tasa, 2, '.', '');

        return self::CODIGOS[$key]
            ?? throw new DomainException("Alícuota de IVA sin código AFIP: {$tasa}%.");
    }
}
