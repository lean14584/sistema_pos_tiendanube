<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Un punto de venta ARCA (ex AFIP) habilitado para una sucursal. Una
 * sucursal puede tener varios (ej.: mostrador + online); el número tiene
 * que ser único en toda la empresa (un mismo CUIT no puede repetir punto
 * de venta entre sucursales).
 */
#[Fillable(['sucursal_id', 'numero', 'nombre', 'active'])]
class PuntoVenta extends Model
{
    protected $table = 'puntos_venta';

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function label(): string
    {
        $numero = str_pad((string) $this->numero, 4, '0', STR_PAD_LEFT);

        return $this->nombre ? "{$numero} — {$this->nombre}" : $numero;
    }

    /**
     * Puntos de venta activos de una sucursal, cacheados 60s — se repetía en
     * cada round-trip de Livewire (cada click) en Pos\Index,
     * Invoices\Create y Quotes\Show, no solo en la carga de página. A
     * diferencia de los demás *ParaSelectCached() del código (que cachean
     * JSON de id/name), acá hace falta la Collection de modelos (la vista
     * llama $pv->label(), no solo propiedades) — pero NUNCA cachear el
     * objeto Eloquent tal cual con el driver `database`: `serialize()`
     * puede meter bytes que el charset utf8mb4 de la columna `cache.value`
     * corrompe en el INSERT sin avisar, y la lectura siguiente revienta con
     * "Return value must be of type Collection, __PHP_Incomplete_Class
     * returned" (pasó en producción, 100% reproducible, no era un caché
     * viejo). Se cachea `toArray()` (solo escalares, serializa siempre
     * limpio) y se reconstruyen los modelos reales al leer — mismo patrón
     * que ya usa LibroIvaCalculator para este problema.
     */
    public static function activosParaSucursalCached(int $sucursalId): Collection
    {
        $rows = Cache::remember(
            "puntos-venta:activos:{$sucursalId}:v2",
            now()->addSeconds(60),
            fn () => self::where('sucursal_id', $sucursalId)->where('active', true)->orderBy('id')->get()->toArray(),
        );

        return self::hydrate($rows);
    }
}
