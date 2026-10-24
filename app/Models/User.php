<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\Role;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'active', 'sucursal_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    // Nunca se audita el hash de la contraseña, ni por seguridad ni
    // porque aporte algo útil como diff.
    protected array $auditExclude = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'active' => 'boolean',
        ];
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function cashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /** Un admin es global: opera en cualquier sucursal, no está atado a una. */
    public function esAdminGlobal(): bool
    {
        return $this->role === Role::Admin;
    }

    /** Un encargado manda en su propia sucursal (usuarios, compras, auditoría) pero no es global. */
    public function esEncargado(): bool
    {
        return $this->role === Role::Encargado;
    }

    /**
     * Borrar (productos, categorías, clientes, comprobantes, etc.) queda
     * reservado a Admin/Encargado — Vendedor y Cajero pueden crear/editar
     * en los módulos que tengan habilitados, pero no eliminar. Mismo
     * criterio ya usado en Invoices\Show::delete().
     */
    public function puedeEliminar(): bool
    {
        return $this->esAdminGlobal() || $this->esEncargado();
    }

    /**
     * Usuarios activos, cacheados 60s — se repetía sin cachear en cada
     * render() de Tasks\Create. Trae solo id/name/role (sin password) y
     * deja afuera del caché la exclusión del usuario actual (depende de
     * quién esté mirando), que el caller debe aplicar en memoria.
     *
     * Se cachea `toArray()`, no la Collection de modelos tal cual: con el
     * driver `database`, serializar un objeto Eloquent puede corromperse en
     * el charset utf8mb4 de `cache.value` y romper "Return value must be of
     * type Collection" en la próxima lectura (ver el mismo fix en
     * PuntoVenta::activosParaSucursalCached()).
     */
    public static function activosParaSelectCached(): Collection
    {
        $rows = Cache::remember(
            'users:activos-select-list-v2',
            now()->addSeconds(60),
            fn () => self::where('active', true)->orderBy('name')->get(['id', 'name', 'role'])->toArray(),
        );

        return self::hydrate($rows);
    }
}
