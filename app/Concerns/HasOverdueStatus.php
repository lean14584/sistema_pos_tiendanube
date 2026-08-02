<?php

namespace App\Concerns;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;

/**
 * Compartido por Invoice y Purchase: "vencida" es un estado derivado, no
 * guardado en `status` (evita tener que correr un job para pasar facturas a
 * "overdue" el día que cruzan su fecha de vencimiento).
 */
trait HasOverdueStatus
{
    protected function isOverdue(): Attribute
    {
        return Attribute::get(fn () => $this->status === InvoiceStatus::Pending
            && $this->due_date !== null
            && $this->due_date->lt(Carbon::today()));
    }

    protected function effectiveStatus(): Attribute
    {
        return Attribute::get(fn () => $this->is_overdue ? InvoiceStatus::Overdue : $this->status);
    }

    /**
     * Filtra por el mismo valor que devuelve `effective_status`, pero a
     * nivel de query — así los listados pueden paginar en vez de traer toda
     * la tabla a PHP para filtrar ahí. Debe reflejar exactamente la lógica
     * de isOverdue()/effectiveStatus() de arriba.
     */
    public function scopeWithEffectiveStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            InvoiceStatus::Overdue->value => $query->where('status', InvoiceStatus::Pending)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', Carbon::today()),
            InvoiceStatus::Pending->value => $query->where('status', InvoiceStatus::Pending)
                ->where(fn ($q) => $q->whereNull('due_date')->orWhereDate('due_date', '>=', Carbon::today())),
            default => $query->where('status', $status),
        };
    }
}
