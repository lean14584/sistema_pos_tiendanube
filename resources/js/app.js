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
 * Imprime con el método anterior: carga la página del ticket (que dispara
 * window.print() sola) en un iframe oculto, mostrando el diálogo de
 * impresión del sistema. Es el respaldo manual de printTicket(), y también
 * el único método si no hay agente local instalado en esta PC.
 */
window.printTicketConDialogo = function (fallbackUrl) {
    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.src = fallbackUrl;

    // Se saca sola del DOM pasado un rato: tiempo de sobra para que el
    // cajero vea y responda el diálogo de impresión.
    setTimeout(() => iframe.remove(), 60000);

    document.body.appendChild(iframe);
};

const POSPRINT_HINT_KEY = 'posprintHintShown';

/**
 * Imprime el ticket térmico vía el protocolo posprint:// que registra el
 * agente local (ver pos-print-agent/) en el Registro de Windows: no hace
 * falta configurar puerto ni token en el navegador. La primera vez, el
 * navegador muestra un aviso cerca de la barra de direcciones preguntando
 * si abrir la app — hay que confirmarlo (y tildar "recordar") para que las
 * próximas veces imprima solo, sin ese aviso.
 *
 * A propósito NO hay detección automática de éxito/fallo acá: el agente no
 * tiene ninguna ventana (para no mostrar nada en pantalla), así que nunca le
 * saca el foco a la pestaña — cualquier heurística basada en eso (blur,
 * visibilitychange) termina disparando siempre, imprimiendo dos veces sin
 * avisar. Si esto no imprimió, está printTicketConDialogo() como respaldo
 * manual explícito (botón aparte en la vista de la factura).
 */
window.printTicket = function (fallbackUrl, escposUrl) {
    if (!localStorage.getItem(POSPRINT_HINT_KEY)) {
        localStorage.setItem(POSPRINT_HINT_KEY, '1');
        showToast('info', 'Si el navegador pregunta si abrir "PosPrintAgent", elegí Abrir y tildá "recordar" — así la próxima vez imprime solo.');
    }

    window.location.href = 'posprint://print?url=' + encodeURIComponent(escposUrl);
};
