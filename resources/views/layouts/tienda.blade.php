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

        .tn-card-media { display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
        .tn-card-media img { transition: transform .35s ease; }
        .tn-card:hover .tn-card-media img { transform: scale(1.08); }
        .tn-card-media-out img, .tn-card-media-out i { opacity: .35; }

        .tn-ribbon {
            position: absolute; top: 10px; left: 10px; z-index: 2;
            border-radius: 999px; padding: .3rem .7rem; font-size: .7rem; font-weight: 800;
            text-transform: none; letter-spacing: 0;
        }
        .tn-ribbon-urgent { background: #FDECEC; color: #B3261E; }
        .tn-ribbon-off { background: rgba(20,24,31,.75); color: #fff; }

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
        @keyframes tn-hero-in {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .tn-hero {
            background: linear-gradient(120deg, var(--tn-700) 0%, var(--tn-500) 100%);
            border-radius: 20px; color: #fff; position: relative; overflow: hidden;
            padding: 2.75rem 2rem;
            animation: tn-hero-in .5s ease both;
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

        .tn-trustbar {
            display: flex; flex-wrap: wrap; gap: 1.5rem; justify-content: center;
            padding: .5rem 0;
        }
        .tn-trust-item {
            display: flex; align-items: center; gap: .5rem;
            font-size: .85rem; font-weight: 600; color: var(--tn-muted);
        }
        .tn-trust-item i { color: var(--tn-600); font-size: .95rem; }

        /* ---------- Breadcrumb ---------- */
        .tn-breadcrumb { font-size: .85rem; color: var(--tn-muted); margin-bottom: 1.25rem; }
        .tn-breadcrumb a { color: var(--tn-muted); font-weight: 600; }
        .tn-breadcrumb a:hover { color: var(--tn-700); }
        .tn-breadcrumb .sep { margin: 0 .5rem; opacity: .5; }

        /* ---------- Quantity stepper ---------- */
        .tn-stepper { display: inline-flex; align-items: stretch; border: 1px solid var(--tn-line); border-radius: 10px; overflow: hidden; }
        .tn-stepper button {
            width: 42px; border: none; background: var(--tn-paper); color: var(--tn-ink);
            font-weight: 800; font-size: 1.1rem; display: flex; align-items: center; justify-content: center;
        }
        .tn-stepper button:hover { background: var(--tn-line); }
        .tn-stepper button:disabled { opacity: .35; }
        .tn-stepper input {
            width: 50px; border: none; border-left: 1px solid var(--tn-line); border-right: 1px solid var(--tn-line);
            text-align: center; font-weight: 700; -moz-appearance: textfield;
        }
        .tn-stepper input::-webkit-outer-spin-button, .tn-stepper input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

        /* ---------- Category tiles ---------- */
        .tn-cat-tile {
            background: #fff; border: 1px solid var(--tn-line); border-radius: 14px;
            padding: 1.25rem 1rem; text-align: center; color: var(--tn-ink);
            transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        }
        .tn-cat-tile:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(20,24,31,.08); border-color: transparent; color: var(--tn-ink); }
        .tn-cat-tile-icon {
            width: 46px; height: 46px; border-radius: 12px; margin: 0 auto .6rem;
            display: flex; align-items: center; justify-content: center;
            background: color-mix(in srgb, var(--tn-500) 14%, white); color: var(--tn-700); font-size: 1.1rem;
        }
        .tn-cat-tile-name { font-weight: 700; font-size: .85rem; }

        /* ---------- Footer ---------- */
        footer.tn-footer {
            background: var(--tn-ink); color: rgba(255,255,255,.65);
            margin-top: 4rem; padding: 3rem 0 1.5rem; font-size: .88rem;
        }
        footer.tn-footer h6 { color: #fff; font-weight: 800; font-size: .85rem; margin-bottom: 1rem; }
        footer.tn-footer a { color: rgba(255,255,255,.65); }
        footer.tn-footer a:hover { color: #fff; }
        footer.tn-footer .tn-footer-brand { color: #fff; font-weight: 800; font-size: 1.2rem; margin-bottom: .6rem; }
        footer.tn-footer .tn-footer-bottom {
            border-top: 1px solid rgba(255,255,255,.12); margin-top: 2rem; padding-top: 1.5rem;
            display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; align-items: center;
            font-size: .8rem; color: rgba(255,255,255,.45);
        }
        footer.tn-footer .tn-pay-icons { display: flex; gap: .6rem; font-size: 1.4rem; color: rgba(255,255,255,.55); }
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
    <div class="container">
        <div class="row gy-4">
            <div class="col-12 col-md-4">
                <div class="tn-footer-brand">{{ $tnCompany->display_name }}</div>
                <p class="mb-0" style="max-width: 320px;">Stock real y el mismo precio que en el local, todos los días.</p>
            </div>
            <div class="col-6 col-md-4">
                <h6>Ayuda</h6>
                <div class="d-flex flex-column gap-2">
                    <a href="{{ route('tienda.catalog') }}" class="text-decoration-none">Catálogo</a>
                    @auth('customer')
                        <a href="{{ route('tienda.account.orders') }}" class="text-decoration-none">Mis pedidos</a>
                    @else
                        <a href="{{ route('tienda.account.login') }}" class="text-decoration-none">Ingresar</a>
                    @endauth
                    <a href="{{ route('tienda.cart') }}" class="text-decoration-none">Carrito</a>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <h6>Medios de pago</h6>
                <div class="tn-pay-icons mb-2">
                    <i class="fa-brands fa-cc-visa" title="Visa"></i>
                    <i class="fa-brands fa-cc-mastercard" title="Mastercard"></i>
                    <i class="fa-solid fa-money-bill-wave" title="Efectivo"></i>
                </div>
                <small>Pagos procesados de forma segura por Mercado Pago.</small>
            </div>
        </div>
        <div class="tn-footer-bottom">
            <span>{{ $tnCompany->display_name }} © {{ now()->year }}</span>
            <span><i class="fas fa-lock me-1"></i> Compra 100% segura</span>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
