<?php

return [

    // Carpeta donde se guardan los respaldos automáticos.
    'path' => env('BACKUP_PATH', storage_path('app/backups')),

    // Carpeta adicional (pendrive, disco externo o carpeta sincronizada con la
    // nube) donde copiar cada respaldo. Vacío = no copiar a otro lado.
    'copy_to' => env('BACKUP_COPY_TO'),

    // Cuántos respaldos conservar (se borran los más viejos). 0 = no borrar.
    'keep' => (int) env('BACKUP_KEEP', 14),

    // Hora del respaldo automático diario (formato 24hs, HH:MM).
    'daily_at' => env('BACKUP_DAILY_AT', '23:30'),

];
