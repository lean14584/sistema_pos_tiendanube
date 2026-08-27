<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['headers_hash', 'headers', 'mapping'])]
class ProductImportMapping extends Model
{
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'mapping' => 'array',
        ];
    }

    /** Hash estable para una lista de cabeceras de Excel (mismo set = mismo archivo "de forma"). */
    public static function hashFor(array $headers): string
    {
        $normalizadas = collect($headers)
            ->map(fn ($h) => mb_strtolower(trim((string) $h)))
            ->sort()
            ->values()
            ->all();

        return sha1(json_encode($normalizadas));
    }

    public static function recordarPara(array $headers): ?self
    {
        return self::where('headers_hash', self::hashFor($headers))->first();
    }

    public static function guardarPara(array $headers, array $mapping): self
    {
        return self::updateOrCreate(
            ['headers_hash' => self::hashFor($headers)],
            ['headers' => $headers, 'mapping' => $mapping],
        );
    }
}
