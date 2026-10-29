<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A comparação de atividades da alteração passa a usar a conexão da aba
 * "Simplifica (legado)" da tela de APIs, a mesma da consulta de processos. O que
 * já estava gravado nos parâmetros antigos (URL e token) vai para os novos, sem
 * sobrescrever o que o gestor já tenha informado, e os antigos são removidos. O
 * interruptor `features.legado_simplifica` continua o mesmo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $antigaUrl = $this->linha('integrations.legado_simplifica.url');
        $antigoToken = $this->linha('integrations.legado_simplifica.token');

        if ($antigaUrl?->value !== null && $antigaUrl->value !== '') {
            $producao = $this->linha('integrations.legado_api.em_producao')?->value === '1';
            $destino = $producao ? 'integrations.legado_api.url_producao' : 'integrations.legado_api.url_homologacao';

            foreach ([$destino, 'integrations.legado_api.base_url'] as $chave) {
                $this->gravarSeVazio($chave, $antigaUrl->value);
            }
        }

        if ($antigoToken?->value !== null && $antigoToken->value !== '') {
            $this->gravarSeVazio('integrations.legado_api.token', $antigoToken->value);
        }

        foreach (['integrations.legado_simplifica.url', 'integrations.legado_simplifica.token'] as $chave) {
            DB::table('parameters')->where('key', $chave)->delete();
            Cache::forget("viabiliza.parameters.{$chave}");
        }
    }

    public function down(): void
    {
        // Os valores já foram para a aba unificada; nada a restaurar.
    }

    private function linha(string $chave): ?object
    {
        return DB::table('parameters')->where('key', $chave)->first();
    }

    /**
     * Grava o valor bruto (já criptografado, quando sensível) só se o destino existe e está vazio.
     */
    private function gravarSeVazio(string $chave, string $valor): void
    {
        $destino = $this->linha($chave);

        if ($destino === null || ($destino->value !== null && $destino->value !== '')) {
            return;
        }

        DB::table('parameters')->where('key', $chave)->update(['value' => $valor, 'updated_at' => now()]);
        Cache::forget("viabiliza.parameters.{$chave}");
    }
};
