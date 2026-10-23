<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * access_token y webhook_secret pasan a tener cast 'encrypted' en el
     * modelo (ver SucursalMercadoPagoConfig::casts()) — hasta ahora quedaban
     * en texto plano en la base. Esta migración re-encripta en el lugar las
     * filas que ya existan.
     *
     * A propósito usa el query builder (DB::table), NO el modelo Eloquent:
     * para cuando esta migración corra, el código del modelo YA tiene el
     * cast 'encrypted' puesto (se despliega junto), así que leer por
     * Eloquent intentaría desencriptar un valor que todavía es texto plano y
     * tiraría DecryptException de entrada. El query builder lee/escribe el
     * valor crudo de la columna, sin pasar por ningún cast.
     */
    public function up(): void
    {
        DB::table('sucursal_mercadopago_configs')
            ->whereNotNull('access_token')
            ->orWhereNotNull('webhook_secret')
            ->get(['id', 'access_token', 'webhook_secret'])
            ->each(function ($row) {
                $updates = [];

                if ($row->access_token !== null && ! self::pareceYaEncriptado($row->access_token)) {
                    $updates['access_token'] = Crypt::encryptString($row->access_token);
                }

                if ($row->webhook_secret !== null && ! self::pareceYaEncriptado($row->webhook_secret)) {
                    $updates['webhook_secret'] = Crypt::encryptString($row->webhook_secret);
                }

                if ($updates !== []) {
                    DB::table('sucursal_mercadopago_configs')->where('id', $row->id)->update($updates);
                }
            });
    }

    /**
     * Para que la migración sea idempotente si se corre más de una vez (este
     * proyecto se despliega por FTP a varios hostings sin SSH, ver CLAUDE.md
     * de otros proyectos del mismo linaje — no siempre hay garantía de que
     * `php artisan migrate` corra una sola vez de forma prolija). Un valor
     * encriptado por Laravel es JSON base64 con claves iv/mac/value/tag;
     * texto plano real nunca tiene esa forma.
     */
    private static function pareceYaEncriptado(string $value): bool
    {
        $decoded = json_decode(base64_decode($value, true) ?: '', true);

        return is_array($decoded) && isset($decoded['iv'], $decoded['mac'], $decoded['value']);
    }

    /**
     * No hay forma genérica de "desencriptar en el lugar" acá sin asumir que
     * el cast todavía está activo en el modelo al momento del rollback — y
     * para cuando se corre down(), probablemente ya no lo esté. Dejar las
     * filas encriptadas es la opción segura (perder el plaintext histórico es
     * preferible a loguear secretos reales en una migración de rollback).
     */
    public function down(): void
    {
        // Intencionalmente no-op, ver docblock de up().
    }
};
