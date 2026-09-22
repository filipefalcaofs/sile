<?php

namespace App\Services\Solicitacao;

use App\Models\Cnae;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;

/**
 * Comprovante de protocolo em PDF (documento formal do cidadão). Emitido sob
 * demanda no portal autenticado — NADA é armazenado: o protocolo é imutável
 * após o registro, então o PDF é re-renderizado a cada emissão a partir dos
 * dados reais (anti-fachada) e o código de verificação é DETERMINÍSTICO
 * (HMAC do número de protocolo com a chave da aplicação), verificável na
 * consulta pública de autenticidade sem tabela própria. Só solicitações
 * protocoladas têm comprovante (sem protocolo, não há o que comprovar).
 * Cada emissão é auditada (RN-002).
 */
class ComprovanteProtocoloService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Código de verificação determinístico no formato CMP-{protocolo}-{hmac10}.
     * O HMAC com a chave da app impede que terceiros forjem códigos de
     * protocolos alheios; a verificação pública recomputa e compara.
     */
    public function codigoVerificacao(ViabilityRequest $solicitacao): string
    {
        $protocolo = (string) $solicitacao->protocol_number;

        $hmac = substr(hash_hmac('sha256', 'comprovante-protocolo|'.$protocolo, (string) config('app.key')), 0, 10);

        return "CMP-{$protocolo}-{$hmac}";
    }

    /**
     * Gera o PDF do comprovante (binário). Solicitação sem protocolo →
     * DomainException (o endpoint traduz para 422). A emissão é auditada.
     */
    public function generate(ViabilityRequest $solicitacao, ?User $ator = null): string
    {
        if ($solicitacao->protocol_number === null || $solicitacao->protocoled_at === null) {
            throw new DomainException('A solicitação ainda não foi protocolada — não há comprovante a emitir.');
        }

        $solicitacao->loadMissing(['company', 'cnaes']);

        $pdf = Pdf::loadView('documentos.comprovante-protocolo', $this->montarDados($solicitacao))
            ->setPaper('a4', 'portrait')
            ->output();

        $this->audit->log(
            logName: 'solicitacoes',
            event: 'comprovante-protocolo-emitido',
            description: "Emissão do comprovante de protocolo da solicitação #{$solicitacao->id}",
            properties: [
                'viability_request_id' => $solicitacao->id,
                'protocol_number' => $solicitacao->protocol_number,
                'verification_code' => $this->codigoVerificacao($solicitacao),
                'generated_by' => $ator?->id,
            ],
            subject: $solicitacao,
            result: 'sucesso',
        );

        return $pdf;
    }

    /**
     * Dados do documento: identificação do protocolo, empresa, imóvel e
     * atividades — o MESMO recorte que o dono vê na consulta autenticada.
     *
     * @return array<string, mixed>
     */
    private function montarDados(ViabilityRequest $solicitacao): array
    {
        $codigo = $this->codigoVerificacao($solicitacao);

        $endereco = implode(', ', array_filter([
            $solicitacao->address_street,
            $solicitacao->address_number,
            $solicitacao->address_complement,
        ]));

        return [
            'protocolo' => $solicitacao->protocol_number,
            'protocolado_em' => $solicitacao->protocoled_at,
            'emitido_em' => now(),
            'empresa' => [
                'razao_social' => $solicitacao->company?->legal_name,
                'nome_fantasia' => $solicitacao->company?->trade_name,
                'cnpj' => $solicitacao->company?->formatted_cnpj,
            ],
            'imovel' => [
                'endereco' => $endereco,
                'bairro' => $solicitacao->address_neighborhood,
                'cep' => $solicitacao->address_zip,
                'area_m2' => $solicitacao->used_area_m2,
            ],
            'atividades' => $solicitacao->cnaes
                ->sortByDesc(fn (Cnae $cnae) => (bool) $cnae->pivot->is_primary)
                ->values()
                ->map(fn (Cnae $cnae) => [
                    'codigo_formatado' => $cnae->formatted_code,
                    'descricao' => $cnae->description,
                    'is_primary' => (bool) $cnae->pivot->is_primary,
                ])
                ->all(),
            'verification_code' => $codigo,
            'url_verificacao' => url('/verificar-documento/'.$codigo),
        ];
    }
}
