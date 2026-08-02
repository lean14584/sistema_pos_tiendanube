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
        $purchases = $this->provider->purchases()->whereNot('status', 'draft')->with('items')->get();

        $debits = $purchases->map(fn ($purchase) => [
            'date' => $purchase->issue_date->toDateString(),
            'label' => $purchase->number,
            'amount' => (float) $purchase->total,
            'href' => route('purchases.show', $purchase),
        ]);

        return view('livewire.providers.account', [
            'debits' => $debits,
            'payments' => $this->provider->payments()->orderBy('date')->get(),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }
}
