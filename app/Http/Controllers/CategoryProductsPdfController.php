<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CompanySettings;
use App\Support\CurrentSucursal;
use Barryvdh\DomPDF\Facade\Pdf;

class CategoryProductsPdfController extends Controller
{
    public function __invoke(Category $category)
    {
        $sucursalId = CurrentSucursal::id();

        $products = $category->products()
            ->with(['stocks' => fn ($q) => $q->where('sucursal_id', $sucursalId)])
            ->orderBy('name')
            ->get();

        $company = CompanySettings::current();
        $logoPath = $company->logo_path ? storage_path('app/public/'.$company->logo_path) : null;

        $pdf = Pdf::loadView('pdf.category-products', [
            'category' => $category,
            'products' => $products,
            'company' => $company,
            'logoPath' => $logoPath && file_exists($logoPath) ? $logoPath : null,
            'sucursal' => CurrentSucursal::get(),
        ]);

        return $pdf->download("Categoria-{$category->name}.pdf");
    }
}
