<?php
$tnCompany = \App\Models\CompanySettings::current();
$tnCartCount = \App\Support\TiendaCart::count();
$tnCustomer = \Illuminate\Support\Facades\Auth::guard('customer')->user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Tienda') · {{ $tnCompany->display_name }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #1B2A22;
            --paper: #F7F5EF;
            --market: #2F5233;
            --market-dark: #203A25;
            --saffron: #E0A458;
            --saffron-dark: #C98A38;
            --stamp: #B23A2E;
            --rule: #D8D2C4;
            --muted: #6B7A6D;
            --font-display: 'Fraunces', Georgia, serif;
            --font-body: 'Work Sans', -apple-system, sans-serif;
        }

        * { box-sizing: border-box; }

        body {
            font-family: var(--font-body);
            color: var(--ink);
            background-color: var(--paper);
            padding-top: 76px;
            -webkit-font-smoothing: antialiased;
        }
        @media (min-width: 768px) { body { padding-top: 84px; } }

        h1, h2, .tn-display { font-family: var(--font-display); color: var(--ink); }

        a { color: var(--market); }
        a:hover { color: var(--market-dark); }

        /* ---------- Navbar ---------- */
        .tn-navbar {
            background-color: var(--paper) !important;
            border-bottom: 1px solid var(--rule);
            padding: .9rem 0;
        }
        .tn-navbar .navbar-brand {
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 1.5rem;
            color: var(--ink);
            display: flex;
            align-items: center;
        }
        .tn-navbar .navbar-brand:hover { color: var(--market); }

        .tn-nav-link {
            color: var(--ink);
            text-decoration: none;
            font-size: .92rem;
            font-weight: 500;
            padding: .5rem .25rem;
        }
        .tn-nav-link:hover { color: var(--market); }

        .tn-cart-link { position: relative; display: inline-flex; align-items: center; padding: .5rem .25rem; color: var(--ink); text-decoration: none; }
        .tn-cart-link:hover { color: var(--market); }
        .tn-cart-badge {
            position: absolute; top: -2px; right: -8px;
            background-color: var(--stamp); color: #fff;
            border-radius: 50%; font-size: .65rem; font-weight: 700;
            min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; padding: 0 3px;
        }

        /* ---------- Buttons ---------- */
        .btn-tn-primary {
            background-color: var(--market); border-color: var(--market); color: #fff;
            border-radius: 4px; font-weight: 600; padding: .65rem 1.5rem;
        }
        .btn-tn-primary:hover { background-color: var(--market-dark); border-color: var(--market-dark); color: #fff; }
        .btn-tn-primary:disabled, .btn-tn-primary.disabled { background-color: var(--market); opacity: .5; }

        .btn-tn-outline {
            background: transparent; border: 1px solid var(--ink); color: var(--ink);
            border-radius: 4px; font-weight: 600; padding: .6rem 1.4rem;
        }
        .btn-tn-outline:hover { background: var(--ink); color: var(--paper); }

        .tn-back-link { display: inline-flex; align-items: center; gap: 6px; color: var(--muted); text-decoration: none; font-size: .88rem; font-weight: 500; margin-bottom: 1.25rem; }
        .tn-back-link:hover { color: var(--market); }
        .tn-back-link i { font-size: .75em; }

        /* ---------- Cards / surfaces ---------- */
        .tn-panel { background: #fff; border: 1px solid var(--rule); border-radius: 6px; }

        .tn-price { font-family: var(--font-display); font-weight: 600; color: var(--market); }

        .form-control, .form-select {
            border-color: var(--rule); border-radius: 4px; font-family: var(--font-body);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--market); box-shadow: 0 0 0 .2rem rgba(47, 82, 51, .12);
        }
        .form-label { color: var(--ink); }

        .tn-badge-ok { background: #E7EEE4; color: var(--market-dark); border-radius: 4px; padding: .35rem .7rem; font-size: .8rem; font-weight: 600; display: inline-block; }
        .tn-badge-off { background: #EFEAE0; color: var(--muted); border-radius: 4px; padding: .35rem .7rem; font-size: .8rem; font-weight: 600; display: inline-block; }
        .tn-badge-warn { background: #FBEEDD; color: #8A5A1E; border-radius: 4px; padding: .35rem .7rem; font-size: .8rem; font-weight: 600; display: inline-block; }

        .alert-tn-info { background: #EEF2EC; border: 1px solid #D4DFCF; color: var(--ink); border-radius: 4px; }
        .alert-tn-danger { background: #F8E9E7; border: 1px solid #ECC9C4; color: var(--stamp); border-radius: 4px; }

        footer.tn-footer {
            padding: 2.5rem 0; text-align: center; color: var(--muted); font-size: .85rem;
            margin-top: 4rem; border-top: 1px solid var(--rule);
        }
    </style>
    {{ $head ?? '' }}
</head>
<body>

<nav class="navbar navbar-expand-md fixed-top tn-navbar">
    <div class="container">
        <a class="navbar-brand" href="{{ route('tienda.catalog') }}">
            @if($tnCompany->logo_path)
                <img src="{{ asset('storage/'.$tnCompany->logo_path) }}" alt="" height="32" class="me-2">
            @endif
            {{ $tnCompany->display_name }}
        </a>
        <div class="d-flex align-items-center ms-auto gap-3">
            @if($tnCustomer)
                <div class="dropdown">
                    <a href="#" class="tn-nav-link dropdown-toggle" data-bs-toggle="dropdown">
                        {{ Str::before($tnCustomer->name, ' ') }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('tienda.account.orders') }}">Mis pedidos</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('tienda.account.logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">Salir</button>
                            </form>
                        </li>
                    </ul>
                </div>
            @else
                <a href="{{ route('tienda.account.login') }}" class="tn-nav-link d-none d-sm-inline">Ingresar</a>
            @endif
            <a href="{{ route('tienda.cart') }}" class="tn-cart-link">
                <i class="fas fa-shopping-bag fs-5"></i>
                @if($tnCartCount > 0)
                    <span class="tn-cart-badge">{{ $tnCartCount }}</span>
                @endif
            </a>
        </div>
    </div>
</nav>

{{ $slot }}

<footer class="tn-footer">
    {{ $tnCompany->display_name }} © {{ now()->year }}
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
