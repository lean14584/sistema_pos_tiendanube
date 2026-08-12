<?php

namespace App\Services\Afip;

use App\Enums\TipoComprobante;
use App\Exceptions\Afip\AfipConnectionException;
use App\Exceptions\Afip\AfipRejectedException;
use App\Services\Afip\Data\CaeRequest;
use App\Services\Afip\Data\CaeResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use SoapClient;
use SoapFault;
use Throwable;

/**
 * Habla directo contra los servidores de AFIP (WSAA + WSFEv1), sin pasar
 * por ningún servicio de terceros. Porta el enfoque ya probado en
 * ArcaService.php (proyecto hermano posOfflineDos) pero corrige su bug
 * conocido: acá CbteTipo y CondicionIVAReceptorId siempre vienen del
 * CaeRequest, nunca hardcodeados.
 */
class AfipSoapGateway implements AfipGatewayInterface
{
    private const CACHE_KEY = 'afip:wsaa:wsfe:token';

    /**
     * Alicuotas de IVA del catálogo de AFIP (FEParamGetTiposIva). Mapea el
     * tax_rate libre de la factura al código más cercano — si el negocio
     * usa una alícuota no estándar, ajustar esta tabla.
     */
    private const ALICUOTAS_IVA = [
        '0' => 3,
        '10.5' => 4,
        '21.0' => 5,
        '27.0' => 6,
        '5.0' => 8,
        '2.5' => 9,
    ];

    private ?string $token = null;

    private ?string $sign = null;

    public function getLastVoucherNumber(int $puntoVenta, TipoComprobante $tipo): int
    {
        $this->ensureLoggedIn();

        try {
            $client = $this->wsfeClient();

            $response = $client->FECompUltimoAutorizado([
                'Auth' => $this->authParams(),
                'PtoVta' => $puntoVenta,
                'CbteTipo' => $tipo->value,
            ]);

            return (int) $response->FECompUltimoAutorizadoResult->CbteNro;
        } catch (SoapFault|Throwable $e) {
            throw new AfipConnectionException(
                'No se pudo consultar el último comprobante autorizado en ARCA: '.$e->getMessage(), previous: $e
            );
        }
    }

    public function requestCae(CaeRequest $request): CaeResponse
    {
        $this->ensureLoggedIn();

        $detalle = [
            'Concepto' => $request->concepto,
            'DocTipo' => $request->docTipo,
            'DocNro' => $request->docNro,
            'CbteDesde' => $request->cbteNro,
            'CbteHasta' => $request->cbteNro,
            'CbteFch' => now()->format('Ymd'),
            'ImpTotal' => $request->impTotal,
            'ImpNeto' => $request->impNeto,
            'ImpIVA' => $request->impIva,
            'ImpTrib' => 0,
            'ImpTotConc' => 0,
            'ImpOpEx' => $request->impOpEx,
            'MonId' => 'PES',
            'MonCotiz' => 1,
            // Requisito RG 5616 — nunca hardcodeado, viene resuelto por
            // ComprobanteResolver según emisor+receptor.
            'CondicionIVAReceptorId' => $request->condicionIvaReceptorId,
        ];

        // Factura C (emisor Monotributista/Exento) no discrimina IVA. El resto
        // manda una entrada por cada alícuota gravada del comprobante.
        if ($request->tipoComprobante->family() !== 'C' && $request->alicuotas !== []) {
            $detalle['Iva'] = [
                'AlicIva' => array_map(fn (array $a) => [
                    'Id' => $this->alicuotaIdPorTasa($a['tasa']),
                    'BaseImp' => $a['baseImp'],
                    'Importe' => $a['importe'],
                ], $request->alicuotas),
            ];
        }

        // Toda Nota de Crédito/Débito tiene que declarar a qué comprobante
        // corrige.
        if ($request->comprobanteAsociado !== null) {
            $detalle['CbtesAsoc'] = [
                'CbteAsoc' => [
                    'Tipo' => $request->comprobanteAsociado->tipo->value,
                    'PtoVta' => $request->comprobanteAsociado->puntoVenta,
                    'Nro' => $request->comprobanteAsociado->numero,
                ],
            ];
        }

        $params = [
            'Auth' => $this->authParams(),
            'FeCAEReq' => [
                'FeCabReq' => [
                    'CantReg' => 1,
                    'PtoVta' => $request->puntoVenta,
                    'CbteTipo' => $request->tipoComprobante->value,
                ],
                'FeDetReq' => [
                    'FECAEDetRequest' => $detalle,
                ],
            ],
        ];

        try {
            $response = $this->wsfeClient()->FECAESolicitar($params);
        } catch (SoapFault|Throwable $e) {
            throw new AfipConnectionException(
                'No se pudo contactar a ARCA para solicitar el CAE: '.$e->getMessage(), previous: $e
            );
        }

        $resultado = $response->FECAESolicitarResult ?? null;

        if (isset($resultado->Errors)) {
            $errores = $this->normalizarErrores($resultado->Errors);

            throw new AfipRejectedException(
                'ARCA rechazó el comprobante: '.implode(' | ', array_column($errores, 'msg')),
                $errores
            );
        }

        $detalleRespuesta = $resultado->FeDetResp->FECAEDetResponse ?? null;

        if (! $detalleRespuesta || $detalleRespuesta->Resultado !== 'A') {
            $errores = $this->normalizarErrores($detalleRespuesta->Observaciones->Obs ?? []);

            throw new AfipRejectedException(
                'ARCA no aprobó el comprobante.'.($errores !== [] ? ' '.implode(' | ', array_column($errores, 'msg')) : ''),
                $errores
            );
        }

        $observaciones = [];
        if (isset($detalleRespuesta->Observaciones->Obs)) {
            foreach ($this->normalizarErrores($detalleRespuesta->Observaciones->Obs) as $obs) {
                $observaciones[] = $obs['msg'];
            }
        }

        return new CaeResponse(
            cae: (string) $detalleRespuesta->CAE,
            caeVencimiento: Carbon::createFromFormat('Ymd', (string) $detalleRespuesta->CAEFchVto),
            observaciones: $observaciones,
            raw: json_decode(json_encode($response), true) ?? [],
        );
    }

