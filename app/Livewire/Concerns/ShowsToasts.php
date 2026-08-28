<?php

namespace App\Livewire\Concerns;

/**
 * Para acciones Livewire que NO navegan (ej. borrar un ítem de una lista):
 * session()->flash() no sirve ahí porque el toast del layout
 * (flash-toasts.blade.php) solo se vuelve a pintar en una carga de página
 * completa, y esas acciones se quedan en la misma pantalla. Dispara el mismo
 * toast de SweetAlert2 directo desde PHP con $this->js().
 */
trait ShowsToasts
{
    protected function toastSuccess(string $message): void
    {
        $this->js('showToast('.json_encode('success').', '.json_encode($message).')');
    }

    protected function toastError(string $message): void
    {
        $this->js('showToast('.json_encode('error').', '.json_encode($message).')');
    }
}
