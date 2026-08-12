@php
    $empresaNombre = $empresa->nombre_fantasia ?: ($empresa->razon_social ?: config('app.name'));
@endphp
<x-mail::message>
# Hola {{ $invoice->client->name }}

Te enviamos tu factura **{{ $invoice->number }}** por un total de **${{ number_format($invoice->total, 2) }}**.

@if ($invoice->isFiscal)
Comprobante fiscal autorizado por ARCA — CAE **{{ $invoice->cae }}**.
@endif

La factura completa va adjunta en PDF.

¡Gracias por tu compra!

<x-mail::subcopy>
{{ $empresaNombre }}
</x-mail::subcopy>
</x-mail::message>
