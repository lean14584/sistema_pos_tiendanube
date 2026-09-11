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

        .company { font-size: 11px; font-weight: bold; line-height: 1; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; }
        .name { font-size: 10px; font-weight: bold; line-height: 1.05; margin-top: 0; }
        .price { font-size: 17px; font-weight: bold; margin-top: 2px; }
        .sku { font-size: 10px; font-family: monospace; letter-spacing: 1px; color: #374151; margin-top: 2px; }
        .barcode { display: block; width: 42mm; margin: 0 auto; }

        /* Tarjeta = precio de lista, Transferencia/Efectivo con su descuento
           por medio de pago (ver CompanySettings::descuentoPctParaMedioDePago,
           mismo cálculo que usa el POS al cobrar). Solo se arma este bloque
           de 3 precios si el cliente tiene esos descuentos configurados; si
           no, sigue mostrando el precio único de siempre (.price). line-height
           chico a propósito: son 3 líneas en vez de 1, hay que compactarlas
           para que siga entrando el código de barras debajo (ver el comentario
           de .label: dompdf no parte ni recorta un <img> que no entra en lo
           que queda de página, lo manda entero a una segunda página). */
        .prices { width: 100%; margin-top: 0; padding: 0 2mm; line-height: 1; }
        .price-row { display: flex; justify-content: space-between; align-items: baseline; }
        .price-row .pm { font-size: 6.5px; font-weight: bold; text-transform: uppercase; color: #111827; }
        .price-row .pv { font-size: 10.5px; font-weight: bold; }
    </style>
</head>
<body>
    @foreach ($labels as $label)
        <div class="label">
            @if ($showCompany)
                <div class="company">{{ $companyName }}</div>
            @endif
            @if ($showName)
                {{-- Truncar el string, no solo recortarlo visualmente: dompdf
                     no respeta overflow:hidden para decidir paginación, así
                     que un nombre demasiado largo puede derramar esta
                     etiqueta a una SEGUNDA página/etiqueta física en vez de
                     solo recortarse — mismo problema, a nivel de página en
                     vez de a nivel del div, que el comentario de .label de
                     arriba. Truncar el texto elimina el riesgo de raíz. --}}
                <div class="name">{{ \Illuminate\Support\Str::limit($label['name'], 50, '...', preserveWords: true) }}</div>
            @endif
            @if ($label['pctTransferencia'] > 0 || $label['pctEfectivo'] > 0)
                <div class="prices">
                    <div class="price-row">
                        <span class="pm">Tarjeta</span>
                        <span class="pv">${{ money($label['price']) }}</span>
                    </div>
                    <div class="price-row">
                        <span class="pm">Transf -{{ rtrim(rtrim(number_format($label['pctTransferencia'], 2), '0'), '.') }}%</span>
                        <span class="pv">${{ money($label['priceTransferencia']) }}</span>
                    </div>
                    <div class="price-row">
                        <span class="pm">Efectivo -{{ rtrim(rtrim(number_format($label['pctEfectivo'], 2), '0'), '.') }}%</span>
                        <span class="pv">${{ money($label['priceEfectivo']) }}</span>
                    </div>
                </div>
            @else
                <div class="price">${{ money($label['price']) }}</div>
            @endif
            @if ($showSku)
                {{-- El código de barras ya incluye los dígitos legibles debajo,
                     así que reemplaza al texto plano del sku cuando se puede
                     armar un EAN13 válido (sku numérico de hasta 12 dígitos, o
                     ya un EAN13 real de 13). Si no, se muestra el sku como
                     texto simple, igual que antes de tener código de barras. --}}
                @if ($label['ean13'] && isset($barcodes[$label['ean13']]))
                    <img class="barcode" src="{{ $barcodes[$label['ean13']] }}" alt="{{ $label['ean13'] }}">
                @elseif ($label['sku'])
                    <div class="sku">{{ $label['sku'] }}</div>
                @endif
            @endif
        </div>
    @endforeach
</body>
</html>
