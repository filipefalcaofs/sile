<?php

use App\Models\AnalysisRecord;
use App\Models\ViabilityRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU.183 — a ficha de análise passa a ser de quem a preencheu.
 *
 * Acrescenta a origem da cópia (based_on_record_id) e a ficha da decisão
 * (analysis_record_id), corrige os dados antigos que a trava barraria e cria a
 * trava de um rascunho por analista no processo. Nenhum conteúdo de ficha é
 * apagado ou alterado: rascunhos duplicados ficam "substituida", a cópia de
 * conclusão ainda não tocada volta a ser base sem dono.
 */
return new class extends Migration
{
    private const INDICE = 'analysis_records_um_rascunho_por_analista';

    public function up(): void
    {
        Schema::table('analysis_records', function (Blueprint $table) {
            $table->foreignId('based_on_record_id')->nullable()->after('analyst_user_id')
                ->constrained('analysis_records')->nullOnDelete();
        });

        Schema::table('viability_decisions', function (Blueprint $table) {
            $table->foreignId('analysis_record_id')->nullable()->after('viability_request_id')
                ->constrained('analysis_records')->nullOnDelete();
        });

        $this->substituirRascunhosDuplicados();
        $this->liberarCopiasDeConclusaoIntocadas();
        $this->vincularDecisoesAsFichas();

        DB::statement('create unique index '.self::INDICE.' on analysis_records (viability_request_id, analyst_user_id) '
            ."where status = 'rascunho' and analyst_user_id is not null");
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::INDICE);

        DB::table('analysis_records as copia')
            ->where('copia.status', 'rascunho')
            ->whereNull('copia.analyst_user_id')
            ->whereNotNull('copia.based_on_record_id')
            ->update(['analyst_user_id' => DB::raw('(select origem.analyst_user_id from analysis_records origem where origem.id = copia.based_on_record_id)')]);

        DB::table('analysis_records')->where('status', 'substituida')->update(['status' => 'rascunho']);

        Schema::table('viability_decisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('analysis_record_id');
        });

        Schema::table('analysis_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('based_on_record_id');
        });
    }

    /**
     * O mesmo analista com mais de um rascunho aberto no processo: fica aberto o
     * de maior revisão, os anteriores passam a "substituida".
     */
    private function substituirRascunhosDuplicados(): void
    {
        $grupos = DB::table('analysis_records')
            ->select('viability_request_id', 'analyst_user_id', DB::raw('max(revision) as ultima'))
            ->where('status', 'rascunho')
            ->whereNotNull('analyst_user_id')
            ->groupBy('viability_request_id', 'analyst_user_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($grupos as $grupo) {
            $antigas = DB::table('analysis_records')
                ->where('viability_request_id', $grupo->viability_request_id)
                ->where('analyst_user_id', $grupo->analyst_user_id)
                ->where('status', 'rascunho')
                ->where('revision', '<', $grupo->ultima)
                ->get(['id', 'revision']);

            foreach ($antigas as $antiga) {
                DB::table('analysis_records')->where('id', $antiga->id)->update(['status' => 'substituida']);

                $this->auditar($antiga->id, 'ficha-substituida',
                    "Revisão {$antiga->revision} da ficha do processo #{$grupo->viability_request_id} substituída pela revisão {$grupo->ultima} do mesmo analista",
                    ['viability_request_id' => $grupo->viability_request_id, 'revision' => $antiga->revision, 'substituida_por' => $grupo->ultima]);
            }
        }
    }

    /**
     * Processo em Para conclusão cuja última revisão é a cópia criada ao
     * encaminhar, ainda não tocada e registrada em nome de quem analisou: a cópia
     * volta a ser base sem dono, apontando para a ficha de origem.
     */
    private function liberarCopiasDeConclusaoIntocadas(): void
    {
        $processos = DB::table('viability_requests')->where('analysis_status', 'para_conclusao')->pluck('id');

        foreach ($processos as $processoId) {
            $ultima = DB::table('analysis_records')->where('viability_request_id', $processoId)->orderByDesc('revision')->first();

            if ($ultima === null || $ultima->status !== 'rascunho' || $ultima->analyst_user_id === null || $ultima->created_at != $ultima->updated_at) {
                continue;
            }

            $origem = DB::table('analysis_records')
                ->where('viability_request_id', $processoId)
                ->where('revision', $ultima->revision - 1)
                ->where('status', 'finalizada')
                ->where('analyst_user_id', $ultima->analyst_user_id)
                ->first();

            if ($origem === null) {
                continue;
            }

            DB::table('analysis_records')->where('id', $ultima->id)->update([
                'analyst_user_id' => null,
                'based_on_record_id' => $origem->id,
            ]);
        }
    }

    /**
     * A decisão aponta para a revisão registrada no evento "decisao" da
     * auditoria; sem o evento, para a única ficha finalizada antes da decisão.
     */
    private function vincularDecisoesAsFichas(): void
    {
        $morphProcesso = (new ViabilityRequest)->getMorphClass();

        DB::table('viability_decisions')
            ->whereNotNull('decided_by_user_id')
            ->whereNull('analysis_record_id')
            ->orderBy('id')
            ->each(function (object $decisao) use ($morphProcesso): void {
                $evento = DB::table('activity_log')
                    ->where('event', 'decisao')
                    ->where('subject_type', $morphProcesso)
                    ->where('subject_id', $decisao->viability_request_id)
                    ->orderByDesc('id')
                    ->value('properties');

                $propriedades = json_decode((string) $evento, true);
                $revisao = is_array($propriedades) ? ($propriedades['revision'] ?? null) : null;

                $fichaId = is_int($revisao)
                    ? DB::table('analysis_records')->where('viability_request_id', $decisao->viability_request_id)->where('revision', $revisao)->value('id')
                    : null;

                if ($fichaId === null) {
                    $candidatas = DB::table('analysis_records')
                        ->where('viability_request_id', $decisao->viability_request_id)
                        ->where('status', 'finalizada')
                        ->where('finalized_at', '<=', $decisao->decided_at)
                        ->pluck('id');

                    $fichaId = $candidatas->count() === 1 ? $candidatas->first() : null;
                }

                if ($fichaId !== null) {
                    DB::table('viability_decisions')->where('id', $decisao->id)->update(['analysis_record_id' => $fichaId]);
                }
            });
    }

    /**
     * @param  array<string, mixed>  $propriedades
     */
    private function auditar(int $fichaId, string $evento, string $descricao, array $propriedades): void
    {
        DB::table('activity_log')->insert([
            'log_name' => 'analise',
            'description' => $descricao,
            'subject_type' => (new AnalysisRecord)->getMorphClass(),
            'subject_id' => $fichaId,
            'event' => $evento,
            'result' => 'sucesso',
            'properties' => json_encode($propriedades),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
