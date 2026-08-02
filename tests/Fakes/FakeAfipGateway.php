<?php

namespace Tests\Fakes;

use App\Enums\TipoComprobante;
use App\Exceptions\Afip\AfipRejectedException;
use App\Services\Afip\AfipGatewayInterface;
use App\Services\Afip\Data\CaeRequest;
use App\Services\Afip\Data\CaeResponse;
use Illuminate\Support\Carbon;

/**
 * Doble de AfipGatewayInterface para tests. No hay Http::fake() posible
 * acá porque el gateway real habla SOAP, no HTTP de Laravel — por eso el
 * contrato existe, para poder cambiar la implementación en tests sin
 * tocar nada de Livewire/InvoiceCaeEmitter.
 */
class FakeAfipGateway implements AfipGatewayInterface
{
    public int $lastVoucherNumber = 0;

    public bool $rejectNextRequest = false;

    public string $rejectionMessage = 'CUIT del receptor no válido';

    /** @var CaeRequest[] */
    public array $requestsRecibidas = [];

    public function getLastVoucherNumber(int $puntoVenta, TipoComprobante $tipo): int
    {
        return $this->lastVoucherNumber;
    }

    public function requestCae(CaeRequest $request): CaeResponse
    {
        $this->requestsRecibidas[] = $request;

        if ($this->rejectNextRequest) {
            $this->rejectNextRequest = false;

            throw new AfipRejectedException($this->rejectionMessage, [
                ['code' => '10015', 'msg' => $this->rejectionMessage],
            ]);
        }

        $this->lastVoucherNumber = $request->cbteNro;

        return new CaeResponse(
            cae: '71'.str_pad((string) $request->cbteNro, 12, '0', STR_PAD_LEFT),
            caeVencimiento: Carbon::today()->addDays(10),
        );
    }
}
