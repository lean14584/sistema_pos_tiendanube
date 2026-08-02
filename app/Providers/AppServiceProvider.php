<?php

namespace App\Providers;

use App\Http\Middleware\EnsureModuleAccess;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Livewire solo re-aplica automáticamente un puñado de middleware del
        // framework (auth, SubstituteBindings, etc.) en los requests AJAX de
        // actualización de un componente — no los middleware personalizados
        // de la app. Sin esto, un usuario cuyo rol pierde acceso a un módulo
        // mientras tiene un componente de esa sección ya montado (otra
        // pestaña, sesión vieja) podría seguir ejecutando acciones (save,
        // delete, etc.) sobre ese componente aunque ya no pueda recargarlo.
        Livewire::addPersistentMiddleware([
            EnsureModuleAccess::class,
        ]);
    }
}
