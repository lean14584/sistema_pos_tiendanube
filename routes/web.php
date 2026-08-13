<?php

use App\Livewire\Audit\Index as AuditIndex;
use App\Livewire\Auth\Login;
use App\Livewire\CashRegister\Index as CashRegisterIndex;
use App\Livewire\CompanySettings\Edit as CompanySettingsEdit;
use App\Livewire\Categories\Edit as CategoryEdit;
use App\Livewire\Categories\Create as CategoryCreate;
use App\Livewire\Categories\Index as CategoryIndex;
use App\Livewire\Clients\Account as ClientAccount;
use App\Livewire\Clients\Edit as ClientEdit;
use App\Livewire\Clients\Create as ClientCreate;
use App\Livewire\Clients\Index as ClientIndex;
use App\Livewire\Dashboard;
use App\Livewire\Invoices\Edit as InvoiceEdit;
use App\Livewire\Invoices\Create as InvoiceCreate;
use App\Livewire\Invoices\Index as InvoiceIndex;
use App\Livewire\Invoices\Show as InvoiceShow;
use App\Livewire\LibroIva\Index as LibroIvaIndex;
use App\Livewire\Messages\Index as MessagesIndex;
use App\Livewire\NotasCredito\Create as NotaCreditoCreate;
use App\Livewire\Products\Edit as ProductEdit;
use App\Livewire\Products\Create as ProductCreate;
use App\Livewire\Products\Index as ProductIndex;
use App\Livewire\Providers\Account as ProviderAccount;
use App\Livewire\Providers\Edit as ProviderEdit;
use App\Livewire\Providers\Create as ProviderCreate;
use App\Livewire\Providers\Index as ProviderIndex;
use App\Livewire\Purchases\Edit as PurchaseEdit;
use App\Livewire\Purchases\Create as PurchaseCreate;
use App\Livewire\Purchases\Index as PurchaseIndex;
use App\Livewire\Purchases\Show as PurchaseShow;
use App\Livewire\Purchases\Suggestions as PurchaseSuggestions;
use App\Livewire\Quotes\Edit as QuoteEdit;
use App\Livewire\Quotes\Create as QuoteCreate;
use App\Livewire\Quotes\Index as QuoteIndex;
use App\Livewire\Quotes\Show as QuoteShow;
use App\Livewire\Reports\Index as ReportsIndex;
use App\Livewire\Tasks\Create as TaskCreate;
use App\Livewire\Tasks\Index as TaskIndex;
use App\Livewire\Users\Edit as UserEdit;
use App\Livewire\Users\Create as UserCreate;
use App\Livewire\Users\Index as UserIndex;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\LibroIvaExportController;
use Illuminate\Support\Facades\Route;

Route::get('/login', Login::class)->middleware('guest')->name('login');
Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

// Webhook público de Mercado Pago (sin auth ni CSRF; se valida contra la API).
Route::match(['get', 'post'], '/mp/webhook', \App\Http\Controllers\MercadoPagoWebhookController::class)->name('mp.webhook');

// Webhook público de Tiendanube (sin auth ni CSRF; firma HMAC opcional).
Route::post('/tiendanube/webhook', \App\Http\Controllers\TiendanubeWebhookController::class)->name('tiendanube.webhook');

// Kiosco público de consulta de precios (para dejar fijo en el salón).
Route::get('/precios', \App\Livewire\PriceCheck\Kiosk::class)->name('precios');

