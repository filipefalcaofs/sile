<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Padroniza o texto das descrições dos parâmetros: verbos no mesmo tempo,
 * sem travessão, sem código interno de história ou épico e sem termo em inglês.
 * O OKD só roda `migrate` ao subir, sem `db:seed`, então o ParameterSeeder
 * sozinho não chega à tela de Parâmetros.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->atualizar($this->descricoesNovas());
    }

    public function down(): void
    {
        $this->atualizar($this->descricoesAntigas());
    }

    /**
     * @param  array<string, string>  $descricoes
     */
    private function atualizar(array $descricoes): void
    {
        if (! Schema::hasTable('parameters')) {
            return;
        }

        foreach ($descricoes as $chave => $descricao) {
            DB::table('parameters')->where('key', $chave)->update(['description' => $descricao]);
            Cache::forget("viabiliza.parameters.{$chave}");
        }
    }

    /**
     * @return array<string, string>
     */
    private function descricoesNovas(): array
    {
        return [
            'features.arcgis_semap' => 'Consulta o lote por inscrição imobiliária no cadastro municipal (ArcGIS, camada SEMAP Inscrições Geo) quando o endereçamento não tem polígono para o endereço',
            'features.benchmark_redesim' => 'Importa o benchmark nacional de tempo de viabilidade a partir dos microdados da REDESIM. Desligado, as telas de benchmark mostram a lacuna da competência; nenhum valor é estimado',
            'risco.mapa_encaminhamento' => 'Mapa de encaminhamento por nível de risco da dimensão decisiva (expresso ou análise). O Decreto 32.636/2020 não tem nível médio',
            'risco.dimensao_tvl' => 'Dimensão de risco que prevalece no encaminhamento da viabilidade locacional (padrão municipal; confirmar com a SEDUR)',
            'louos.vagas.exigencia_por_grupo' => 'Exigência de vagas (estacionamento, carga/descarga) por grupo de uso da LOUOS, base do veredito de conformidade. Vazio enquanto a SEDUR não parametriza (o motor registra "não parametrizado" e não bloqueia)',
            'solicitacao.protocolo.prefixo' => 'Prefixo do número de protocolo da viabilidade ({prefixo}-AAAA-NNNNNN). Formato oficial a confirmar com a SEDUR',
            'solicitacao.prazo_estimado_dias' => 'Prazo estimado (dias) exibido na consulta de protocolo. É uma estimativa; a medição real por etapa ainda será implementada',
            'solicitacao.cancelamento.estados_cancelaveis' => 'Estados em que a solicitação pode ser cancelada pelo requerente (antes da decisão). Definição fina pendente da SEDUR',
            'solicitacao.duplicidade.janela_dias' => 'Janela (dias) da detecção de reincidência por CNPJ. Gera alerta com link ao processo anterior e nunca bloqueia (direito de petição). Definição oficial pendente da SEDUR',
            'features.notificacao_resultado_expresso' => 'Habilita a notificação por e-mail do resultado do fluxo expresso ao requerente (sem anexo da viabilidade locacional; demais canais em etapa futura)',
            'expresso.bap.prazo_horas' => 'Prazo (horas) sem BAP vinculado antes do indeferimento automático por prazo (ativação bloqueada até a integração com o REGIN)',
            'tll.fator_multiplicador' => 'Fator multiplicador aplicado ao valor do DAM da TLL quando o CNAE de maior valor exige fator multiplicador; 1.0 é neutro',
            'analise.tvl.assinatura.modo' => 'Modo de assinatura do PDF da viabilidade locacional: imagem do diretor ou nenhuma; assinatura digital gov.br/ICP depende da SEDUR',
            'notificacoes.escalonamento.tratamento' => 'Tratamento do escalonamento por SLA por faixa (padrão: só notifica, sem decisão automática)',
            'features.deteccao_abuso' => 'Habilita a detecção de padrões de abuso/fraude; nasce desligada e nunca pune; apenas registra alerta e encaminha à malha fina',
            'abuso.janela_dias' => 'Janela (dias) analisada pelos detectores de abuso a cada execução da rotina agendada',
            'features.ia_auditoria_preditiva' => 'Habilita a varredura de auditoria preditiva dos processos deferidos no fluxo expresso; desligada por padrão (aval do DPO) e nunca pune; só gera alerta e encaminha à malha fina',
            'security.password.require_mixed_case' => 'Exige letras maiúsculas e minúsculas na senha',
            'security.password.require_numbers' => 'Exige números na senha',
            'security.password.require_symbols' => 'Exige símbolos na senha',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function descricoesAntigas(): array
    {
        return [
            'features.arcgis_semap' => 'Consulta o lote por inscrição imobiliária no cadastro municipal (ArcGIS, camada SEMAP — Inscrições Geo) quando o endereçamento não tem polígono para o endereço',
            'features.benchmark_redesim' => 'Importa o benchmark nacional de tempo de viabilidade a partir dos microdados da REDESIM. Desligado, as telas de benchmark mostram a lacuna da competência — nenhum valor é estimado',
            'risco.mapa_encaminhamento' => 'Mapa de encaminhamento por nível de risco da dimensão decisiva (expresso/analise) — Decreto 32.636/2020 não tem nível médio',
            'risco.dimensao_tvl' => 'Dimensão de risco que prevalece no encaminhamento da viabilidade locacional (default municipal; confirmar com a SEDUR)',
            'louos.vagas.exigencia_por_grupo' => 'Exigência de vagas (estacionamento, carga/descarga) por grupo de uso da LOUOS — base do veredito de conformidade; vazio enquanto a SEDUR não parametriza (motor registra "não parametrizado", não bloqueia)',
            'solicitacao.protocolo.prefixo' => 'Prefixo do número de protocolo da viabilidade ({prefixo}-AAAA-NNNNNN) — formato oficial a confirmar SEDUR',
            'solicitacao.prazo_estimado_dias' => 'Prazo estimado (dias) exibido na consulta de protocolo — ressalva de estimativa; a medição real por etapa ainda será implementada',
            'solicitacao.cancelamento.estados_cancelaveis' => 'Estados em que a solicitação pode ser cancelada pelo requerente (antes da decisão) — definição fina pendente SEDUR',
            'solicitacao.duplicidade.janela_dias' => 'Janela (dias) da detecção de reincidência por CNPJ — alerta com link ao processo anterior, nunca bloqueia (direito de petição). Definição oficial pendente SEDUR',
            'features.notificacao_resultado_expresso' => 'Habilita a notificação por e-mail do resultado do fluxo expresso ao requerente (sem anexo da viabilidade locacional; canais plenos no EP11)',
            'expresso.bap.prazo_horas' => 'Prazo (horas) sem BAP vinculado antes do indeferimento automático por prazo (ativação bloqueada até o Regin)',
            'tll.fator_multiplicador' => 'Fator multiplicador aplicado ao valor do DAM da TLL quando o CNAE de maior valor exige fator multiplicador (HU-145); 1.0 é neutro',
            'analise.tvl.assinatura.modo' => 'Modo de assinatura do PDF da viabilidade locacional: imagem do diretor ou nenhuma; assinatura digital gov.br/ICP é gancho da SEDUR',
            'notificacoes.escalonamento.tratamento' => 'Tratamento do escalonamento por SLA por faixa (default só notifica, sem decisão automática)',
            'features.deteccao_abuso' => 'Habilita a detecção de padrões de abuso/fraude; nasce desligada e NUNCA pune — apenas registra alerta e encaminha à malha fina',
            'abuso.janela_dias' => 'Janela (dias) analisada pelos detectores de abuso a cada execução do scheduler',
            'features.ia_auditoria_preditiva' => 'Habilita a varredura de auditoria preditiva dos processos deferidos no fluxo expresso (Módulo 3); desligada por padrão (aval do DPO), nunca pune — só gera alerta e encaminha à malha fina',
            'security.password.require_mixed_case' => 'Exigir letras maiúsculas e minúsculas na senha',
            'security.password.require_numbers' => 'Exigir números na senha',
            'security.password.require_symbols' => 'Exigir símbolos na senha',
        ];
    }
};
