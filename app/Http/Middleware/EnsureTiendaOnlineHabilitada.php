<?php

namespace App\Http\Middleware;

use App\Models\CompanySettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTiendaOnlineHabilitada
{
    /**
     * Handle an incoming request.
     *
     * Gate de instalación, no de rol: cada cliente prende/apaga el
     * storefront público vía CompanySettings sin redeploy (igual que
     * factura_a_habilitada o tiendanube_store_id). CompanySettings::current()
     * se llama fresco en cada request a propósito — no está memoizado (ver
     * el comentario en el modelo).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(CompanySettings::current()->tienda_online_habilitada, 404);

        return $next($request);
    }
}
