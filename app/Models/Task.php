<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'description', 'assigned_to', 'assigned_by', 'status'])]
class Task extends Model
{
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** Se pide en el sidebar en TODAS las páginas: memoizado por request. */
    public static function openCountCached(User $user): int
    {
        return once(function () use ($user) {
            $query = self::whereIn('status', ['pendiente', 'en_progreso']);

            return $user->role === Role::Admin
                ? $query->count()
                : $query->where('assigned_to', $user->id)->count();
        });
    }
}
