<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('parameters')
            ->whereIn('key', [
                'ui.dashboard.acessos_janela_dias',
                'features.procuracoes',
                'seguranca.throttle.cnpj_lookup.por_minuto',
                'seguranca.throttle.geocoding.por_minuto',
                'geo.zona.atributos_nome',
                'louos.sandbox.amostra_padrao',
                'features.consulta_viabilidade',
                'seguranca.throttle.consulta_viabilidade.por_minuto',
                'features.solicitacao_viabilidade',
                'features.simulacao_solicitacao',
                'solicitacao.consulta_publica.assinatura_ttl_dias',
                'solicitacao.anexos.mime_permitidos',
                'seguranca.throttle.consulta_protocolo.por_minuto',
                'seguranca.throttle.regin_recebe.por_minuto',
                'expresso.notificacao.assunto_deferida',
                'expresso.notificacao.assunto_indeferida',
                'features.analise_tecnica',
                'analise.escritorio_virtual.condicionante_sede',
                'analise.escritorio_virtual.mensagem_bloqueio_abrigado',
                'analise.escritorio_virtual.mensagem_recusa_abrigo',
                'analise.escritorio_virtual.mensagem_cnae_sede_em_abrigado',
                'analise.escritorio_virtual.mensagem_sede_duplicada',
                'analise.escritorio_virtual.mensagem_cnae_fora_anexo_a',
                'analise.escritorio_virtual.flag_analise_sede',
                'analise.escritorio_virtual.mensagem_confirma_perda_sede',
                'analise.escritorio_virtual.pergunta_geral',
                'analise.escritorio_virtual.pergunta_vinculada',
                'analise.precedentes.max_itens',
                'features.enviar_tvl_analise',
                'analise.enviar_analise.mensagem_nao_encontrado',
                'analise.enviar_analise.mensagem_confirmacao',
                'notificacoes.escalonamento.gestor_role',
                'notificacoes.pendencia.assunto',
                'notificacoes.pendencia.corpo',
                'relatorios.export.assincrono_limiar_linhas',
                'relatorios.export.formatos_habilitados',
                'relatorios.expresso.janela_dias',
            ])
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('parameter_proposals')->whereIn('parameter_id', $ids)->delete();
        DB::table('parameters')->whereIn('id', $ids)->delete();
    }

    public function down(): void
    {
        // Os valores passaram a viver em config/sile.php. Recriar a linha
        // no catálogo exigiria o metadado antigo; o re-seed não as recoloca.
    }
};
