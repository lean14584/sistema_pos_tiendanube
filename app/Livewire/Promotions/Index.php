<?php

namespace App\Livewire\Promotions;

use App\Enums\PromotionType;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\Product;
use App\Models\Promotion;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use ShowsToasts;

    public ?int $editingId = null;

    public string $product_id = '';

    public string $type = 'nxm';

    public string $buy_qty = '2';

    public string $pay_qty = '1';

    public string $percent = '50';

    public string $min_qty = '10';

    public bool $active = true;

    public string $starts_at = '';

    public string $ends_at = '';

    protected function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'type' => ['required', 'in:nxm,segunda,cantidad'],
            'buy_qty' => ['required_if:type,nxm', 'nullable', 'integer', 'min:2'],
            'pay_qty' => ['required_if:type,nxm', 'nullable', 'integer', 'min:1', 'lt:buy_qty'],
            'percent' => ['required_if:type,segunda', 'required_if:type,cantidad', 'nullable', 'numeric', 'between:1,100'],
            'min_qty' => ['required_if:type,cantidad', 'nullable', 'integer', 'min:2'],
            'active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    public function edit(Promotion $promotion): void
    {
        $this->editingId = $promotion->id;
        $this->product_id = (string) $promotion->product_id;
        $this->type = $promotion->type->value;
        $this->buy_qty = (string) ($promotion->buy_qty ?? 2);
        $this->pay_qty = (string) ($promotion->pay_qty ?? 1);
        $this->percent = (string) ($promotion->percent ?? 50);
        $this->min_qty = (string) ($promotion->min_qty ?? 10);
        $this->active = $promotion->active;
        $this->starts_at = $promotion->starts_at?->toDateString() ?? '';
        $this->ends_at = $promotion->ends_at?->toDateString() ?? '';
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'product_id', 'starts_at', 'ends_at']);
        $this->type = 'nxm';
        $this->buy_qty = '2';
        $this->pay_qty = '1';
        $this->percent = '50';
        $this->min_qty = '10';
        $this->active = true;
    }

    /**
     * True si ya existe otra promoción activa para el mismo producto cuya
     * vigencia se superpone con la que se está guardando. Evita que el POS
     * tenga que desempatar arbitrariamente entre dos promos vigentes a la vez.
     */
    private function seSuperponeConOtraPromo(): bool
    {
        if (! $this->active) {
            return false;
        }

        $starts = $this->starts_at ?: null;
        $ends = $this->ends_at ?: null;

        return Promotion::query()
            ->where('product_id', $this->product_id)
            ->where('active', true)
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $ends ?? '9999-12-31'))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $starts ?? '0001-01-01'))
            ->exists();
    }

    public function save(): void
    {
        $this->validate();

        if ($this->seSuperponeConOtraPromo()) {
            $this->addError('product_id', 'Ya hay otra promoción activa y vigente para este producto en ese período.');

            return;
        }

        // Solo se guardan los parámetros del tipo elegido; el resto queda nulo.
        $data = [
            'product_id' => $this->product_id,
            'type' => $this->type,
            'active' => $this->active,
            'starts_at' => $this->starts_at ?: null,
            'ends_at' => $this->ends_at ?: null,
            'buy_qty' => null,
            'pay_qty' => null,
            'percent' => null,
            'min_qty' => null,
        ];

        match ($this->type) {
            'nxm' => $data = array_merge($data, ['buy_qty' => $this->buy_qty, 'pay_qty' => $this->pay_qty]),
            'segunda' => $data = array_merge($data, ['percent' => $this->percent]),
            'cantidad' => $data = array_merge($data, ['percent' => $this->percent, 'min_qty' => $this->min_qty]),
        };

        if ($this->editingId) {
            Promotion::findOrFail($this->editingId)->update($data);
        } else {
            Promotion::create($data);
        }

        $this->cancel();
        session()->flash('status', 'Promoción guardada.');
    }

    public function toggle(Promotion $promotion): void
    {
        $promotion->update(['active' => ! $promotion->active]);
    }

    public function delete(Promotion $promotion): void
    {
        $promotion->delete();

        $this->toastSuccess('Promoción eliminada.');
    }

    public function render()
    {
        return view('livewire.promotions.index', [
            'promotions' => Promotion::with('product')->latest()->get(),
            'products' => Product::orderBy('name')->get(),
            'types' => PromotionType::cases(),
        ]);
    }
}
