<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 2mm; }

        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #111827; }

        /*
         * Una etiqueta = una página física (ver Labels::descargarEtiquetadoraPdf,
         * que fija el tamaño de página exacto de la HPRT LPQ80 con setPaper).
         * overflow:hidden a propósito: si el contenido no entra, se recorta
         * ahí (se pierde el SKU, que va último) en vez de derramar a una
         * SEGUNDA etiqueta física — ver el mismo criterio en
         * resources/views/livewire/products/labels.blade.php (modo pantalla).
         */
        .label {
            width: 100%;
            height: {{ $heightMm - 4 }}mm;
            overflow: hidden;
            text-align: center;
            page-break-after: always;
        }
        .label:last-child { page-break-after: auto; }

        .company { font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; }
        .name { font-size: 11px; font-weight: bold; line-height: 1.15; margin-top: 1px; }
        .price { font-size: 17px; font-weight: bold; margin-top: 2px; }
        .sku { font-size: 10px; font-family: monospace; letter-spacing: 1px; color: #374151; margin-top: 2px; }
    </style>
</head>
<body>
    @foreach ($labels as $label)
        <div class="label">
            @if ($showCompany)
                <div class="company">{{ $companyName }}</div>
            @endif
            @if ($showName)
                <div class="name">{{ $label['name'] }}</div>
            @endif
            <div class="price">${{ money($label['price']) }}</div>
            @if ($showSku && $label['sku'])
                <div class="sku">{{ $label['sku'] }}</div>
            @endif
        </div>
    @endforeach
</body>
</html>
