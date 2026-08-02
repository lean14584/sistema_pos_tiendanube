<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_can_create_a_category(): void
    {
        Livewire::actingAs($this->admin())
            ->test('categories.create')
            ->set('name', 'Bebidas')
            ->set('description', 'Gaseosas y jugos')
            ->call('save')
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', ['name' => 'Bebidas']);
    }

    public function test_name_is_required(): void
    {
        Livewire::actingAs($this->admin())
            ->test('categories.create')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_can_edit_a_category(): void
    {
        $category = Category::create(['name' => 'Old', 'description' => null]);

        Livewire::actingAs($this->admin())
            ->test('categories.edit', ['category' => $category])
            ->set('name', 'New name')
            ->call('save')
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New name']);
    }

    public function test_cannot_delete_category_with_products(): void
    {
        $category = Category::create(['name' => 'Bebidas']);
        Product::create(['name' => 'Coca Cola', 'price' => 100, 'category_id' => $category->id]);

        Livewire::actingAs($this->admin())
            ->test('categories.index')
            ->call('delete', $category->id);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_can_delete_category_without_products(): void
    {
        $category = Category::create(['name' => 'Sin uso']);

        Livewire::actingAs($this->admin())
            ->test('categories.index')
            ->call('delete', $category->id);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
