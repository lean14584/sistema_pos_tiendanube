<div class="p-8 max-w-5xl mx-auto">
    <x-page-header title="Editar producto" icon="cube" />
    @include('livewire.products._form', ['submitLabel' => 'Guardar cambios', 'existingImageUrl' => $product->imageUrl(), 'canRemoveImage' => true])
</div>
