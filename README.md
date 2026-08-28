# Sistema de facturación / POS

Laravel 13 + Livewire 4. Facturación (con emisión a ARCA/AFIP), punto de venta,
cuenta corriente de clientes y proveedores, stock, compras, presupuestos,
listas de precio, promociones, cobranzas, respaldo automático e integración
con Tiendanube y Mercado Pago.

## Desarrollo local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # si DB_CONNECTION sigue en sqlite
php artisan migrate --seed       # el seed carga usuarios y datos de ejemplo
npm run build                    # o `npm run dev` mientras se trabaja
php artisan serve
```

Usuarios de ejemplo que deja el seed: `admin/admin`, `vendedor/vendedor`,
`cajero/cajero`.

```bash
php artisan test
```

## Instalación nueva para un cliente real

**No usar el seed de desarrollo** (`--seed` carga clientes, facturas y
usuarios ficticios). Los pasos son:

1. `composer install --no-dev --optimize-autoloader`
2. `npm install && npm run build`
3. Copiar `.env.production.example` a `.env`, completar los datos marcados
   con `...` (base de datos, mail, dominio, CUIT).
4. `php artisan key:generate`
5. `php artisan migrate`
6. `php artisan app:setup-admin` — crea el primer usuario administrador, sin
   ningún dato de ejemplo.
7. Iniciar sesión → **Configuración de la Empresa**: razón social, CUIT,
   condición de IVA, logo, punto de venta.
8. Si va a emitir a ARCA: subir certificado/clave en `storage/afip/` (nunca
   se commitean) y setear `AFIP_CUIT` / `AFIP_ENV=produccion`.
9. Configurar el respaldo automático (ver abajo) y probarlo una vez a mano.
10. Dar de alta categorías, productos y clientes reales desde la interfaz
    (o importarlos por Excel desde Productos → Importar).

## Respaldo

`php artisan backup:run` genera un `.zip` con la base de datos completa
(MySQL o SQLite, se detecta solo) + los archivos subidos (logos,
certificados). Se puede:

- Descargar a mano desde la pantalla de Respaldo (rol admin).
- Programar diario: `Schedule::command('backup:run')` ya está registrado en
  `routes/console.php` — solo hace falta que algo dispare
  `php artisan schedule:run` cada minuto (cron en Linux, Tarea Programada de
  Windows en un hosting Windows/Laragon).
- Copiar automáticamente a otra carpeta (pendrive, carpeta sincronizada con
  la nube) seteando `BACKUP_COPY_TO` en `.env` — **el respaldo tiene que
  vivir en otro dispositivo, no solo en el mismo disco del servidor.**

## Antes de vender esto a un cliente nuevo

- [ ] `.env` con `APP_ENV=production`, `APP_DEBUG=false`.
- [ ] `php artisan app:setup-admin` corrido (no el seed de desarrollo).
- [ ] Configuración de la Empresa completa (razón social, CUIT, punto de venta).
- [ ] Si emite a ARCA: certificado/clave subidos y probados con
      `AFIP_ENV=produccion` (probar primero en `homologacion` si es la
      primera vez que ese CUIT factura desde este sistema).
- [ ] Respaldo automático configurado y probado a mano una vez, con
      `BACKUP_COPY_TO` apuntando fuera del servidor.
- [ ] Dominio propio con HTTPS.
- [ ] Impresora de tickets probada si el cliente va a imprimir en el mostrador.

## Stack

- Laravel 13, Livewire 4, Alpine.js, Tailwind 4, Vite.
- SQLite (desarrollo) o MySQL (recomendado para producción con más de un
  usuario simultáneo).
- SweetAlert2 para confirmaciones y carteles.
