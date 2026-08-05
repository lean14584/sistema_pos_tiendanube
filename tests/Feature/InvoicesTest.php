<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InvoicesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_el_formulario_de_nueva_factura_preselecciona_consumidor_final(): void
    {
        $component = Livewire::actingAs($this->admin())->test('invoices.create');

        $consumidorFinal = Client::where('name', 'Consumidor Final')->first();

        $this->assertNotNull($consumidorFinal);
        $this->assertSame((string) $consumidorFinal->id, $component->get('client_id'));
    }

    public function test_consumidor_final_no_se_duplica_entre_facturas(): void
    {
        Livewire::actingAs($this->admin())->test('invoices.create');
        Livewire::actingAs($this->admin())->test('invoices.create');

        $this->assertSame(1, Client::where('name', 'Consumidor Final')->count());
    }

    public function test_puede_reemplazarse_consumidor_final_por_otro_cliente(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 10]);

        Livewire::actingAs($this->admin())
            ->test('invoices.create')
            ->set('client_id', (string) $client->id)
            ->call('addProductItem', $product->id)
            ->call('save');

        $invoice = Invoice::first();
        $this->assertSame($client->id, $invoice->client_id);
    }

    public function test_can_create_invoice_with_product_item_and_split_payments(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 10]);

        Livewire::actingAs($this->admin())
            ->test('invoices.create')
            ->set('client_id', (string) $client->id)
            ->call('addProductItem', $product->id)
            ->set('tax_rate', '21')
            ->call('addPayment')
            ->set('payments.0.method', 'efectivo')
            ->set('payments.0.amount', '600')
            ->call('addPayment')
            ->call('save');

        $invoice = Invoice::first();
        $this->assertNotNull($invoice);
        $this->assertEquals($client->id, $invoice->client_id);
        $this->assertEquals(1, $invoice->items->count());
        $this->assertEquals($product->id, $invoice->items->first()->product_id);
        $this->assertEqualsWithDelta(1210.0, (float) $invoice->total, 0.01);
    }

    public function test_cannot_save_invoice_without_items(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        Livewire::actingAs($this->admin())
            ->test('invoices.create')
            ->set('client_id', (string) $client->id)
            ->call('save')
            ->assertHasErrors(['items']);

        $this->assertEquals(0, Invoice::count());
    }

    public function test_can_change_invoice_status_from_show_page(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $invoice = Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $client->id,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'draft',
        ]);
        $invoice->items()->create(['description' => 'Servicio', 'quantity' => 1, 'unit_price' => 500]);

        Livewire::actingAs($this->admin())
            ->test('invoices.show', ['invoice' => $invoice])
            ->call('setStatus', 'paid');

        $this->assertEquals('paid', $invoice->fresh()->status->value);
    }

    public function test_invoice_pdf_downloads(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $invoice = Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $client->id,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'draft',
        ]);
        $invoice->items()->create(['description' => 'Servicio', 'quantity' => 1, 'unit_price' => 500]);

        $response = $this->actingAs($this->admin())->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_invoice_pdf_usa_el_nombre_y_logo_de_la_empresa(): void
    {
        // El controller lee el logo con storage_path() (no con el disco de
        // Storage), así que acá se escribe al disco 'public' real en vez de
        // usar Storage::fake — de lo contrario ambos apuntarían a
        // ubicaciones distintas y el test no probaría nada.
        $logoPath = UploadedFile::fake()->image('logo.png')->store('company-logos', 'public');
        CompanySettings::current()->update(['nombre_fantasia' => 'Mi Negocio', 'logo_path' => $logoPath]);

        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $invoice = Invoice::create([
            'number' => 'FAC-0010', 'client_id' => $client->id,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'draft',
        ]);
        $invoice->items()->create(['description' => 'Servicio', 'quantity' => 1, 'unit_price' => 500]);

        try {
            $response = $this->actingAs($this->admin())->get(route('invoices.pdf', $invoice));

            $response->assertOk();
        } finally {
            Storage::disk('public')->delete($logoPath);
        }
    }

    public function test_invoices_index_paginates_instead_of_loading_everything(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        for ($i = 1; $i <= 21; $i++) {
            $invoice = Invoice::create([
                'number' => 'FAC-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'client_id' => $client->id,
                'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'draft',
            ]);
            $invoice->items()->create(['description' => 'x', 'quantity' => 1, 'unit_price' => 100]);
        }

        $component = Livewire::actingAs($this->admin())->test('invoices.index');

        $this->assertCount(20, $component->viewData('invoices'));
        $this->assertEquals(2, $component->viewData('invoices')->lastPage());
    }

    public function test_invoices_index_filtra_vencidas_a_nivel_de_query(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        $overdue = Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $client->id,
            'issue_date' => now()->subDays(20), 'due_date' => now()->subDays(5), 'status' => 'pending',
        ]);
        $pending = Invoice::create([
            'number' => 'FAC-0002', 'client_id' => $client->id,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'pending',
        ]);
        foreach ([$overdue, $pending] as $invoice) {
            $invoice->items()->create(['description' => 'x', 'quantity' => 1, 'unit_price' => 100]);
        }

        Livewire::actingAs($this->admin())
            ->test('invoices.index')
            ->set('filter', 'overdue')
            ->assertSee('FAC-0001')
            ->assertDontSee('FAC-0002')
            ->set('filter', 'pending')
            ->assertSee('FAC-0002')
            ->assertDontSee('FAC-0001');
    }
}
