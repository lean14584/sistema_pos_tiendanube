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

const PRINT_AGENT_STORAGE_KEY = 'posPrintAgentConfig';

function getPrintAgentConfig() {
    try {
        const raw = JSON.parse(localStorage.getItem(PRINT_AGENT_STORAGE_KEY));

        return raw && raw.port ? raw : null;
    } catch {
        return null;
    }
}

/**
 * Ventana para configurar el agente local (ver pos-print-agent/): puerto y
 * token que ese programa genera en la PC del cajero. Se guarda en
 * localStorage porque es una configuración de ESA PC/navegador, no del
 * negocio — cada caja puede tener una impresora y un agente distintos.
 */
window.configurarImpresoraLocal = function () {
    const actual = getPrintAgentConfig() || { port: 9123, token: '' };

    Swal.fire({
        title: 'Impresora local (agente)',
        html:
            '<input id="swal-agent-port" class="swal2-input" placeholder="Puerto (ej. 9123)" value="'
            + actual.port + '">'
            + '<input id="swal-agent-token" class="swal2-input" placeholder="Token del agente" value="'
            + (actual.token || '') + '">',
        confirmButtonText: 'Guardar',
        showCancelButton: true,
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const port = document.getElementById('swal-agent-port').value.trim();
            const token = document.getElementById('swal-agent-token').value.trim();
            if (!port) {
                Swal.showValidationMessage('El puerto es obligatorio');

                return false;
            }

            return { port, token };
        },
    }).then((result) => {
        if (result.isConfirmed) {
            localStorage.setItem(PRINT_AGENT_STORAGE_KEY, JSON.stringify(result.value));
            showToast('success', 'Impresora local configurada');
        }
    });
};

/**
 * Manda el ticket al agente local instalado en la PC del cajero (ver
 * pos-print-agent/): imprime directo, sin ningún diálogo. Devuelve false (en
 * vez de tirar error) si el agente no está configurado, no está corriendo, o
 * la impresora falla — así el llamador puede caer al método anterior.
 */
async function imprimirConAgenteLocal(escposUrl) {
    const config = getPrintAgentConfig();
    if (!config) {
        return false;
    }

    try {
        const ticket = await fetch(escposUrl, { credentials: 'same-origin' });
        if (!ticket.ok) {
            return false;
        }
        const bytes = await ticket.arrayBuffer();

        const impresion = await fetch(`http://127.0.0.1:${config.port}/print`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/octet-stream',
                'X-Print-Token': config.token || '',
            },
            body: bytes,
        });
        if (!impresion.ok) {
            return false;
        }
        const data = await impresion.json();

        return data.ok === true;
    } catch {
        return false;
    }
}

/**
 * Imprime el ticket térmico. Primero intenta el agente local (sin ningún
 * diálogo del sistema); si no está instalado/configurado o falla, cae al
 * método anterior: cargar la página del ticket (que dispara window.print()
 * sola) en un iframe oculto, mostrando solo el diálogo de impresión del
 * navegador.
 */
window.printTicket = function (fallbackUrl, escposUrl) {
    imprimirConAgenteLocal(escposUrl).then((impresoOk) => {
        if (impresoOk) {
            return;
        }

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
    });
};
