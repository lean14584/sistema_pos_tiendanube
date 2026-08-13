<div class="p-6 max-w-6xl mx-auto">
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Editar factura {{ $invoice->number }}</h1>
    @include('livewire.invoices._form', ['submitLabel' => 'Guardar cambios'])
</div>
