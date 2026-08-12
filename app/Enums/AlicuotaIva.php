<?php

namespace App\Enums;

/**
 * Alícuotas de IVA que se pueden cargar en un producto o en un ítem de
 * factura en Argentina. Se guardan como número (decimal) en la base; este
 * enum es solo para la UI y la validación de las opciones válidas.
 */
enum AlicuotaIva: string
{
    case Exento = '0';
    case Reducida = '10.5';
    case General = '21';

    public function tasa(): float
    {
        return (float) $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Exento => 'Exento',
            self::Reducida => '10,5%',
            self::General => '21%',
        };
    }

    /**
     * Valores válidos para reglas de validación ('0', '10.5', '21').
     *
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /**
     * Pasa un número (21.00, "10.50", null) al string que usa el <select>
     * ("21", "10.5", "0"). Sirve para precargar el valor de un producto/ítem.
     */
    public static function normalizar(float|string|null $rate): string
    {
        $n = rtrim(rtrim(number_format((float) $rate, 2, '.', ''), '0'), '.');

        return $n === '' ? '0' : $n;
    }
}
