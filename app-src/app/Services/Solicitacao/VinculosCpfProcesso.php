<?php

namespace App\Services\Solicitacao;

use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestCpf;
use Illuminate\Support\Facades\DB;

/**
 * Mantém os CPFs vinculados a um processo iguais à última informação
 * recebida: os sócios passam a ser exatamente a lista dada (ex-sócio perde o
 * acesso) e o vínculo de solicitante acompanha o solicitante_cpf do processo.
 */
class VinculosCpfProcesso
{
    /**
     * @param  list<string>  $socios
     */
    public function sincronizar(ViabilityRequest $processo, array $socios): void
    {
        DB::transaction(function () use ($processo, $socios): void {
            $this->sincronizarPapel($processo, ViabilityRequestCpf::PAPEL_SOCIO, $socios);

            $solicitante = preg_replace('/\D/', '', (string) $processo->solicitante_cpf) ?? '';
            $this->sincronizarPapel($processo, ViabilityRequestCpf::PAPEL_SOLICITANTE, strlen($solicitante) === 11 ? [$solicitante] : []);
        });
    }

    /**
     * @param  list<string>  $cpfs
     */
    private function sincronizarPapel(ViabilityRequest $processo, string $papel, array $cpfs): void
    {
        $processo->cpfsVinculados()
            ->where('papel', $papel)
            ->whereNotIn('cpf', $cpfs)
            ->delete();

        foreach (array_unique($cpfs) as $cpf) {
            $processo->cpfsVinculados()->firstOrCreate(['cpf' => $cpf, 'papel' => $papel]);
        }
    }
}
