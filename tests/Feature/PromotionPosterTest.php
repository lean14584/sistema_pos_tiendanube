<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\PromotionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionPosterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_genera_un_png_valido_sin_promociones(): void
    {
        $response = $this->actingAs($this->admin())->get(route('promotions.poster'));

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));

        $info = getimagesizefromstring($response->getContent());
        $this->assertNotFalse($info);
        $this->assertSame(1080, $info[0]);
    }

    public function test_genera_un_png_con_promociones_individuales_y_de_familia(): void
    {
        $product = \App\Models\Product::create(['name' => 'Coca 1.5L', 'price' => 1800, 'stock' => 10]);
        \App\Models\Promotion::create(['product_id' => $product->id, 'type' => 'nxm', 'buy_qty' => 2, 'pay_qty' => 1, 'active' => true]);

        $p2 = \App\Models\Product::create(['name' => 'Fideos', 'price' => 900, 'stock' => 10]);
        $group = PromotionGroup::create(['name' => 'Familia Limpieza', 'buy_qty' => 3, 'pay_qty' => 2, 'active' => true]);
        $group->products()->attach($p2->id);

        $response = $this->actingAs($this->admin())->get(route('promotions.poster'));

        $response->assertOk();
        $info = getimagesizefromstring($response->getContent());
        $this->assertNotFalse($info);
    }

    public function test_cajero_no_puede_generar_el_cartel(): void
    {
        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true]);

        $this->actingAs($cajero)->get(route('promotions.poster'))->assertForbidden();
    }
}
