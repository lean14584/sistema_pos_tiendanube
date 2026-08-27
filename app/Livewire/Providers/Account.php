<?php

namespace App\Livewire\Providers;

use App\Enums\PaymentMethod;
use App\Models\Provider;
use App\Models\ProviderPayment;
use App\Support\CashLinker;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Account extends Component
{
    public Provider $provider;

    public string $date;

    public string $amount = '';

    public string $method = 'efectivo';

    public string $notes = '';

    public function mount(Provider $provider): void
    {
        $this->provider = $provider;
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

        $payment = ProviderPayment::create([
            'provider_id' => $this->provider->id,
            'date' => $this->date,
            'amount' => $this->amount,
            'method' => $this->method,
            'notes' => $this->notes ?: null,
        ]);

        CashLinker::linkProviderPayment($payment);

        $this->amount = '';
        $this->notes = '';
    }

    public function deletePayment(int $paymentId): void
    {
        $payment = ProviderPayment::where('provider_id', $this->provider->id)->whereKey($paymentId)->first();

        if ($payment) {
            CashLinker::unlinkProviderPayment($payment);
            $payment->delete();
        }
    }

    public function render()
    {
        $purchases = $this->provider->purchases()->whereNot('status', 'draft')->with('items', 'payments')->get();
        $payments = $this->provider->payments()->orderBy('date')->get();

        // Se dejan cargadas para que saldoCuentaCorriente() reutilice estos
        // mismos datos (loadMissing) en vez de volver a consultarlos.
        $this->provider->setRelation('purchases', $purchases);
        $this->provider->setRelation('payments', $payments);

        // El débito de cada compra es lo que realmente queda debiendo: total
        // menos lo que se pagó en el momento (purchase_payments).
        $debits = $purchases->map(fn ($purchase) => [
            'date' => $purchase->issue_date->toDateString(),
            'label' => $purchase->number,
            'amount' => (float) $purchase->total - (float) $purchase->payments->sum('amount'),
            'href' => route('purchases.show', $purchase),
        ]);

        return view('livewire.providers.account', [
            'debits' => $debits,
            'payments' => $payments,
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }
}
