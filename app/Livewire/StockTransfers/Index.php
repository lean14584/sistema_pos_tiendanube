<?php

namespace App\Livewire\StockTransfers;

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Sucursal;
use App\Support\CurrentSucursal;
use App\Support\StockAdjuster;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Envío de mercadería entre sucursales: resta stock de una y suma en la otra
 * como una sola operación, en vez de dos Ajustes de Stock sueltos y sin
 * relación entre sí.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $from_sucursal_id = '';

    public string $to_sucursal_id = '';

    public string $notes = '';

    /** @var array<int, array{product_id: int, description: string, quantity: string}> */
    public array $items = [];

    public string $productQuery = '';

    public function mount(): void
    {
        $this->from_sucursal_id = (string) CurrentSucursal::id();
    }

    /** Un cajero/vendedor solo puede enviar DESDE su propia sucursal, no elegir otra. */
    public function puedeElegirOrigen(): bool
    {
        return (bool) auth()->user()?->esAdminGlobal();
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

    public function addProductItem(int $productId): void
    {
        if (collect($this->items)->contains('product_id', $productId)) {
            $this->productQuery = '';

            return;
        }

        $product = Product::findOrFail($productId);

        $this->items[] = [
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => '1',
        ];

        $this->productQuery = '';
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(): void
    {
        // Un cajero/vendedor no elige origen en la UI, pero wire:model igual
        // expone la propiedad en el payload — no confiar en lo que mande el
        // cliente para este campo si no tiene permiso de elegirlo, o podría
        // forzar un envío "desde" una sucursal donde no está.
        if (! $this->puedeElegirOrigen()) {
            $this->from_sucursal_id = (string) CurrentSucursal::id();
        }

        if ($this->from_sucursal_id === '') {
            $this->addError('to_sucursal_id', 'No hay ninguna sucursal de origen resoluble.');

            return;
        }

        $rules = [
            'to_sucursal_id' => ['required', 'exists:sucursales,id', 'different:from_sucursal_id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];

        if ($this->puedeElegirOrigen()) {
            $rules['from_sucursal_id'] = ['required', 'exists:sucursales,id', 'different:to_sucursal_id'];
        }

        $this->validate($rules, [
            'to_sucursal_id.different' => 'El destino tiene que ser distinto del origen.',
            'from_sucursal_id.different' => 'El origen tiene que ser distinto del destino.',
            'items.required' => 'Agregá al menos un producto.',
        ]);

        $fromId = (int) $this->from_sucursal_id;
        $toId = (int) $this->to_sucursal_id;

        // No tiene sentido "enviar" más de lo que físicamente hay en el
        // origen (a diferencia de una venta, acá no hay una razón de negocio
        // para permitirlo — sería mover algo que no existe).
        foreach ($this->items as $index => $item) {
            $product = Product::find($item['product_id']);
            $disponible = $product?->stockEnSucursal($fromId) ?? 0;

            if ((int) $item['quantity'] > $disponible) {
                $this->addError("items.{$index}.quantity", "Solo hay {$disponible} en la sucursal de origen.");

                return;
            }
        }

        DB::transaction(function () use ($fromId, $toId) {
            $transfer = StockTransfer::create([
                'from_sucursal_id' => $fromId,
                'to_sucursal_id' => $toId,
                'user_id' => auth()->id(),
                'notes' => $this->notes ?: null,
            ]);

            foreach ($this->items as $item) {
                $transfer->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);

                StockAdjuster::applyManualDelta($item['product_id'], -(int) $item['quantity'], $fromId);
                StockAdjuster::applyManualDelta($item['product_id'], (int) $item['quantity'], $toId);
            }
        });

        session()->flash('status', 'Envío de mercadería registrado.');
        $this->reset(['to_sucursal_id', 'notes', 'items', 'productQuery']);
    }

    public function render()
    {
        $activeSucursalId = CurrentSucursal::id();

        $transfers = StockTransfer::with(['fromSucursal', 'toSucursal', 'user', 'items.product'])
            ->where(fn ($q) => $q->where('from_sucursal_id', $activeSucursalId)->orWhere('to_sucursal_id', $activeSucursalId))
            ->latest('created_at')
            ->paginate(20);

        return view('livewire.stock-transfers.index', [
            'transfers' => $transfers,
            'sucursales' => Sucursal::where('active', true)->orderBy('name')->get(),
            'sucursalActiva' => CurrentSucursal::get(),
            'puedeElegirOrigen' => $this->puedeElegirOrigen(),
        ]);
    }
}
