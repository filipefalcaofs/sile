<?php

namespace App\Services\Documentos;

use App\Models\IndeferimentoDocument;
use App\Models\TvlDocument;
use App\Models\ViabilityRequest;

/**
 * Verificação de autenticidade de documentos pelo código de verificação
 * (consulta pública, sem login). Resolve os três formatos emitidos pelo
 * sistema: TVL-{ulid} (tvl_documents), IND-{ulid} (indeferimento_documents) e
 * CMP-{protocolo}-{hmac10} (comprovante de protocolo — determinístico, o HMAC
 * é recomputado e comparado; nada é armazenado). O retorno é o payload MÍNIMO
 * (LGPD): tipo, protocolo e data de referência — nunca empresa/CNPJ/endereço.
 * Código desconhecido ou adulterado → valido=false (nunca um "válido"
 * inventado).
 */
class VerificacaoDocumentoService
{
    /**
     * @return array{valido: bool, tipo: ?string, protocolo: ?string, data_label: ?string, data: ?string}
     */
    public function verificar(string $codigo): array
    {
        $documento = $this->resolver($codigo);

        if ($documento === null) {
            return ['valido' => false, 'tipo' => null, 'protocolo' => null, 'data_label' => null, 'data' => null];
        }

        return $documento;
    }

    /**
     * @return array{valido: bool, tipo: string, protocolo: ?string, data_label: string, data: ?string}|null
     */
    private function resolver(string $codigo): ?array
    {
        if (str_starts_with($codigo, 'TVL-')) {
            $documento = TvlDocument::query()
                ->with('viabilityDecision.viabilityRequest')
                ->where('verification_code', $codigo)
                ->first();

            if ($documento === null) {
                return null;
            }

            return [
                'valido' => true,
                'tipo' => 'Termo de Viabilidade de Localização (TVL)',
                'protocolo' => $documento->viabilityDecision?->viabilityRequest?->protocol_number,
                'data_label' => 'Emitido em',
                'data' => $documento->generated_at?->toIso8601String(),
            ];
        }

        if (str_starts_with($codigo, 'IND-')) {
            $documento = IndeferimentoDocument::query()
                ->with('viabilityDecision.viabilityRequest')
                ->where('verification_code', $codigo)
                ->first();

            if ($documento === null) {
                return null;
            }

            return [
                'valido' => true,
                'tipo' => 'Documento de Indeferimento',
                'protocolo' => $documento->viabilityDecision?->viabilityRequest?->protocol_number,
                'data_label' => 'Emitido em',
                'data' => $documento->generated_at?->toIso8601String(),
            ];
        }

        if (str_starts_with($codigo, 'CMP-')) {
            return $this->resolverComprovante($codigo);
        }

        return null;
    }

    /**
     * Comprovante de protocolo: o código é CMP-{protocolo}-{hmac10}; o HMAC é
     * recomputado com a chave da app e comparado em tempo constante, e o
     * protocolo precisa existir de fato.
     *
     * @return array{valido: bool, tipo: string, protocolo: ?string, data_label: string, data: ?string}|null
     */
    private function resolverComprovante(string $codigo): ?array
    {
        if (! preg_match('/^CMP-(.+)-([a-f0-9]{10})$/', $codigo, $m)) {
            return null;
        }

        [, $protocolo, $hmacInformado] = $m;

        $hmacEsperado = substr(hash_hmac('sha256', 'comprovante-protocolo|'.$protocolo, (string) config('app.key')), 0, 10);

        if (! hash_equals($hmacEsperado, $hmacInformado)) {
            return null;
        }

        $solicitacao = ViabilityRequest::query()
            ->where('protocol_number', $protocolo)
            ->first(['id', 'protocol_number', 'protocoled_at']);

        if ($solicitacao === null) {
            return null;
        }

        return [
            'valido' => true,
            'tipo' => 'Comprovante de Protocolo',
            'protocolo' => $solicitacao->protocol_number,
            'data_label' => 'Protocolado em',
            'data' => $solicitacao->protocoled_at?->toIso8601String(),
        ];
    }
}
