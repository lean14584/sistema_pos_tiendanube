<?php

namespace App\Livewire\Clients;

use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Support\CashLinker;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Account extends Component
{
    public Client $client;

    public string $date;

    public string $amount = '';

    public string $method = 'efectivo';

    public string $notes = '';

    public function mount(Client $client): void
    {
        $this->client = $client;
        $this->date = now()->toDateString();
    }

    public function addPayment(): void
    {
        $this->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = ClientPayment::create([
            'client_id' => $this->client->id,
            'date' => $this->date,
            'amount' => $this->amount,
            'method' => $this->method,
            'notes' => $this->notes ?: null,
        ]);

        CashLinker::linkClientPayment($payment);

        $this->amount = '';
        $this->notes = '';
    }

    public function deletePayment(int $paymentId): void
    {
        $payment = ClientPayment::where('client_id', $this->client->id)->whereKey($paymentId)->first();

        if ($payment) {
            CashLinker::unlinkClientPayment($payment);
            $payment->delete();
        }
    }

    public function render()
    {
        $invoices = $this->client->invoices()->whereNot('status', 'draft')->with('items', 'payments')->get();

        // El débito de cada factura es lo que realmente queda debiendo: total
        // menos lo que se pagó en el momento de la venta (invoice_payments).
        $debits = $invoices->map(fn ($invoice) => [
            'date' => $invoice->issue_date->toDateString(),
            'label' => $invoice->number,
            'amount' => (float) $invoice->total - (float) $invoice->payments->sum('amount'),
            'href' => route('invoices.show', $invoice),
        ]);

        return view('livewire.clients.account', [
            'debits' => $debits,
            'payments' => $this->client->payments()->orderBy('date')->get(),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }
}
