<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
        $product = Product::create(['name' => 'Coca 1.5L', 'price' => 1800, 'stock' => 10]);
        Promotion::create(['product_id' => $product->id, 'type' => 'nxm', 'buy_qty' => 2, 'pay_qty' => 1, 'active' => true]);

        $p2 = Product::create(['name' => 'Fideos', 'price' => 900, 'stock' => 10]);
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

    public function test_incluye_la_foto_del_producto_cuando_tiene_una_cargada(): void
    {
        Storage::fake('public');

        $im = imagecreatetruecolor(200, 200);
        imagefill($im, 0, 0, imagecolorallocate($im, 10, 200, 10));
        ob_start();
        imagejpeg($im);
        $jpeg = ob_get_clean();
        imagedestroy($im);

        Storage::disk('public')->put('products/coca.jpg', $jpeg);

        $product = Product::create(['name' => 'Coca 1.5L', 'price' => 1800, 'stock' => 10, 'image_path' => 'products/coca.jpg']);
        Promotion::create(['product_id' => $product->id, 'type' => 'nxm', 'buy_qty' => 2, 'pay_qty' => 1, 'active' => true]);

        $response = $this->actingAs($this->admin())->get(route('promotions.poster'));

        $response->assertOk();
        $info = getimagesizefromstring($response->getContent());
        $this->assertNotFalse($info);
    }

    public function test_no_rompe_si_el_producto_tiene_un_image_path_pero_el_archivo_no_existe(): void
    {
        Storage::fake('public');

        $product = Product::create(['name' => 'Coca 1.5L', 'price' => 1800, 'stock' => 10, 'image_path' => 'products/no-existe.jpg']);
        Promotion::create(['product_id' => $product->id, 'type' => 'nxm', 'buy_qty' => 2, 'pay_qty' => 1, 'active' => true]);

        $response = $this->actingAs($this->admin())->get(route('promotions.poster'));

        $response->assertOk();
        $this->assertNotFalse(getimagesizefromstring($response->getContent()));
    }
}
