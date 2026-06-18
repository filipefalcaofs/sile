<?php

namespace App\Services\Ai\Embeddings;

use App\Models\AiPrecedentEmbedding;
use App\Models\Cnae;
use App\Models\ViabilityRequest;
use App\Services\Ai\AiFeatureGate;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;

/**
 * Indexador de precedentes (HU-142) — infra de RAG da Onda 3 (Fase 14). Gera o
 * embedding do RESUMO ANONIMIZADO de cada precedente (solicitação decidida) e
 * persiste o vetor para a busca por similaridade (PrecedentVectorSearch) que
 * sustenta o assistente do analista (HU-121).
 *
 * Anti-fachada + LGPD:
 *  - SÓ roda com um provedor de embeddings ATIVO (AiFeatureGate). Sem provedor,
 *    devolve `unavailable` — não chama, não persiste, não simula.
 *  - O conteúdo embeddado é MINIMIZADO: atividade (CNAE), bairro, desfecho, zona
 *    e fundamentação legal — NUNCA nome do requerente, logradouro, CPF ou CNPJ.
 *  - IDEMPOTENTE por content_hash: resumo inalterado ⇒ não reembedda (custo/quota).
 *
 * A escrita do vetor é driver-aware: `?::vector` no PostgreSQL (pgvector), json
 * no SQLite — espelha o write geoespacial da Fase 8.
 */
class PrecedentIndexer
{
    public function __construct(private readonly AiFeatureGate $gate) {}

    public function index(ViabilityRequest $precedent): PrecedentIndexResult
    {
        if (! $this->gate->embeddingsAvailable()) {
            return PrecedentIndexResult::unavailable();
        }

        $content = $this->anonymizedSummary($precedent);
        $hash = hash('sha256', $content);

        $existing = AiPrecedentEmbedding::query()
            ->where('viability_request_id', $precedent->id)
            ->first();

        // Idempotência: precedente inalterado não volta ao provedor.
        if ($existing !== null && $existing->content_hash === $hash) {
            return PrecedentIndexResult::skipped($existing);
        }

        $response = Embeddings::for([$content])
            ->dimensions($this->dimensions())
            ->generate();

        $embedding = $this->persist($precedent, $hash, $response->meta->model, $response->first());

        return PrecedentIndexResult::embedded($embedding);
    }

    /**
     * Resumo PII-free do precedente para o embedding. Inclui só substância
     * locacional/legal; exclui nome, logradouro, CPF e CNPJ (LGPD — minimização).
     */
    public function anonymizedSummary(ViabilityRequest $precedent): string
    {
        $linhas = [];

        if (is_string($precedent->protocol_number) && $precedent->protocol_number !== '') {
            $linhas[] = "Precedente {$precedent->protocol_number}.";
        }

        $servico = $precedent->serviceType?->name;
        if (is_string($servico) && $servico !== '') {
            $linhas[] = "Tipo de serviço: {$servico}.";
        }

        $desfecho = $precedent->decision?->outcome?->label();
        if (is_string($desfecho) && $desfecho !== '') {
            $linhas[] = "Desfecho: {$desfecho}.";
        }

        $atividades = $this->activities($precedent);
        if ($atividades !== []) {
            $linhas[] = 'Atividades: '.implode('; ', $atividades).'.';
        }

        if (is_string($precedent->address_neighborhood) && $precedent->address_neighborhood !== '') {
            $linhas[] = "Bairro: {$precedent->address_neighborhood}.";
        }

        $zona = $this->zoneName($precedent);
        if ($zona !== null) {
            $linhas[] = "Zona urbanística: {$zona}.";
        }

        $fundamentacao = $precedent->decision?->fundamentacao;
        if (is_array($fundamentacao) && $fundamentacao !== []) {
            $linhas[] = 'Fundamentação: '.implode('; ', array_map('strval', $fundamentacao)).'.';
        }

        return trim(implode(' ', $linhas));
    }

    /**
     * Atividades (CNAEs) do precedente — principal primeiro, formato + descrição.
     *
     * @return list<string>
     */
    private function activities(ViabilityRequest $precedent): array
    {
        return $precedent->cnaes()
            ->orderByDesc('viability_request_cnaes.is_primary')
            ->get()
            ->map(fn (Cnae $cnae): string => trim("{$cnae->formatted_code} — {$cnae->description}", ' —'))
            ->filter(fn (string $texto): bool => $texto !== '')
            ->values()
            ->all();
    }

    /**
     * Nome da zona urbanística da ficha vigente, SÓ quando identificada (mesmo
     * caminho do PrecedentService). Sem zona identificada, devolve null e o
     * resumo a omite — nunca inventa zona.
     */
    private function zoneName(ViabilityRequest $precedent): ?string
    {
        $snapshot = $precedent->currentAnalysisRecord()->first()?->engine_snapshot;
        $porCnae = is_array($snapshot) ? ($snapshot['por_cnae'] ?? null) : null;

        if (! is_array($porCnae) || $porCnae === []) {
            return null;
        }

        $item = $porCnae[0];
        foreach ($porCnae as $candidato) {
            if (is_array($candidato) && ($candidato['is_primary'] ?? false) === true) {
                $item = $candidato;
                break;
            }
        }

        $zona = is_array($item) ? ($item['consulta']['territorio']['zona'] ?? null) : null;

        if (! is_array($zona) || ($zona['status'] ?? null) !== 'identificado') {
            return null;
        }

        $nome = $zona['nome'] ?? null;

        return is_string($nome) && $nome !== '' ? $nome : null;
    }

    private function persist(ViabilityRequest $precedent, string $hash, string $model, array $vector): AiPrecedentEmbedding
    {
        $embedding = AiPrecedentEmbedding::query()->updateOrCreate(
            ['viability_request_id' => $precedent->id],
            ['content_hash' => $hash, 'model' => $model],
        );

        $this->writeVector($embedding, $vector);

        return $embedding;
    }

    /**
     * @param  array<float>  $vector
     */
    private function writeVector(AiPrecedentEmbedding $embedding, array $vector): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            // ::vector explícito (o tipo não é nativo do Eloquent) — valores são
            // floats que o serviço controla, sem entrada do usuário no literal.
            DB::update(
                'UPDATE ai_precedent_embeddings SET embedding = ?::vector WHERE id = ?',
                [VectorLiteral::format($vector), $embedding->id],
            );

            return;
        }

        DB::table('ai_precedent_embeddings')
            ->where('id', $embedding->id)
            ->update(['embedding' => json_encode($vector)]);
    }

    private function dimensions(): int
    {
        return (int) config('sile.ai.embeddings.dimensions', 1536);
    }
}
