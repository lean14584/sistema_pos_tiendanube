<?php

namespace App\Concerns;

use App\Models\AuditLog;
use BackedEnum;
use Illuminate\Support\Carbon;

/**
 * Registra altas/bajas/modificaciones en audit_logs. Se apoya en los
 * eventos nativos de Eloquent — por diseño, NO captura updates hechos con
 * el query builder (ej. Product::whereKey($id)->increment('stock', ...)
 * en StockAdjuster), solo lo que pasa por ->save()/->update()/->create()/
 * ->delete(). Eso separa naturalmente los cambios automáticos (ventas,
 * compras, devoluciones) de los cambios manuales hechos por una persona,
 * que es lo único que tiene sentido auditar acá.
 */
trait Auditable
{
    /**
     * Diff calculado en `updating` (antes de que la query corra y de que
     * Eloquent sincronice $original) y usado en `updated` (que solo se
     * dispara si el guardado tuvo éxito). No usar $this->propiedad de
     * Eloquent normal para esto: como Model::__set redirige cualquier
     * asignación a setAttribute(), terminaría guardándose como si fuera
     * una columna real. Al declararla acá como propiedad de la clase, el
     * acceso directo dentro de este trait no pasa por esa magia.
     */
    protected array $auditPendingDiff = [];

    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->writeAuditLog('created', $model->auditCreatedDiff());
        });

        static::updating(function ($model) {
            $model->auditPendingDiff = $model->auditUpdatedDiff();
        });

        static::updated(function ($model) {
            $diff = $model->auditPendingDiff;
            $model->auditPendingDiff = [];

            if ($diff !== []) {
                $model->writeAuditLog('updated', $diff);
            }
        });

        static::deleted(function ($model) {
            $model->writeAuditLog('deleted', $model->auditDeletedDiff());
        });
    }

    /**
     * Campos que nunca se auditan (ej. password) además de los comunes a
     * todos los modelos. Sobreescribir con `protected array $auditExclude`
     * en el modelo que use el trait.
     */
    protected function auditExcluded(): array
    {
        return array_merge(['id', 'created_at', 'updated_at'], $this->auditExclude ?? []);
    }

    private function auditCreatedDiff(): array
    {
        return collect($this->getAttributes())
            ->except($this->auditExcluded())
            ->map(fn ($value) => ['old' => null, 'new' => $this->auditScalar($value)])
            ->all();
    }

    private function auditUpdatedDiff(): array
    {
        return collect($this->getDirty())
            ->except($this->auditExcluded())
            ->mapWithKeys(fn ($value, $key) => [
                $key => ['old' => $this->auditScalar($this->getOriginal($key)), 'new' => $this->auditScalar($value)],
            ])
            ->all();
    }

    private function auditDeletedDiff(): array
    {
        return collect($this->getAttributes())
            ->except($this->auditExcluded())
            ->map(fn ($value) => ['old' => $this->auditScalar($value), 'new' => null])
            ->all();
    }

    private function auditScalar(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof Carbon) {
            return $value->toDateTimeString();
        }

        return $value;
    }

    private function writeAuditLog(string $event, array $changes): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'event' => $event,
            'changes' => $changes,
        ]);
    }
}
