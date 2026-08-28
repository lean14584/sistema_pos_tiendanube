<?php

namespace App\Livewire\PromotionGroups;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\Product;
use App\Models\PromotionGroup;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use ShowsToasts;

    public ?int $editingId = null;

    public string $name = '';

    public string $buy_qty = '3';

    public string $pay_qty = '2';

    public bool $active = true;

    public string $starts_at = '';

    public string $ends_at = '';

    /** @var array<int, array{id:int, name:string}> */
    public array $selected = [];

    public string $productQuery = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'buy_qty' => ['required', 'integer', 'min:2'],
            'pay_qty' => ['required', 'integer', 'min:1', 'lt:buy_qty'],
            'active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    #[Computed]
    public function productResults()
    {
        $term = trim($this->productQuery);

        if ($term === '') {
            return collect();
        }

        return Product::where('name', 'like', "%{$term}%")
            ->orWhere('sku', 'like', "%{$term}%")
            ->limit(8)
            ->get();
    }

    public function addProduct(int $productId): void
    {
        $product = Product::find($productId);

        if ($product && ! isset($this->selected[$productId])) {
            $this->selected[$productId] = ['id' => $product->id, 'name' => $product->name];
        }

        $this->productQuery = '';
    }

    public function removeProduct(int $productId): void
    {
        unset($this->selected[$productId]);
    }

    public function edit(PromotionGroup $promotionGroup): void
    {
        $this->editingId = $promotionGroup->id;
        $this->name = $promotionGroup->name;
        $this->buy_qty = (string) $promotionGroup->buy_qty;
        $this->pay_qty = (string) $promotionGroup->pay_qty;
        $this->active = $promotionGroup->active;
        $this->starts_at = $promotionGroup->starts_at?->toDateString() ?? '';
        $this->ends_at = $promotionGroup->ends_at?->toDateString() ?? '';
        $this->selected = $promotionGroup->products->mapWithKeys(fn ($p) => [$p->id => ['id' => $p->id, 'name' => $p->name]])->all();
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'starts_at', 'ends_at', 'selected', 'productQuery']);
        $this->buy_qty = '3';
        $this->pay_qty = '2';
        $this->active = true;
    }

    public function save(): void
    {
        $this->validate();

        if (count($this->selected) < 2) {
            $this->addError('selected', 'Elegí al menos 2 productos para la familia.');

            return;
        }

        $data = [
            'name' => $this->name,
            'buy_qty' => $this->buy_qty,
            'pay_qty' => $this->pay_qty,
            'active' => $this->active,
            'starts_at' => $this->starts_at ?: null,
            'ends_at' => $this->ends_at ?: null,
        ];

        $group = $this->editingId
            ? tap(PromotionGroup::findOrFail($this->editingId))->update($data)
            : PromotionGroup::create($data);

        $group->products()->sync(array_keys($this->selected));

        $this->cancel();
        session()->flash('status', 'Familia de promoción guardada.');
    }

    public function toggle(PromotionGroup $promotionGroup): void
    {
        $promotionGroup->update(['active' => ! $promotionGroup->active]);
    }

    public function delete(PromotionGroup $promotionGroup): void
    {
        $promotionGroup->delete();

        $this->toastSuccess('Familia de promoción eliminada.');
    }

    public function render()
    {
        return view('livewire.promotion-groups.index', [
            'groups' => PromotionGroup::with('products')->latest()->get(),
        ]);
    }
}
