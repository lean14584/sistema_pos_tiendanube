<?php

namespace App\Livewire\Tiendanube;

use App\Models\CompanySettings;
use App\Services\Tiendanube\TiendanubeClient;
use App\Services\Tiendanube\TiendanubeSync;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public CompanySettings $company;

    public string $tiendanube_store_id = '';

    public string $tiendanube_token = '';

    /** Resultado de la última acción, para mostrar en pantalla. */
    public ?string $resultado = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->company = CompanySettings::current();
        $this->tiendanube_store_id = (string) $this->company->tiendanube_store_id;
        $this->tiendanube_token = (string) $this->company->tiendanube_token;
    }

    public function saveCredentials(): void
    {
        $data = $this->validate([
            'tiendanube_store_id' => ['nullable', 'string', 'max:50'],
            'tiendanube_token' => ['nullable', 'string', 'max:255'],
        ]);

        $this->company->update($data);
        $this->company->refresh();
        $this->reset('resultado', 'error');

        session()->flash('status', 'Credenciales de Tiendanube guardadas.');
    }

    public function testConnection(): void
    {
        $this->reset('resultado', 'error');

        try {
            $store = app(TiendanubeClient::class)->getStore();
            $nombre = $this->texto($store['name'] ?? 'tu tienda');
            $this->resultado = '✓ Conectado a «'.$nombre.'».';
        } catch (\Throwable $e) {
            $this->error = 'No se pudo conectar: '.$e->getMessage();
        }
    }

    public function importProducts(): void
    {
        $this->correr(function (TiendanubeSync $sync) {
            $r = $sync->importProducts();

            return "Productos: {$r['creados']} creados, {$r['actualizados']} actualizados.";
        });
    }

    public function importOrders(): void
    {
        $this->correr(function (TiendanubeSync $sync) {
            $r = $sync->importOrders();

            return "Pedidos: {$r['importados']} importados, {$r['omitidos']} ya existían.";
        });
    }

    public function syncStock(): void
    {
        $this->correr(function (TiendanubeSync $sync) {
            $r = $sync->pushStock();

            return "Stock enviado a Tiendanube: {$r['enviados']} productos".($r['errores'] ? ", {$r['errores']} con error." : '.');
        });
    }

    private function correr(callable $accion): void
    {
        $this->reset('resultado', 'error');

        if (! app(TiendanubeClient::class)->isConfigured()) {
            $this->error = 'Primero cargá y guardá el Store ID y el Access Token.';

            return;
        }

        try {
            $this->resultado = $accion(app(TiendanubeSync::class));
        } catch (\Throwable $e) {
            $this->error = 'Error: '.$e->getMessage();
        }
    }

    private function texto(mixed $valor): string
    {
        if (is_array($valor)) {
            return (string) (reset($valor) ?: '');
        }

        return (string) $valor;
    }

    public function render()
    {
        return view('livewire.tiendanube.index', [
            'configurado' => app(TiendanubeClient::class)->isConfigured(),
        ]);
    }
}
