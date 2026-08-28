<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Support\AccountStatementPdfBuilder;

class ClientAccountStatementController extends Controller
{
    public function __invoke(Client $client, AccountStatementPdfBuilder $builder)
    {
        return $builder->forClient($client)->download("cuenta-corriente-{$client->name}.pdf");
    }
}
