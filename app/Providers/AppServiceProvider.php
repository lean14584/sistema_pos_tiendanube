<?php

namespace App\Providers;

use App\Http\Middleware\EnsureModuleAccess;
use App\Services\MercadoPago\MercadoPagoQrService;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton para que la caché por-instancia de configFor() (ver esa
        // clase) sirva de verdad: sin esto, cada app(MercadoPagoQrService::class)
        // suelto (hay varios en Invoices\Show) crea una instancia nueva y
        // vuelve a leer sucursal_mercadopago_configs. Se resetea solo entre
        // requests reales (o entre tests, que arrancan un container nuevo),
        // así que no hay riesgo de servir una config vieja tras guardar una
        // nueva desde otra pantalla.
        $this->app->singleton(MercadoPagoQrService::class);
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
