<?php

namespace App\Providers;

use App\Services\Afip\AfipGatewayInterface;
use App\Services\Afip\AfipSoapGateway;
use Illuminate\Support\ServiceProvider;

class AfipServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AfipGatewayInterface::class, AfipSoapGateway::class);
    }
}
