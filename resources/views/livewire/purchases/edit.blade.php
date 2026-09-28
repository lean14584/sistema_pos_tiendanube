<div class="p-8 max-w-6xl mx-auto">
    <x-page-header title="Editar compra {{ $purchase->number }}" icon="shopping-cart" />
    @include('livewire.purchases._form', ['submitLabel' => 'Guardar cambios', 'showBatchFields' => false])
</div>
