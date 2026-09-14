<?php

namespace App\Livewire\Vencimientos;

use App\Enums\InvoiceStatus;
use App\Livewire\Concerns\ScopedToSucursal;
use App\Models\Client;
use App\Models\Provider;
use App\Models\Sucursal;
use App\Support\CurrentSucursal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use ScopedToSucursal;

    /** null (string vacío) = todas las sucursales consolidadas. Solo un admin global puede elegir esto. */
    #[Url]
    public string $sucursal_id = '';

    /**
     * Aging de una entidad (cliente o proveedor): toma sus comprobantes con
     * saldo (total menos lo pagado en el momento) ordenados por vencimiento,
     * e imputa los pagos a cuenta corriente a los más viejos primero (FIFO).
     * Devuelve los renglones que todavía quedan debiendo, con su vencimiento.
     *
     * @param  Collection  $comprobantes  cada uno: ['due'=>Carbon,'remaining'=>float,'label'=>string,'sucursal_id'=>?int]
     * @return array<int, array{label:string, due:Carbon, amount:float}>
     */
    private function aging($comprobantes, float $creditoACuenta): array
    {
        $rows = $comprobantes
            ->map(fn ($c) => ['due' => $c['due'], 'remaining' => (float) $c['remaining'], 'label' => $c['label'], 'sucursal_id' => $c['sucursal_id'] ?? null])
            ->filter(fn ($r) => $r['remaining'] > 0.009)
            ->sortBy(fn ($r) => $r['due']->timestamp)
            ->values()
            ->all();

        // Imputo los pagos a cuenta a los comprobantes más viejos.
        $credito = $creditoACuenta;
        foreach ($rows as $i => $row) {
            if ($credito <= 0.009) {
                break;
            }
            $tomo = min($credito, $row['remaining']);
            $rows[$i]['remaining'] = round($row['remaining'] - $tomo, 2);
            $credito -= $tomo;
        }

        return array_values(array_filter($rows, fn ($r) => $r['remaining'] > 0.009));
    }

    private function estadoYDias(Carbon $due): array
    {
        $dias = (int) Carbon::today()->diffInDays($due->copy()->startOfDay(), false);

        return [
            'dias' => $dias,
            'estado' => $dias < 0 ? 'vencido' : ($dias === 0 ? 'hoy' : 'proximo'),
        ];
    }

    public function render()
    {
        // Un cajero/vendedor solo ve la deuda de su propia sucursal, sin
        // importar lo que traiga la URL — mismo criterio que Informes y
        // Facturas (ver ScopedToSucursal). Antes esta pantalla no filtraba
        // por sucursal en absoluto: un admin de una instalación
        // multisucursal veía mezclada la deuda de todos los locales.
        $sucursalId = $this->puedeVerTodasLasSucursales()
            ? ($this->sucursal_id !== '' ? (int) $this->sucursal_id : null)
            : CurrentSucursal::id();

        // Solo importan los comprobantes pendientes: un 'paid' siempre da
        // remaining = 0 y aging() lo descarta igual, así que ni vale la pena
        // traerlo (la mayoría de la historia de facturación termina pagada).
        // 'items' sigue haciendo falta: Invoice::total es un atributo
        // calculado a partir de items, no una columna — sin eager load acá
        // sería un N+1 lazy-load por factura.
        $invoicesPendientesTodas = fn ($q) => $q->where('status', InvoiceStatus::Pending)->with('items', 'payments');
        $invoicesPendientesEnSucursal = fn ($q) => $invoicesPendientesTodas($q)
            ->when($sucursalId !== null, fn ($q) => $q->where('sucursal_id', $sucursalId));

        // ---- POR COBRAR (clientes) ----
        // MEJORA: ClientPayment (el crédito a cuenta corriente) no tiene
        // sucursal_id — es siempre a nivel de TODA la empresa. Antes se
        // traían solo las facturas pendientes de la sucursal filtrada pero
        // se les imputaba el crédito COMPLETO del cliente vía aging(): si
        // el mismo cliente compraba en más de una sucursal, filtrar por
        // Sucursal A y después por Sucursal B aplicaba el crédito entero
        // dos veces, mostrando una deuda incorrecta en cada vista. Ahora se
        // trae SIEMPRE la deuda completa del cliente (todas las sucursales)
        // para que aging() reparta el crédito una sola vez de forma
        // correcta, y recién después se filtran las filas resultantes a la
        // sucursal que se está mirando.
        $porCobrar = collect();
        $clients = Client::query()
            ->whereHas('invoices', $invoicesPendientesEnSucursal)
            ->with(['invoices' => $invoicesPendientesTodas, 'payments'])
            ->get();

        foreach ($clients as $client) {
            $comprobantes = $client->invoices->map(fn ($i) => [
                'due' => $i->due_date,
                'remaining' => (float) $i->total - (float) $i->payments->sum('amount'),
                'label' => $i->number,
                'sucursal_id' => $i->sucursal_id,
            ]);
            $credito = (float) $client->payments->sum('amount');

            foreach ($this->aging($comprobantes, $credito) as $row) {
                if ($sucursalId !== null && $row['sucursal_id'] !== $sucursalId) {
                    continue;
                }

                $porCobrar->push(array_merge([
                    'name' => $client->name,
                    'href' => route('clients.account', $client),
                    'amount' => $row['remaining'],
                    'due' => $row['due'],
                    'label' => $row['label'],
                ], $this->estadoYDias($row['due'])));
            }
        }

        $purchasesPendientes = fn ($q) => $q->where('status', InvoiceStatus::Pending)->with('items', 'taxes', 'payments');

        // ---- POR PAGAR (proveedores) ----
        $porPagar = collect();
        $providers = Provider::query()
            ->whereHas('purchases', $purchasesPendientes)
            ->with(['purchases' => $purchasesPendientes, 'payments'])
            ->get();

        foreach ($providers as $provider) {
            $comprobantes = $provider->purchases->map(fn ($p) => [
                'due' => $p->due_date,
                'remaining' => (float) $p->total - (float) $p->payments->sum('amount'),
                'label' => $p->number,
            ]);
            $credito = (float) $provider->payments->sum('amount');

            foreach ($this->aging($comprobantes, $credito) as $row) {
                $porPagar->push(array_merge([
                    'name' => $provider->name,
                    'href' => route('providers.account', $provider),
                    'amount' => $row['remaining'],
                    'due' => $row['due'],
                    'label' => $row['label'],
                ], $this->estadoYDias($row['due'])));
            }
        }

        $porCobrar = $porCobrar->sortBy(fn ($r) => $r['due']->timestamp)->values();
        $porPagar = $porPagar->sortBy(fn ($r) => $r['due']->timestamp)->values();

        return view('livewire.vencimientos.index', [
            'porCobrar' => $porCobrar,
            'porPagar' => $porPagar,
            'totalCobrar' => $porCobrar->sum('amount'),
            'totalPagar' => $porPagar->sum('amount'),
            'vencidoCobrar' => $porCobrar->where('estado', 'vencido')->sum('amount'),
            'vencidoPagar' => $porPagar->where('estado', 'vencido')->sum('amount'),
            'puedeVerTodasLasSucursales' => $this->puedeVerTodasLasSucursales(),
            'sucursales' => $this->puedeVerTodasLasSucursales() ? Sucursal::orderBy('name')->get() : collect(),
        ]);
    }
}
