<?php

namespace App\Support;

/**
 * Evita el "eco" en la sincronización con Tiendanube.
 *
 * Cuando traemos datos DE Tiendanube (importar, webhooks) escribimos en la base
 * local, y eso dispararía los observers que a su vez empujan DE VUELTA a
 * Tiendanube, en un ida y vuelta infinito. Para cortarlo, todo lo que escribe
 * datos que vienen de Tiendanube se corre dentro de mute(), y los observers no
 * hacen nada mientras esté silenciado.
 */
class TiendanubeSyncGuard
{
    private static int $muted = 0;

    /**
     * Corre $cb con los observers silenciados (soporta anidamiento).
     */
    public static function mute(callable $cb): mixed
    {
        self::$muted++;

        try {
            return $cb();
        } finally {
            self::$muted--;
        }
    }

    public static function muted(): bool
    {
        return self::$muted > 0;
    }
}
