<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Texto padrão da SEDUR (23/10/2026) para o indeferimento automático por
 * convite não atendido no prazo. Só troca o que ainda está com o texto
 * antigo de fábrica — edições feitas pela gestão são preservadas. Fresh
 * installs já nascem certos pelo seeder. Idempotente e reversível.
 */
return new class extends Migration
{
    private const CHAVE = 'justificativa.indeferimento.convite_expirado';

    private const ANTIGO = 'Processo indeferido pelo não atendimento ao convite no prazo estabelecido em consonância com Lei Federal nº 11.598/2007 e Resolução CGSIM nº 61/2020. Recomenda-se a abertura de novo processo contendo toda a documentação solicitada.';

    private const NOVO = "Processo indeferido pelo não atendimento ao convite no prazo estabelecido em consonância com Lei Federal nº 11.598/2007 e Resolução CGSIM nº 61/2020.\n\nRecomenda-se a abertura de novo processo contendo toda a documentação/ esclarecimento solicitado(s).";

    public function up(): void
    {
        $this->trocar(self::ANTIGO, self::NOVO);
    }

    public function down(): void
    {
        $this->trocar(self::NOVO, self::ANTIGO);
    }

    private function trocar(string $de, string $para): void
    {
        DB::table('decision_texts')
            ->where('key', self::CHAVE)
            ->where('template', $de)
            ->update(['template' => $para, 'updated_at' => now()]);
    }
};
