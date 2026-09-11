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

function imprimirConIframeOculto(url) {
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
}

/**
 * Imprime el ticket térmico vía el protocolo posprint:// que registra el
 * agente local (ver pos-print-agent/) en el Registro de Windows: no hace
 * falta configurar puerto ni token en el navegador, ni repetirlo si el
 * agente se reinstala. La primera vez que se usa, el navegador pregunta si
 * confiar en el sitio para abrir la app (con opción de recordarlo); después
 * queda andando solo. Si el protocolo no está registrado (agente no
 * instalado en esa PC), la navegación es un no-op y no hay forma 100%
 * confiable de detectarlo — se usa la señal estándar para esto (si el
 * agente SÍ estaba instalado, el hand-off a la app externa le saca el foco
 * a la pestaña); si no se detecta ese blur en un plazo corto, se asume que
 * no está instalado y se cae al método anterior (iframe + window.print(),
 * que muestra el diálogo del sistema).
 */
window.printTicket = function (fallbackUrl, escposUrl) {
    let handled = false;
    const onBlur = () => {
        handled = true;
    };
    window.addEventListener('blur', onBlur, { once: true });

    window.location.href = 'posprint://print?url=' + encodeURIComponent(escposUrl);

    setTimeout(() => {
        window.removeEventListener('blur', onBlur);
        if (!handled) {
            imprimirConIframeOculto(fallbackUrl);
        }
    }, 1200);
};
