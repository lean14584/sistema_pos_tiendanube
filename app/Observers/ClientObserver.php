<?php

namespace App\Observers;

use App\Models\Client;
use App\Support\TiendanubeAutoSync;

class ClientObserver
{
    public function saved(Client $client): void
    {
        TiendanubeAutoSync::queue($client);
    }
}
