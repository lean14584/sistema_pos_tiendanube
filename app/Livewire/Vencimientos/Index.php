<?php

namespace App\Livewire\Vencimientos;

use App\Models\Client;
use App\Models\Provider;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    /**
     * Aging de una entidad (cliente o proveedor): toma sus comprobantes con
     * saldo (total menos lo pagado en el momento) ordenados por vencimiento,
     * e imputa los pagos a cuenta corriente a los más viejos primero (FIFO).
     * Devuelve los renglones que todavía quedan debiendo, con su vencimiento.
     *
     * @param  \Illuminate\Support\Collection  $comprobantes  cada uno: ['due'=>Carbon,'remaining'=>float,'label'=>string]
     * @return array<int, array{label:string, due:Carbon, amount:float}>
     */
    private function aging($comprobantes, float $creditoACuenta): array
    {
        $rows = $comprobantes
            ->map(fn ($c) => ['due' => $c['due'], 'remaining' => (float) $c['remaining'], 'label' => $c['label']])
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
        // ---- POR COBRAR (clientes) ----
        $porCobrar = collect();
        $clients = Client::query()
            ->with(['invoices' => fn ($q) => $q->whereNot('status', 'draft')->with('items', 'payments'), 'payments'])
            ->get();

        foreach ($clients as $client) {
            $comprobantes = $client->invoices->map(fn ($i) => [
                'due' => $i->due_date,
                'remaining' => (float) $i->total - (float) $i->payments->sum('amount'),
                'label' => $i->number,
            ]);
            $credito = (float) $client->payments->sum('amount');

            foreach ($this->aging($comprobantes, $credito) as $row) {
                $porCobrar->push(array_merge([
                    'name' => $client->name,
                    'href' => route('clients.account', $client),
                    'amount' => $row['remaining'],
                    'due' => $row['due'],
                    'label' => $row['label'],
                ], $this->estadoYDias($row['due'])));
            }
        }

        // ---- POR PAGAR (proveedores) ----
        $porPagar = collect();
        $providers = Provider::query()
            ->with(['purchases' => fn ($q) => $q->whereNot('status', 'draft')->with('items', 'taxes', 'payments'), 'payments'])
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
        ]);
    }
}
