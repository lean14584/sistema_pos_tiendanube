<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Models\Sucursal;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_pedir_link_con_email_cargado_dispara_la_notificacion(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'admin@example.com', 'active' => true]);

        Livewire::test('auth.forgot-password')
            ->set('email', 'admin@example.com')
            ->call('submit')
            ->assertSet('sent', true);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_pedir_link_con_email_inexistente_no_revela_nada(): void
    {
        Notification::fake();

        // Mismo resultado visible que si el email existiera: no hay forma de
        // usar este formulario para averiguar qué cuentas están cargadas.
        Livewire::test('auth.forgot-password')
            ->set('email', 'no-existe@example.com')
            ->call('submit')
            ->assertSet('sent', true);

        Notification::assertNothingSent();
    }

    public function test_resetear_con_token_valido_cambia_la_contrasena(): void
    {
        $user = User::factory()->create([
            'email' => 'cajero@example.com',
            'password' => 'vieja12345',
            'active' => true,
        ]);

        $token = Password::createToken($user);

        Livewire::test('auth.reset-password', ['token' => $token])
            ->set('email', 'cajero@example.com')
            ->set('password', 'nueva12345')
            ->set('password_confirmation', 'nueva12345')
            ->call('submit')
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('nueva12345', $user->fresh()->password));
    }

    public function test_resetear_con_token_invalido_no_cambia_nada(): void
    {
        $user = User::factory()->create([
            'email' => 'cajero2@example.com',
            'password' => 'vieja12345',
            'active' => true,
        ]);

        Livewire::test('auth.reset-password', ['token' => 'token-inventado'])
            ->set('email', 'cajero2@example.com')
            ->set('password', 'nueva12345')
            ->set('password_confirmation', 'nueva12345')
            ->call('submit')
            ->assertSet('error', fn ($error) => $error !== '');

        $this->assertTrue(Hash::check('vieja12345', $user->fresh()->password));
    }

    public function test_dos_usuarios_sin_email_no_chocan_por_el_unique(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);
        $sucursal = Sucursal::create(['name' => 'Centro', 'razon_social' => 'Mi Empresa SRL', 'punto_venta' => 91, 'active' => true]);

        Livewire::actingAs($admin)
            ->test('users.create')
            ->set('name', 'Uno')
            ->set('username', 'uno')
            ->set('password', 'password123')
            ->set('role', 'cajero')
            ->set('sucursal_id', (string) $sucursal->id)
            ->call('save')
            ->assertHasNoErrors();

        Livewire::actingAs($admin)
            ->test('users.create')
            ->set('name', 'Dos')
            ->set('username', 'dos')
            ->set('password', 'password123')
            ->set('role', 'cajero')
            ->set('sucursal_id', (string) $sucursal->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, User::whereIn('username', ['uno', 'dos'])->whereNull('email')->count());
    }
}
