<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Purchase;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VencimientosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_muestra_por_cobrar_y_por_pagar_con_saldo(): void
    {
        // Cliente con factura impaga vencida.
        $client = Client::create(['name' => 'Kiosco Sur', 'email' => 'k@test.com']);
        $inv = Invoice::create(['number' => 'FAC-1', 'client_id' => $client->id, 'tax_rate' => 0, 'issue_date' => now()->subDays(20), 'due_date' => now()->subDays(5), 'status' => 'pending']);
        $inv->items()->create(['description' => 'x', 'quantity' => 1, 'unit_price' => 8000]);

        // Proveedor con compra impaga.
        $product = Product::create(['name' => 'Insumo', 'price' => 20000, 'iva_rate' => 21, 'stock' => 0]);
        $provider = Provider::create(['name' => 'Proveedor A']);
        $pur = Purchase::create(['number' => 'COM-1', 'provider_id' => $provider->id, 'tax_rate' => 0, 'issue_date' => now()->subDays(10), 'due_date' => now()->subDays(2), 'status' => 'pending']);
        $pur->items()->create(['product_id' => $product->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => 20000]);

        Livewire::actingAs($this->admin())->test('vencimientos.index')
            ->assertSee('Kiosco Sur')
            ->assertSee('8.000,00')
            ->assertSee('Proveedor A')
            ->assertSee('20.000,00')
            ->assertSee('Vencido');
    }

    public function test_los_pagos_a_cuenta_reducen_el_saldo(): void
    {
        $client = Client::create(['name' => 'Cliente Pago', 'email' => 'p@test.com']);
        $inv = Invoice::create(['number' => 'FAC-2', 'client_id' => $client->id, 'tax_rate' => 0, 'issue_date' => now(), 'due_date' => now()->addDays(5), 'status' => 'pending']);
        $inv->items()->create(['description' => 'x', 'quantity' => 1, 'unit_price' => 5000]);
        // Ya pagó todo a cuenta corriente.
        $client->payments()->create(['date' => now(), 'amount' => 5000, 'method' => 'efectivo']);

        Livewire::actingAs($this->admin())->test('vencimientos.index')
            ->assertDontSee('Cliente Pago');
    }

    /**
     * Antes de este fix, "por cobrar" no filtraba por sucursal en absoluto:
     * un admin veía mezclada la deuda de todos los locales sin poder
     * separarla. "Por pagar" no lleva este mismo filtro porque las compras
     * (Purchase) no tienen sucursal_id — son de toda la empresa.
     */
    public function test_admin_puede_filtrar_por_cobrar_por_sucursal(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);

        $clientePrincipal = Client::create(['name' => 'Cliente Principal', 'email' => 'cp@test.com']);
        $invP = Invoice::create(['number' => 'FAC-P', 'client_id' => $clientePrincipal->id, 'sucursal_id' => $principal->id, 'tax_rate' => 0, 'issue_date' => now()->subDays(5), 'due_date' => now()->subDays(1), 'status' => 'pending']);
        $invP->items()->create(['description' => 'x', 'quantity' => 1, 'unit_price' => 1000]);

        $clienteNorte = Client::create(['name' => 'Cliente Norte', 'email' => 'cn@test.com']);
        $invN = Invoice::create(['number' => 'FAC-N', 'client_id' => $clienteNorte->id, 'sucursal_id' => $norte->id, 'tax_rate' => 0, 'issue_date' => now()->subDays(5), 'due_date' => now()->subDays(1), 'status' => 'pending']);
        $invN->items()->create(['description' => 'x', 'quantity' => 1, 'unit_price' => 2000]);

        Livewire::actingAs($this->admin())->test('vencimientos.index')
            ->assertSee('Cliente Principal')
            ->assertSee('Cliente Norte');

        Livewire::actingAs($this->admin())->test('vencimientos.index')
            ->set('sucursal_id', (string) $norte->id)
            ->assertDontSee('Cliente Principal')
            ->assertSee('Cliente Norte');
    }

    public function test_vendedor_solo_ve_por_cobrar_de_su_propia_sucursal(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true, 'sucursal_id' => $principal->id]);

        $clientePrincipal = Client::create(['name' => 'Cliente Principal', 'email' => 'cp@test.com']);
        $invP = Invoice::create(['number' => 'FAC-P', 'client_id' => $clientePrincipal->id, 'sucursal_id' => $principal->id, 'tax_rate' => 0, 'issue_date' => now()->subDays(5), 'due_date' => now()->subDays(1), 'status' => 'pending']);
        $invP->items()->create(['description' => 'x', 'quantity' => 1, 'unit_price' => 1000]);

        $clienteNorte = Client::create(['name' => 'Cliente Norte', 'email' => 'cn@test.com']);
        $invN = Invoice::create(['number' => 'FAC-N', 'client_id' => $clienteNorte->id, 'sucursal_id' => $norte->id, 'tax_rate' => 0, 'issue_date' => now()->subDays(5), 'due_date' => now()->subDays(1), 'status' => 'pending']);
        $invN->items()->create(['description' => 'x', 'quantity' => 1, 'unit_price' => 2000]);

        Livewire::actingAs($vendedor)->test('vencimientos.index')
            ->assertSee('Cliente Principal')
            ->assertDontSee('Cliente Norte');
    }
}
