<div class="p-8 max-w-5xl mx-auto">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100 mb-8">Editar compra {{ $purchase->number }}</h1>
    @include('livewire.purchases._form', ['submitLabel' => 'Guardar cambios'])
</div>
