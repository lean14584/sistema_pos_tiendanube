<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_alta_de_producto_guarda_la_foto(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin())
            ->test('products.create')
            ->set('name', 'Coca 2.25')
            ->set('price', '1500')
            ->set('iva_rate', '21')
            ->set('stock', '10')
            ->set('image', UploadedFile::fake()->image('coca.jpg'))
            ->call('save');

        $product = Product::where('name', 'Coca 2.25')->first();

        $this->assertNotNull($product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
        $this->assertNotNull($product->imageUrl());
    }

    public function test_editar_reemplaza_la_foto_y_borra_la_anterior(): void
    {
        Storage::fake('public');

        $product = Product::create(['name' => 'X', 'price' => 100, 'iva_rate' => 21, 'stock' => 1]);
        $viejaRuta = UploadedFile::fake()->image('vieja.jpg')->store('products', 'public');
        $product->update(['image_path' => $viejaRuta]);

        Livewire::actingAs($this->admin())
            ->test('products.edit', ['product' => $product])
            ->set('image', UploadedFile::fake()->image('nueva.jpg'))
            ->call('save');

        $product->refresh();

        $this->assertNotSame($viejaRuta, $product->image_path);
        Storage::disk('public')->assertMissing($viejaRuta);
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_quitar_foto_la_borra(): void
    {
        Storage::fake('public');

        $product = Product::create(['name' => 'Y', 'price' => 100, 'iva_rate' => 21, 'stock' => 1]);
        $ruta = UploadedFile::fake()->image('foto.jpg')->store('products', 'public');
        $product->update(['image_path' => $ruta]);

        Livewire::actingAs($this->admin())
            ->test('products.edit', ['product' => $product])
            ->call('removeImage');

        $this->assertNull($product->fresh()->image_path);
        Storage::disk('public')->assertMissing($ruta);
    }
}
