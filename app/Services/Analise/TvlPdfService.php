<?php

namespace App\Services\Analise;

use App\Enums\IntencaoAtividade;
use App\Models\AnalysisRecord;
use App\Models\Cnae;
use App\Models\TvlDocument;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Services\Analise\Concerns\MontaDadosDocumentoDecisao;
use App\Support\Audit\AuditService;
use Barryvdh\DomPDF\Facade\Pdf;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Emissão do TVL (Termo de Viabilidade de Localização) em PDF no backoffice
 * (HU-132). O documento deixou de ir ao cidadão (canal oficial é Regin/SEFAZ) e
 * virou relatório administrativo interno emitível sob demanda APÓS o deferimento
 * (fluxo expresso OU análise humana).
 *
 * A fonte é a MESMA ViabilityDecision deferida (Fase 9 / 10-10) — fonte única do
 * PDF e da explicabilidade — somada às condicionantes/parecer da ficha finalizada
 * (10-09). Anti-fachada: o dompdf renderiza um Blade REAL (o conteúdo começa com
 * '%PDF'), grava no disco parametrizado e NUNCA público, e cada emissão/reimpressão
 * cria uma linha auditada em tvl_documents (RN-002/CA-03). Só decisões deferidas
 * (FA-01); o gate de perfil emitir-tvl (RN-001) e o download por URL assinada são
 * do endpoint (10-15). A assinatura é parametrizável (imagem do diretor ou
 * nenhuma); gov.br/ICP-Brasil é gancho documentado (RN-005 → SEDUR).
 */
class TvlPdfService
{
    use MontaDadosDocumentoDecisao;

    public function __construct(private readonly AuditService $audit) {}

    /**
     * Gera o TVL em PDF de uma decisão DEFERIDA: renderiza o Blade com o dompdf,
     * grava no disco parametrizado (não público) e registra a emissão auditada em
     * tvl_documents. Decisão não deferida → DomainException (FA-01). Cada chamada
     * (emissão/reimpressão) é uma nova linha com verification_code/arquivo próprios.
     */
    public function generate(ViabilityDecision $decision, ?User $ator = null): TvlDocument
    {
        if (! $decision->isDeferida()) {
            throw new DomainException('O TVL só pode ser emitido para decisões deferidas.');
        }

        $disk = $this->resolverDisco();
        $verificationCode = $this->gerarCodigoVerificacao();
        $generatedAt = now();

        $pdf = Pdf::loadView('tvl.documento', $this->montarDados($decision, $verificationCode, $generatedAt))
            ->setPaper(
                (string) config('sile.analise.tvl.paper', 'a4'),
                (string) config('sile.analise.tvl.orientation', 'portrait'),
            )
            ->output();

        $path = "tvl/{$decision->id}/{$verificationCode}.pdf";
        Storage::disk($disk)->put($path, $pdf);

        $document = TvlDocument::create([
            'viability_decision_id' => $decision->id,
            'disk' => $disk,
            'path' => $path,
            'verification_code' => $verificationCode,
            'generated_by_user_id' => $ator?->id,
            'generated_at' => $generatedAt,
        ]);

        $this->audit->log(
            logName: 'analise',
            event: 'tvl-emitido',
            description: "Emissão do TVL do processo #{$decision->viability_request_id} (documento {$verificationCode})",
            properties: [
                'viability_request_id' => $decision->viability_request_id,
                'viability_decision_id' => $decision->id,
                'tvl_document_id' => $document->id,
                'tvl_product_number' => $decision->tvl_product_number,
                'verification_code' => $verificationCode,
                'generated_by' => $ator?->id,
            ],
            subject: $decision,
            result: 'sucesso',
            rulesVersion: $this->rulesVersionRepresentativa($decision->rules_versions),
        );

        return $document;
    }

