<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\CondicionIva;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['cuit', 'razon_social', 'nombre_fantasia', 'domicilio', 'logo_path', 'punto_venta', 'condicion_iva'])]
class CompanySettings extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'condicion_iva' => CondicionIva::class,
        ];
    }

    /**
     * Nombre a mostrar en PDFs y demás lugares de cara al cliente: prioriza
     * el nombre de fantasía sobre la razón social, y cae al nombre de la
     * app si todavía no se cargaron los datos de la empresa.
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => $this->nombre_fantasia ?: ($this->razon_social ?: config('app.name')));
    }

    /**
     * Fila única de configuración de la empresa (sembrada por la migración
     * con id=1), para no repetir firstOrCreate en cada lugar que la usa.
     */
    public static function current(): self
    {
        return static::query()->findOrFail(1);
    }
}
