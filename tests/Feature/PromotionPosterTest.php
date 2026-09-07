<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySettings;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionGroup;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\PromotionPoster;
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

    public function test_encabezado_usa_la_razon_social_global_como_primera_opcion(): void
    {
        $company = CompanySettings::current();
        $company->update(['razon_social' => 'Mi Empresa S.A.', 'nombre_fantasia' => 'Fantasía']);

        $this->assertSame('Mi Empresa S.A.', PromotionPoster::headerName($company->fresh()));
    }

    public function test_encabezado_cae_al_nombre_de_fantasia_si_no_hay_razon_social_global(): void
    {
        $company = CompanySettings::current();
        $company->update(['razon_social' => '', 'nombre_fantasia' => 'Fantasía']);

        $this->assertSame('Fantasía', PromotionPoster::headerName($company->fresh()));
    }

    public function test_encabezado_cae_a_la_razon_social_de_una_sucursal_como_ultimo_recurso(): void
    {
        // Reproduce el caso real: el usuario cargó "razón social" en la
        // pantalla de Sucursales (que es un campo aparte, para facturar por
        // sucursal) pensando que era el dato global de la empresa.
        $company = CompanySettings::current();
        $company->update(['razon_social' => '', 'nombre_fantasia' => null]);

        Sucursal::create(['name' => 'Alternativa', 'razon_social' => 'Super Perro', 'punto_venta' => 3, 'active' => true]);

        $this->assertSame('Super Perro', PromotionPoster::headerName($company->fresh()));
    }

    public function test_encabezado_vacio_si_no_hay_nada_cargado_en_ningun_lado(): void
    {
        $company = CompanySettings::current();
        $company->update(['razon_social' => '', 'nombre_fantasia' => null]);

        $this->assertNull(PromotionPoster::headerName($company->fresh()));
    }
}
