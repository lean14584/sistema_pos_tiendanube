<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TipoDocumento;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\GeneratesExcelFixtures;
use Tests\TestCase;

class ClientImportTest extends TestCase
{
    use GeneratesExcelFixtures;
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_crea_clientes_nuevos_infiriendo_tipo_de_documento_por_el_cuit(): void
    {
        $archivo = $this->excel([
            ['Nombre', 'CUIT', 'Email', 'Condición IVA'],
            ['Ferretería Sur', '30712345678', 'sur@example.com', 'Responsable Inscripto'],
        ]);

        Livewire::actingAs($this->admin())
            ->test('clients.import')
            ->set('archivo', $archivo)
            ->call('confirmarImportacion')
            ->assertSet('step', 'resultado');

        $cliente = Client::where('name', 'Ferretería Sur')->first();
        $this->assertNotNull($cliente);
        $this->assertSame('30712345678', $cliente->tax_id);
        $this->assertSame(TipoDocumento::Cuit, $cliente->tipo_documento);
        $this->assertSame('sur@example.com', $cliente->email);
        $this->assertSame('responsable_inscripto', $cliente->condicion_iva->value);
    }

    public function test_actualiza_cliente_existente_por_cuit_en_vez_de_duplicarlo(): void
    {
        $existente = Client::create(['name' => 'Nombre Viejo', 'email' => 'x@x.com', 'tax_id' => '20111111112']);

        $archivo = $this->excel([
            ['Nombre', 'CUIT'],
            ['Nombre Nuevo', '20111111112'],
        ]);

        Livewire::actingAs($this->admin())
            ->test('clients.import')
            ->set('archivo', $archivo)
            ->call('confirmarImportacion');

        $this->assertSame(1, Client::count());
        $existente->refresh();
        $this->assertSame('Nombre Nuevo', $existente->name);
    }

    public function test_fila_sin_nombre_se_omite(): void
    {
        $archivo = $this->excel([
            ['Nombre', 'CUIT'],
            ['', '20111111112'],
            ['Cliente Válido', ''],
        ]);

        $component = Livewire::actingAs($this->admin())
            ->test('clients.import')
            ->set('archivo', $archivo)
            ->call('confirmarImportacion');

        $this->assertSame(1, Client::count());
        $this->assertCount(1, $component->get('resultado')['omitidos']);
    }

    public function test_cliente_sin_email_se_crea_con_email_vacio(): void
    {
        $archivo = $this->excel([
            ['Nombre'],
            ['Sin Email SA'],
        ]);

        Livewire::actingAs($this->admin())
            ->test('clients.import')
            ->set('archivo', $archivo)
            ->call('confirmarImportacion');

        $this->assertDatabaseHas('clients', ['name' => 'Sin Email SA', 'email' => '']);
    }

    public function test_cajero_no_puede_entrar_a_importar_clientes(): void
    {
        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true]);

        $this->actingAs($cajero)->get(route('clients.import'))->assertForbidden();
    }
}
