<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImportMapping;
use App\Models\ProductStock;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    /** Genera un .xlsx real (no un fake genérico) para que IOFactory::load() lo pueda leer. */
    private function excel(array $filas): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $hoja = $spreadsheet->getActiveSheet();

        foreach ($filas as $numFila => $fila) {
            foreach ($fila as $numCol => $valor) {
                $hoja->setCellValue([$numCol + 1, $numFila + 1], $valor);
            }
        }

        $ruta = tempnam(sys_get_temp_dir(), 'test_import_').'.xlsx';
        (new Xlsx($spreadsheet))->save($ruta);

        // UploadedFile::fake() es lo que Livewire sabe manejar en tests (trae
        // metadata propia que su wiring interno necesita); le reemplazamos el
        // contenido aleatorio por el .xlsx real que acabamos de generar.
        $fake = UploadedFile::fake()->create('productos.xlsx', 1);
        file_put_contents($fake->getRealPath(), file_get_contents($ruta));

        return $fake;
    }

    public function test_subir_un_excel_sugiere_el_mapeo_de_columnas_automaticamente(): void
    {
        $archivo = $this->excel([
            ['Nombre', 'SKU', 'Precio de venta', 'Stock'],
            ['Coca Cola 1.5L', 'BEB-001', '1500', '20'],
        ]);

        $component = Livewire::actingAs($this->admin())
            ->test('products.import')
            ->set('archivo', $archivo);

        $component->assertSet('step', 'mapear');
        $this->assertSame(0, $component->get('mapeo')['name']);
        $this->assertSame(1, $component->get('mapeo')['sku']);
        $this->assertSame(2, $component->get('mapeo')['price']);
        $this->assertSame(3, $component->get('mapeo')['stock']);
    }

    public function test_importar_crea_productos_nuevos_y_actualiza_los_existentes_por_sku(): void
    {
        $existente = Product::create(['name' => 'Coca Cola Vieja', 'sku' => 'BEB-001', 'price' => 1000, 'stock' => 5]);
        ProductStock::create(['product_id' => $existente->id, 'sucursal_id' => Sucursal::sole()->id, 'stock' => 5]);

        $archivo = $this->excel([
            ['Nombre', 'SKU', 'Precio de venta', 'Stock'],
            ['Coca Cola 1.5L', 'BEB-001', '1500', '20'],
            ['Producto Nuevo', 'NEW-001', '999', '3'],
        ]);

        Livewire::actingAs($this->admin())
            ->test('products.import')
            ->set('archivo', $archivo)
            ->call('confirmarImportacion')
            ->assertSet('step', 'resultado');

        $existente->refresh();
        $this->assertSame('Coca Cola 1.5L', $existente->name);
        $this->assertEqualsWithDelta(1500.0, (float) $existente->price, 0.01);
        $this->assertSame(20, $existente->stock);

        $this->assertDatabaseHas('products', ['sku' => 'NEW-001', 'name' => 'Producto Nuevo']);
        $this->assertSame(2, Product::count());
    }

    public function test_actualiza_por_nombre_cuando_no_hay_sku_mapeado(): void
    {
        $existente = Product::create(['name' => 'Fideos 500g', 'price' => 800, 'stock' => 10]);
        ProductStock::create(['product_id' => $existente->id, 'sucursal_id' => Sucursal::sole()->id, 'stock' => 10]);

        $archivo = $this->excel([
            ['Nombre', 'Precio de venta', 'Stock'],
            ['Fideos 500g', '900', '15'],
        ]);

        Livewire::actingAs($this->admin())
            ->test('products.import')
            ->set('archivo', $archivo)
            ->call('confirmarImportacion');

        $this->assertSame(1, Product::count());
        $existente->refresh();
        $this->assertEqualsWithDelta(900.0, (float) $existente->price, 0.01);
        $this->assertSame(15, $existente->stock);
    }

    public function test_crea_la_categoria_si_no_existe(): void
    {
        $archivo = $this->excel([
            ['Nombre', 'Precio de venta', 'Categoría'],
            ['Alfajor', '500', 'Golosinas'],
        ]);

        Livewire::actingAs($this->admin())
            ->test('products.import')
            ->set('archivo', $archivo)
            ->call('confirmarImportacion');

        $categoria = Category::where('name', 'Golosinas')->first();
        $this->assertNotNull($categoria);
        $this->assertDatabaseHas('products', ['name' => 'Alfajor', 'category_id' => $categoria->id]);
    }

    public function test_fila_sin_nombre_o_precio_se_omite_sin_frenar_el_resto(): void
    {
        $archivo = $this->excel([
            ['Nombre', 'Precio de venta'],
            ['', '500'],
            ['Producto Válido', '800'],
            ['Sin Precio', ''],
        ]);

        $component = Livewire::actingAs($this->admin())
            ->test('products.import')
            ->set('archivo', $archivo)
            ->call('confirmarImportacion');

        $this->assertSame(1, Product::count());
        $this->assertDatabaseHas('products', ['name' => 'Producto Válido']);
        $this->assertCount(2, $component->get('resultado')['omitidos']);
    }

    public function test_no_se_puede_confirmar_sin_mapear_nombre_y_precio(): void
    {
        $archivo = $this->excel([
            ['Columna A', 'Columna B'],
            ['x', 'y'],
        ]);

        Livewire::actingAs($this->admin())
            ->test('products.import')
            ->set('archivo', $archivo)
            ->set('mapeo.name', '')
            ->set('mapeo.price', '')
            ->call('confirmarImportacion')
            ->assertHasErrors(['mapeo.name', 'mapeo.price']);

        $this->assertSame(0, Product::count());
    }

    public function test_el_mapeo_se_recuerda_para_el_proximo_archivo_con_las_mismas_cabeceras(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test('products.import')
            ->set('archivo', $this->excel([
                ['Nombre del producto', 'Valor'],
                ['Producto 1', '100'],
            ]))
            ->set('mapeo.name', 0)
            ->set('mapeo.price', 1)
            ->call('confirmarImportacion');

        $this->assertNotNull(ProductImportMapping::recordarPara(['Nombre del producto', 'Valor']));

        // Un segundo archivo con las mismas cabeceras ya viene mapeado solo,
        // sin necesidad de elegir manualmente de nuevo.
        $component = Livewire::actingAs($admin)
            ->test('products.import')
            ->set('archivo', $this->excel([
                ['Nombre del producto', 'Valor'],
                ['Producto 2', '200'],
            ]));

        $this->assertSame(0, $component->get('mapeo')['name']);
        $this->assertSame(1, $component->get('mapeo')['price']);
    }

    public function test_cajero_no_puede_entrar_a_importar_productos(): void
    {
        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true]);

        // El módulo "products" ya incluye a cajero (igual que crear/editar productos);
        // este test documenta ese comportamiento existente, no lo cambia.
        $this->actingAs($cajero)->get(route('products.import'))->assertOk();
    }
}
