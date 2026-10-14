<?php

namespace App\Livewire\Discounts;

use App\Enums\DiscountType;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\Discount;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use ShowsToasts, WithPagination;

    // Solo se setea server-side, desde edit()/cancel() — mismo motivo que
    // Promotions\Index::editingId (ver ese comentario).
    #[Locked]
    public ?int $editingId = null;

    #[Url]
    public string $query = '';

    public function updating(string $name): void
    {
        if ($name === 'query') {
            $this->resetPage();
        }
    }

    public string $name = '';

    public string $type = 'percent';

    public string $value = '';

    public bool $active = true;

    public string $starts_at = '';

    public string $ends_at = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => [
                'required', 'numeric', 'min:0.01',
                $this->type === 'percent' ? 'max:100' : 'max:99999999.99',
            ],
            'active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    public function edit(Discount $discount): void
    {
        abort_unless(Auth::user()->puedeEliminar(), 403, 'Tu rol no tiene permiso para gestionar descuentos.');

        $this->editingId = $discount->id;
        $this->name = $discount->name;
        $this->type = $discount->type->value;
        $this->value = (string) $discount->value;
        $this->active = $discount->active;
        $this->starts_at = $discount->starts_at?->toDateString() ?? '';
        $this->ends_at = $discount->ends_at?->toDateString() ?? '';
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'value', 'starts_at', 'ends_at']);
        $this->type = 'percent';
        $this->active = true;
    }

    public function save(): void
    {
        abort_unless(Auth::user()->puedeEliminar(), 403, 'Tu rol no tiene permiso para gestionar descuentos.');

        $this->validate();

        $data = [
            'name' => $this->name,
            'type' => $this->type,
            'value' => $this->value,
            'active' => $this->active,
            'starts_at' => $this->starts_at ?: null,
            'ends_at' => $this->ends_at ?: null,
        ];

        if ($this->editingId) {
            Discount::findOrFail($this->editingId)->update($data);
        } else {
            Discount::create($data);
        }

        $this->cancel();
        session()->flash('status', 'Descuento guardado.');
    }

    public function toggle(Discount $discount): void
    {
        abort_unless(Auth::user()->puedeEliminar(), 403, 'Tu rol no tiene permiso para gestionar descuentos.');

        $discount->update(['active' => ! $discount->active]);
    }

    public function delete(Discount $discount): void
    {
        abort_unless(Auth::user()->puedeEliminar(), 403, 'Tu rol no tiene permiso para eliminar descuentos.');

        $discount->delete();

        $this->toastSuccess('Descuento eliminado.');
    }

    public function render()
    {
        $discounts = Discount::query()
            ->when($this->query !== '', fn ($q) => $q->where('name', 'like', '%'.$this->query.'%'))
            ->latest()
            ->paginate(20);

        return view('livewire.discounts.index', [
            'discounts' => $discounts,
            'hasAnyDiscounts' => Discount::query()->exists(),
            'types' => DiscountType::cases(),
            'canManage' => Auth::user()->puedeEliminar(),
        ]);
    }
}
