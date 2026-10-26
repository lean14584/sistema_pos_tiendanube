<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TiendaLogoutController extends Controller
{
    /**
     * A diferencia de Auth\LogoutController (staff), acá NO se invalida
     * toda la sesión: esta misma sesión de navegador podría tener también
     * un staff logueado en el guard `web` (son guards independientes pero
     * comparten el mismo store de sesión) — invalidate() los desloguearía
     * a los dos. Solo se cierra el guard `customer` y se rota el token.
     */
    public function __invoke(Request $request)
    {
        Auth::guard('customer')->logout();

        $request->session()->regenerateToken();

        return redirect()->route('tienda.catalog');
    }
}
