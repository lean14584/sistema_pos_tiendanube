<?php

namespace App\Console\Commands;

use App\Services\MercadoPago\MercadoPagoQrService;
use Illuminate\Console\Command;
use Throwable;

class MercadoPagoSetupCommand extends Command
{
    protected $signature = 'mp:setup';

    protected $description = 'Crea (si no existen) la sucursal y la caja de Mercado Pago y muestra el QR fijo para imprimir y pegar en la pared';

    public function handle(MercadoPagoQrService $mp): int
    {
        if (! $mp->isConfigured()) {
            $this->error('Falta configurar MP_ACCESS_TOKEN en el archivo .env');

            return self::FAILURE;
        }

        $this->info('Contactando a Mercado Pago...');

        try {
            $this->line('Vendedor (collector) ID: '.$mp->collectorId());
            $data = $mp->ensureStoreAndPos();
        } catch (Throwable $e) {
            $this->error('Error: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('✓ Sucursal y caja listas.');
        $this->table(['Dato', 'Valor'], [
            ['Store ID', $data['store_id']],
            ['POS (caja) ID', $data['pos_id']],
            ['External store', config('mercadopago.store_external_id')],
            ['External POS', config('mercadopago.pos_external_id')],
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
