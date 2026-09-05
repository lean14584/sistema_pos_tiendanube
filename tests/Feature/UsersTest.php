<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_can_create_a_user(): void
    {
        $sucursal = Sucursal::create(['name' => 'Centro', 'razon_social' => 'Mi Empresa SRL', 'punto_venta' => 1]);

        Livewire::actingAs($this->admin())
            ->test('users.create')
            ->set('name', 'Juana Pérez')
            ->set('username', 'jperez')
            ->set('password', 'secret12')
            ->set('role', 'vendedor')
            ->set('sucursal_id', (string) $sucursal->id)
            ->call('save')
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['username' => 'jperez', 'role' => 'vendedor', 'sucursal_id' => $sucursal->id]);
    }

    public function test_sucursal_es_obligatoria_para_vendedor_y_cajero_pero_no_para_admin(): void
    {
        Livewire::actingAs($this->admin())
            ->test('users.create')
            ->set('name', 'Juana Pérez')
            ->set('username', 'jperez2')
            ->set('password', 'secret12')
            ->set('role', 'vendedor')
            ->call('save')
            ->assertHasErrors(['sucursal_id' => 'required']);

        Livewire::actingAs($this->admin())
            ->test('users.create')
            ->set('name', 'Admin Nuevo')
            ->set('username', 'anuevo')
            ->set('password', 'secret12')
            ->set('role', 'admin')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['username' => 'anuevo', 'sucursal_id' => null]);
    }

    public function test_username_must_be_unique(): void
    {
        User::factory()->create(['username' => 'jperez']);

        Livewire::actingAs($this->admin())
            ->test('users.create')
            ->set('name', 'Otro')
            ->set('username', 'jperez')
            ->set('password', 'secret12')
            ->call('save')
            ->assertHasErrors(['username' => 'unique']);
    }

    public function test_editing_without_password_keeps_old_password(): void
    {
        // Admin acá a propósito: este test no es sobre sucursales, y un
        // vendedor/cajero de fábrica sin sucursal asignada haría fallar el
        // guardado por la validación nueva (sucursal_id requerida para esos roles).
        $user = User::factory()->create(['username' => 'jperez', 'password' => 'original', 'role' => Role::Admin]);

        Livewire::actingAs($this->admin())
            ->test('users.edit', ['user' => $user])
            ->set('name', 'Nuevo Nombre')
            ->set('password', '')
            ->call('save');

        $this->assertTrue(Hash::check('original', $user->fresh()->password));
        $this->assertEquals('Nuevo Nombre', $user->fresh()->name);
    }

    public function test_cannot_delete_own_user(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test('users.index')
            ->call('delete', $admin->id);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_cannot_delete_last_active_admin(): void
    {
        // The only active admin, targeted for deletion by a different user (not self-delete).
        $lastActiveAdmin = User::factory()->create(['role' => Role::Admin, 'active' => true]);
        $inactiveAdmin = User::factory()->create(['role' => Role::Admin, 'active' => false]);

        Livewire::actingAs($inactiveAdmin)
            ->test('users.index')
            ->call('delete', $lastActiveAdmin->id);

        $this->assertDatabaseHas('users', ['id' => $lastActiveAdmin->id]);
    }

    public function test_users_index_paginates_instead_of_loading_everything(): void
    {
        $admin = $this->admin();
        User::factory()->count(20)->create();

        $component = Livewire::actingAs($admin)->test('users.index');

        $this->assertCount(20, $component->viewData('users'));
        $this->assertEquals(2, $component->viewData('users')->lastPage());
    }
}
