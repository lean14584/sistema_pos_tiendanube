<?php

namespace App\Observers;

use App\Models\Category;
use App\Support\TiendanubeAutoSync;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        TiendanubeAutoSync::queue($category);
    }
}
