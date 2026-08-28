import Swal from 'sweetalert2';

window.Swal = Swal;

/**
 * Reemplazo de wire:confirm con SweetAlert2. wire:confirm usa el confirm()
 * nativo del navegador (bloqueante); SweetAlert2 es async, así que la acción
 * se dispara recién cuando se confirma, no en el mismo tick del click.
 */
window.confirmThen = function (message, action) {
    Swal.fire({
        title: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            action();
        }
    });
};

/**
 * Mismo toast que flash-toasts.blade.php (session status/error), pero para
 * acciones Livewire que NO navegan (ej. borrar de una lista): session()->
 * flash() no sirve ahí porque el toast del layout solo se vuelve a pintar en
 * una carga de página completa, y esas acciones se quedan en la misma. Se
 * dispara desde PHP con $this->js("showToast(...)").
 */
window.showToast = function (type, message) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: type,
        title: message,
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true,
        didOpen: (el) => {
            el.addEventListener('mouseenter', Swal.stopTimer);
            el.addEventListener('mouseleave', Swal.resumeTimer);
        },
    });
};
