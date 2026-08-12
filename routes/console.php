<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Respaldo automático diario. Requiere que el programador de Laravel esté
// corriendo (una tarea de Windows que ejecute `php artisan schedule:run` cada
// minuto), o bien programar `php artisan backup:run` directo una vez por día.
Schedule::command('backup:run')
    ->dailyAt(config('backups.daily_at', '23:30'))
    ->withoutOverlapping();
