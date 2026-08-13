<div class="p-6 max-w-5xl mx-auto">
    <x-page-header title="Editar factura {{ $invoice->number }}" icon="document-text" />
    @include('livewire.invoices._form', ['submitLabel' => 'Guardar cambios'])
</div>
