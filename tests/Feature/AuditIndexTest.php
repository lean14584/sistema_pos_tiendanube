<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_ver_la_pantalla_de_auditoria(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);
        Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        Livewire::actingAs($admin)
            ->test('audit.index')
            ->assertOk()
            ->assertSee('Notebook', escape: false)
            ->assertSee('Alta');
    }

    public function test_vendedor_no_puede_acceder_a_auditoria(): void
    {
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true]);

        $this->actingAs($vendedor)->get(route('audit.index'))->assertForbidden();
    }

    public function test_filtro_por_modelo_funciona(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);
        Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        Livewire::actingAs($admin)
            ->test('audit.index')
            ->set('modelo', \App\Models\Invoice::class)
            ->assertDontSee('Notebook');
    }
}
