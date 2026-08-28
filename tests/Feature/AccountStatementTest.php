<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\ClientAccountStatementMail;
use App\Mail\ProviderAccountStatementMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AccountStatementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_descargar_pdf_de_cuenta_corriente_de_cliente(): void
    {
        $client = Client::create(['name' => 'Distribuidora Norte', 'email' => 'dn@test.com']);
        $invoice = Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $client->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now(), 'status' => 'pending',
        ]);
        $invoice->items()->create(['description' => 'Item', 'quantity' => 1, 'unit_price' => 5000, 'iva_rate' => 0]);

        $response = $this->actingAs($this->admin())->get(route('clients.statement', $client));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_descargar_pdf_de_cuenta_corriente_de_proveedor(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1', 'email' => 'p1@test.com']);
        $purchase = Purchase::create([
            'number' => 'COM-0001', 'provider_id' => $provider->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now(), 'status' => 'pending',
        ]);
        $purchase->items()->create(['product_id' => Product::create(['name' => 'P', 'price' => 1])->id, 'description' => 'Item', 'quantity' => 1, 'unit_price' => 3000]);

        $response = $this->actingAs($this->admin())->get(route('providers.statement', $provider));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_envia_el_resumen_de_cuenta_por_email_al_cliente(): void
    {
        Mail::fake();
        $client = Client::create(['name' => 'Distribuidora Norte', 'email' => 'dn@test.com']);

        Livewire::actingAs($this->admin())
            ->test('clients.account', ['client' => $client])
            ->call('enviarPorEmail');

        Mail::assertSent(ClientAccountStatementMail::class, fn (ClientAccountStatementMail $mail) => $mail->hasTo('dn@test.com') && $mail->client->is($client)
        );
    }

    public function test_no_envia_si_el_cliente_no_tiene_email(): void
    {
        Mail::fake();
        $client = Client::create(['name' => 'Sin Email', 'email' => '']);

        Livewire::actingAs($this->admin())
            ->test('clients.account', ['client' => $client])
            ->call('enviarPorEmail');

        Mail::assertNothingSent();
    }

    public function test_envia_el_resumen_de_cuenta_por_email_al_proveedor(): void
    {
        Mail::fake();
        $provider = Provider::create(['name' => 'Proveedor 1', 'email' => 'p1@test.com']);

        Livewire::actingAs($this->admin())
            ->test('providers.account', ['provider' => $provider])
            ->call('enviarPorEmail');

        Mail::assertSent(ProviderAccountStatementMail::class, fn (ProviderAccountStatementMail $mail) => $mail->hasTo('p1@test.com') && $mail->provider->is($provider)
        );
    }
}
