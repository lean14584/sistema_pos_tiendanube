<x-import-excel-wizard
    title="Importar ventas históricas desde Excel"
    subtitle="Subí un listado de ventas de otro sistema (ej. Tango). Quedan solo para consulta: no generan numeración AFIP ni modifican la cuenta corriente."
    :back-route="route('historical-sales.index')"
    back-label="Ventas históricas"
    :index-route="route('historical-sales.index')"
    index-label="Ver ventas históricas"
    entidad-singular="venta"
    :campos="$campos"
    :cabeceras="$cabeceras"
    :preview-mapeado="$previewMapeado"
    :total-filas="$totalFilas"
    :step="$step"
    :resultado="$resultado"
>
    <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
        Cada fila se agrega como un registro nuevo: si importás el mismo archivo dos veces, se duplican.
    </p>
</x-import-excel-wizard>
