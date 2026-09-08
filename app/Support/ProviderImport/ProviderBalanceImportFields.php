<?php

namespace App\Support\ProviderImport;

use App\Support\Import\FieldSetHelpers;

/**
 * Campos para cargar el saldo de apertura de cuenta corriente de
 * proveedores ya existentes (migración desde otro sistema, ej. Tango). No
 * crea proveedores nuevos: una fila que no matchea ningún proveedor se
 * omite. Espejo de ClientBalanceImportFields.
 */
class ProviderBalanceImportFields
{
    use FieldSetHelpers;

    public const FIELDS = [
        'tax_id' => ['label' => 'CUIT / DNI', 'required' => false, 'aliases' => ['cuit', 'dni', 'documento', 'tax_id']],
        'opening_balance_date' => ['label' => 'Fecha de corte', 'required' => false, 'aliases' => ['fecha de corte', 'fecha saldo', 'fecha']],
        'opening_balance' => ['label' => 'Saldo (+ si le debemos, - si nos debe)', 'required' => true, 'aliases' => ['saldo inicial', 'saldo', 'importe', 'monto']],
        // No es "required": una fila puede identificarse solo por CUIT/DNI.
        // procesarFilas() exige que haya al menos uno de los dos.
        'name' => ['label' => 'Nombre / Razón social', 'required' => false, 'aliases' => ['nombre', 'razon social', 'razón social', 'proveedor']],
    ];

    /** Orden de resolución para el auto-sugerido (específico antes que genérico). */
    private const PRIORITY = ['tax_id', 'opening_balance_date', 'opening_balance', 'name'];
}
