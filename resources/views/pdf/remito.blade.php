<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 36px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1f2937; font-size: 12px; margin: 0; }
        .muted { color: #6b7280; }
        .small { font-size: 10px; }

        .brand-band { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .brand-band td { padding: 0; vertical-align: top; }
        .brand-mark { display: inline-block; width: 14px; height: 14px; background-color: #4f46e5; border-radius: 3px; }
        .brand-name { font-size: 13px; font-weight: bold; color: #111827; padding-left: 6px; }
        .doc-title { font-size: 22px; font-weight: bold; color: #111827; margin: 0 0 2px; }
        .doc-kind { font-size: 12px; font-weight: bold; letter-spacing: 1px; color: #4f46e5; }

        .legend {
            margin: 6px 0 16px; padding: 5px 8px;
            background-color: #fef3c7; color: #92400e;
            border-radius: 4px; font-size: 10px; font-weight: bold; text-align: center;
        }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #f9fafb; }
        .info-table td { padding: 12px 14px; vertical-align: top; }
        .info-label { font-size: 9px; text-transform: uppercase; color: #6b7280; margin: 0 0 2px; }
        .info-value { font-weight: bold; color: #111827; margin: 0; }

        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th {
            text-align: left; font-size: 9px; text-transform: uppercase; color: #6b7280;
            border-bottom: 1px solid #d1d5db; padding: 6px 8px;
        }
        .items-table td { padding: 6px 8px; border-bottom: 1px solid #f3f4f6; }
        .text-right { text-align: right; }

        .totals-box { width: 40%; margin-left: 60%; border-collapse: collapse; margin-top: 8px; }
        .totals-box td { padding: 4px 8px; }
        .total-row td { font-weight: bold; color: #111827; border-top: 1px solid #d1d5db; }

        .sign { margin-top: 48px; width: 100%; border-collapse: collapse; }
        .sign td { width: 50%; padding-top: 26px; border-top: 1px solid #9ca3af; text-align: center; color: #6b7280; font-size: 10px; }
        .sign .gap { border: 0; width: 8%; }

        .footer-note { margin-top: 26px; padding-top: 10px; border-top: 1px solid #e5e7eb; text-align: center; color: #9ca3af; font-size: 9px; }
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
                @if ($company->domicilio)<div class="muted small" style="margin-top: 4px;">{{ $company->domicilio }}</div>@endif
                @if ($company->cuit)<div class="muted small">CUIT: {{ $company->cuit }}</div>@endif
            </td>
            <td style="width: 45%; text-align: right;">
                <div class="doc-kind">REMITO — NOTA DE ENTREGA</div>
                <p class="doc-title">{{ $remito->number }}</p>
                <div class="muted small">Fecha: {{ $remito->issue_date->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="legend">DOCUMENTO NO VÁLIDO COMO FACTURA</div>

    <table class="info-table">
        <tr>
            <td>
                <p class="info-label">Entregar a</p>
                <p class="info-value">{{ $remito->client->name }}</p>
                @if ($remito->client->address)<p class="muted small">{{ $remito->client->address }}</p>@endif
                @if ($remito->client->tax_id)<p class="muted small">ID fiscal: {{ $remito->client->tax_id }}</p>@endif
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="text-right">Cantidad</th>
                @if ($conPrecios)
                    <th class="text-right">Precio unit.</th>
                    <th class="text-right">Total</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($remito->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                    @if ($conPrecios)
                        <td class="text-right">${{ money($item->unit_price) }}</td>
                        <td class="text-right">${{ money($item->line_total) }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($conPrecios)
        <table class="totals-box">
            <tr class="total-row"><td>Total</td><td class="text-right">${{ money($remito->total) }}</td></tr>
        </table>
    @endif

    <table class="sign">
        <tr>
            <td>Firma y aclaración (entrega)</td>
            <td class="gap"></td>
            <td>Firma y aclaración (recepción)</td>
        </tr>
    </table>

    <p class="footer-note">Remito generado por {{ $company->display_name }}.</p>
</body>
</html>
