<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Product;
use App\Models\User;
use App\Support\Ean13;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductAutoSkuTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_arranca_en_el_ean13_completo_del_1_si_no_hay_ningun_sku_numerico_cargado(): void
    {
        // El campo ya viene con los ceros + dígito verificador puestos (no
        // solo "1"), para que quede listo para el lector sin pasos extra.
        Livewire::actingAs($this->admin())
            ->test('products.create')
            ->assertSet('sku', Ean13::fromSku('1'));
    }

    public function test_continua_la_secuencia_del_mayor_sku_numerico_existente(): void
    {
        Product::create(['name' => 'Plato', 'sku' => '9876', 'price' => 100, 'iva_rate' => 21, 'stock' => 0]);
        Product::create(['name' => 'Sabanas', 'sku' => '9877', 'price' => 200, 'iva_rate' => 21, 'stock' => 0]);
        // Un sku no numérico no debe romper el cálculo del máximo.
        Product::create(['name' => 'Con código de proveedor', 'sku' => 'NB-14', 'price' => 300, 'iva_rate' => 21, 'stock' => 0]);

        Livewire::actingAs($this->admin())
            ->test('products.create')
            ->assertSet('sku', Ean13::fromSku('9878'));
    }

    public function test_reconoce_la_secuencia_aunque_el_sku_existente_ya_este_paddeado_a_13_digitos(): void
    {
        // Un sku ya guardado como EAN13 completo (ej. generado por una
        // sesión anterior) tiene que seguir contando para el próximo número,
        // no arrancar de nuevo desde 1.
        Product::create(['name' => 'Plato', 'sku' => Ean13::fromSku('9876'), 'price' => 100, 'iva_rate' => 21, 'stock' => 0]);

        Livewire::actingAs($this->admin())
            ->test('products.create')
            ->assertSet('sku', Ean13::fromSku('9877'));
    }

    public function test_el_codigo_autogenerado_sigue_siendo_editable(): void
    {
        Livewire::actingAs($this->admin())
            ->test('products.create')
            ->set('name', 'Termo 1 litro')
            ->set('price', '5000')
            ->set('iva_rate', '21')
            ->set('stock', '0')
            ->set('sku', '5555')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('5555', Product::where('name', 'Termo 1 litro')->value('sku'));
    }
}
