<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupInitialAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_el_primer_admin(): void
    {
        $this->artisan('app:setup-admin')
            ->expectsQuestion('Nombre completo', 'Leandro')
            ->expectsQuestion('Usuario para iniciar sesión', 'leandro')
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'password123')
            ->assertExitCode(0);

        $user = User::where('username', 'leandro')->first();
        $this->assertNotNull($user);
        $this->assertSame(Role::Admin, $user->role);
        $this->assertTrue($user->active);
    }

    public function test_no_hace_nada_si_ya_hay_usuarios(): void
    {
        User::factory()->create();

        $this->artisan('app:setup-admin')->assertExitCode(1);

        $this->assertSame(1, User::count());
    }
}
