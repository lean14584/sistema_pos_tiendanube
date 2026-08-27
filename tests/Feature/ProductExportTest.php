<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ProductExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_exportar_genera_un_xlsx_con_las_mismas_etiquetas_que_espera_el_importador(): void
    {
        $categoria = Category::create(['name' => 'Bebidas']);
        Product::create([
            'name' => 'Coca Cola 1.5L', 'sku' => 'BEB-001', 'category_id' => $categoria->id,
            'price' => 1500, 'cost_price' => 1000, 'iva_rate' => 21, 'stock' => 20, 'min_stock' => 5,
            'description' => 'Gaseosa',
        ]);

        $response = $this->actingAs($this->admin())->get(route('products.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $ruta = tempnam(sys_get_temp_dir(), 'export_test_').'.xlsx';
        file_put_contents($ruta, $response->streamedContent());

        $filas = IOFactory::load($ruta)->getActiveSheet()->toArray(null, true, true, false);

        $this->assertSame(
            ['SKU / Código', 'Categoría', 'Stock mínimo', 'Precio de costo', 'Alícuota IVA', 'Precio de venta', 'Stock', 'Descripción', 'Nombre'],
            $filas[0]
        );
        $this->assertSame('BEB-001', $filas[1][0]);
        $this->assertSame('Bebidas', $filas[1][1]);
        $this->assertEqualsWithDelta(5, (float) $filas[1][2], 0.01);
        $this->assertEqualsWithDelta(1000.0, (float) $filas[1][3], 0.01);
        $this->assertSame('21', $filas[1][4]);
        $this->assertEqualsWithDelta(1500.0, (float) $filas[1][5], 0.01);
        $this->assertEqualsWithDelta(20, (float) $filas[1][6], 0.01);
        $this->assertSame('Gaseosa', $filas[1][7]);
        $this->assertSame('Coca Cola 1.5L', $filas[1][8]);

        unlink($ruta);
    }
}
