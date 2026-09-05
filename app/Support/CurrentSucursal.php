<?php

namespace App\Support;

use App\Models\Sucursal;
use Illuminate\Support\Facades\Auth;

/**
 * Resuelve "en qué sucursal está operando ahora mismo" el usuario logueado:
 * un cajero/vendedor siempre trabaja en la suya (sucursal_id fijo, sin
 * elegir). Un admin es global y puede cambiar de sucursal activa desde el
 * selector del sidebar (guardado en sesión).
 *
 * Nunca devuelve null en un sistema migrado correctamente: siempre hay al
 * menos una sucursal (la migración de product_stocks se asegura de crear
 * una "Principal" si no existía ninguna) y esta clase cae a "la primera
 * sucursal" como red de seguridad final.
 */
class CurrentSucursal
{
    public static function id(): ?int
    {
        $user = Auth::user();

        if ($user?->sucursal_id) {
            return $user->sucursal_id;
        }

        if ($user?->esAdminGlobal()) {
            $sessionId = session('sucursal_activa_id');

            if ($sessionId && Sucursal::whereKey($sessionId)->exists()) {
                return (int) $sessionId;
            }
        }

        return Sucursal::orderBy('id')->value('id');
    }

    public static function get(): ?Sucursal
    {
        $id = self::id();

        return $id ? Sucursal::find($id) : null;
    }

    public static function set(int $sucursalId): void
    {
        session(['sucursal_activa_id' => $sucursalId]);
    }
}
