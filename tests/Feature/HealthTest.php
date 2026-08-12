<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TipoComprobanteInterno;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Support\SystemHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ve_el_estado_del_sistema(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        $this->actingAs($admin)->get(route('health.index'))->assertOk()->assertSee('Estado del sistema');
    }

    public function test_un_cajero_no_accede_al_estado(): void
    {
        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true]);

        $this->actingAs($cajero)->get(route('health.index'))->assertForbidden();
    }

    public function test_una_factura_sin_emitir_genera_un_aviso(): void
    {
        $client = Client::create(['name' => 'C', 'email' => 'c@test.com']);
        Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $client->id,
            'tipo_comprobante_interno' => TipoComprobanteInterno::FacturaB,
            'issue_date' => now(), 'due_date' => now(), 'tax_rate' => 0, 'status' => 'pending',
        ]);

        $chequeos = app(SystemHealth::class)->chequeos();
        $sinEmitir = $chequeos->firstWhere('clave', 'sin_emitir');

        $this->assertSame('warning', $sinEmitir['estado']);
        $this->assertGreaterThanOrEqual(1, app(SystemHealth::class)->avisos());
    }
}
