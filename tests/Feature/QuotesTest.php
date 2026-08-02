<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuotesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_can_create_a_quote(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $product = Product::create(['name' => 'Servicio X', 'price' => 200]);

        Livewire::actingAs($this->admin())
            ->test('quotes.create')
            ->set('client_id', (string) $client->id)
            ->call('addProductItem', $product->id)
            ->call('save');

        $quote = Quote::first();
        $this->assertNotNull($quote);
        $this->assertEquals('draft', $quote->status->value);
        $this->assertEquals(200.0, (float) $quote->total);
    }

    public function test_convert_to_invoice_keeps_price_when_keep_mode(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $product = Product::create(['name' => 'Servicio X', 'price' => 500]);

        $quote = Quote::create([
            'number' => 'PRE-0001', 'client_id' => $client->id,
            'issue_date' => now(), 'valid_until' => now()->addDays(15), 'status' => 'draft',
        ]);
        $quote->items()->create(['product_id' => $product->id, 'description' => 'Servicio X', 'quantity' => 1, 'unit_price' => 300]);

        // Price changed after the quote was made.
        $product->update(['price' => 999]);

        Livewire::actingAs($this->admin())
            ->test('quotes.show', ['quote' => $quote])
            ->set('priceMode', 'keep')
            ->call('convertToInvoice');

        $invoice = Invoice::first();
        $this->assertNotNull($invoice);
        $this->assertEquals(300.0, (float) $invoice->items->first()->unit_price);
        $this->assertEquals('converted', $quote->fresh()->status->value);
        $this->assertEquals($invoice->id, $quote->fresh()->converted_invoice_id);
    }

    public function test_convert_to_invoice_updates_price_when_update_mode(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $product = Product::create(['name' => 'Servicio X', 'price' => 500]);

        $quote = Quote::create([
            'number' => 'PRE-0001', 'client_id' => $client->id,
            'issue_date' => now(), 'valid_until' => now()->addDays(15), 'status' => 'draft',
        ]);
        $quote->items()->create(['product_id' => $product->id, 'description' => 'Servicio X', 'quantity' => 1, 'unit_price' => 300]);

        $product->update(['price' => 999]);

        Livewire::actingAs($this->admin())
            ->test('quotes.show', ['quote' => $quote])
            ->set('priceMode', 'update')
            ->call('convertToInvoice');

        $invoice = Invoice::first();
        $this->assertEquals(999.0, (float) $invoice->items->first()->unit_price);
    }

    public function test_converted_quote_cannot_be_edited(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $quote = Quote::create([
            'number' => 'PRE-0001', 'client_id' => $client->id,
            'issue_date' => now(), 'valid_until' => now()->addDays(15), 'status' => 'converted',
        ]);

        $this->actingAs($this->admin())
            ->get(route('quotes.edit', $quote))
            ->assertOk()
            ->assertSee('ya fue convertido');
    }

    public function test_quotes_index_paginates_instead_of_loading_everything(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        for ($i = 1; $i <= 21; $i++) {
            Quote::create([
                'number' => 'PRE-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'client_id' => $client->id,
                'issue_date' => now(), 'valid_until' => now()->addDays(15), 'status' => 'draft',
            ]);
        }

        $component = Livewire::actingAs($this->admin())->test('quotes.index');

        $this->assertCount(20, $component->viewData('quotes'));
        $this->assertEquals(2, $component->viewData('quotes')->lastPage());
    }
}
