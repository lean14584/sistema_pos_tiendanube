<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\User;
use App\Support\Ean13;
use App\Support\Ean13Barcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductLabelsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_agregar_producto_genera_una_etiqueta_por_unidad(): void
    {
        $product = Product::create(['name' => 'Yerba', 'sku' => 'YER-1', 'price' => 1500, 'iva_rate' => 21, 'stock' => 10]);

        $component = Livewire::actingAs($this->admin())
            ->test('products.labels')
            ->call('addProduct', $product->id)
            ->set('selected.'.$product->id.'.qty', 3);

        $component->assertSee('Yerba')->assertSee('1.500,00');
        // 3 etiquetas del mismo producto.
        $component->assertSeeInOrder(['1.500,00', '1.500,00', '1.500,00']);
    }

    public function test_las_etiquetas_usan_el_precio_de_la_lista_elegida(): void
    {
        $mayorista = PriceList::create(['name' => 'Mayorista', 'adjustment_percent' => 20, 'is_default' => false, 'active' => true]);
        $product = Product::create(['name' => 'Fideos', 'price' => 1000, 'iva_rate' => 0, 'stock' => 5]);

        Livewire::actingAs($this->admin())
            ->test('products.labels')
            ->call('addProduct', $product->id)
            ->set('price_list_id', $mayorista->id)
            ->assertSee('1.200,00'); // 1000 + 20%
    }

    public function test_el_modo_etiquetadora_muestra_boton_de_descargar_pdf_en_vez_de_imprimir(): void
    {
        $product = Product::create(['name' => 'Yerba', 'sku' => 'YER-1', 'price' => 1500, 'iva_rate' => 21, 'stock' => 10]);

        $component = Livewire::actingAs($this->admin())
            ->test('products.labels')
            ->call('addProduct', $product->id)
            ->set('modoEtiquetadora', true);

        $component->assertSee('Descargar PDF');
        $component->assertDontSee('Columnas por hoja');
    }

    public function test_el_modo_etiquetadora_descarga_un_pdf_con_el_tamano_fijo_de_la_hprt_lpq80(): void
    {
        $product = Product::create(['name' => 'Yerba', 'sku' => 'YER-1', 'price' => 1500, 'iva_rate' => 21, 'stock' => 10]);

        Livewire::actingAs($this->admin())
            ->test('products.labels')
            ->call('addProduct', $product->id)
            ->set('modoEtiquetadora', true)
            ->call('descargarEtiquetadoraPdf')
            ->assertFileDownloaded('etiquetas.pdf');
    }

    /**
     * Los tests de esta clase que renderizan la vista PDF directamente (sin
     * pasar por el componente Livewire) arman el array de la etiqueta a
     * mano — este helper completa los campos de precio por medio de pago en
     * 0 (feature apagada, mismo comportamiento que un cliente sin los
     * descuentos configurados) salvo que el test los pise explícitamente.
     *
     * @return array{name:string,sku:?string,price:float,ean13:?string,priceTransferencia:float,priceEfectivo:float,pctTransferencia:float,pctEfectivo:float}
     */
    private function labelData(string $name, ?string $sku, float $price, array $overrides = []): array
    {
        return array_merge([
            'name' => $name,
            'sku' => $sku,
            'price' => $price,
            'ean13' => Ean13::fromSku($sku),
            'priceTransferencia' => $price,
            'priceEfectivo' => $price,
            'pctTransferencia' => 0.0,
            'pctEfectivo' => 0.0,
        ], $overrides);
    }

    public function test_la_etiqueta_pdf_incluye_el_codigo_de_barras_cuando_el_sku_es_numerico(): void
    {
        $ean13 = Ean13::fromSku('9876');

        $html = view('pdf.etiquetas-precio', [
            'labels' => collect([$this->labelData('Plato De Porcelana', '9876', 2000)]),
            'barcodes' => collect([$ean13 => Ean13Barcode::dataUri($ean13)]),
            'companyName' => 'DECO-HOGAR',
            'showSku' => true,
            'showName' => true,
            'showCompany' => true,
            'heightMm' => 44,
        ])->render();

        $this->assertStringContainsString('data:image/png;base64,', $html);
        // El código de barras reemplaza al texto plano del sku, no conviven.
        $this->assertStringNotContainsString('<div class="sku">9876</div>', $html);
    }

    public function test_la_etiqueta_pdf_usa_texto_plano_si_el_sku_no_es_numerico(): void
    {
        // "YER-1" no se puede convertir a EAN13 (no es numérico): se sigue
        // viendo el sku como texto, igual que antes de tener código de barras.
        $html = view('pdf.etiquetas-precio', [
            'labels' => collect([$this->labelData('Yerba', 'YER-1', 1500)]),
            'barcodes' => collect(),
            'companyName' => 'DECO-HOGAR',
            'showSku' => true,
            'showName' => true,
            'showCompany' => true,
            'heightMm' => 44,
        ])->render();

        $this->assertStringNotContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('YER-1', $html);
    }

    public function test_la_etiqueta_pdf_trunca_nombres_muy_largos_en_vez_de_solo_recortarlos_visualmente(): void
    {
        // dompdf no respeta overflow:hidden para decidir paginación: un
        // nombre demasiado largo puede derramar la etiqueta a una SEGUNDA
        // página en vez de solo recortarse (esto rompió de verdad al
        // agrandar la letra/código de barras — ver Str::limit en la vista).
        $nombreLargo = 'Juego De Sabanas King Size Algodon Premium Con Funda Extra Grande Y Almohadas De Regalo';

        $html = view('pdf.etiquetas-precio', [
            'labels' => collect([$this->labelData($nombreLargo, '9876', 2000)]),
            'barcodes' => collect(),
            'companyName' => 'DECO-HOGAR',
            'showSku' => false,
            'showName' => true,
            'showCompany' => true,
            'heightMm' => 44,
        ])->render();

        $this->assertStringNotContainsString($nombreLargo, $html);
        // preserveWords: corta en el último espacio antes del límite, nunca
        // a mitad de una palabra (ej. no "Prem..." ni "Gran...").
        $this->assertStringContainsString('Juego De Sabanas King Size Algodon Premium Con', $html);
        $this->assertStringNotContainsString('Almohadas', $html);
    }

    public function test_un_nombre_que_entra_justo_en_el_limite_no_se_trunca(): void
    {
        // 47 caracteres: entra completo bajo el límite de 50.
        $nombreExacto = 'Juego De Sabanas King Size Algodon Premium';
        $this->assertLessThanOrEqual(50, strlen($nombreExacto));

        $html = view('pdf.etiquetas-precio', [
            'labels' => collect([$this->labelData($nombreExacto, '9877', 15000)]),
            'barcodes' => collect(),
            'companyName' => 'DECO-HOGAR',
            'showSku' => false,
            'showName' => true,
            'showCompany' => true,
            'heightMm' => 44,
        ])->render();

        $this->assertStringContainsString($nombreExacto, $html);
    }

    public function test_la_etiqueta_pdf_muestra_los_3_precios_por_medio_de_pago_cuando_estan_configurados(): void
    {
        // El cliente pidió ver los 3 precios en la etiqueta: Tarjeta al
        // precio de lista, Transferencia y Efectivo con su descuento (ver
        // CompanySettings::descuentoPctParaMedioDePago).
        $html = view('pdf.etiquetas-precio', [
            'labels' => collect([$this->labelData('Plato De Porcelana', '9876', 2000, [
                'priceTransferencia' => 1800.0,
                'priceEfectivo' => 1700.0,
                'pctTransferencia' => 10.0,
                'pctEfectivo' => 15.0,
            ])]),
            'barcodes' => collect(),
            'companyName' => 'DECO-HOGAR',
            'showSku' => false,
            'showName' => true,
            'showCompany' => true,
            'heightMm' => 44,
        ])->render();

        $this->assertStringContainsString('Tarjeta', $html);
        $this->assertStringContainsString('2.000,00', $html);
        $this->assertStringContainsString('Transf -10%', $html);
        $this->assertStringContainsString('1.800,00', $html);
        $this->assertStringContainsString('Efectivo -15%', $html);
        $this->assertStringContainsString('1.700,00', $html);
    }

    public function test_la_etiqueta_pdf_muestra_un_solo_precio_si_no_hay_descuentos_por_medio_de_pago(): void
    {
        // Cliente que no usa la feature (ej. pos-tiendanube hoy): sigue
        // viendo el precio único de siempre, sin el bloque de 3 filas.
        $html = view('pdf.etiquetas-precio', [
            'labels' => collect([$this->labelData('Plato De Porcelana', '9876', 2000)]),
            'barcodes' => collect(),
            'companyName' => 'DECO-HOGAR',
            'showSku' => false,
            'showName' => true,
            'showCompany' => true,
            'heightMm' => 44,
        ])->render();

        // "Tarjeta" bare no sirve para este assert: aparece en el comentario
        // de la hoja de estilos aunque el bloque de 3 precios esté apagado.
        $this->assertStringNotContainsString('class="pm">Tarjeta', $html);
        $this->assertStringContainsString('<div class="price">$2.000,00</div>', $html);
    }

    public function test_agregar_categoria_entera_suma_sus_productos(): void
    {
        $cat = Category::create(['name' => 'Bebidas']);
        Product::create(['name' => 'Agua', 'category_id' => $cat->id, 'price' => 500, 'iva_rate' => 0, 'stock' => 5]);
        Product::create(['name' => 'Gaseosa', 'category_id' => $cat->id, 'price' => 900, 'iva_rate' => 0, 'stock' => 5]);

        $component = Livewire::actingAs($this->admin())
            ->test('products.labels')
            ->set('catToAdd', $cat->id)
            ->call('addCategory');

        $this->assertCount(2, $component->get('selected'));
        $component->assertSee('Agua')->assertSee('Gaseosa');
    }
}
