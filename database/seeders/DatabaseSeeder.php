<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Purchase;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['username' => 'admin'],
            ['name' => 'Administrador', 'password' => 'admin', 'role' => Role::Admin, 'active' => true]
        );
        User::query()->firstOrCreate(
            ['username' => 'vendedor'],
            ['name' => 'Vendedor Demo', 'password' => 'vendedor', 'role' => Role::Vendedor, 'active' => true]
        );
        User::query()->firstOrCreate(
            ['username' => 'cajero'],
            ['name' => 'Cajero Demo', 'password' => 'cajero', 'role' => Role::Cajero, 'active' => true]
        );

        if (Category::count() > 0) {
            return;
        }

        $bebidas = Category::create(['name' => 'Bebidas', 'description' => 'Gaseosas, jugos y aguas']);
        $alimentos = Category::create(['name' => 'Alimentos', 'description' => 'Productos comestibles']);
        $limpieza = Category::create(['name' => 'Limpieza', 'description' => 'Artículos de limpieza']);

        $products = [
            Product::create(['category_id' => $bebidas->id, 'name' => 'Coca Cola 1.5L', 'sku' => 'BEB-001', 'price' => 1500, 'cost_price' => 1000, 'stock' => 50, 'min_stock' => 10]),
            Product::create(['category_id' => $bebidas->id, 'name' => 'Agua Mineral 1.5L', 'sku' => 'BEB-002', 'price' => 800, 'cost_price' => 500, 'stock' => 4, 'min_stock' => 10]),
            Product::create(['category_id' => $alimentos->id, 'name' => 'Arroz 1kg', 'sku' => 'ALI-001', 'price' => 1200, 'cost_price' => 900, 'stock' => 30, 'min_stock' => 5]),
            Product::create(['category_id' => $alimentos->id, 'name' => 'Fideos 500g', 'sku' => 'ALI-002', 'price' => 900, 'cost_price' => 950, 'stock' => 20, 'min_stock' => 5]),
            Product::create(['category_id' => $limpieza->id, 'name' => 'Detergente 750ml', 'sku' => 'LIM-001', 'price' => 1100, 'cost_price' => 700, 'stock' => 15, 'min_stock' => 3]),
            Product::create(['name' => 'Servicio de instalación', 'price' => 5000, 'stock' => 0]),
        ];

        $clients = [
            Client::create(['name' => 'Almacén Don José', 'email' => 'donjose@example.com', 'phone' => '11-4444-1111', 'tax_id' => '20-11111111-1']),
            Client::create(['name' => 'Kiosco La Esquina', 'email' => 'laesquina@example.com', 'phone' => '11-4444-2222']),
            Client::create(['name' => 'Supermercado Central', 'email' => 'central@example.com', 'phone' => '11-4444-3333', 'address' => 'Av. Siempre Viva 742']),
        ];

        $providers = [
            Provider::create(['name' => 'Distribuidora Norte', 'email' => 'ventas@distnorte.com', 'phone' => '11-5555-1111', 'tax_id' => '30-22222222-2']),
            Provider::create(['name' => 'Mayorista del Sur', 'email' => 'contacto@mayosur.com', 'phone' => '11-5555-2222']),
        ];

        // Draft invoice.
        $draft = Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $clients[0]->id, 'tax_rate' => 21,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'draft',
        ]);
        $draft->items()->create(['product_id' => $products[0]->id, 'description' => $products[0]->name, 'quantity' => 10, 'unit_price' => $products[0]->price]);

        // Paid invoice with payment method.
        $paid = Invoice::create([
            'number' => 'FAC-0002', 'client_id' => $clients[1]->id, 'tax_rate' => 21,
            'issue_date' => now()->subDays(3), 'due_date' => now()->addDays(12), 'status' => 'paid',
        ]);
        $paid->items()->create(['product_id' => $products[2]->id, 'description' => $products[2]->name, 'quantity' => 5, 'unit_price' => $products[2]->price]);
        $paid->payments()->create(['method' => 'efectivo', 'amount' => round($paid->fresh()->total, 2)]);

        // Overdue invoice (pending, due date in the past).
        $overdue = Invoice::create([
            'number' => 'FAC-0003', 'client_id' => $clients[2]->id, 'tax_rate' => 21,
            'issue_date' => now()->subDays(30), 'due_date' => now()->subDays(15), 'status' => 'pending',
        ]);
        $overdue->items()->create(['product_id' => $products[4]->id, 'description' => $products[4]->name, 'quantity' => 8, 'unit_price' => $products[4]->price]);

        // A quote, still open.
        $quote = Quote::create([
            'number' => 'PRE-0001', 'client_id' => $clients[0]->id, 'tax_rate' => 21,
            'issue_date' => now(), 'valid_until' => now()->addDays(15), 'status' => 'sent',
        ]);
        $quote->items()->create(['product_id' => $products[5]->id, 'description' => $products[5]->name, 'quantity' => 1, 'unit_price' => $products[5]->price]);

        // A purchase (already increments stock via direct creation here, so seed stock values above already assume it).
        $purchase = Purchase::create([
            'number' => 'COM-0001', 'provider_id' => $providers[0]->id, 'tax_rate' => 21,
            'issue_date' => now()->subDays(5), 'due_date' => now()->addDays(10), 'status' => 'pending',
        ]);
        $purchase->items()->create(['product_id' => $products[1]->id, 'description' => $products[1]->name, 'quantity' => 20, 'unit_price' => $products[1]->cost_price]);
    }
}
