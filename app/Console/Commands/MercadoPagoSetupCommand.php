<?php

namespace App\Console\Commands;

use App\Models\Sucursal;
use App\Services\MercadoPago\MercadoPagoQrService;
use Illuminate\Console\Command;
use Throwable;

class MercadoPagoSetupCommand extends Command
{
    protected $signature = 'mp:setup {sucursal? : ID de la sucursal a configurar (si no se pasa, usa el MP_ACCESS_TOKEN global del .env)}';

    protected $description = 'Crea (si no existen) la sucursal y la caja de Mercado Pago y muestra el QR fijo para imprimir y pegar en la pared';

    public function handle(MercadoPagoQrService $mp): int
    {
        $sucursalId = null;

        if ($this->argument('sucursal')) {
            $sucursal = Sucursal::find($this->argument('sucursal'));

            if (! $sucursal) {
                $this->error('No existe esa sucursal.');

                return self::FAILURE;
            }

            $sucursalId = $sucursal->id;
            $this->info("Configurando Mercado Pago para «{$sucursal->name}»...");
        }

        if (! $mp->isConfigured($sucursalId)) {
            $this->error($sucursalId
                ? 'Esta sucursal no tiene Access Token de Mercado Pago cargado (ABM de Sucursales) ni hay uno global en el .env.'
                : 'Falta configurar MP_ACCESS_TOKEN en el archivo .env');

            return self::FAILURE;
        }

        $this->info('Contactando a Mercado Pago...');

        try {
            $this->line('Vendedor (collector) ID: '.$mp->collectorId($sucursalId));
            $data = $mp->ensureStoreAndPos($sucursalId);
        } catch (Throwable $e) {
            $this->error('Error: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('✓ Sucursal y caja listas.');
        $this->table(['Dato', 'Valor'], [
            ['Store ID', $data['store_id']],
            ['POS (caja) ID', $data['pos_id']],
        ]);

        $this->newLine();

        if ($data['qr_image']) {
            $this->info('QR FIJO de la caja (imprimí esta imagen y pegala en la pared):');
            $this->line($data['qr_image']);
        } else {
            $this->warn('La API no devolvió la imagen del QR. Podés generarla desde el panel de Mercado Pago para esta caja.');
        }

        return self::SUCCESS;
    }
}
