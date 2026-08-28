{{--
    Cartel único para toda la app: en vez de que cada pantalla dibuje su
    propio banner de "status"/"error", este parcial (incluido una sola vez
    en el layout) muestra un toast de SweetAlert2. Va dentro de <main>, no
    en <head>, para que Alpine lo vuelva a inicializar en cada navegación
    con wire:navigate (Livewire reconstruye este nodo en cada visita).
--}}
@if (session('status') || session('error'))
    <div
        x-data
        x-init="showToast(@js(session('status') ? 'success' : 'error'), @js(session('status') ?? session('error')))"
    ></div>
@endif
