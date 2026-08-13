<div class="p-8 max-w-5xl mx-auto">
    <x-page-header title="Editar usuario" icon="shield-check" />
    @include('livewire.users._form', ['submitLabel' => 'Guardar cambios', 'isEdit' => true])
</div>
