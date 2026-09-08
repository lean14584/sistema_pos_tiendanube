<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Recuerda, por "forma" de archivo (hash de las cabeceras del Excel) y por
 * pantalla de import (`context`: products, clients, providers, etc.), qué
 * columna del Excel corresponde a cada campo del sistema — para no tener
 * que reemparejar cada vez que se sube un archivo con el mismo formato
 * (ej. siempre el mismo proveedor/planilla).
 */
#[Fillable(['context', 'headers_hash', 'headers', 'mapping'])]
class ImportMapping extends Model
{
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'mapping' => 'array',
        ];
    }

    public static function hashFor(array $headers): string
    {
        $normalizadas = collect($headers)
            ->map(fn ($h) => mb_strtolower(trim((string) $h)))
            ->sort()
            ->values()
            ->all();

        return sha1(json_encode($normalizadas));
    }

    public static function recordarPara(string $context, array $headers): ?self
    {
        return self::where('context', $context)->where('headers_hash', self::hashFor($headers))->first();
    }

    public static function guardarPara(string $context, array $headers, array $mapping): self
    {
        return self::updateOrCreate(
            ['context' => $context, 'headers_hash' => self::hashFor($headers)],
            ['headers' => $headers, 'mapping' => $mapping],
        );
    }
}
