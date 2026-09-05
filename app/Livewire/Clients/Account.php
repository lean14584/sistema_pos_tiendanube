<?php

namespace App\Livewire\Clients;

use App\Enums\PaymentMethod;
use App\Mail\ClientAccountStatementMail;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\CompanySettings;
use App\Support\CashLinker;
use App\Support\Whatsapp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
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
            'method' => ['required', Rule::enum(PaymentMethod::class)],
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

    public function enviarPorEmail(): void
    {
        if (! $this->client->email) {
            session()->flash('error', 'El cliente no tiene un email cargado.');

            return;
        }

        try {
            Mail::to($this->client->email)->send(new ClientAccountStatementMail($this->client, $this->client->saldoCuentaCorriente()));
            session()->flash('status', 'Resumen de cuenta enviado por email a '.$this->client->email.'.');
        } catch (\Throwable $e) {
            session()->flash('error', 'No se pudo enviar el email: '.$e->getMessage());
        }
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
        $payments = $this->client->payments()->orderBy('date')->get();
        $this->client->setRelation('payments', $payments);

        // debitLines() ya trae el signo correcto (NC/Devolución restan) y
        // excluye el Remito ya facturado; acá solo se agrega el link de la fila.
        $debits = $this->client->debitLines()->map(fn ($d) => [
            'date' => $d['date'],
            'label' => $d['label'],
            'amount' => $d['amount'],
            'href' => route('invoices.show', $d['invoice']),
        ]);

        // Saldo (nos debe) para el recordatorio de WhatsApp.
        $saldo = $this->client->saldoCuentaCorriente();
        $whatsapp = $saldo > 0.009
            ? Whatsapp::link($this->client->phone, sprintf(
                'Hola %s, te recordamos que tenes un saldo pendiente de $%s con %s. Muchas gracias.',
                $this->client->name,
                number_format($saldo, 2, ',', '.'),
                CompanySettings::current()->display_name,
            ))
            : null;

        return view('livewire.clients.account', [
            'debits' => $debits,
            'payments' => $payments,
            'paymentMethods' => PaymentMethod::cases(),
            'whatsappReminder' => $whatsapp,
        ]);
    }
}
