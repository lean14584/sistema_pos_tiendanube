<?php

namespace App\Support\HistoricalSaleImport;

use App\Support\Import\FieldSetHelpers;

class HistoricalSaleImportFields
{
    use FieldSetHelpers;

    public const FIELDS = [
        'tax_id' => ['label' => 'CUIT / DNI cliente', 'required' => false, 'aliases' => ['cuit', 'dni', 'documento', 'tax_id']],
        'sale_date' => ['label' => 'Fecha', 'required' => true, 'aliases' => ['fecha de venta', 'fecha comprobante', 'fecha']],
        'comprobante_type' => ['label' => 'Tipo de comprobante', 'required' => false, 'aliases' => ['tipo de comprobante', 'tipo comprobante', 'comprobante', 'tipo']],
        'comprobante_number' => ['label' => 'Número', 'required' => false, 'aliases' => ['numero de comprobante', 'número de comprobante', 'numero', 'número', 'nro']],
        'total' => ['label' => 'Total', 'required' => true, 'aliases' => ['total', 'importe', 'monto']],
        'notes' => ['label' => 'Observaciones', 'required' => false, 'aliases' => ['observaciones', 'notas', 'detalle']],
        'client_name' => ['label' => 'Cliente', 'required' => true, 'aliases' => ['cliente', 'razon social', 'razón social', 'nombre']],
    ];

    /** Orden de resolución para el auto-sugerido (específico antes que genérico). */
    private const PRIORITY = ['tax_id', 'sale_date', 'comprobante_type', 'comprobante_number', 'total', 'notes', 'client_name'];
}
