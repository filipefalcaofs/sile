<?php

namespace App\Http\Controllers;

use App\Services\Documentos\VerificacaoDocumentoService;
use App\Support\Audit\AuditService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Verificação PÚBLICA de autenticidade de documentos (TVL, indeferimento,
 * comprovante de protocolo) pelo código de verificação — SEM login, com
 * throttle público (reuso do limiter consulta-protocolo). O payload é MÍNIMO
 * (LGPD): tipo, protocolo e data de referência — nunca empresa/CNPJ/endereço.
 * Toda consulta é auditada com causer null + IP (RN-002, padrão da consulta
 * pública de protocolo).
 */
class VerificacaoDocumentoController extends Controller
{
    public function __construct(
        private VerificacaoDocumentoService $verificacao,
        private AuditService $audit,
    ) {}

    public function show(string $codigo): Response
    {
        $resultado = $this->verificacao->verificar($codigo);

        $this->audit->log(
            'documentos',
            'verificacao-documento-publica',
            'Consulta pública de autenticidade de documento',
            properties: [
                'verification_code' => $codigo,
                'valido' => $resultado['valido'],
                'tipo' => $resultado['tipo'],
            ],
        );

        return Inertia::render('verificacao-documento', [
            'codigo' => $codigo,
            'resultado' => $resultado,
        ]);
    }
}
