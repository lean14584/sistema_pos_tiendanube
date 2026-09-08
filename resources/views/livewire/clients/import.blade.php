<x-import-excel-wizard
    title="Importar clientes desde Excel"
    subtitle="Subí un archivo .xlsx/.csv (ej. exportado de Tango), emparejá sus columnas con los campos del sistema, y listo."
    :back-route="route('clients.index')"
    back-label="Clientes"
    :index-route="route('clients.index')"
    index-label="Ver clientes"
    entidad-singular="cliente"
    :campos="$campos"
    :cabeceras="$cabeceras"
    :preview-mapeado="$previewMapeado"
    :total-filas="$totalFilas"
    :step="$step"
    :resultado="$resultado"
>
    <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
        Se busca el cliente existente primero por CUIT/DNI y si no, por nombre exacto: si coincide se actualiza, si no se crea uno nuevo.
    </p>
</x-import-excel-wizard>