Route::middleware('auth')->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');

    Route::middleware('module:pos')->get('pos', \App\Livewire\Pos\Index::class)->name('pos.index');

    Route::middleware('module:quotes')->prefix('quotes')->name('quotes.')->group(function () {
        Route::get('/', QuoteIndex::class)->name('index');
        Route::get('/new', QuoteCreate::class)->name('create');
        Route::get('/{quote}', QuoteShow::class)->name('show');
        Route::get('/{quote}/edit', QuoteEdit::class)->name('edit');
    });

    Route::middleware('module:invoices')->prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', InvoiceIndex::class)->name('index');
        Route::get('/new', InvoiceCreate::class)->name('create');
        Route::get('/{invoice}', InvoiceShow::class)->name('show');
        Route::get('/{invoice}/edit', InvoiceEdit::class)->name('edit');
        Route::get('/{invoice}/pdf', InvoicePdfController::class)->name('pdf');
        Route::get('/{invoice}/remito-pdf', \App\Http\Controllers\RemitoPdfController::class)->name('remito-pdf');
        Route::get('/{invoice}/facturar', \App\Livewire\Invoices\FacturarRemito::class)->name('facturar-remito');
        Route::get('/{invoice}/nota-credito', NotaCreditoCreate::class)->name('nota-credito.create');
    });

    Route::middleware('module:clients')->prefix('clients')->name('clients.')->group(function () {
        Route::get('/', ClientIndex::class)->name('index');
        Route::get('/new', ClientCreate::class)->name('create');
        Route::get('/{client}/edit', ClientEdit::class)->name('edit');
        Route::get('/{client}/account', ClientAccount::class)->name('account');
        Route::get('/recibo/{payment}', \App\Http\Controllers\ReciboPdfController::class)->name('recibo');
    });

    Route::middleware('module:cobranzas')->get('cobranzas', \App\Livewire\Cobranzas\Index::class)->name('cobranzas.index');

    Route::middleware('module:products')->prefix('products')->name('products.')->group(function () {
        Route::get('/', ProductIndex::class)->name('index');
        Route::get('/new', ProductCreate::class)->name('create');
        Route::get('/etiquetas', \App\Livewire\Products\Labels::class)->name('labels');
        Route::get('/{product}/edit', ProductEdit::class)->name('edit');
    });

    Route::middleware('module:categories')->prefix('categories')->name('categories.')->group(function () {
        Route::get('/', CategoryIndex::class)->name('index');
        Route::get('/new', CategoryCreate::class)->name('create');
        Route::get('/{category}/edit', CategoryEdit::class)->name('edit');
    });

    Route::middleware('module:price-lists')->prefix('price-lists')->name('price-lists.')->group(function () {
        Route::get('/', \App\Livewire\PriceLists\Index::class)->name('index');
    });

    Route::middleware('module:providers')->prefix('providers')->name('providers.')->group(function () {
        Route::get('/', ProviderIndex::class)->name('index');
        Route::get('/new', ProviderCreate::class)->name('create');
        Route::get('/{provider}/edit', ProviderEdit::class)->name('edit');
        Route::get('/{provider}/account', ProviderAccount::class)->name('account');
    });

    Route::middleware('module:purchases')->prefix('purchases')->name('purchases.')->group(function () {
        Route::get('/', PurchaseIndex::class)->name('index');
        Route::get('/new', PurchaseCreate::class)->name('create');
        Route::get('/suggestions', PurchaseSuggestions::class)->name('suggestions');
        Route::get('/{purchase}', PurchaseShow::class)->name('show');
        Route::get('/{purchase}/edit', PurchaseEdit::class)->name('edit');
    });

    Route::middleware('module:cash-register')->prefix('cash-register')->name('cash-register.')->group(function () {
        Route::get('/', CashRegisterIndex::class)->name('index');
    });

    Route::middleware('module:reports')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', ReportsIndex::class)->name('index');
        Route::get('/export/pdf', [\App\Http\Controllers\ReportsExportController::class, 'pdf'])->name('export.pdf');
        Route::get('/export/csv', [\App\Http\Controllers\ReportsExportController::class, 'csv'])->name('export.csv');
    });

    Route::middleware('module:users')->prefix('users')->name('users.')->group(function () {
        Route::get('/', UserIndex::class)->name('index');
        Route::get('/new', UserCreate::class)->name('create');
        Route::get('/{user}/edit', UserEdit::class)->name('edit');
    });

    Route::middleware('module:messages')->prefix('messages')->name('messages.')->group(function () {
        Route::get('/{with?}', MessagesIndex::class)->name('index');
    });

    Route::middleware('module:tasks')->prefix('tasks')->name('tasks.')->group(function () {
        Route::get('/', TaskIndex::class)->name('index');
        Route::get('/new', TaskCreate::class)->name('create');
    });

    Route::middleware('module:company-settings')->prefix('company-settings')->name('company-settings.')->group(function () {
        Route::get('/', CompanySettingsEdit::class)->name('edit');
    });

    Route::middleware('module:company-settings')->prefix('tiendanube')->name('tiendanube.')->group(function () {
        Route::get('/', \App\Livewire\Tiendanube\Index::class)->name('index');
    });

    Route::middleware('module:audit')->get('auditoria', AuditIndex::class)->name('audit.index');

    Route::middleware('module:backups')->prefix('respaldo')->name('backups.')->group(function () {
        Route::get('/', \App\Livewire\Backups\Index::class)->name('index');
        Route::get('/descargar', \App\Http\Controllers\BackupDownloadController::class)->name('download');
    });

    Route::middleware('module:health')->get('estado', \App\Livewire\Health\Index::class)->name('health.index');

    Route::middleware('module:libro-iva')->prefix('libro-iva')->name('libro-iva.')->group(function () {
        Route::get('/', LibroIvaIndex::class)->name('index');
        Route::get('/export', LibroIvaExportController::class)->name('export');
    });
});
