<?php

namespace App\Services\Afip;

use App\Enums\TipoComprobante;
use App\Services\Afip\Data\CaeRequest;
use App\Services\Afip\Data\CaeResponse;

interface AfipGatewayInterface
{
    /**
     * Último número de comprobante autorizado por AFIP para ese punto de
     * venta + tipo. El próximo a emitir es este valor + 1.
     */
    public function getLastVoucherNumber(int $puntoVenta, TipoComprobante $tipo): int;

    /**
     * @throws \App\Exceptions\Afip\AfipConnectionException
     * @throws \App\Exceptions\Afip\AfipRejectedException
     */
    public function requestCae(CaeRequest $request): CaeResponse;
}
