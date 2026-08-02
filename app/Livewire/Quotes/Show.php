<?php

namespace App\Livewire\Quotes;

use App\Enums\QuoteStatus;
use App\Models\Invoice;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Quote $quote;

    public string $priceMode = 'keep';

    public function mount(Quote $quote): void
    {
        $this->quote = $quote;
    }

    public function setStatus(string $status): void
    {
        $this->quote->update(['status' => $status]);
    }

    public function delete(): void
    {
        $this->quote->delete();

        $this->redirect(route('quotes.index'), navigate: true);
    }

    public function convertToInvoice(): void
    {
        $updatePrices = $this->priceMode === 'update';

        $invoice = DB::transaction(function () use ($updatePrices) {
            $invoice = Invoice::create([
                'number' => $this->nextInvoiceNumber(),
                'client_id' => $this->quote->client_id,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(15)->toDateString(),
                'tax_rate' => $this->quote->tax_rate,
                'notes' => $this->quote->notes,
                'status' => 'draft',
            ]);

            foreach ($this->quote->items as $item) {
                $product = $item->product_id ? $item->product : null;

                if ($updatePrices && $product) {
                    $invoice->items()->create([
                        'product_id' => $product->id,
                        'description' => $product->name,
                        'quantity' => $item->quantity,
                        'unit_price' => $product->price,
                    ]);
                } else {
                    $invoice->items()->create([
                        'product_id' => $item->product_id,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                    ]);
                }
            }

            $this->quote->update([
                'status' => QuoteStatus::Converted,
                'converted_invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });

        $this->redirect(route('invoices.show', $invoice), navigate: true);
    }

    private function nextInvoiceNumber(): string
    {
        $count = Invoice::count() + 1;

        return 'FAC-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        $this->quote->load('client', 'items.product');

        return view('livewire.quotes.show', [
            'editableStatuses' => QuoteStatus::editable(),
        ]);
    }
}
