<?php

namespace App\Livewire\Categories;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\Category;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use ShowsToasts;

    public function delete(Category $category): void
    {
        if ($category->products()->exists()) {
            $this->toastError("No se puede eliminar \"{$category->name}\" porque tiene productos asociados.");

            return;
        }

        $category->delete();

        $this->toastSuccess('Categoría eliminada.');
    }

    public function render()
    {
        return view('livewire.categories.index', [
            'categories' => Category::withCount('products')->orderBy('name')->get(),
        ]);
    }
}