    /**
     * Monta os dados do documento a partir da decisão deferida e da ficha
     * finalizada do processo (condicionantes/parecer). Público para servir também
     * a pré-visualização do endpoint (10-15) — o mesmo contrato do PDF renderizado.
     *
     * @return array<string, mixed>
     */
    public function montarDados(ViabilityDecision $decision, string $verificationCode, ?DateTimeInterface $emitidoEm = null): array
    {
        $request = $decision->viabilityRequest;
        $ficha = $request?->currentAnalysisRecord;

        return [
            'tvl_product_number' => $decision->tvl_product_number,
            'verification_code' => $verificationCode,
            'url_verificacao' => url('/verificar-documento/'.$verificationCode),
            'emitido_em' => $emitidoEm ?? now(),
            'decidido_em' => $decision->decided_at,
            'protocolo' => $request?->protocol_number,
            'empresa' => $this->dadosEmpresa($request?->company),
            'imovel' => $this->dadosImovel($request),
            'atividades' => $this->atividadesDeferidas($decision),
            'condicionantes' => $this->condicionantesDaFicha($ficha),
            'parecer' => $ficha?->parecer,
            'fundamentacao' => $this->listaDeTexto($decision->fundamentacao),
            'assinatura' => $this->montarAssinatura(),
        ];
    }

    /**
     * Código de verificação único por emissão (ULID monotônico) — também
     * compõe o path determinístico do arquivo, de modo que cada reimpressão tem
     * o seu próprio documento.
     */
    private function gerarCodigoVerificacao(): string
    {
        return 'TVL-'.((string) Str::ulid());
    }

    /**
     * Atividades (CNAE) deferidas da decisão: numa decisão comum (motor
     * LOUOS/risco) todas as CNAEs do per_cnae estão deferidas (consolidação
     * RN-009). Já nos ramos de exclusão de atividade (RN-AA-03/04/05), o
     * per_cnae grava exatamente os CNAEs EXCLUÍDOS — dizer que uma exclusão
     * foi "deferida" sob o título "Atividades deferidas" afirmaria o oposto
     * do que aconteceu (I2 da revisão final). A chave `intencao` (gravada
     * pelo FluxoExpressoService) já distingue o caso: item marcado
     * `IntencaoAtividade::Excluir` some da lista; sem a chave (fluxo comum),
     * segue sendo tratado como deferido. Escopo deliberadamente limitado —
     * como o TVL deve APRESENTAR uma exclusão é pergunta em aberto para a
     * SEDUR, não decidida aqui; a seção pode ficar vazia e o Blade já lida
     * com isso.
     *
     * A descrição é enriquecida pelo cadastro de CNAE; o código formatado vem
     * do snapshot ou é derivado.
     *
     * @return list<array<string, mixed>>
     */
    private function atividadesDeferidas(ViabilityDecision $decision): array
    {
        $perCnae = is_array($decision->per_cnae) ? $decision->per_cnae : [];
        $perCnae = array_values(array_filter(
            $perCnae,
            static fn ($item): bool => ! (is_array($item) && ($item['intencao'] ?? null) === IntencaoAtividade::Excluir->value),
        ));

        $codigos = [];
        foreach ($perCnae as $item) {
            if (is_array($item) && isset($item['cnae'])) {
                $codigos[] = (string) $item['cnae'];
            }
        }

        $descricoes = $codigos === []
            ? collect()
            : Cnae::query()->whereIn('code', $codigos)->pluck('description', 'code');

        $atividades = [];
        foreach ($perCnae as $item) {
            if (! is_array($item) || ! isset($item['cnae'])) {
                continue;
            }

            $codigo = (string) $item['cnae'];

            $atividades[] = [
                'codigo' => $codigo,
                'codigo_formatado' => $item['cnae_formatado'] ?? $this->formatarCnae($codigo),
                'descricao' => $descricoes[$codigo] ?? null,
                'condicionantes' => $this->listaDeTexto($item['condicionantes'] ?? []),
            ];
        }

        return $atividades;
    }

    /**
     * Condicionantes gerais da ficha finalizada (10-09). Decisão do expresso (sem
     * ficha) → nenhuma condicionante na ficha (honesto).
     *
     * @return list<string>
     */
    private function condicionantesDaFicha(?AnalysisRecord $ficha): array
    {
        if ($ficha === null) {
            return [];
        }

        return $this->listaDeTexto($ficha->conditions);
    }
}
