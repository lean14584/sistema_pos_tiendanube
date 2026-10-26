<?php
$tnCompany = \App\Models\CompanySettings::current();
$tnCartCount = \App\Support\TiendaCart::count();
$tnCustomer = \Illuminate\Support\Facades\Auth::guard('customer')->user();

// Paleta dinámica: si el cliente cargó su color de marca en Configuración
// de Empresa, la tienda usa ESE color (misma escala OKLCH que ya genera
// App\Support\ThemeCss para el panel de staff — mismo matiz percibido en
// todos los tonos). Sin nada cargado, cae a un azul/ámbar vivo por defecto
// en vez de quedar gris — la tienda tiene que verse viva desde el día 1.
$tnPrimary = \App\Support\ColorScale::fromHex($tnCompany->theme_primary_color ?: '#2155CD');
$tnAccent = \App\Support\ColorScale::fromHex($tnCompany->theme_secondary_color ?: '#F5A524');
$tnVars = '';
foreach ($tnPrimary as $stop => $hex) { $tnVars .= "--tn-{$stop}:{$hex};"; }
foreach ($tnAccent as $stop => $hex) { $tnVars .= "--tn-acc-{$stop}:{$hex};"; }
$tnLogoUrl = $tnCompany->logo_path ? asset('storage/'.$tnCompany->logo_path) : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Tienda') · {{ $tnCompany->display_name }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,600&display=swap" rel="stylesheet">
    <style>
        :root {
            {!! $tnVars !!}
            --tn-ink: #14181F;
            --tn-paper: #F4F6FA;
            --tn-line: #E7EAF1;
            --tn-muted: #6B7280;
            --font: 'Plus Jakarta Sans', -apple-system, sans-serif;
        }

        * { box-sizing: border-box; }

        body {
            font-family: var(--font);
            color: var(--tn-ink);
            background-color: var(--tn-paper);
            padding-top: 68px;
            -webkit-font-smoothing: antialiased;
        }

        h1, h2, h3, .tn-display { font-family: var(--font); font-weight: 800; letter-spacing: -.02em; }

        a { color: var(--tn-700); text-decoration: none; }
        a:hover { color: var(--tn-800); }

        /* ---------- Navbar ---------- */
        .tn-navbar {
            background: linear-gradient(135deg, var(--tn-700), var(--tn-600));
            padding: .7rem 0;
            box-shadow: 0 2px 16px rgba(0,0,0,.12);
        }
        .tn-navbar .navbar-brand {
            display: flex; align-items: center; gap: .6rem;
            font-weight: 800; font-size: 1.35rem; color: #fff;
        }
        .tn-navbar .navbar-brand:hover { color: #fff; opacity: .92; }
        .tn-logo-chip {
            background: #fff; border-radius: 10px; padding: 4px 8px;
            display: inline-flex; align-items: center; height: 36px;
        }
        .tn-logo-chip img { max-height: 26px; max-width: 120px; display: block; }

        .tn-nav-link { color: rgba(255,255,255,.92); text-decoration: none; font-size: .92rem; font-weight: 600; padding: .5rem .25rem; }
        .tn-nav-link:hover { color: #fff; }

        .tn-search-bar {
            background: #fff; border-radius: 10px; border: none;
            padding: .6rem 1rem; font-size: .92rem; width: 100%;
        }
        .tn-search-bar:focus { outline: 3px solid rgba(255,255,255,.35); }

        .tn-cart-link { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 10px; background: rgba(255,255,255,.14); color: #fff; text-decoration: none; }
        .tn-cart-link:hover { background: rgba(255,255,255,.24); color: #fff; }
        .tn-cart-badge {
            position: absolute; top: -6px; right: -6px;
            background-color: var(--tn-acc-500); color: #1a1a1a;
            border-radius: 50%; font-size: .68rem; font-weight: 800;
            min-width: 19px; height: 19px; display: flex; align-items: center; justify-content: center; padding: 0 3px;
            box-shadow: 0 0 0 2px var(--tn-700);
        }

        .tn-account-pill {
            display: inline-flex; align-items: center; gap: .4rem;
            background: rgba(255,255,255,.14); color: #fff; border-radius: 10px;
            padding: .45rem .8rem; font-size: .88rem; font-weight: 600;
        }
        .tn-account-pill:hover { background: rgba(255,255,255,.24); color: #fff; }

        /* ---------- Buttons ---------- */
        .btn-tn-primary {
            background: linear-gradient(135deg, var(--tn-600), var(--tn-700));
            border: none; color: #fff;
            border-radius: 10px; font-weight: 700; padding: .75rem 1.6rem;
            box-shadow: 0 4px 14px color-mix(in srgb, var(--tn-600) 45%, transparent);
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .btn-tn-primary:hover { color: #fff; transform: translateY(-1px); box-shadow: 0 6px 18px color-mix(in srgb, var(--tn-600) 55%, transparent); }
        .btn-tn-primary:disabled, .btn-tn-primary.disabled { opacity: .5; transform: none; }

        .btn-tn-accent {
            background: var(--tn-acc-500); border: none; color: #1a1a1a;
            border-radius: 10px; font-weight: 800; padding: .75rem 1.6rem;
        }
        .btn-tn-accent:hover { background: var(--tn-acc-600); color: #1a1a1a; }

        .btn-tn-outline {
            background: #fff; border: 2px solid var(--tn-line); color: var(--tn-ink);
            border-radius: 10px; font-weight: 700; padding: .6rem 1.4rem;
        }
        .btn-tn-outline:hover { border-color: var(--tn-600); color: var(--tn-700); }

        .tn-back-link { display: inline-flex; align-items: center; gap: 6px; color: var(--tn-muted); text-decoration: none; font-size: .88rem; font-weight: 600; margin-bottom: 1.25rem; }
        .tn-back-link:hover { color: var(--tn-700); }
        .tn-back-link i { font-size: .75em; }

        /* ---------- Cards / surfaces ---------- */
        .tn-card {
            background: #fff; border-radius: 16px; border: 1px solid var(--tn-line);
            box-shadow: 0 1px 3px rgba(20,24,31,.04);
            transition: transform .18s ease, box-shadow .18s ease;
            overflow: hidden;
        }
        .tn-card:hover { transform: translateY(-4px); box-shadow: 0 14px 28px rgba(20,24,31,.1); border-color: transparent; }

        .tn-price { font-family: var(--font); font-weight: 800; color: var(--tn-ink); }

        .form-control, .form-select {
            border-color: var(--tn-line); border-radius: 10px; font-family: var(--font);
            padding: .65rem .9rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--tn-600); box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--tn-500) 18%, transparent);
        }
        .form-label { color: var(--tn-ink); font-weight: 600; }

        .tn-badge-ok { background: #E6F6EC; color: #1B7A3D; border-radius: 999px; padding: .35rem .8rem; font-size: .78rem; font-weight: 700; display: inline-block; }
        .tn-badge-off { background: #F1F2F4; color: var(--tn-muted); border-radius: 999px; padding: .35rem .8rem; font-size: .78rem; font-weight: 700; display: inline-block; }
        .tn-badge-warn { background: #FEF3E2; color: #966016; border-radius: 999px; padding: .35rem .8rem; font-size: .78rem; font-weight: 700; display: inline-block; }

        .alert-tn-info { background: color-mix(in srgb, var(--tn-500) 10%, white); border: 1px solid color-mix(in srgb, var(--tn-500) 25%, white); color: var(--tn-800); border-radius: 10px; }
        .alert-tn-danger { background: #FDECEC; border: 1px solid #F6C4C4; color: #B3261E; border-radius: 10px; }

        /* ---------- Hero ---------- */
        .tn-hero {
            background: linear-gradient(120deg, var(--tn-700) 0%, var(--tn-500) 100%);
            border-radius: 20px; color: #fff; position: relative; overflow: hidden;
            padding: 2.75rem 2rem;
        }
        .tn-hero::after {
            content: ''; position: absolute; width: 340px; height: 340px; border-radius: 50%;
            background: color-mix(in srgb, var(--tn-acc-400) 55%, transparent);
            top: -140px; right: -100px; filter: blur(5px);
        }
        .tn-hero-title { font-size: 2.1rem; font-weight: 800; position: relative; z-index: 1; margin-bottom: .4rem; }
        @media (min-width: 768px) { .tn-hero-title { font-size: 2.6rem; } }
        .tn-hero-sub { opacity: .92; position: relative; z-index: 1; font-size: 1.02rem; max-width: 520px; }

        .tn-chip {
            display: inline-block; background: rgba(255,255,255,.16); color: #fff;
            border-radius: 999px; padding: .4rem 1rem; font-size: .85rem; font-weight: 600;
            text-decoration: none; margin: .2rem .35rem .2rem 0; position: relative; z-index: 1;
        }
        .tn-chip:hover { background: rgba(255,255,255,.28); color: #fff; }

        footer.tn-footer {
            padding: 3rem 0 2rem; text-align: center; color: var(--tn-muted); font-size: .85rem;
            margin-top: 4rem; border-top: 1px solid var(--tn-line);
        }
    </style>
    {{ $head ?? '' }}
</head>
<body>

<nav class="navbar navbar-expand-md fixed-top tn-navbar">
    <div class="container">
        <a class="navbar-brand" href="{{ route('tienda.catalog') }}">
            @if($tnLogoUrl)
                <span class="tn-logo-chip"><img src="{{ $tnLogoUrl }}" alt=""></span>
            @else
                <i class="fas fa-bag-shopping"></i>
            @endif
            <span class="d-none d-sm-inline">{{ $tnCompany->display_name }}</span>
        </a>
        <div class="d-flex align-items-center ms-auto gap-2 gap-md-3">
            @if($tnCustomer)
                <div class="dropdown">
                    <a href="#" class="tn-account-pill dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-circle-user"></i>
                        <span class="d-none d-sm-inline">{{ Str::before($tnCustomer->name, ' ') }}</span>
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
                <a href="{{ route('tienda.account.login') }}" class="tn-account-pill">
                    <i class="fas fa-circle-user"></i>
                    <span class="d-none d-sm-inline">Ingresar</span>
                </a>
            @endif
            <a href="{{ route('tienda.cart') }}" class="tn-cart-link">
                <i class="fas fa-cart-shopping"></i>
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
