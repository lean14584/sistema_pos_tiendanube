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
        x-init="
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: @js(session('status') ? 'success' : 'error'),
                title: @js(session('status') ?? session('error')),
                showConfirmButton: false,
                timer: 3500,
                timerProgressBar: true,
                didOpen: (el) => {
                    el.addEventListener('mouseenter', Swal.stopTimer);
                    el.addEventListener('mouseleave', Swal.resumeTimer);
                },
            })
        "
    ></div>
@endif
