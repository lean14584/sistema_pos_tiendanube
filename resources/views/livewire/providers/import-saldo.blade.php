<x-import-excel-wizard
    title="Cargar saldo inicial de proveedores"
    subtitle="Subí un Excel con el saldo de cuenta corriente de cada proveedor (ej. extracto de Tango). No crea proveedores nuevos: solo actualiza el saldo de los que ya existen, matcheados por CUIT/DNI o nombre."
    :back-route="route('providers.index')"
    back-label="Proveedores"
    :index-route="route('providers.index')"
    index-label="Ver proveedores"
    entidad-singular="proveedor"
    :campos="$campos"
    :cabeceras="$cabeceras"
    :preview-mapeado="$previewMapeado"
    :total-filas="$totalFilas"
    :step="$step"
    :resultado="$resultado"
>
    <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
        El saldo cargado aparece como una línea "Saldo inicial (migración)" en la cuenta corriente de cada proveedor.
    </p>
</x-import-excel-wizard>
