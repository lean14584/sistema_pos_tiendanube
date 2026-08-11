<?php

namespace App\Observers;

use App\Models\Product;
use App\Support\TiendanubeAutoSync;

class ProductObserver
{
    public function saved(Product $product): void
    {
        TiendanubeAutoSync::queue($product);
    }
}
