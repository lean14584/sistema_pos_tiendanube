<?php

namespace App\Livewire\Categories;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use ShowsToasts;

    #[Url]
    public string $query = '';

    public function delete(Category $category): void
    {
        abort_unless(Auth::user()->puedeEliminar(), 403, 'Tu rol no tiene permiso para eliminar categorías.');

        if ($category->products()->exists()) {
            $this->toastError("No se puede eliminar \"{$category->name}\" porque tiene productos asociados.");

            return;
        }

        $category->delete();

        $this->toastSuccess('Categoría eliminada.');
    }

    public function render()
    {
        $categories = Category::withCount('products')
            ->when($this->query !== '', function ($q) {
                $term = '%'.$this->query.'%';
                $q->where(fn ($q2) => $q2->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term));
            })
            ->orderBy('name')
            ->get();

        return view('livewire.categories.index', [
            'categories' => $categories,
            'hasAnyCategories' => Category::query()->exists(),
        ]);
    }
}
