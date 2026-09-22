<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Embeddings dos precedentes (HU-142) — infra de RAG da Onda 3 (Fase 14) que
     * sustenta a busca por similaridade dos assistentes (HU-120/121). Um vetor por
     * precedente (solicitação decidida), gerado do RESUMO ANONIMIZADO (sem PII).
     *
     * DRIVER-AWARE (espelha geo_features/viability_requests): a coluna `embedding`
     * é `vector(N)` no PostgreSQL (extensão pgvector — imagem postgis-pgvector do
     * projeto) com índice HNSW cosine para a busca DIY (o SimilaritySearch do
     * laravel/ai v0.8.1 é incompleto). Em SQLite (suíte) vira `json` SEM índice —
     * a busca vetorial degrada honesto (não finge ranking). A dimensão é
     * parametrizada (config sile.ai.embeddings.dimensions) — mesma fonte que o
     * PrecedentIndexer usa ao gerar o embedding, para a coluna e o vetor casarem.
     */
    public function up(): void
    {
        Schema::create('ai_precedent_embeddings', function (Blueprint $table) {
            $table->id();
            // Precedente = solicitação decidida (HU-142). 1:1 (unique) — uma
            // representação vetorial canônica por precedente; some com ele.
            $table->foreignId('viability_request_id')->constrained()->cascadeOnDelete();
            // Idempotência: sha256 do resumo anonimizado; inalterado ⇒ não reembedda.
            $table->string('content_hash', 64);
            // Modelo de embeddings que produziu o vetor (evidência/reprocesso).
            $table->string('model');
            $table->timestamps();

            $table->unique('viability_request_id');
        });

        $dimensions = (int) config('sile.ai.embeddings.dimensions', 1536);

        if (DB::getDriverName() === 'pgsql') {
            // Extensão pgvector + coluna vetorial + índice HNSW cosine (busca DIY).
            // Só no pgsql: em SQLite a extensão/índice quebrariam a migração da suíte.
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::statement("ALTER TABLE ai_precedent_embeddings ADD COLUMN embedding vector({$dimensions})");
            DB::statement('CREATE INDEX ai_precedent_embeddings_embedding_hnsw ON ai_precedent_embeddings USING hnsw (embedding vector_cosine_ops)');
        } else {
            // Fallback portável: o vetor fica em json (sem índice). A busca por
            // similaridade fica indisponível neste driver (degradação honesta).
            Schema::table('ai_precedent_embeddings', function (Blueprint $table) {
                $table->json('embedding')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Só a tabela é removida — a extensão `vector` pode ser usada por outras
        // estruturas e é destrutivo dropá-la num rollback.
        Schema::dropIfExists('ai_precedent_embeddings');
    }
};
