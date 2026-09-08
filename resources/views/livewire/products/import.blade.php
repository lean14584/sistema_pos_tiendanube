<x-import-excel-wizard
    title="Importar productos desde Excel"
    subtitle="Subí un archivo .xlsx/.csv, emparejá sus columnas con los campos del sistema, y listo."
    :back-route="route('products.index')"
    back-label="Productos"
    :index-route="route('products.index')"
    index-label="Ver productos"
    entidad-singular="producto"
    :campos="$campos"
    :cabeceras="$cabeceras"
    :preview-mapeado="$previewMapeado"
    :total-filas="$totalFilas"
    :step="$step"
    :resultado="$resultado"
>
    <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
        Si usás Tiendanube, estos productos todavía no se enviaron: usá "Enviar productos" en el panel de Tiendanube para sincronizarlos.
    </p>
</x-import-excel-wizard>