    private function ensureLoggedIn(): void
    {
        if ($this->token !== null && $this->sign !== null) {
            return;
        }

        $cached = Cache::get(self::CACHE_KEY);

        if ($cached !== null) {
            $this->token = $cached['token'];
            $this->sign = $cached['sign'];

            return;
        }

        $this->login();
    }

    private function login(): void
    {
        $tra = $this->createTra();
        $cms = $this->signTra($tra);

        try {
            $client = new SoapClient(config('afip.wsaa_url'), ['soap_version' => SOAP_1_2, 'trace' => true]);
            $response = $client->loginCms(['in0' => $cms]);
            $ticket = simplexml_load_string($response->loginCmsReturn);
        } catch (SoapFault|Throwable $e) {
            throw new AfipConnectionException('No se pudo autenticar contra el WSAA de ARCA: '.$e->getMessage(), previous: $e);
        }

        $this->token = (string) $ticket->credentials->token;
        $this->sign = (string) $ticket->credentials->sign;
        $expirationTime = Carbon::parse((string) $ticket->header->expirationTime);

        $bufferMinutes = config('afip.token_cache_buffer_minutes', 10);

        Cache::put(
            self::CACHE_KEY,
            ['token' => $this->token, 'sign' => $this->sign],
            $expirationTime->subMinutes($bufferMinutes)
        );
    }

    private function createTra(): string
    {
        $uniqueId = time();
        $generationTime = now()->subMinute()->toAtomString();
        $expirationTime = now()->addHour()->toAtomString();

        return <<<XML
        <loginTicketRequest version="1.0">
            <header>
                <uniqueId>{$uniqueId}</uniqueId>
                <generationTime>{$generationTime}</generationTime>
                <expirationTime>{$expirationTime}</expirationTime>
            </header>
            <service>wsfe</service>
        </loginTicketRequest>
        XML;
    }

    private function signTra(string $tra): string
    {
        $certPath = config('afip.cert_path');
        $keyPath = config('afip.key_path');

        if (! file_exists($certPath) || ! file_exists($keyPath)) {
            throw new AfipConnectionException(
                "Certificado o clave privada de ARCA no encontrados. Se esperaban en: {$certPath} y {$keyPath}."
            );
        }

        $traPath = tempnam(sys_get_temp_dir(), 'tra');
        $signedPath = tempnam(sys_get_temp_dir(), 'signed');
        file_put_contents($traPath, $tra);

        $status = openssl_pkcs7_sign(
            $traPath,
            $signedPath,
            file_get_contents($certPath),
            [file_get_contents($keyPath), ''],
            [],
            ! PKCS7_DETACHED
        );

        if (! $status) {
            @unlink($traPath);
            @unlink($signedPath);

            throw new AfipConnectionException('No se pudo firmar el TRA con OpenSSL: '.openssl_error_string());
        }

        $cms = file_get_contents($signedPath);
        $parts = explode("\n\n", $cms, 2);
        $cms = $parts[1] ?? $cms;

        unlink($traPath);
        unlink($signedPath);

        return trim(str_replace(["\r", "\n"], '', $cms));
    }

    private function wsfeClient(): SoapClient
    {
        return new SoapClient(config('afip.wsfe_url'), ['soap_version' => SOAP_1_2, 'trace' => true, 'exceptions' => true]);
    }

    private function authParams(): array
    {
        return [
            'Token' => $this->token,
            'Sign' => $this->sign,
            'Cuit' => config('afip.cuit'),
        ];
    }

    private function alicuotaIdPorTasa(float $tasa): int
    {
        foreach (self::ALICUOTAS_IVA as $porcentaje => $id) {
            if (abs((float) $porcentaje - $tasa) < 0.05) {
                return $id;
            }
        }

        return self::ALICUOTAS_IVA['21.0'];
    }

    /**
     * @return array<int, array{code: string, msg: string}>
     */
    private function normalizarErrores(mixed $errores): array
    {
        if ($errores === null || $errores === []) {
            return [];
        }

        // AFIP devuelve un objeto único cuando hay un solo error/obs, o un
        // array cuando hay varios — normalizamos siempre a una lista.
        $lista = is_array($errores) ? $errores : [$errores];

        return array_map(
            fn ($e) => ['code' => (string) ($e->Code ?? ''), 'msg' => (string) ($e->Msg ?? '')],
            $lista
        );
    }
}
