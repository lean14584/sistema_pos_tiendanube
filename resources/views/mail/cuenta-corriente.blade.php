@php
    $empresaNombre = $empresa->nombre_fantasia ?: ($empresa->razon_social ?: config('app.name'));
@endphp
<x-mail::message>
# Hola {{ $nombre }}

Te enviamos el resumen de tu cuenta corriente con {{ $empresaNombre }}.

@if ($saldo > 0.009)
Saldo pendiente: **${{ number_format($saldo, 2) }}**.
@else
No tenés saldo pendiente. ¡Gracias!
@endif

El detalle completo de movimientos va adjunto en PDF.

<x-mail::subcopy>
{{ $empresaNombre }}
</x-mail::subcopy>
</x-mail::message>
