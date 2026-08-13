<div class="p-8 max-w-5xl mx-auto">
    <x-page-header title="Nuevo producto" icon="cube" />
    @include('livewire.products._form', ['submitLabel' => 'Crear producto', 'existingImageUrl' => null, 'canRemoveImage' => false])
</div>
