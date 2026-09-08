<?php

namespace App\Support\Import;

use App\Enums\TipoDocumento;

/**
 * Infiere el tipo de documento a partir de la cantidad de dígitos del
 * CUIT/DNI, para los imports de Tango que no traen una columna separada de
 * "tipo de documento" (Cliente/Proveedor solo tienen una columna de
 * CUIT/DNI en la práctica).
 */
class TaxIdResolver
{
    public static function tipoDocumentoPara(?string $taxId): TipoDocumento
    {
        $digitos = preg_replace('/\D/', '', (string) $taxId);

        return match (true) {
            strlen($digitos) === 11 => TipoDocumento::Cuit,
            in_array(strlen($digitos), [7, 8], true) => TipoDocumento::Dni,
            default => TipoDocumento::SinIdentificar,
        };
    }
}
