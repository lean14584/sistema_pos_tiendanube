<?php

namespace App\Livewire\PriceLists;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\PriceList;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use ShowsToasts;

    public ?int $editingId = null;

    public string $name = '';

    public string $adjustment_percent = '0';

    public bool $active = true;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'adjustment_percent' => ['required', 'numeric', 'between:-99,999'],
            'active' => ['boolean'],
        ];
    }

    public function edit(PriceList $priceList): void
    {
        $this->editingId = $priceList->id;
        $this->name = $priceList->name;
        $this->adjustment_percent = (string) $priceList->adjustment_percent;
        $this->active = $priceList->active;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'adjustment_percent', 'active']);
        $this->adjustment_percent = '0';
        $this->active = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            PriceList::findOrFail($this->editingId)->update($data);
        } else {
            PriceList::create($data + ['is_default' => PriceList::count() === 0]);
        }

        $this->cancel();
        session()->flash('status', 'Lista de precios guardada.');
    }

    /** Marca una lista como la predeterminada (desmarca las demás). */
    public function makeDefault(PriceList $priceList): void
    {
        DB::transaction(function () use ($priceList) {
            PriceList::query()->update(['is_default' => false]);
            $priceList->update(['is_default' => true, 'active' => true]);
        });
    }

    public function delete(PriceList $priceList): void
    {
        if ($priceList->is_default) {
            $this->toastError('No se puede eliminar la lista predeterminada.');

            return;
        }

        if ($priceList->clients()->exists()) {
            $this->toastError("No se puede eliminar \"{$priceList->name}\": hay clientes que la usan.");

            return;
        }

        $priceList->delete();

        $this->toastSuccess('Lista de precios eliminada.');
    }

    public function render()
    {
        return view('livewire.price-lists.index', [
            'priceLists' => PriceList::withCount('clients')->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }
}
