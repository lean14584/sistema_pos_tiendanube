<?php

namespace App\Support\ProviderImport;

use App\Support\Import\FieldSetHelpers;

class ProviderImportFields
{
    use FieldSetHelpers;

    public const FIELDS = [
        'tax_id' => ['label' => 'CUIT / DNI', 'required' => false, 'aliases' => ['cuit', 'dni', 'documento', 'tax_id']],
        'email' => ['label' => 'Email', 'required' => false, 'aliases' => ['email', 'correo', 'e-mail']],
        'phone' => ['label' => 'Teléfono', 'required' => false, 'aliases' => ['telefono', 'teléfono', 'celular', 'tel']],
        'address' => ['label' => 'Dirección', 'required' => false, 'aliases' => ['direccion', 'dirección', 'domicilio']],
        'name' => ['label' => 'Nombre / Razón social', 'required' => true, 'aliases' => ['nombre', 'razon social', 'razón social', 'proveedor']],
    ];

    /** Orden de resolución para el auto-sugerido (específico antes que genérico). */
    private const PRIORITY = ['tax_id', 'email', 'phone', 'address', 'name'];
}
