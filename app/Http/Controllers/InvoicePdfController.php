<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Support\InvoicePdfBuilder;

class InvoicePdfController extends Controller
{
    public function __invoke(Invoice $invoice, InvoicePdfBuilder $builder)
    {
        return $builder->pdf($invoice)->download("{$invoice->number}.pdf");
    }
}
