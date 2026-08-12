<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Product;
use App\Services\MercadoPago\MercadoPagoQrService;
use App\Services\Tiendanube\TiendanubeClient;
use Illuminate\Support\Collection;

/**
 * Chequeos de "salud" del sistema para la pantalla de Estado: cosas que el
 * dueño querría revisar de un vistazo (respaldo al día, certificado ARCA por
 * vencer, stock, facturas sin emitir, conexiones, disco).
 *
 * Cada chequeo devuelve un estado: ok | warning | error | info.
 */
class SystemHealth
{
    /**
     * @return Collection<int, array{clave:string, label:string, estado:string, detalle:string}>
     */
    public function chequeos(): Collection
    {
        return collect([
            $this->respaldo(),
            $this->certificadoArca(),
            $this->facturasSinEmitir(),
            $this->stock(),
            $this->conexion('Mercado Pago', app(MercadoPagoQrService::class)->isConfigured()),
            $this->conexion('Tiendanube', app(TiendanubeClient::class)->isConfigured()),
            $this->disco(),
        ]);
    }

    /** Cantidad de avisos activos (warning + error), para mostrar en el dashboard. */
    public function avisos(): int
    {
        return $this->chequeos()->whereIn('estado', ['warning', 'error'])->count();
    }

    private function respaldo(): array
    {
        $dir = config('backups.path');
        $archivos = is_dir($dir) ? (glob(rtrim($dir, '/\\').DIRECTORY_SEPARATOR.'respaldo-*.zip') ?: []) : [];

        if ($archivos === []) {
            return $this->check('respaldo', 'Respaldo', 'warning', 'Todavía no hay ningún respaldo guardado. Generá uno.');
        }

        $ultimo = collect($archivos)->max(fn (string $f) => filemtime($f));
        $dias = (int) floor((time() - $ultimo) / 86400);
        $cuando = 'último hace '.($dias === 0 ? 'menos de un día' : "{$dias} día(s)");

        return $dias <= 2
            ? $this->check('respaldo', 'Respaldo', 'ok', $cuando)
            : $this->check('respaldo', 'Respaldo', 'warning', $cuando.'. Conviene hacer uno nuevo.');
    }

    private function certificadoArca(): array
    {
        $cert = config('afip.cert_path');

        if (! $cert || ! file_exists($cert)) {
            return $this->check('cert', 'Certificado ARCA', 'info', 'No hay certificado cargado (necesario para facturar A/B).');
        }

        $data = @openssl_x509_parse((string) file_get_contents($cert));
        $validTo = $data['validTo_time_t'] ?? null;

        if (! $validTo) {
            return $this->check('cert', 'Certificado ARCA', 'error', 'El certificado no se pudo leer. Volvé a cargarlo.');
        }

        $dias = (int) ceil(($validTo - time()) / 86400);
        $vence = 'vence el '.date('d/m/Y', $validTo);

        if ($dias < 0) {
            return $this->check('cert', 'Certificado ARCA', 'error', 'VENCIDO ('.$vence.'). Renovalo en ARCA.');
        }

        if ($dias <= 30) {
            return $this->check('cert', 'Certificado ARCA', 'warning', "Vence en {$dias} día(s) ({$vence}). Renovalo pronto.");
        }

        return $this->check('cert', 'Certificado ARCA', 'ok', $vence." (en {$dias} días)");
    }

    private function facturasSinEmitir(): array
    {
        $n = Invoice::pendientesDeEmisionCountCached();

        return $n === 0
            ? $this->check('sin_emitir', 'Facturas sin emitir', 'ok', 'No hay facturas pendientes de emitir.')
            : $this->check('sin_emitir', 'Facturas sin emitir', 'warning', "{$n} factura(s) fiscales sin CAE.");
    }

    private function stock(): array
    {
        $n = Product::lowStockCountCached();

        return $n === 0
            ? $this->check('stock', 'Stock', 'ok', 'Sin productos en stock bajo.')
            : $this->check('stock', 'Stock', 'warning', "{$n} producto(s) por debajo del stock mínimo.");
    }

    private function conexion(string $nombre, bool $configurado): array
    {
        $clave = 'conn_'.str($nombre)->slug();

        return $configurado
            ? $this->check($clave, $nombre, 'ok', 'Conectado.')
            : $this->check($clave, $nombre, 'info', 'No configurado.');
    }

    private function disco(): array
    {
        $libre = @disk_free_space(base_path());

        if ($libre === false) {
            return $this->check('disco', 'Espacio en disco', 'info', 'No se pudo determinar.');
        }

        $gb = $libre / 1_073_741_824;
        $detalle = number_format($gb, 1).' GB libres';

        return $gb < 1
            ? $this->check('disco', 'Espacio en disco', 'warning', $detalle.'. Queda poco espacio.')
            : $this->check('disco', 'Espacio en disco', 'ok', $detalle);
    }

    /**
     * @return array{clave:string, label:string, estado:string, detalle:string}
     */
    private function check(string $clave, string $label, string $estado, string $detalle): array
    {
        return ['clave' => $clave, 'label' => $label, 'estado' => $estado, 'detalle' => $detalle];
    }
}
