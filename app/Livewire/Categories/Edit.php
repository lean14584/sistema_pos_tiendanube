<?php

namespace App\Livewire\Categories;

use App\Models\Category;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public Category $category;

    public string $name = '';

    public string $description = '';

    public function mount(Category $category): void
    {
        $this->category = $category;
        $this->name = $category->name;
        $this->description = (string) $category->description;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $this->category->update($data);

        $this->redirect(route('categories.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.categories.edit');
    }
}
