<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ticket {{ $invoice->number }}</title>
    <style>
        @page {
            size: 58mm auto;
            margin: 0;
        }

        body {
            margin: 0;
            padding: 16px 0;
            background: #e5e7eb;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-family: sans-serif;
        }

        img {
            /* El PNG se renderiza a 384px @ 203dpi (≈48mm reales, ver
               TicketPrinterService::CANVAS_WIDTH) — no a los 58mm del ancho
               de papel. Forzarlo a 58mm lo estira ~20%: el negro sólido se
               vuelve gris (se ve "floja") y el contenido de la derecha se
               corre fuera del área imprimible. */
            width: 48mm;
            display: block;
        }

        .toolbar {
            margin-bottom: 12px;
        }

        .toolbar button {
            padding: 8px 16px;
            font-size: 14px;
            cursor: pointer;
        }

        @media print {
            body {
                padding: 0;
                background: #fff;
            }

            .toolbar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>

    <img id="ticket-img" src="{{ route('invoices.ticket-image', $invoice) }}" alt="Ticket {{ $invoice->number }}">

    <script>
        const img = document.getElementById('ticket-img');
        const triggerPrint = () => window.print();

        if (img.complete) {
            triggerPrint();
        } else {
            img.addEventListener('load', triggerPrint);
        }

        window.addEventListener('afterprint', () => window.close());
    </script>
</body>
</html>
