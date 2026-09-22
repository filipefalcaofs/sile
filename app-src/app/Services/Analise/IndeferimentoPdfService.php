<?php

namespace App\Services\Analise;

use App\Models\Cnae;
use App\Models\IndeferimentoDocument;
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
 * Documento de INDEFERIMENTO fundamentado em PDF no backoffice — espelho do
 * TvlPdfService (HU-132): emitido sob demanda APÓS a decisão indeferida (fluxo
 * expresso OU análise humana), para arquivo, atendimento presencial e
 * auditoria. A fonte é a MESMA ViabilityDecision (fonte única da decisão e da
 * explicabilidade), somada ao parecer/motivos da ficha finalizada quando
 * existem. Anti-fachada: dompdf renderiza um Blade REAL, grava no disco
 * parametrizado NUNCA público e cada emissão/reimpressão cria uma linha
 * auditada em indeferimento_documents (RN-002). Só decisões indeferidas; o
 * gate de perfil emitir-tvl e o download por URL assinada são do endpoint.
 */
class IndeferimentoPdfService
{
    use MontaDadosDocumentoDecisao;

    public function __construct(private readonly AuditService $audit) {}

    /**
     * Gera o PDF de uma decisão INDEFERIDA. Decisão não indeferida →
     * DomainException (o endpoint traduz para 422). Cada chamada é uma nova
     * linha com verification_code/arquivo próprios.
     */
    public function generate(ViabilityDecision $decision, ?User $ator = null): IndeferimentoDocument
    {
        if (! $decision->isIndeferida()) {
            throw new DomainException('O documento de indeferimento só pode ser emitido para decisões indeferidas.');
        }

        $disk = $this->resolverDisco();
        $verificationCode = $this->gerarCodigoVerificacao();
        $generatedAt = now();

        $pdf = Pdf::loadView('documentos.indeferimento', $this->montarDados($decision, $verificationCode, $generatedAt))
            ->setPaper(
                (string) config('sile.analise.tvl.paper', 'a4'),
                (string) config('sile.analise.tvl.orientation', 'portrait'),
            )
            ->output();

        $path = "indeferimento/{$decision->id}/{$verificationCode}.pdf";
        Storage::disk($disk)->put($path, $pdf);

        $document = IndeferimentoDocument::create([
            'viability_decision_id' => $decision->id,
            'disk' => $disk,
            'path' => $path,
            'verification_code' => $verificationCode,
            'generated_by_user_id' => $ator?->id,
            'generated_at' => $generatedAt,
        ]);

        $this->audit->log(
            logName: 'analise',
            event: 'indeferimento-emitido',
            description: "Emissão do documento de indeferimento do processo #{$decision->viability_request_id} (documento {$verificationCode})",
            properties: [
                'viability_request_id' => $decision->viability_request_id,
                'viability_decision_id' => $decision->id,
                'indeferimento_document_id' => $document->id,
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
     * Dados do documento a partir da decisão indeferida e da ficha finalizada
     * (parecer/motivos), quando existem — decisão do expresso (sem ficha) sai
     * só com a fundamentação da decisão (honesto).
     *
     * @return array<string, mixed>
     */
    public function montarDados(ViabilityDecision $decision, string $verificationCode, ?DateTimeInterface $emitidoEm = null): array
    {
        $request = $decision->viabilityRequest;
        $ficha = $request?->currentAnalysisRecord;

        return [
            'verification_code' => $verificationCode,
            'url_verificacao' => url('/verificar-documento/'.$verificationCode),
            'emitido_em' => $emitidoEm ?? now(),
            'decidido_em' => $decision->decided_at,
            'protocolo' => $request?->protocol_number,
            'empresa' => $this->dadosEmpresa($request?->company),
            'imovel' => $this->dadosImovel($request),
            'atividades' => $this->atividadesIndeferidas($decision),
            'fundamentacao' => $this->listaDeTexto($decision->fundamentacao),
            'motivos' => $this->listaDeTexto($ficha?->analysis_reasons),
            'parecer' => $ficha?->parecer,
            'assinatura' => $this->montarAssinatura(),
        ];
    }

    /**
     * Código de verificação único por emissão (ULID monotônico) — também compõe
     * o path determinístico do arquivo (cada reimpressão tem documento próprio).
     */
    private function gerarCodigoVerificacao(): string
    {
        return 'IND-'.((string) Str::ulid());
    }

    /**
     * Atividades (CNAE) da decisão indeferida, com a descrição enriquecida pelo
     * cadastro de CNAE e o veredito registrado no snapshot per_cnae.
     *
     * @return list<array<string, mixed>>
     */
    private function atividadesIndeferidas(ViabilityDecision $decision): array
    {
        $perCnae = is_array($decision->per_cnae) ? $decision->per_cnae : [];

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
                'veredito' => is_string($item['veredito'] ?? null) ? $item['veredito'] : null,
            ];
        }

        return $atividades;
    }
}
