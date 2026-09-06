<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Antes de este fix, un cajero/vendedor/encargado podía ver, editar,
 * facturar o descargar el PDF de una factura de OTRA sucursal con solo
 * cambiar el id en la URL: el listado filtraba por sucursal, pero el acceso
 * directo a un registro puntual no.
 */
class InvoiceSucursalScopeTest extends TestCase
{
    use RefreshDatabase;

    private function sucursal(string $name, int $puntoVenta): Sucursal
    {
        return Sucursal::create(['name' => $name, 'razon_social' => 'Mi Empresa SRL', 'punto_venta' => $puntoVenta, 'active' => true]);
    }

    private function invoiceEn(Sucursal $sucursal, string $number = 'FAC-0001'): Invoice
    {
        $client = Client::create(['name' => 'Cliente', 'email' => 'cliente'.uniqid().'@test.com']);

        return Invoice::create([
            'number' => $number,
            'client_id' => $client->id,
            'sucursal_id' => $sucursal->id,
            'issue_date' => now(),
            'due_date' => now()->addDays(15),
            'status' => 'draft',
        ]);
    }

    public function test_el_listado_solo_muestra_las_facturas_de_la_propia_sucursal(): void
    {
        $centro = $this->sucursal('Centro', 81);
        $norte = $this->sucursal('Norte', 82);

        $this->invoiceEn($centro, 'FAC-0001');
        $this->invoiceEn($norte, 'FAC-0002');

        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true, 'sucursal_id' => $centro->id]);

        $component = Livewire::actingAs($cajero)->test('invoices.index');

        $this->assertCount(1, $component->viewData('invoices'));
    }

    public function test_cajero_no_puede_ver_una_factura_de_otra_sucursal(): void
    {
        $otra = $this->sucursal('Otra', 83);
        $mia = $this->sucursal('Mia', 84);
        $invoice = $this->invoiceEn($otra);

        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true, 'sucursal_id' => $mia->id]);

        $this->actingAs($cajero)->get(route('invoices.show', $invoice))->assertForbidden();
        $this->actingAs($cajero)->get(route('invoices.edit', $invoice))->assertForbidden();
        $this->actingAs($cajero)->get(route('invoices.pdf', $invoice))->assertForbidden();
    }

    public function test_admin_si_puede_ver_facturas_de_cualquier_sucursal(): void
    {
        $otra = $this->sucursal('Otra', 85);
        $invoice = $this->invoiceEn($otra);

        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        $this->actingAs($admin)->get(route('invoices.show', $invoice))->assertOk();
        $this->actingAs($admin)->get(route('invoices.pdf', $invoice))->assertOk();
    }
}
