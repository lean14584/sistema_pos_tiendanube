<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSeeLivewire('auth.login');
    }

    public function test_user_can_log_in_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'username' => 'testadmin',
            'password' => 'secret123',
            'role' => Role::Admin,
            'active' => true,
        ]);

        Livewire::test('auth.login')
            ->set('username', 'testadmin')
            ->set('password', 'secret123')
            ->call('submit')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'username' => 'testadmin',
            'password' => 'secret123',
            'role' => Role::Admin,
            'active' => true,
        ]);

        Livewire::test('auth.login')
            ->set('username', 'testadmin')
            ->set('password', 'wrong')
            ->call('submit')
            ->assertSet('error', 'Usuario o contraseña incorrectos.');

        $this->assertGuest();
    }

    public function test_demasiados_intentos_fallidos_bloquean_el_login_temporalmente(): void
    {
        // Antes no había ningún límite: se podía probar contraseñas sin freno.
        User::factory()->create([
            'username' => 'testadmin', 'password' => 'secret123',
            'role' => Role::Admin, 'active' => true,
        ]);

        for ($i = 0; $i < 5; $i++) {
            Livewire::test('auth.login')
                ->set('username', 'testadmin')
                ->set('password', 'wrong')
                ->call('submit');
        }

        // El 6to intento, incluso con la contraseña CORRECTA, queda bloqueado.
        $component = Livewire::test('auth.login')
            ->set('username', 'testadmin')
            ->set('password', 'secret123')
            ->call('submit');

        $this->assertStringContainsString('Demasiados intentos', $component->get('error'));
        $this->assertGuest();
    }

    public function test_authenticated_admin_sees_dashboard_with_full_sidebar(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();
        $response->assertSee('Usuarios');
        $response->assertSee('Caja');
    }

    public function test_cajero_does_not_see_restricted_modules_in_sidebar(): void
    {
        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true]);

        $response = $this->actingAs($cajero)->get('/');

        $response->assertOk();
        $response->assertSee('Caja');
        $response->assertDontSee('Usuarios');
        $response->assertDontSee('Compras');
    }

    public function test_cajero_is_forbidden_from_users_route(): void
    {
        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true]);

        $this->actingAs($cajero)->get(route('users.index'))->assertForbidden();
    }
}
