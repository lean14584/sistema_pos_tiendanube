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

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #f9fafb; }
        .info-table td { padding: 12px 14px; vertical-align: top; width: 50%; }
        .info-label { font-size: 9px; text-transform: uppercase; color: #6b7280; margin: 0 0 2px; }
        .info-value { font-weight: bold; color: #111827; margin: 0; }

        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th {
            text-align: left; font-size: 9px; text-transform: uppercase; color: #6b7280;
            border-bottom: 1px solid #d1d5db; padding: 6px 8px;
        }
        .items-table td { padding: 6px 8px; border-bottom: 1px solid #f3f4f6; }
        .text-right { text-align: right; }
        .stock-low { color: #dc2626; font-weight: bold; }

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
            </td>
            <td style="width: 45%; text-align: right;">
                <div class="doc-kind">PRODUCTOS Y STOCK POR CATEGORÍA</div>
                <p class="doc-title">{{ $category->name }}</p>
                <div class="muted small">Generado el {{ now()->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td>
                <p class="info-label">Categoría</p>
                <p class="info-value">{{ $category->name }}</p>
                @if ($category->description)
                    <p class="muted small">{{ $category->description }}</p>
                @endif
            </td>
            <td>
                <p class="info-label">Sucursal</p>
                <p class="info-value">{{ $sucursal->name ?? '—' }}</p>
                <p class="muted small">{{ $products->count() }} producto{{ $products->count() === 1 ? '' : 's' }}</p>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Producto</th>
                <th>SKU</th>
                <th class="text-right">Precio</th>
                <th class="text-right">Stock</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                @php
                    $stock = $product->stockEnSucursal($sucursal?->id);
                    $bajoStock = $product->min_stock !== null && $stock < $product->min_stock;
                @endphp
                <tr>
                    <td>{{ $product->name }}</td>
                    <td class="muted">{{ $product->sku ?: '—' }}</td>
                    <td class="text-right">$ {{ number_format((float) $product->price, 2, ',', '.') }}</td>
                    <td class="text-right {{ $bajoStock ? 'stock-low' : '' }}">{{ $stock }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted" style="text-align: center; padding: 20px 0;">
                        Esta categoría todavía no tiene productos.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer-note">Generado por {{ $company->display_name }}.</p>
</body>
</html>
