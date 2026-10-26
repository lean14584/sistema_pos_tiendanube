<?php
$tnCompany = \App\Models\CompanySettings::current();
$tnCartCount = \App\Support\TiendaCart::count();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Tienda') - {{ $tnCompany->display_name }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --tn-primary: #0d6efd;
            --tn-danger: #dc3545;
            --tn-dark: #212529;
        }
        body { font-family: 'Montserrat', sans-serif; background-color: #f4f6f9; padding-top: 75px; }
        @media (min-width: 768px) { body { padding-top: 90px; } }

        .tn-navbar { background-color: rgba(255,255,255,.95) !important; backdrop-filter: blur(10px); padding: .8rem 0; }
        .tn-navbar .navbar-brand { font-weight: 700; font-size: 1.4rem; color: var(--tn-dark); }

        .tn-filter-sidebar { background: transparent; }
        .tn-filter-title { font-size: 1.1rem; font-weight: 700; color: #333; margin-bottom: 15px; }
        .tn-filter-group-title { font-size: .9rem; font-weight: 600; color: #444; margin-top: 20px; margin-bottom: 10px; }
        .tn-filter-link { display: block; color: #666; text-decoration: none; font-size: .88rem; padding: 5px 0; transition: color .2s; }
        .tn-filter-link:hover, .tn-filter-link.active { color: var(--tn-primary); font-weight: 600; }

        .tn-product-card { border: none; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,.02); background: #fff; height: 100%; transition: transform .2s; }
        @media (min-width: 768px) {
            .tn-product-card { border-radius: 16px; }
            .tn-product-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,.08); }
        }
        .tn-img-container { background: #f8f9fa; height: 160px; display: flex; align-items: center; justify-content: center; padding: 10px; position: relative; }
        @media (min-width: 768px) { .tn-img-container { height: 220px; padding: 15px; } }
        .tn-img-container img { max-width: 100%; max-height: 100%; object-fit: cover; border-radius: 6px; }
        .tn-price { font-weight: 700; color: var(--tn-dark); font-size: 1.15rem; }

        footer.tn-footer { padding: 2rem 0; text-align: center; color: #6c757d; font-size: .875rem; margin-top: 3rem; border-top: 1px solid #eee; background: #fff; }

        .tn-cart-link { position: relative; display: inline-flex; align-items: center; padding: .5rem; color: var(--tn-dark); }
        .tn-cart-badge { position: absolute; top: 0; right: -2px; background-color: var(--tn-danger); color: #fff; border-radius: 50%; font-size: .65rem; font-weight: 700; min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; padding: 0 3px; }

        .tn-back-link { display: inline-flex; align-items: center; gap: 6px; color: #6c757d; text-decoration: none; font-size: .85rem; font-weight: 600; margin-bottom: 1rem; transition: color .15s; }
        .tn-back-link:hover { color: var(--tn-dark); }
        .tn-back-link i { font-size: .75rem; }
    </style>
    {{ $head ?? '' }}
</head>
<body>

<nav class="navbar navbar-expand-lg fixed-top tn-navbar shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="{{ route('tienda.catalog') }}">
            @if($tnCompany->logo_path)
                <img src="{{ asset('storage/'.$tnCompany->logo_path) }}" alt="Logo" height="36" class="me-2">
            @endif
            {{ $tnCompany->display_name }}
        </a>
        <div class="d-flex align-items-center ms-auto">
            <a href="{{ route('tienda.cart') }}" class="tn-cart-link me-2">
                <i class="fas fa-shopping-cart fs-5"></i>
                @if($tnCartCount > 0)
                    <span class="tn-cart-badge">{{ $tnCartCount }}</span>
                @endif
            </a>
            <a href="{{ route('tienda.catalog') }}" class="btn btn-sm btn-outline-dark rounded-pill px-3">
                <i class="fas fa-home"></i> <span class="d-none d-sm-inline">Inicio</span>
            </a>
        </div>
    </div>
</nav>

{{ $slot }}

<footer class="tn-footer">
    &copy; {{ now()->year }} {{ $tnCompany->display_name }}
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
