<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EncargadoRoleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    private function encargado(int $sucursalId): User
    {
        return User::factory()->create(['role' => Role::Encargado, 'active' => true, 'sucursal_id' => $sucursalId]);
    }

    public function test_encargado_puede_entrar_a_usuarios_compras_caja_y_auditoria(): void
    {
        $sucursal = Sucursal::sole();
        $encargado = $this->encargado($sucursal->id);

        foreach (['users.index', 'purchases.index', 'cash-register.index', 'audit.index', 'providers.index'] as $ruta) {
            $this->actingAs($encargado)->get(route($ruta))->assertOk();
        }
    }

    public function test_encargado_no_puede_entrar_a_sucursales_ni_configuracion_de_empresa(): void
    {
        $sucursal = Sucursal::sole();
        $encargado = $this->encargado($sucursal->id);

        $this->actingAs($encargado)->get(route('sucursales.index'))->assertForbidden();
        $this->actingAs($encargado)->get(route('company-settings.edit'))->assertForbidden();
    }

    public function test_encargado_crea_un_vendedor_forzado_a_su_propia_sucursal(): void
    {
        $propia = Sucursal::sole();
        $otra = Sucursal::create(['name' => 'Otra', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $encargado = $this->encargado($propia->id);

        Livewire::actingAs($encargado)
            ->test('users.create')
            ->set('name', 'Nuevo Vendedor')
            ->set('username', 'nvendedor')
            ->set('password', 'secret12')
            ->set('role', 'vendedor')
            // Intenta forzar la otra sucursal desde el cliente.
            ->set('sucursal_id', (string) $otra->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['username' => 'nvendedor', 'sucursal_id' => $propia->id]);
    }

    public function test_encargado_no_puede_crear_un_admin_ni_otro_encargado(): void
    {
        $sucursal = Sucursal::sole();
        $encargado = $this->encargado($sucursal->id);

        Livewire::actingAs($encargado)
            ->test('users.create')
            ->set('name', 'Intento Admin')
            ->set('username', 'intentoadmin')
            ->set('password', 'secret12')
            ->set('role', 'admin')
            ->call('save')
            ->assertHasErrors(['role']);

        $this->assertDatabaseMissing('users', ['username' => 'intentoadmin']);
    }

    public function test_encargado_no_puede_editar_un_usuario_de_otra_sucursal(): void
    {
        $propia = Sucursal::sole();
        $otra = Sucursal::create(['name' => 'Otra', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $encargado = $this->encargado($propia->id);
        $vendedorDeOtra = User::factory()->create(['role' => Role::Vendedor, 'active' => true, 'sucursal_id' => $otra->id]);

        $this->actingAs($encargado)->get(route('users.edit', $vendedorDeOtra))->assertForbidden();
    }

    public function test_encargado_no_puede_editar_a_un_admin(): void
    {
        $sucursal = Sucursal::sole();
        $encargado = $this->encargado($sucursal->id);
        $admin = $this->admin();

        $this->actingAs($encargado)->get(route('users.edit', $admin))->assertForbidden();
    }

    public function test_encargado_puede_editar_su_propia_contrasena_sin_poder_autoascenderse(): void
    {
        $sucursal = Sucursal::sole();
        $encargado = $this->encargado($sucursal->id);

        Livewire::actingAs($encargado)
            ->test('users.edit', ['user' => $encargado])
            ->set('name', $encargado->name)
            ->set('username', $encargado->username)
            // Intenta autoascenderse manipulando el campo.
            ->set('role', 'admin')
            ->call('save')
            ->assertHasNoErrors();

        $fresh = $encargado->fresh();
        $this->assertSame('encargado', $fresh->role->value);
        $this->assertSame($sucursal->id, $fresh->sucursal_id);
    }

    public function test_encargado_no_ve_usuarios_de_otra_sucursal_en_el_listado(): void
    {
        $propia = Sucursal::sole();
        $otra = Sucursal::create(['name' => 'Otra', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $encargado = $this->encargado($propia->id);
        User::factory()->create(['role' => Role::Vendedor, 'active' => true, 'sucursal_id' => $propia->id, 'name' => 'De Mi Sucursal']);
        User::factory()->create(['role' => Role::Vendedor, 'active' => true, 'sucursal_id' => $otra->id, 'name' => 'De Otra Sucursal']);

        Livewire::actingAs($encargado)
            ->test('users.index')
            ->assertSee('De Mi Sucursal')
            ->assertDontSee('De Otra Sucursal');
    }

    public function test_encargado_no_puede_eliminar_usuario_de_otra_sucursal(): void
    {
        $propia = Sucursal::sole();
        $otra = Sucursal::create(['name' => 'Otra', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $encargado = $this->encargado($propia->id);
        $vendedorDeOtra = User::factory()->create(['role' => Role::Vendedor, 'active' => true, 'sucursal_id' => $otra->id]);

        Livewire::actingAs($encargado)
            ->test('users.index')
            ->call('delete', $vendedorDeOtra->id);

        $this->assertDatabaseHas('users', ['id' => $vendedorDeOtra->id]);
    }

    public function test_encargado_solo_ve_auditoria_de_su_propia_sucursal(): void
    {
        $propia = Sucursal::sole();
        $otra = Sucursal::create(['name' => 'Otra', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $encargado = $this->encargado($propia->id);
        $vendedorDeOtra = User::factory()->create(['role' => Role::Vendedor, 'active' => true, 'sucursal_id' => $otra->id]);

        $this->actingAs($vendedorDeOtra);
        Product::create(['name' => 'Producto de otra sucursal', 'price' => 100, 'stock' => 1]);

        $this->actingAs($encargado);
        Product::create(['name' => 'Producto de mi sucursal', 'price' => 100, 'stock' => 1]);

        Livewire::actingAs($encargado)
            ->test('audit.index')
            ->assertSee('Producto de mi sucursal', escape: false)
            ->assertDontSee('Producto de otra sucursal');
    }

    public function test_admin_ve_columna_y_filtro_de_sucursal_en_auditoria_pero_encargado_no(): void
    {
        $sucursal = Sucursal::sole();
        $admin = $this->admin();
        $encargado = $this->encargado($sucursal->id);

        Livewire::actingAs($admin)->test('audit.index')->assertSee('Todas las sucursales');
        Livewire::actingAs($encargado)->test('audit.index')->assertDontSee('Todas las sucursales');
    }

    public function test_audit_log_registra_la_sucursal_del_que_actua(): void
    {
        $sucursal = Sucursal::sole();
        $encargado = $this->encargado($sucursal->id);

        $this->actingAs($encargado);
        Product::create(['name' => 'Yerba', 'price' => 100, 'stock' => 1]);

        $log = AuditLog::where('auditable_type', Product::class)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($sucursal->id, $log->sucursal_id);
    }
}
