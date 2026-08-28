<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Support\AccountStatementPdfBuilder;

class ProviderAccountStatementController extends Controller
{
    public function __invoke(Provider $provider, AccountStatementPdfBuilder $builder)
    {
        return $builder->forProvider($provider)->download("cuenta-corriente-{$provider->name}.pdf");
    }
}
