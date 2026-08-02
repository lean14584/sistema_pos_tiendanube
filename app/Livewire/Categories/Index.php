<?php

namespace App\Livewire\Categories;

use App\Models\Category;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public function delete(Category $category): void
    {
        if ($category->products()->exists()) {
            session()->flash('error', "No se puede eliminar \"{$category->name}\" porque tiene productos asociados.");

            return;
        }

        $category->delete();
    }

    public function render()
    {
        return view('livewire.categories.index', [
            'categories' => Category::withCount('products')->orderBy('name')->get(),
        ]);
    }
}
