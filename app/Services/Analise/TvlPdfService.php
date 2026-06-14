<?php

namespace App\Services\Analise;

use App\Models\AnalysisRecord;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\TvlDocument;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

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
            throw new DomainException('O TVL só pode ser emitido para decisões deferidas (HU-132 FA-01).');
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
     * Disco parametrizado (analise.tvl.disk) — NUNCA público: o TVL é interno
     * (CA-02). Configuração 'public' é recusada (guarda anti-vazamento/LGPD).
     */
    private function resolverDisco(): string
    {
        $disk = (string) Settings::get('analise.tvl.disk', config('sile.analise.tvl.disk', 'local'));

        if ($disk === 'public') {
            throw new RuntimeException('O disco do TVL não pode ser público (analise.tvl.disk) — o documento é interno (HU-132 CA-02).');
        }

        return $disk;
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
     * @return array<string, string|null>
     */
    private function dadosEmpresa(?Company $company): array
    {
        return [
            'razao_social' => $company?->legal_name,
            'nome_fantasia' => $company?->trade_name,
            'cnpj' => $company?->formatted_cnpj,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dadosImovel(?ViabilityRequest $request): array
    {
        if ($request === null) {
            return [];
        }

        $endereco = implode(', ', array_filter([
            $request->address_street,
            $request->address_number,
            $request->address_complement,
        ]));

        return [
            'endereco' => $endereco,
            'bairro' => $request->address_neighborhood,
            'cep' => $request->address_zip,
            'area_m2' => $request->used_area_m2,
        ];
    }

    /**
     * Atividades (CNAE) deferidas da decisão: numa decisão deferida todas as CNAEs
     * do per_cnae estão deferidas (consolidação RN-009). A descrição é enriquecida
     * pelo cadastro de CNAE; o código formatado vem do snapshot ou é derivado.
     *
     * @return list<array<string, mixed>>
     */
    private function atividadesDeferidas(ViabilityDecision $decision): array
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

    /**
     * Assinatura parametrizável (RN-005): 'imagem' embute a firma digitalizada do
     * diretor (quando configurada e existente); 'nenhuma' não assina. gov.br/ICP é
     * gancho futuro (→ SEDUR) — por ora os modos efetivos são 'imagem'/'nenhuma'.
     *
     * @return array<string, mixed>|null
     */
    private function montarAssinatura(): ?array
    {
        $modo = (string) Settings::get(
            'analise.tvl.assinatura.modo',
            config('sile.analise.tvl.assinatura.modo', 'imagem'),
        );

        if ($modo === 'nenhuma') {
            return null;
        }

        $imagem = $this->resolverImagemAssinatura((string) Settings::get(
            'analise.tvl.assinatura.imagem_path',
            config('sile.analise.tvl.assinatura.imagem_path', ''),
        ));

        return ['modo' => $modo, 'imagem' => $imagem];
    }

    /**
     * Resolve a imagem da assinatura num data URI base64 (embutível no dompdf sem
     * acesso remoto). Caminho vazio/inexistente → null (sem firma forjada).
     */
    private function resolverImagemAssinatura(string $path): ?string
    {
        if (trim($path) === '') {
            return null;
        }

        foreach ([$path, base_path($path), storage_path($path), public_path($path)] as $candidato) {
            if ($candidato !== '' && is_file($candidato)) {
                $conteudo = @file_get_contents($candidato);

                if ($conteudo === false || $conteudo === '') {
                    return null;
                }

                return 'data:'.$this->detectarMimeImagem($candidato).';base64,'.base64_encode($conteudo);
            }
        }

        return null;
    }

    private function detectarMimeImagem(string $caminho): string
    {
        $mime = function_exists('mime_content_type') ? @mime_content_type($caminho) : false;

        if (is_string($mime) && str_starts_with($mime, 'image/')) {
            return $mime;
        }

        return match (strtolower((string) pathinfo($caminho, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
    }

    private function formatarCnae(string $code): string
    {
        return preg_replace('/^(\d{4})(\d)(\d{2})$/', '$1-$2/$3', $code) ?? $code;
    }

    /**
     * Normaliza um campo jsonb em uma lista de strings (aceita lista de strings
     * ou de objetos com 'descricao'), descartando vazios.
     *
     * @return list<string>
     */
    private function listaDeTexto(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        $itens = [];
        foreach ($valor as $entrada) {
            if (is_string($entrada) && trim($entrada) !== '') {
                $itens[] = $entrada;

                continue;
            }

            if (is_array($entrada) && isset($entrada['descricao']) && is_string($entrada['descricao']) && trim($entrada['descricao']) !== '') {
                $itens[] = $entrada['descricao'];
            }
        }

        return $itens;
    }

    /**
     * Versão de regra representativa para a coluna rules_version da auditoria
     * (RN-004 — o documento reflete a versão das regras da decisão). Espelha o
     * helper do AnalysisRecordService: primeira versão real do mapa aninhado.
     *
     * @param  array<string, mixed>|null  $rulesVersions
     */
    private function rulesVersionRepresentativa(?array $rulesVersions): ?string
    {
        foreach ($rulesVersions ?? [] as $grupo) {
            if (is_array($grupo)) {
                foreach ($grupo as $versao) {
                    if (is_string($versao) && $versao !== '') {
                        return $versao;
                    }
                }

                continue;
            }

            if (is_string($grupo) && $grupo !== '') {
                return $grupo;
            }
        }

        return null;
    }
}
