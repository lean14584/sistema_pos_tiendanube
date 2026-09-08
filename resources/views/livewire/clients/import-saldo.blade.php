<x-import-excel-wizard
    title="Cargar saldo inicial de clientes"
    subtitle="Subí un Excel con el saldo de cuenta corriente de cada cliente (ej. extracto de Tango). No crea clientes nuevos: solo actualiza el saldo de los que ya existen, matcheados por CUIT/DNI o nombre."
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
        El saldo cargado aparece como una línea "Saldo inicial (migración)" en la cuenta corriente de cada cliente.
    </p>
</x-import-excel-wizard>
