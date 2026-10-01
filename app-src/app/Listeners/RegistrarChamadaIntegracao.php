<?php

namespace App\Listeners;

use App\Services\Integracao\IntegrationCallRecorder;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;

/**
 * Toda saída pelo cliente HTTP do Laravel (REGIN, GeoServer, SEDUR, CNPJ,
 * gov.br, IA, geocodificação) cai aqui. Auto-descoberto pelo handle* —
 * registrar de novo no provider duplicaria a linha.
 */
class RegistrarChamadaIntegracao
{
    public function __construct(private IntegrationCallRecorder $recorder) {}

    public function handleResponseReceived(ResponseReceived $event): void
    {
        $this->recorder->saidaRespondida($event->request, $event->response);
    }

    public function handleConnectionFailed(ConnectionFailed $event): void
    {
        $this->recorder->saidaSemConexao($event->request, $event->exception);
    }
}
