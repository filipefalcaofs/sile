<?php

namespace Database\Seeders;

use App\Enums\ParameterGovernance;
use App\Models\Parameter;
use App\Models\ParameterProposal;
use Illuminate\Database\Seeder;

/**
 * Catálogo inicial de parâmetros administráveis (HU-014).
 *
 * Upsert por key APENAS dos metadados — `value` nunca entra no array de
 * update: re-seed em deploy preserva o que o administrador gravou (Pitfall 2).
 */
class ParameterSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::catalog() as $key => $meta) {
            $sensitive = (bool) ($meta['sensitive'] ?? false);
            unset($meta['sensitive']);

            $meta['governance'] = in_array($key, self::decisionKeys(), true)
                ? ParameterGovernance::Decision->value
                : ParameterGovernance::Operational->value;

            $parameter = Parameter::query()->updateOrCreate(['key' => $key], $meta);

            // `sensitive` fica fora do fillable (decisão 02-02): só o seed a
            // define, via forceFill, sempre ANTES de qualquer gravação de value.
            if ($parameter->sensitive !== $sensitive) {
                $parameter->forceFill(['sensitive' => $sensitive])->save();
            }
        }

        $retiredIds = Parameter::query()->whereIn('key', self::retiredKeys())->pluck('id');

        if ($retiredIds->isNotEmpty()) {
            ParameterProposal::query()->whereIn('parameter_id', $retiredIds)->delete();
            Parameter::query()->whereIn('id', $retiredIds)->delete();
        }
    }

    /**
     * Chaves que saíram do painel: texto, throttle, janela de tela e
     * interruptor de módulo que o produto não desliga. O re-seed apaga a
     * linha antiga para o valor ficar só no código (config/sile.php).
     *
     * @return list<string>
     */
    public static function retiredKeys(): array
    {
        return [
            'integrations.cnpj_lookup.base_url',
            'features.notificacao_whatsapp',
            'integrations.whatsapp.em_producao',
            'integrations.whatsapp.url_homologacao',
            'integrations.whatsapp.url_producao',
            'integrations.whatsapp.base_url',
            'integrations.whatsapp.token',
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
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function catalog(): array
    {
        return [

            'security.password.min_length' => [
                'group' => 'seguranca',
                'type' => 'integer',
                'default_value' => '8',
                'validation_rules' => ['required', 'integer', 'min:6', 'max:64'],
                'description' => 'Tamanho mínimo da senha dos usuários',
            ],
            'security.password.require_mixed_case' => [
                'group' => 'seguranca',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Exigir letras maiúsculas e minúsculas na senha',
            ],
            'security.password.require_numbers' => [
                'group' => 'seguranca',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Exigir números na senha',
            ],
            'security.password.require_symbols' => [
                'group' => 'seguranca',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Exigir símbolos na senha',
            ],
            'security.login.max_attempts' => [
                'group' => 'seguranca',
                'type' => 'integer',
                'default_value' => '5',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:20'],
                'description' => 'Tentativas de login antes do bloqueio temporário',
            ],
            'security.password_reset_expire' => [
                'group' => 'seguranca',
                'type' => 'integer',
                'default_value' => '60',
                'validation_rules' => ['required', 'integer', 'min:10', 'max:1440'],
                'description' => 'Validade em minutos do link de recuperação de senha',
            ],
            'features.cnpj_lookup' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a consulta automática de CNPJ no cadastro de empresas, na base da SEDUR',
            ],
            'integrations.cnpj_lookup.base_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://brasilapi.com.br/api/cnpj/v1',
                'validation_rules' => ['required', 'url'],
                'requires_connection_test' => true,
                'description' => 'URL base do provedor de consulta de CNPJ (dados abertos da RFB)',
            ],
            'features.govbr_login' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita o login com a conta GOV.BR no portal do cidadão (exige credenciais configuradas)',
            ],
            'features.ad_login' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a autenticação de servidores via AD (API institucional SEDUR) no console; desligar bloqueia o login das contas AD com aviso explícito',
            ],
            'integrations.govbr.em_producao' => [
                'group' => 'integracoes',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Usa a URL de produção do Login Único GOV.BR; desligado usa a URL de homologação',
            ],
            'integrations.govbr.url_homologacao' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://sso.staging.acesso.gov.br',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL do Login Único GOV.BR em homologação',
            ],
            'integrations.govbr.url_producao' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://sso.acesso.gov.br',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL do Login Único GOV.BR em produção',
            ],
            'integrations.govbr.base_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://sso.staging.acesso.gov.br',
                'validation_rules' => ['required', 'url'],
                'requires_connection_test' => true,
                'description' => 'URL ativa do Login Único GOV.BR (espelha a URL escolhida no formulário de APIs)',
            ],
            'integrations.govbr.client_id' => [
                'group' => 'integracoes',
                'type' => 'string',
                'sensitive' => true,
                'default_value' => null,
                'validation_rules' => ['required', 'string', 'max:255'],
                'description' => 'Client ID da credencial do Login Único GOV.BR (Termo de Adesão SGD)',
            ],
            'integrations.govbr.client_secret' => [
                'group' => 'integracoes',
                'type' => 'string',
                'sensitive' => true,
                'default_value' => null,
                'validation_rules' => ['required', 'string', 'max:255'],
                'description' => 'Client Secret da credencial do Login Único GOV.BR (armazenado criptografado)',
            ],
            'integrations.sedur_contato.em_producao' => [
                'group' => 'integracoes',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Usa a URL de produção da consulta de CPF e CNPJ da SEDUR; desligado usa a URL de homologação',
            ],
            'integrations.sedur_contato.url_homologacao' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://api.sedur.salvador.ba.gov.br/k8s/hml/ws-contato',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL base da consulta de CPF e CNPJ da SEDUR em homologação',
            ],
            'integrations.sedur_contato.url_producao' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-contato',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL base da consulta de CPF e CNPJ da SEDUR em produção',
            ],
            'integrations.sedur_contato.base_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-contato',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL ativa da consulta de CPF e CNPJ da SEDUR (espelha a URL escolhida no formulário de APIs)',
            ],
            'integrations.sedur_auth.em_producao' => [
                'group' => 'integracoes',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Usa a URL de produção da API de autenticação AD da SEDUR; desligado usa a URL de homologação',
            ],
            'integrations.sedur_auth.url_homologacao' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://api.sedur.salvador.ba.gov.br/k8s/hml',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL base da API de autenticação AD da SEDUR em homologação',
            ],
            'integrations.sedur_auth.url_producao' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://api.sedur.salvador.ba.gov.br/k8s/prd',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL base da API de autenticação AD da SEDUR em produção',
            ],
            'integrations.sedur_auth.base_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://api.sedur.salvador.ba.gov.br/k8s/prd',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL ativa da API de autenticação AD da SEDUR (espelha a URL escolhida no formulário de APIs)',
            ],
            'security.govbr.minimum_level' => [
                'group' => 'seguranca',
                'type' => 'string',
                'default_value' => 'bronze',
                'validation_rules' => ['required', 'in:bronze,prata,ouro'],
                'description' => 'Nível mínimo de confiabilidade da conta GOV.BR aceito no login (bronze, prata ou ouro)',
            ],
            'retencao.access_logs.dias' => [
                'group' => 'retencao',
                'type' => 'integer',
                'default_value' => '365',
                'validation_rules' => ['required', 'integer', 'min:30', 'max:3650'],
                'description' => 'Dias de retenção do histórico de acessos antes da limpeza automática (LGPD)',
            ],
            'features.geocoding' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a geocodificação de endereços (Nominatim/OSM)',
            ],
            'features.geoserver_zona' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Consulta zona urbanística no GeoServer SEDUR (WFS) quando a camada local está pendente',
            ],
            'features.geoserver_via_classe' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Consulta a classificação viária da LOUOS no GeoServer SEDUR (insumo do Quadro 11A). Desligado, as condições pela via ficam indisponíveis',
            ],
            'features.geoserver_enderecamento' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Consulta o polígono do endereçamento no GeoServer SEDUR e decide a zona pela maior porção do lote (art. 18, § 2º, da Lei 9.148/2016). Desligado, a zona segue pelo ponto da inscrição',
            ],
            'features.arcgis_semap' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Consulta o lote por inscrição imobiliária no cadastro municipal (ArcGIS, camada SEMAP — Inscrições Geo) quando o endereçamento não tem polígono para o endereço',
            ],
            'features.benchmark_redesim' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Importa o benchmark nacional de tempo de viabilidade a partir dos microdados da REDESIM. Desligado, as telas de benchmark mostram a lacuna da competência — nenhum valor é estimado',
            ],
            'integrations.redesim_estatistica.base_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://estatistica.redesim.gov.br/tempos-abertura-redesim',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL base da API de estatísticas da REDESIM, de onde vêm os microdados de tempo de viabilidade por UF e competência',
            ],
            'integrations.arcgis_semap.query_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://geo.salvador.ba.gov.br/arcgis/rest/services/MAPA_BASE/MAPA_BASE_SEFAZ/MapServer/18/query',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL de consulta da camada SEMAP (Inscrições Geo) do ArcGIS municipal',
            ],
            'features.geoserver_territorio' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Verifica regras territoriais por poligonal (ZEM, ZPAM, ABM, ZUE, loteamentos TAC) no GeoServer SEDUR (WFS). Desligado, a solicitação vai à análise com a indisponibilidade registrada',
            ],
            'features.cadastro_imobiliario' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Consulta o Cadastro Imobiliário (SEFAZ via BFF da SEDUR) pela inscrição do processo',
            ],
            'integrations.inscricao_imobiliaria.em_producao' => [
                'group' => 'integracoes',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Usa a URL de produção da API de inscrição imobiliária; desligado usa a URL de homologação',
            ],
            'integrations.inscricao_imobiliaria.url_homologacao' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://api.sedur.salvador.ba.gov.br/k8s/hml/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL base da API de inscrição imobiliária em homologação (editável; usada quando produção está desligada)',
            ],
            'integrations.inscricao_imobiliaria.url_producao' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL base da API de inscrição imobiliária em produção (editável; usada somente com o interruptor ligado)',
            ],
            'integrations.inscricao_imobiliaria.base_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://api.sedur.salvador.ba.gov.br/k8s/hml/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
                'validation_rules' => ['required', 'url'],
                'requires_connection_test' => true,
                'description' => 'URL ativa do BFF SEDUR de inscrição imobiliária (espelha a URL escolhida no painel dedicado)',
            ],
            'integrations.geoserver.em_producao' => [
                'group' => 'integracoes',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Usa a URL de produção do GeoServer; desligado usa a URL de homologação',
            ],
            'integrations.geoserver.url_homologacao' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://geoserver.sedur.salvador.ba.gov.br/geoserver',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL de homologação do GeoServer. O valor inicial repete o serviço conhecido; troque quando a SEDUR informar um host próprio',
            ],
            'integrations.geoserver.url_producao' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://geoserver.sedur.salvador.ba.gov.br/geoserver',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL de produção do GeoServer da SEDUR (WFS/WMS)',
            ],
            'integrations.geoserver.base_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://geoserver.sedur.salvador.ba.gov.br/geoserver',
                'validation_rules' => ['required', 'url'],
                'requires_connection_test' => true,
                'description' => 'URL ativa do GeoServer (espelha a URL escolhida no formulário de APIs)',
            ],
            'integrations.geocoding.em_producao' => [
                'group' => 'integracoes',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Usa a URL de produção da geocodificação; desligado usa a URL de homologação',
            ],
            'integrations.geocoding.url_homologacao' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://nominatim-hmlg.salvador.ba.gov.br',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL de homologação do Nominatim da Prefeitura',
            ],
            'integrations.geocoding.url_producao' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://nominatim-hmlg.salvador.ba.gov.br',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL de produção da geocodificação. O valor inicial repete a homologação conhecida; troque quando a SEDUR informar o host de produção',
            ],
            'integrations.geocoding.base_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://nominatim-hmlg.salvador.ba.gov.br',
                'validation_rules' => ['required', 'url'],
                'requires_connection_test' => true,
                'description' => 'URL ativa da geocodificação (espelha a URL escolhida no formulário de APIs)',
            ],
            'geo.validacao.sobreposicao_minima' => [
                'group' => 'geo',
                'type' => 'integer',
                'default_value' => '50',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:100'],
                'description' => 'Percentual mínimo de sobreposição entre o polígono informado e o lote oficial antes de alertar',
            ],
            'risco.mapa_encaminhamento' => [
                'group' => 'risco',
                'type' => 'json',
                'default_value' => '{"baixo_a":"expresso","baixo_b":"expresso","alto":"analise"}',
                'validation_rules' => ['required', 'json', 'json_fluxo_map'],
                'description' => 'Mapa de encaminhamento por nível de risco da dimensão decisiva (expresso/analise) — Decreto 32.636/2020 não tem nível médio',
            ],
            'risco.dimensao_tvl' => [
                'group' => 'risco',
                'type' => 'string',
                'default_value' => 'municipal',
                'validation_rules' => ['required', 'in:municipal,sanitario'],
                'description' => 'Dimensão de risco que prevalece no encaminhamento da viabilidade locacional (default municipal; confirmar com a SEDUR)',
            ],
            'louos.vagas.exigencia_por_grupo' => [
                'group' => 'louos',
                'type' => 'json',
                'default_value' => '{}',
                'validation_rules' => ['required', 'json'],
                'description' => 'Exigência de vagas (estacionamento, carga/descarga) por grupo de uso da LOUOS — base do veredito de conformidade; vazio enquanto a SEDUR não parametriza (motor registra "não parametrizado", não bloqueia)',
            ],
            'solicitacao.cnaes_complementares.max' => [
                'group' => 'solicitacao',
                'type' => 'integer',
                'default_value' => '99',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:99'],
                'description' => 'Máximo de CNAEs complementares por solicitação',
            ],
            'solicitacao.protocolo.prefixo' => [
                'group' => 'solicitacao',
                'type' => 'string',
                'default_value' => 'VIA',
                'validation_rules' => ['required', 'string', 'max:10'],
                'description' => 'Prefixo do número de protocolo da viabilidade ({prefixo}-AAAA-NNNNNN) — formato oficial a confirmar SEDUR',
            ],
            'solicitacao.anexos.max_mb' => [
                'group' => 'solicitacao',
                'type' => 'integer',
                'default_value' => '10',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:100'],
                'description' => 'Tamanho máximo (MB) por documento anexado à solicitação',
            ],
            'solicitacao.area_poligono.tolerancia_percentual' => [
                'group' => 'solicitacao',
                'type' => 'integer',
                'default_value' => '10',
                'validation_rules' => ['required', 'integer', 'min:0', 'max:100'],
                'description' => 'Tolerância (%) entre a área declarada e a área do polígono antes de alertar (alerta, não bloqueia)',
            ],
            'solicitacao.prazo_estimado_dias' => [
                'group' => 'solicitacao',
                'type' => 'integer',
                'default_value' => '30',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:365'],
                'description' => 'Prazo estimado (dias) exibido na consulta de protocolo — ressalva de estimativa; a medição real por etapa ainda será implementada',
            ],
            'solicitacao.atendimento.expiracao_minutos' => [
                'group' => 'solicitacao',
                'type' => 'integer',
                'default_value' => '30',
                'validation_rules' => ['required', 'integer', 'min:5', 'max:240'],
                'description' => 'Expiração (minutos) do vínculo de atendimento presencial assistido',
            ],
            'solicitacao.cancelamento.estados_cancelaveis' => [
                'group' => 'solicitacao',
                'type' => 'json',
                'default_value' => '["rascunho","protocolada"]',
                'validation_rules' => ['required', 'json'],
                'description' => 'Estados em que a solicitação pode ser cancelada pelo requerente (antes da decisão) — definição fina pendente SEDUR',
            ],
            'solicitacao.duplicidade.janela_dias' => [
                'group' => 'solicitacao',
                'type' => 'integer',
                'default_value' => '180',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:3650'],
                'description' => 'Janela (dias) da detecção de reincidência por CNPJ — alerta com link ao processo anterior, nunca bloqueia (direito de petição). Definição oficial pendente SEDUR',
            ],
            'renovacao.intervalo_meses' => [
                'group' => 'solicitacao',
                'type' => 'integer',
                'default_value' => '3',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:60'],
                'description' => 'Intervalo mínimo (meses) entre renovações da mesma viabilidade locacional. Dentro do intervalo o sistema recusa um novo protocolo',
            ],
            'features.fluxo_expresso' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita o deferimento/indeferimento automático (fluxo expresso); desligado, toda solicitação protocolada vai para análise técnica (degradação comunicada)',
            ],
            'features.simulacao_protocolo' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'SOMENTE HOMOLOGAÇÃO: processos do simulador REGIN com veredito pendente registram a viabilidade locacional como permitida. Desligado, seguem o fluxo normal (pendente → análise). NUNCA ligar em produção',
            ],
            'features.notificacao_resultado_expresso' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a notificação por e-mail do resultado do fluxo expresso ao requerente (sem anexo da viabilidade locacional; canais plenos no EP11)',
            ],
            'expresso.bap.prazo_horas' => [
                'group' => 'expresso',
                'type' => 'integer',
                'default_value' => '48',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:720'],
                'description' => 'Prazo (horas) sem BAP vinculado antes do indeferimento automático por prazo (ativação bloqueada até o Regin)',
            ],
            'expresso.tvl.prefixo' => [
                'group' => 'expresso',
                'type' => 'string',
                'default_value' => 'TVL',
                'validation_rules' => ['required', 'string', 'max:10'],
                'description' => 'Prefixo do número da viabilidade locacional ({prefixo}-AAAA-NNNNNN)',
            ],
            'analise.sla.distribuicao_dias' => [
                'group' => 'analise',
                'type' => 'integer',
                'default_value' => '2',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:60'],
                'description' => 'Prazo (dias) para distribuir/assumir um processo na caixa do setor antes do vencimento (etapa distribuição)',
            ],
            'analise.sla.analise_dias' => [
                'group' => 'analise',
                'type' => 'integer',
                'default_value' => '10',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:180'],
                'description' => 'Prazo (dias) para concluir a análise técnica de um processo assumido (etapa análise)',
            ],
            'analise.sla.semaforo.amarelo_percentual' => [
                'group' => 'analise',
                'type' => 'integer',
                'default_value' => '80',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:99'],
                'description' => 'Percentual do prazo a partir do qual o semáforo do SLA fica amarelo',
            ],
            'analise.setor_triagem_id' => [
                'group' => 'analise',
                'type' => 'integer',
                'default_value' => null,
                'validation_rules' => ['nullable', 'integer', 'exists:sectors,id'],
                'description' => 'Setor cuja caixa recebe os processos que o motor encaminha à análise técnica; vazio, o processo fica sem caixa até a atribuição manual (roteamento por CNAE/território é pendência SEDUR)',
            ],
            'tll.fator_multiplicador' => [
                'group' => 'tll',
                'type' => 'decimal',
                'default_value' => '1.0',
                'validation_rules' => ['required', 'numeric', 'min:0'],
                'description' => 'Fator multiplicador aplicado ao valor do DAM da TLL quando o CNAE de maior valor exige fator multiplicador (HU-071 RN-004); 1.0 é neutro',
            ],
            'analise.pendencia.prazo_resposta_dias' => [
                'group' => 'analise',
                'type' => 'integer',
                'default_value' => '15',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:180'],
                'description' => 'Prazo (dias) para o requerente responder a uma pendência antes de expirar',
            ],
            'analise.convite.prazo_resposta_horas_uteis' => [
                'group' => 'analise',
                'type' => 'integer',
                'default_value' => '48',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:2000'],
                'description' => 'Prazo (horas úteis) para o requerente responder a um convite antes de expirar (indefere automaticamente)',
            ],
            'analise.escritorio_virtual.cnae_gatilho_sede' => [
                'group' => 'analise',
                'type' => 'string',
                'default_value' => '8211-3/00',
                'validation_rules' => ['required', 'string', 'max:12'],
                'description' => 'CNAE que dispara a análise de sede de escritório virtual (Serviços combinados de escritório e apoio administrativo)',
            ],
            'analise.precedentes.janela_meses' => [
                'group' => 'analise',
                'type' => 'integer',
                'default_value' => '12',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:120'],
                'description' => 'Janela temporal (meses) das estatísticas de precedentes do CNAE na zona',
            ],
            'analise.tvl.assinatura.modo' => [
                'group' => 'analise',
                'type' => 'string',
                'default_value' => 'imagem',
                'validation_rules' => ['required', 'in:imagem,nenhuma'],
                'description' => 'Modo de assinatura do PDF da viabilidade locacional: imagem do diretor ou nenhuma; assinatura digital gov.br/ICP é gancho da SEDUR',
            ],
            // Comunicação multicanal (EP11). Os toggles de canal nascem
            // administráveis: e-mail e in-app ligados; WhatsApp DESLIGADO
            // (provedor real bloqueado até a Fase 13 — degradação honesta).
            // Constantes técnicas de storage (disk dos documentos e do TVL PDF),
            // padding de números de protocolo/TVL, timeout/retries/backoff da
            // consulta de CNPJ e paginações de listagem ficam em config/sile.php
            // — não são valores de negócio e não precisam ser administráveis.
            'features.notificacao_email' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita o canal oficial de e-mail nas notificações de processo',
            ],
            'features.notificacao_in_app' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita o canal in-app (central de notificações) nas notificações de processo',
            ],
            'notificacoes.mapa_canais' => [
                'group' => 'notificacoes',
                'type' => 'json',
                'default_value' => '{"pendencia_aberta":["email","in_app"],"pendencia_respondida":["in_app"],"pendencia_expirada":["email","in_app"],"prazo_vencendo":["email","in_app"],"escalonamento_sla":["email","in_app"],"resultado":["email","in_app"]}',
                'validation_rules' => ['required', 'json'],
                'description' => 'Canais por tipo de notificação. O canal oficial ao requerente é o e-mail',
            ],
            'notificacoes.vencimento.antecedencia_dias' => [
                'group' => 'notificacoes',
                'type' => 'integer',
                'default_value' => '3',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:60'],
                'description' => 'Dias de antecedência do alerta de vencimento de prazo',
            ],
            'notificacoes.escalonamento.tratamento' => [
                'group' => 'notificacoes',
                'type' => 'json',
                'default_value' => '{"amarelo":"notificar_analista","vencido":"notificar_gestor"}',
                'validation_rules' => ['required', 'json'],
                'description' => 'Tratamento do escalonamento por SLA por faixa (default só notifica, sem decisão automática)',
            ],
            'integrations.regin.em_producao' => [
                'group' => 'integracoes',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Usa a URL de produção da API REGIN/JUCEB; desligado usa a URL de homologação',
            ],
            'integrations.regin.url_homologacao' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'http://10.57.247.9:8080/api_integracao',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL base da API REGIN em homologação (editável; usada quando produção está desligada)',
            ],
            'integrations.regin.url_producao' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'http://regin.prefeitura.juceb.ba.gov.br:8080/api_integracao',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL base da API REGIN em produção (editável; usada somente com o interruptor ligado)',
            ],
            'integrations.regin.usuario' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'sedur_integracao',
                'validation_rules' => ['required', 'string', 'max:255'],
                'description' => 'Usuário da API de integração REGIN/JUCEB',
            ],
            'integrations.regin.senha' => [
                'group' => 'integracoes',
                'type' => 'string',
                'sensitive' => true,
                'default_value' => null,
                'validation_rules' => ['nullable', 'string', 'min:8', 'max:255'],
                'requires_connection_test' => true,
                'description' => 'Senha da API REGIN/JUCEB (armazenada criptografada, nunca reexibida)',
            ],
            'integrations.regin.cnpj_prefeitura' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => '13927801000149',
                'validation_rules' => ['required', 'string', 'size:14'],
                'description' => 'CNPJ da Prefeitura (somente dígitos) usado como origem/destino nos envelopes da API REGIN',
            ],
            // Auditoria e compliance (EP12). O toggle de detecção de abuso
            // (HU-149) nasce DESLIGADO (nunca pune — só registra alerta e
            // encaminha à malha fina). Paginação da trilha fica em config/sile.php.
            'features.deteccao_abuso' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a detecção de padrões de abuso/fraude; nasce desligada e NUNCA pune — apenas registra alerta e encaminha à malha fina',
            ],
            // Limiares dos detectores determinísticos (HU-149) — grupo 'abuso'.
            // Defaults conservadores; a SEDUR ajusta sem deploy (HU-014).
            'abuso.janela_dias' => [
                'group' => 'abuso',
                'type' => 'integer',
                'default_value' => '30',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:365'],
                'description' => 'Janela (dias) analisada pelos detectores de abuso a cada execução do scheduler',
            ],
            'abuso.volume_cnpj.limite' => [
                'group' => 'abuso',
                'type' => 'integer',
                'default_value' => '5',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:1000'],
                'description' => 'Quantidade de solicitações do mesmo CNPJ na janela antes de gerar alerta (detector de volume por CNPJ)',
            ],
            'abuso.volume_contador.limite' => [
                'group' => 'abuso',
                'type' => 'integer',
                'default_value' => '20',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:1000'],
                'description' => 'Quantidade de solicitações do mesmo contador na janela antes de gerar alerta (detector de volume por contador)',
            ],
            'abuso.escritorio_virtual.limite' => [
                'group' => 'abuso',
                'type' => 'integer',
                'default_value' => '3',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:1000'],
                'description' => 'Quantidade de empresas no mesmo endereço de escritório virtual na janela antes de gerar alerta',
            ],
            'abuso.severidade_malha_fina' => [
                'group' => 'abuso',
                'type' => 'string',
                'default_value' => 'alta',
                'validation_rules' => ['required', 'in:baixa,media,alta'],
                'description' => 'Severidade mínima do alerta que dispara encaminhamento à malha fina (alertas iguais ou acima são encaminhados; nunca indeferidos)',
            ],
            'relatorios.export.retencao_dias' => [
                'group' => 'relatorios',
                'type' => 'integer',
                'default_value' => '7',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:365'],
                'description' => 'Dias de retenção dos arquivos de exportação gerados em segundo plano',
            ],
            // Meta da taxa de resposta expressa: nasce SEM valor (pendência SEDUR).
            // default null + NÃO espelhada no config/sile.php → Settings::get cai
            // no fallback do call site ("meta não definida"), nunca inventa meta.
            'relatorios.expresso.meta_taxa' => [
                'group' => 'relatorios',
                'type' => 'string',
                'default_value' => null,
                'validation_rules' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'description' => 'Meta (%) da taxa de resposta expressa (vazio = meta não definida)',
            ],
            // Observatório de Saturação Locacional (Módulo 2): capacidade
            // recomendada por CNAE (por bairro) e os limiares que classificam um
            // par bairro×CNAE como "saturando"/"saturado". A capacidade é DADO de
            // negócio administrável (mapa código CNAE → limite); vazio = sem
            // capacidade definida (degrada honesto, sem inventar saturação).
            'relatorios.saturacao.capacidades' => [
                'group' => 'relatorios',
                'type' => 'json',
                'default_value' => '{}',
                'validation_rules' => ['required', 'json'],
                'description' => 'Capacidade recomendada de estabelecimentos por bairro, por CNAE (mapa código → limite). Vazio = sem capacidade definida',
            ],
            'relatorios.saturacao.alerta_percentual' => [
                'group' => 'relatorios',
                'type' => 'integer',
                'default_value' => '80',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:100'],
                'description' => 'Percentual da capacidade a partir do qual um par bairro×CNAE é sinalizado como "saturando"',
            ],
            'relatorios.saturacao.bloqueio_percentual' => [
                'group' => 'relatorios',
                'type' => 'integer',
                'default_value' => '100',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:200'],
                'description' => 'Percentual da capacidade a partir do qual um par bairro×CNAE é considerado "saturado"',
            ],
            // Auditoria Preditiva de Processos Expressos (Módulo 3 — IA/Malha
            // Fina). Toggle nasce DESLIGADO (governança DPO/LGPD art. 20 — nunca
            // pune, só alerta + malha fina); janela e limiar de score são valores
            // de negócio administráveis.
            'features.ia_auditoria_preditiva' => [
                'group' => 'ia',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a varredura de auditoria preditiva dos processos deferidos no fluxo expresso (Módulo 3); desligada por padrão (aval do DPO), nunca pune — só gera alerta e encaminha à malha fina',
            ],
            'ia.auditoria_preditiva.janela_dias' => [
                'group' => 'ia',
                'type' => 'integer',
                'default_value' => '30',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:365'],
                'description' => 'Janela (dias) analisada pela auditoria preditiva sobre os deferimentos do fluxo expresso',
            ],
            'ia.auditoria_preditiva.limiar_score' => [
                'group' => 'ia',
                'type' => 'integer',
                'default_value' => '70',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:100'],
                'description' => 'Score mínimo (0-100) para um processo deferido no expresso virar anomalia preditiva',
            ],
            'ia.auditoria_preditiva.pesos' => [
                'group' => 'ia',
                'type' => 'json',
                'default_value' => '{"volume":40,"inscricao":30,"prosseguiu":40}',
                'validation_rules' => ['required', 'json'],
                'description' => 'Pesos dos sinais determinísticos da auditoria preditiva (volume de deferimentos, inscrição repetida, prosseguiu apesar de alerta)',
            ],
            'ia.auditoria_preditiva.cortes_severidade' => [
                'group' => 'ia',
                'type' => 'json',
                'default_value' => '{"alta":80,"media":60}',
                'validation_rules' => ['required', 'json'],
                'description' => 'Cortes de severidade da auditoria preditiva: score ≥ alta encaminha à malha fina; ≥ média gera anomalia média',
            ],
            // Funções de IA (Fase 14 — HU-014 aplicada à IA). Toggle por função
            // (key features.ia_*, grupo de negócio 'ia' — key ≠ group é a norma).
            // TODOS nascem DESLIGADOS (0): a fundação multi-provider (Onda 0) não
            // liga função nenhuma — cada onda (1-3) liga a sua ao entregar.
            // Desligado degrada controlado (a função some/avisa), nunca falha
            // silenciosa. A saída de IA é sempre sugestão revisável, nunca decisão.
            'features.ia_ocr' => [
                'group' => 'ia',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a leitura/OCR de documentos por IA; desligado degrada de forma comunicada',
            ],
            'features.ia_classificacao' => [
                'group' => 'ia',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a classificação documental por IA; desligado degrada de forma comunicada',
            ],
            'features.ia_inconsistencias' => [
                'group' => 'ia',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a detecção de inconsistências documento × declaração por IA; desligado degrada de forma comunicada',
            ],
            'features.ia_resumo' => [
                'group' => 'ia',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita o resumo de solicitação/processo por IA; desligado degrada de forma comunicada',
            ],
            'features.ia_parecer' => [
                'group' => 'ia',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a sugestão de minuta de parecer por IA; sempre sugestão revisável, nunca decisão; desligado degrada de forma comunicada',
            ],
            'features.ia_justificativa' => [
                'group' => 'ia',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a sugestão de justificativa da atividade por IA; só após a decisão do analista na ficha; sempre sugestão revisável, nunca decisão; desligado degrada de forma comunicada',
            ],
            'features.ia_explicacao' => [
                'group' => 'ia',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a explicação do resultado ao cidadão por IA; desligado degrada de forma comunicada',
            ],
            'features.ia_assistente' => [
                'group' => 'ia',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita os assistentes conversacionais por IA do cidadão e do analista; desligado degrada de forma comunicada',
            ],
        ];
    }

    /**
     * Chaves cujo valor vigente só muda com quatro olhos. Qualquer outra
     * entrada do catálogo permanece operacional (PUT imediato).
     *
     * @return list<string>
     */
    public static function decisionKeys(): array
    {
        return [
            'risco.mapa_encaminhamento',
            'risco.dimensao_tvl',
            'analise.escritorio_virtual.cnae_gatilho_sede',
            'features.fluxo_expresso',
            'features.simulacao_protocolo',
            'renovacao.intervalo_meses',
        ];
    }
}
