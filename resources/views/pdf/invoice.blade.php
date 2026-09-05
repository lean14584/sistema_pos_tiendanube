<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 36px 40px; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #1f2937;
            font-size: 12px;
            margin: 0;
        }

        .muted { color: #6b7280; }
        .small { font-size: 10px; }

        /* ---- Header band ---- */
        .brand-band {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .brand-band td { padding: 0; vertical-align: top; }
        .brand-mark {
            display: inline-block;
            width: 14px;
            height: 14px;
            background-color: #4f46e5;
            border-radius: 3px;
        }
        .brand-name {
            font-size: 13px;
            font-weight: bold;
            color: #111827;
            padding-left: 6px;
        }
        .doc-title {
            font-size: 22px;
            font-weight: bold;
            color: #111827;
            margin: 0 0 2px;
        }
        .status-pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
            color: #ffffff;
        }

        /* ---- Info table (bill-to / invoice meta) ---- */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: #f9fafb;
            border-radius: 6px;
        }
        .info-table td {
            padding: 12px 16px;
            vertical-align: top;
            width: 50%;
        }
        .info-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #9ca3af;
            margin: 0 0 4px;
        }
        .info-value { font-size: 12px; color: #111827; font-weight: bold; margin: 0 0 2px; }

        /* ---- Items table ---- */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .items-table thead th {
            text-align: left;
            background-color: #4f46e5;
            color: #ffffff;
            padding: 8px 10px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .items-table thead th:first-child { border-radius: 4px 0 0 4px; }
        .items-table thead th:last-child { border-radius: 0 4px 4px 0; }
        .items-table tbody td {
            padding: 9px 10px;
            border-bottom: 1px solid #f3f4f6;
        }
        .items-table tbody tr:nth-child(even) { background-color: #f9fafb; }
        .text-right { text-align: right; }

        /* ---- Totals ---- */
        .totals-wrap { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .totals-wrap > tr > td { vertical-align: top; }
        .totals-box { width: 220px; border-collapse: collapse; }
        .totals-box td { padding: 4px 0; font-size: 12px; }
        .totals-box .total-row td {
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
            font-weight: bold;
            font-size: 14px;
            color: #4f46e5;
        }

        /* ---- Footer blocks ---- */
        .panel {
            margin-top: 22px;
            padding: 12px 16px;
            background-color: #f9fafb;
            border-radius: 6px;
        }
        .panel-title {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #9ca3af;
            margin: 0 0 6px;
        }
        .footer-note {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #9ca3af;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <table class="brand-band">
        <tr>
            <td style="width: 55%;">
                @if ($logoPath)
                    <img src="{{ $logoPath }}" style="height: 32px; vertical-align: middle;">
                @else
                    <span class="brand-mark"></span>
                @endif
                <span class="brand-name">{{ $company->display_name }}</span>
            </td>
            <td style="width: 45%; text-align: right;">
                <p class="doc-title">{{ $invoice->number }}</p>
                @php
                    $statusColors = [
                        'draft' => '#6b7280', 'pending' => '#d97706',
                        'paid' => '#059669', 'overdue' => '#dc2626',
                    ];
                @endphp
                <span class="status-pill" style="background-color: {{ $statusColors[$invoice->effective_status->value] }};">
                    {{ strtoupper($invoice->effective_status->label()) }}
                </span>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td>
                <p class="info-label">Facturado a</p>
                <p class="info-value">{{ $invoice->client->name }}</p>
                <p class="muted small">{{ $invoice->client->email }}</p>
                @if ($invoice->client->address)<p class="muted small">{{ $invoice->client->address }}</p>@endif
                @if ($invoice->client->tax_id)<p class="muted small">ID fiscal: {{ $invoice->client->tax_id }}</p>@endif
            </td>
            <td style="text-align: right;">
                <p class="info-label">Fecha de emisión</p>
                <p class="info-value">{{ $invoice->issue_date->format('d/m/Y') }}</p>
                <p class="info-label" style="margin-top: 8px;">Fecha de vencimiento</p>
                <p class="info-value">{{ $invoice->due_date->format('d/m/Y') }}</p>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="text-right">Cant.</th>
                <th class="text-right">Precio unit.</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                    <td class="text-right">${{ money($item->unit_price) }}</td>
                    <td class="text-right">${{ money($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-wrap">
        <tr>
            <td style="width: 60%;"></td>
            <td style="width: 40%;">
                <table class="totals-box">
                    <tr><td>Neto gravado</td><td class="text-right">${{ money($invoice->neto_gravado) }}</td></tr>
                    @if ($invoice->neto_exento > 0)
                        <tr><td>Exento / no gravado</td><td class="text-right">${{ money($invoice->neto_exento) }}</td></tr>
                    @endif
                    @foreach ($invoice->ivaPorAlicuota() as $linea)
                        <tr><td>IVA {{ rtrim(rtrim(number_format($linea['tasa'], 2), '0'), '.') }}%</td><td class="text-right">${{ money($linea['iva']) }}</td></tr>
                    @endforeach
                    <tr class="total-row"><td>Total</td><td class="text-right">${{ money($invoice->total) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($invoice->isFiscal)
        <table style="width: 100%; border-collapse: collapse; margin-top: 14px;">
            <tr>
                <td style="width: 70%; vertical-align: top;">
                    <p class="info-label">CAE</p>
                    <p class="info-value">{{ $invoice->cae }}</p>
                    <p class="muted small">Vto. CAE: {{ $invoice->cae_vencimiento->format('d/m/Y') }}</p>
                    <p class="muted small">
                        {{ $invoice->tipo_comprobante->label() }} ·
                        Pto. Vta. {{ str_pad($invoice->punto_venta, 4, '0', STR_PAD_LEFT) }}
                        N° {{ str_pad($invoice->numero_comprobante_afip, 8, '0', STR_PAD_LEFT) }}
                    </p>
                </td>
                <td style="width: 30%; text-align: right; vertical-align: top;">
                    <img src="data:image/png;base64,{{ $qrImage }}" style="width: 90px; height: 90px;">
                </td>
            </tr>
        </table>
    @endif

    @if ($invoice->payments->isNotEmpty())
        <div class="panel">
            <p class="panel-title">Métodos de pago</p>
            <table style="width: 100%; border-collapse: collapse;">
                @foreach ($invoice->payments as $payment)
                    <tr>
                        <td style="padding: 2px 0;">{{ $payment->method->label() }}</td>
                        <td style="padding: 2px 0; text-align: right; font-weight: bold;">${{ money($payment->amount) }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    @if ($invoice->notes)
        <div class="panel">
            <p class="panel-title">Notas</p>
            <p class="muted" style="margin: 0;">{{ $invoice->notes }}</p>
        </div>
    @endif

    <p class="footer-note">Generado por {{ $company->display_name }} · {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
