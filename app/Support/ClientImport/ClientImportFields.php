<?php

namespace App\Support\ClientImport;

use App\Support\Import\FieldSetHelpers;

class ClientImportFields
{
    use FieldSetHelpers;

    public const FIELDS = [
        'tax_id' => ['label' => 'CUIT / DNI', 'required' => false, 'aliases' => ['cuit', 'dni', 'documento', 'tax_id']],
        'condicion_iva' => ['label' => 'Condición IVA', 'required' => false, 'aliases' => ['condicion iva', 'condición iva', 'categoria fiscal', 'categoría fiscal', 'iva']],
        'credit_limit' => ['label' => 'Límite de crédito', 'required' => false, 'aliases' => ['limite de credito', 'límite de crédito', 'limite credito']],
        'email' => ['label' => 'Email', 'required' => false, 'aliases' => ['email', 'correo', 'e-mail']],
        'phone' => ['label' => 'Teléfono', 'required' => false, 'aliases' => ['telefono', 'teléfono', 'celular', 'tel']],
        'address' => ['label' => 'Dirección', 'required' => false, 'aliases' => ['direccion', 'dirección', 'domicilio']],
        'name' => ['label' => 'Nombre / Razón social', 'required' => true, 'aliases' => ['nombre', 'razon social', 'razón social', 'cliente']],
    ];

    /** Orden de resolución para el auto-sugerido (específico antes que genérico). */
    private const PRIORITY = ['tax_id', 'condicion_iva', 'credit_limit', 'email', 'phone', 'address', 'name'];
}
