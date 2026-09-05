<?php

namespace App\Livewire\Cobranzas;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\CompanySettings;
use App\Support\CashLinker;
use App\Support\Whatsapp;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public string $search = '';

    // Alta rápida de cobro por cliente (inline).
    public ?int $payingClientId = null;

    public string $payDate = '';

    public string $payAmount = '';

    public string $payMethod = 'efectivo';

    public function mount(): void
    {
        $this->payDate = now()->toDateString();
    }

    /**
     * Saldo (nos debe) de cada cliente: suma de facturas no borrador menos lo
     * cobrado en el momento de la venta, menos los cobros a cuenta corriente.
     * Devuelve solo los que quedan debiendo, ordenados de mayor a menor.
     *
     * @return Collection<int, array{client: Client, saldo: float}>
     */
    private function deudores()
    {
        // Un 'paid' siempre da remaining = 0 y se descarta igual más abajo,
        // así que ni vale la pena traerlo (la mayoría de la historia termina
        // pagada). 'items' hace falta porque Invoice::total es un atributo
        // calculado a partir de items, no una columna: sin este eager load,
        // debitLines() (llamado por saldoCuentaCorriente()) dispara un
        // lazy-load de items por factura — y esta pantalla se re-ejecuta
        // cada ~300ms mientras se escribe en el buscador.
        $pendientes = fn ($q) => $q->where('status', InvoiceStatus::Pending)->with('items', 'payments');

        $clients = Client::query()
            ->when(trim($this->search) !== '', fn ($q) => $q->where('name', 'like', '%'.trim($this->search).'%'))
            ->whereHas('invoices', $pendientes)
            ->with(['invoices' => $pendientes, 'payments'])
            ->orderBy('name')
            ->get();

        // Las relaciones ya vienen precargadas por el with() de arriba, así
        // que saldoCuentaCorriente() (loadMissing) no dispara queries extra.
        return $clients
            ->map(fn (Client $client) => ['client' => $client, 'saldo' => $client->saldoCuentaCorriente()])
            ->filter(fn ($row) => $row['saldo'] > 0.009)
            ->sortByDesc('saldo')
            ->values();
    }

    public function startPayment(int $clientId, float $saldo): void
    {
        $this->payingClientId = $clientId;
        $this->payDate = now()->toDateString();
        $this->payAmount = number_format($saldo, 2, '.', '');
        $this->payMethod = 'efectivo';
        $this->resetErrorBag();
    }

    public function cancelPayment(): void
    {
        $this->payingClientId = null;
        $this->payAmount = '';
    }

    public function savePayment(): void
    {
        $this->validate([
            'payingClientId' => ['required', 'exists:clients,id'],
            'payDate' => ['required', 'date'],
            'payAmount' => ['required', 'numeric', 'min:0.01'],
            'payMethod' => ['required', Rule::enum(PaymentMethod::class)],
        ]);

        $payment = ClientPayment::create([
            'client_id' => $this->payingClientId,
            'date' => $this->payDate,
            'amount' => $this->payAmount,
            'method' => $this->payMethod,
            'notes' => 'Cobranza',
        ]);

        CashLinker::linkClientPayment($payment);

        $this->cancelPayment();
        session()->flash('status', 'Cobro registrado.');
    }

    private function mensajeRecordatorio(Client $client, float $saldo): string
    {
        $empresa = CompanySettings::current()->display_name;

        return "Hola {$client->name}, te recordamos que tenes un saldo pendiente de $"
            .number_format($saldo, 2, ',', '.')
            ." con {$empresa}. Muchas gracias.";
    }

    public function render()
    {
        $deudores = $this->deudores()->map(function ($row) {
            /** @var Client $client */
            $client = $row['client'];
            $row['whatsapp'] = Whatsapp::link($client->phone, $this->mensajeRecordatorio($client, $row['saldo']));

            return $row;
        });

        return view('livewire.cobranzas.index', [
            'deudores' => $deudores,
            'totalACobrar' => $deudores->sum('saldo'),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }
}
