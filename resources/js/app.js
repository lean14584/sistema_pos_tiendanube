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

/**
 * Imprime el ticket térmico sin abrir pestaña ni ventana visible: carga la
 * página del ticket (que ya dispara window.print() sola al cargar la
 * imagen) en un iframe oculto de la misma pantalla. El diálogo de impresión
 * del sistema es lo único que se ve, igual que al imprimir cualquier PDF.
 */
window.printTicket = function (url) {
    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.src = url;

    // Se saca sola del DOM pasado un rato: tiempo de sobra para que el
    // cajero vea y responda el diálogo de impresión.
    setTimeout(() => iframe.remove(), 60000);

    document.body.appendChild(iframe);
};
