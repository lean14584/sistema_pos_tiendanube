<div class="p-8 max-w-5xl mx-auto">
    <x-page-header title="Nuevo usuario" icon="shield-check" />
    @include('livewire.users._form', ['submitLabel' => 'Crear usuario', 'isEdit' => false])
</div>
