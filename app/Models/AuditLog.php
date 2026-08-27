<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'auditable_type', 'auditable_id', 'event', 'changes'])]
class AuditLog extends Model
{
    const UPDATED_AT = null;

    private const ETIQUETAS = [
        Invoice::class => 'Factura',
        Quote::class => 'Presupuesto',
        Product::class => 'Producto',
        Category::class => 'Categoría',
        PriceList::class => 'Lista de precios',
        Promotion::class => 'Promoción',
        PromotionGroup::class => 'Promoción por familia',
        Client::class => 'Cliente',
        Provider::class => 'Proveedor',
        Purchase::class => 'Compra',
        User::class => 'Usuario',
        CompanySettings::class => 'Datos de la empresa',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function etiquetaModelo(string $tipo): string
    {
        return self::ETIQUETAS[$tipo] ?? class_basename($tipo);
    }

    /**
     * @return array<string, string> clase => etiqueta, para el filtro de la pantalla de auditoría.
     */
    public static function tiposAuditados(): array
    {
        return self::ETIQUETAS;
    }
}
