<?php

use App\Models\ViabilityRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca o encaminhamento devolvido à Caixa do Setor, para o processo sair da
     * Caixa de Vistoria mesmo quando o setor não muda na devolução. Os já
     * devolvidos são marcados pelo evento "vistoria-devolver" da auditoria.
     */
    public function up(): void
    {
        Schema::table('inspection_referrals', function (Blueprint $table) {
            $table->timestamp('devolvido_em')->nullable()->after('motivo');
        });

        $morphProcesso = (new ViabilityRequest)->getMorphClass();

        DB::table('inspection_referrals as r')
            ->whereRaw('r.id = (select max(x.id) from inspection_referrals x where x.viability_request_id = r.viability_request_id)')
            ->orderBy('r.id')
            ->select('r.id', 'r.viability_request_id', 'r.created_at')
            ->each(function (object $referral) use ($morphProcesso): void {
                $devolucao = DB::table('activity_log')
                    ->where('event', 'vistoria-devolver')
                    ->where('subject_type', $morphProcesso)
                    ->where('subject_id', $referral->viability_request_id)
                    ->where('created_at', '>=', $referral->created_at)
                    ->orderByDesc('id')
                    ->value('created_at');

                if ($devolucao !== null) {
                    DB::table('inspection_referrals')->where('id', $referral->id)->update(['devolvido_em' => $devolucao]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('inspection_referrals', function (Blueprint $table) {
            $table->dropColumn('devolvido_em');
        });
    }
};
