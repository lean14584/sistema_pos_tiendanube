<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TipoDocumento;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\GeneratesExcelFixtures;
use Tests\TestCase;

class ProviderImportTest extends TestCase
{
    use GeneratesExcelFixtures;
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_crea_proveedores_nuevos_infiriendo_tipo_de_documento_por_el_cuit(): void
    {
        $archivo = $this->excel([
            ['Nombre', 'CUIT', 'Teléfono'],
            ['Distribuidora Norte', '30712345678', '1122334455'],
        ]);

        Livewire::actingAs($this->admin())
            ->test('providers.import')
            ->set('archivo', $archivo)
            ->call('confirmarImportacion')
            ->assertSet('step', 'resultado');

        $proveedor = Provider::where('name', 'Distribuidora Norte')->first();
        $this->assertNotNull($proveedor);
        $this->assertSame('30712345678', $proveedor->tax_id);
        $this->assertSame(TipoDocumento::Cuit, $proveedor->tipo_documento);
        $this->assertSame('1122334455', $proveedor->phone);
    }

    public function test_actualiza_proveedor_existente_por_cuit_en_vez_de_duplicarlo(): void
    {
        $existente = Provider::create(['name' => 'Nombre Viejo', 'tax_id' => '20111111112']);

        $archivo = $this->excel([
            ['Nombre', 'CUIT'],
            ['Nombre Nuevo', '20111111112'],
        ]);

        Livewire::actingAs($this->admin())
            ->test('providers.import')
            ->set('archivo', $archivo)
            ->call('confirmarImportacion');

        $this->assertSame(1, Provider::count());
        $existente->refresh();
        $this->assertSame('Nombre Nuevo', $existente->name);
    }

    public function test_cajero_no_puede_entrar_a_importar_proveedores(): void
    {
        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true]);

        $this->actingAs($cajero)->get(route('providers.import'))->assertForbidden();
    }
}
