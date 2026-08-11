<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\CondicionIva;
use App\Enums\TipoComprobanteInterno;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['cuit', 'razon_social', 'nombre_fantasia', 'domicilio', 'logo_path', 'punto_venta', 'condicion_iva', 'factura_a_habilitada', 'factura_b_habilitada'])]
class CompanySettings extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'condicion_iva' => CondicionIva::class,
            'factura_a_habilitada' => 'boolean',
            'factura_b_habilitada' => 'boolean',
        ];
    }

    /**
     * Tipos de comprobante elegibles en el formulario de factura, ya
     * filtrados por lo que la empresa tiene habilitado. Remito X y Devolución
     * son internos (siempre disponibles); Factura A/B dependen de los toggles.
     *
     * @return array<int, TipoComprobanteInterno>
     */
    public function tiposComprobanteSeleccionables(): array
    {
        return array_values(array_filter(
            TipoComprobanteInterno::seleccionablesEnFactura(),
            fn (TipoComprobanteInterno $t) => match ($t) {
                TipoComprobanteInterno::FacturaA => $this->factura_a_habilitada,
                TipoComprobanteInterno::FacturaB => $this->factura_b_habilitada,
                default => true,
            }
        ));
    }

    /**
     * Tipo de comprobante por defecto para una factura nueva: prioriza B,
     * después A, y si no hay ninguna fiscal habilitada cae a Remito X.
     */
    public function tipoComprobantePorDefecto(): TipoComprobanteInterno
    {
        return match (true) {
            $this->factura_b_habilitada => TipoComprobanteInterno::FacturaB,
            $this->factura_a_habilitada => TipoComprobanteInterno::FacturaA,
            default => TipoComprobanteInterno::RemitoX,
        };
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
