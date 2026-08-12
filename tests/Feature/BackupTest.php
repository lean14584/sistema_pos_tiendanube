<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ve_la_pantalla_de_respaldo(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        $this->actingAs($admin)->get(route('backups.index'))->assertOk()->assertSee('Respaldo');
    }

    public function test_un_cajero_no_puede_acceder_al_respaldo(): void
    {
        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true]);

        $this->actingAs($cajero)->get(route('backups.index'))->assertForbidden();
        $this->actingAs($cajero)->get(route('backups.download'))->assertForbidden();
    }

    // La generación real del .zip (VACUUM INTO + archivos) no se puede probar
    // acá porque RefreshDatabase envuelve el test en una transacción y SQLite
    // no permite VACUUM dentro de una. Se verifica manualmente contra la base
    // de archivo real.
}
