<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sucursal;
use App\Models\TiendaPedido;
use App\Support\TiendaCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class TiendaAccountTest extends TestCase
{
    use RefreshDatabase;

    private function enableTienda(): void
    {
        CompanySettings::current()->update(['tienda_online_habilitada' => true]);
    }

    private function customer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Ana López',
            'email' => 'ana@example.com',
            'phone' => '2604000000',
            'password' => 'Secreta123',
        ], $overrides));
    }

    public function test_register_creates_customer_and_logs_in(): void
    {
        $this->enableTienda();

        Livewire::test('tienda.account.register')
            ->set('name', 'Ana López')
            ->set('email', 'ana@example.com')
            ->set('phone', '2604000000')
            ->set('password', 'Secreta123')
            ->set('password_confirmation', 'Secreta123')
            ->call('submit')
            ->assertRedirect(route('tienda.catalog'));

        $this->assertDatabaseHas('customers', ['email' => 'ana@example.com']);
        $this->assertTrue(Auth::guard('customer')->check());
    }

    public function test_register_rejects_duplicate_email(): void
    {
        $this->enableTienda();
        $this->customer();

        Livewire::test('tienda.account.register')
            ->set('name', 'Otra Ana')
            ->set('email', 'ana@example.com')
            ->set('password', 'Secreta123')
            ->set('password_confirmation', 'Secreta123')
            ->call('submit')
            ->assertHasErrors(['email']);
    }

    public function test_login_with_correct_credentials(): void
    {
        $this->enableTienda();
        $this->customer();

        Livewire::test('tienda.account.login')
            ->set('email', 'ana@example.com')
            ->set('password', 'Secreta123')
            ->call('submit')
            ->assertRedirect(route('tienda.catalog'));

        $this->assertTrue(Auth::guard('customer')->check());
    }

    public function test_login_rate_limits_after_too_many_attempts(): void
    {
        $this->enableTienda();
        $this->customer();

        for ($i = 0; $i < 5; $i++) {
            Livewire::test('tienda.account.login')
                ->set('email', 'ana@example.com')
                ->set('password', 'mal')
                ->call('submit');
        }

        Livewire::test('tienda.account.login')
            ->set('email', 'ana@example.com')
            ->set('password', 'Secreta123')
            ->call('submit')
            ->assertSet('error', fn ($error) => str_contains($error, 'Demasiados intentos'));

        $this->assertFalse(Auth::guard('customer')->check());
    }

    public function test_guest_cart_merges_into_account_on_login(): void
    {
        $this->enableTienda();
        $customer = $this->customer();
        $product = Product::create(['name' => 'Agua 500ml', 'price' => 900, 'stock' => 10]);
        TiendaCart::add($product->id, 2);

        Livewire::test('tienda.account.login')
            ->set('email', 'ana@example.com')
            ->set('password', 'Secreta123')
            ->call('submit');

        $this->assertDatabaseHas('tienda_cart_items', [
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $this->assertSame(2, TiendaCart::count());
    }

    public function test_cart_persists_across_logout_and_login(): void
    {
        $this->enableTienda();
        $customer = $this->customer();
        $product = Product::create(['name' => 'Agua 500ml', 'price' => 900, 'stock' => 10]);

        $this->actingAs($customer, 'customer');
        TiendaCart::add($product->id, 3);

        Auth::guard('customer')->logout();
        $this->assertTrue(TiendaCart::isEmpty(), 'El carrito de invitado (sesión) debe estar vacío después del logout.');

        $this->actingAs($customer, 'customer');
        $this->assertSame(3, TiendaCart::count(), 'El carrito de la cuenta tiene que seguir ahí al volver a loguearse.');
    }

    public function test_checkout_prefills_from_logged_in_customer_and_saves_customer_id(): void
    {
        $this->enableTienda();
        $customer = $this->customer();
        $product = Product::create(['name' => 'Agua 500ml', 'price' => 900, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => Sucursal::sole()->id, 'stock' => 10]);

        // Pre-creado a propósito: actingAs(..., 'customer') cambia el guard
        // por defecto de TODO el test (Auth::shouldUse), así que si
        // Client::consumidorFinal() recién se crea DESPUÉS de esto, su
        // auditoría dispara CurrentSucursal::id() -> Auth::user() (guard
        // por defecto) -> ahora un Customer, no un User -> esAdminGlobal()
        // no existe ahí. En producción esto no pasa: el guard por defecto
        // (`web`, staff) nunca lo pisa una sesión de cliente real.
        Client::consumidorFinal();

        $this->actingAs($customer, 'customer');
        TiendaCart::add($product->id, 1);

        Livewire::test('tienda.checkout')
            ->assertSet('customer_name', 'Ana López')
            ->assertSet('customer_email', 'ana@example.com')
            ->assertSet('customer_phone', '2604000000')
            ->call('confirmar');

        $pedido = TiendaPedido::sole();
        $this->assertSame($customer->id, $pedido->customer_id);
    }

    public function test_orders_page_only_lists_own_orders(): void
    {
        $this->enableTienda();
        $customer = $this->customer();
        $otro = $this->customer(['email' => 'otro@example.com']);
        $product = Product::create(['name' => 'Agua 500ml', 'price' => 900, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => Sucursal::sole()->id, 'stock' => 10]);
        Client::consumidorFinal(); // ver comentario en el test de arriba

        $this->actingAs($otro, 'customer');
        TiendaCart::add($product->id, 1);
        Livewire::test('tienda.checkout')->call('confirmar');

        $this->actingAs($customer, 'customer');
        $this->assertTrue(TiendaCart::isEmpty());

        $response = $this->get(route('tienda.account.orders'));
        $response->assertOk();

        Livewire::actingAs($customer, 'customer')
            ->test('tienda.account.orders')
            ->assertViewHas('pedidos', fn ($pedidos) => $pedidos->isEmpty());
    }

    public function test_logout_requires_login_first(): void
    {
        $this->enableTienda();

        $this->post(route('tienda.account.logout'))->assertRedirect(route('tienda.account.login'));
    }
}
