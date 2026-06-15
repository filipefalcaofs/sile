<?php

namespace Database\Seeders;

use App\Models\Parameter;
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

            $parameter = Parameter::query()->updateOrCreate(['key' => $key], $meta);

            // `sensitive` fica fora do fillable (decisão 02-02): só o seed a
            // define, via forceFill, sempre ANTES de qualquer gravação de value.
            if ($parameter->sensitive !== $sensitive) {
                $parameter->forceFill(['sensitive' => $sensitive])->save();
            }
        }
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
            'ui.access_history.per_page' => [
                'group' => 'ui',
                'type' => 'integer',
                'default_value' => '15',
                'validation_rules' => ['required', 'integer', 'min:5', 'max:100'],
                'description' => 'Itens por página no histórico de acessos',
            ],
            'ui.cnaes.per_page' => [
                'group' => 'ui',
                'type' => 'integer',
                'default_value' => '15',
                'validation_rules' => ['required', 'integer', 'min:5', 'max:100'],
                'description' => 'Itens por página na listagem de CNAEs',
            ],
            'ui.users.per_page' => [
                'group' => 'ui',
                'type' => 'integer',
                'default_value' => '15',
                'validation_rules' => ['required', 'integer', 'min:5', 'max:100'],
                'description' => 'Itens por página na listagem de usuários',
            ],
            'ui.email_logs.per_page' => [
                'group' => 'ui',
                'type' => 'integer',
                'default_value' => '20',
                'validation_rules' => ['required', 'integer', 'min:5', 'max:100'],
                'description' => 'Itens por página no log de e-mails',
            ],
            'ui.dashboard.acessos_janela_dias' => [
                'group' => 'ui',
                'type' => 'integer',
                'default_value' => '7',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:90'],
                'description' => 'Janela em dias do indicador de acessos no painel de gestão',
            ],
            'features.procuracoes' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita o módulo de procurações no portal',
            ],
            'features.cnpj_lookup' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a consulta automática de dados por CNPJ no cadastro de empresas',
            ],
            'integrations.cnpj_lookup.base_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://brasilapi.com.br/api/cnpj/v1',
                'validation_rules' => ['required', 'url'],
                'requires_connection_test' => true,
                'description' => 'URL base do provedor de consulta de CNPJ (dados abertos da RFB)',
            ],
            'ui.companies.per_page' => [
                'group' => 'ui',
                'type' => 'integer',
                'default_value' => '15',
                'validation_rules' => ['required', 'integer', 'min:5', 'max:100'],
                'description' => 'Itens por página na listagem de empresas',
            ],
            'features.govbr_login' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita o login com a conta GOV.BR no portal do cidadão (exige credenciais configuradas)',
            ],
            'integrations.govbr.base_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://sso.staging.acesso.gov.br',
                'validation_rules' => ['required', 'url'],
                'requires_connection_test' => true,
                'description' => 'URL base do Login Único GOV.BR (staging: sso.staging.acesso.gov.br; produção: sso.acesso.gov.br)',
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
            'seguranca.throttle.cnpj_lookup.por_minuto' => [
                'group' => 'seguranca',
                'type' => 'integer',
                'default_value' => '30',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:300'],
                'description' => 'Limite de consultas de CNPJ por minuto por usuário no portal',
            ],
            'integrations.cnpj_lookup.retries' => [
                'group' => 'integracoes',
                'type' => 'integer',
                'default_value' => '2',
                'validation_rules' => ['required', 'integer', 'min:0', 'max:5'],
                'description' => 'Número de novas tentativas na consulta de CNPJ quando a integração falha',
            ],
            'integrations.cnpj_lookup.timeout' => [
                'group' => 'integracoes',
                'type' => 'integer',
                'default_value' => '8',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:30'],
                'description' => 'Tempo limite em segundos para a consulta de CNPJ',
            ],
            'integrations.cnpj_lookup.backoff_ms' => [
                'group' => 'integracoes',
                'type' => 'integer',
                'default_value' => '200',
                'validation_rules' => ['required', 'integer', 'min:0', 'max:5000'],
                'description' => 'Intervalo em milissegundos entre as tentativas de consulta de CNPJ',
            ],
            'features.geocoding' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a geocodificação de endereços (Nominatim/OSM)',
            ],
            'integrations.geocoding.base_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://nominatim.openstreetmap.org',
                'validation_rules' => ['required', 'url'],
                'requires_connection_test' => true,
                'description' => 'URL base do serviço de geocodificação (Nominatim; trocável por self-host sem deploy)',
            ],
            'seguranca.throttle.geocoding.por_minuto' => [
                'group' => 'seguranca',
                'type' => 'integer',
                'default_value' => '60',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:300'],
                'description' => 'Limite de geocodificações por minuto por usuário (Nominatim recomenda ~1 req/s)',
            ],
            'geo.validacao.sobreposicao_minima' => [
                'group' => 'geo',
                'type' => 'integer',
                'default_value' => '50',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:100'],
                'description' => 'Percentual mínimo de sobreposição entre o polígono informado e o lote oficial antes de alertar (HU-037)',
            ],
            'risco.mapa_encaminhamento' => [
                'group' => 'risco',
                'type' => 'json',
                'default_value' => '{"baixo_a":"expresso","baixo_b":"expresso","alto":"analise"}',
                'validation_rules' => ['required', 'json'],
                'description' => 'Mapa de encaminhamento por nível de risco da dimensão decisiva (expresso/analise) — Decreto 32.636/2020 não tem nível médio',
            ],
            'risco.dimensao_tvl' => [
                'group' => 'risco',
                'type' => 'string',
                'default_value' => 'municipal',
                'validation_rules' => ['required', 'in:municipal,sanitario'],
                'description' => 'Dimensão de risco que prevalece no encaminhamento/TVL (default municipal; confirmar com a SEDUR)',
            ],
            'louos.vagas.exigencia_por_grupo' => [
                'group' => 'louos',
                'type' => 'json',
                'default_value' => '{}',
                'validation_rules' => ['required', 'json'],
                'description' => 'Exigência de vagas (estacionamento, carga/descarga) por grupo de uso da LOUOS — base do veredito de conformidade (HU-042); vazio enquanto a SEDUR não parametriza (motor registra "não parametrizado", não bloqueia)',
            ],
            'louos.sandbox.amostra_padrao' => [
                'group' => 'louos',
                'type' => 'integer',
                'default_value' => '50',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:1000'],
                'description' => 'Tamanho padrão da amostra de cenários reprocessados na simulação de impacto de regra (HU-143)',
            ],
            'features.consulta_viabilidade' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a consulta prévia de viabilidade no portal do cidadão (endereço, CNAE e inscrição imobiliária)',
            ],
            'seguranca.throttle.consulta_viabilidade.por_minuto' => [
                'group' => 'seguranca',
                'type' => 'integer',
                'default_value' => '20',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:300'],
                'description' => 'Limite de consultas de viabilidade por minuto por usuário/IP no portal',
            ],
            'features.solicitacao_viabilidade' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a solicitação de viabilidade no portal do cidadão',
            ],
            'features.simulacao_solicitacao' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a simulação de viabilidade dentro do formulário de solicitação (HU-141; orientativa, não bloqueia)',
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
            'solicitacao.protocolo.padding' => [
                'group' => 'solicitacao',
                'type' => 'integer',
                'default_value' => '6',
                'validation_rules' => ['required', 'integer', 'min:4', 'max:10'],
                'description' => 'Quantidade de dígitos da sequência do número de protocolo',
            ],
            'solicitacao.consulta_publica.assinatura_ttl_dias' => [
                'group' => 'solicitacao',
                'type' => 'integer',
                'default_value' => '30',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:365'],
                'description' => 'Validade (dias) do link assinado de consulta pública de protocolo (HU-069)',
            ],
            'solicitacao.anexos.max_mb' => [
                'group' => 'solicitacao',
                'type' => 'integer',
                'default_value' => '10',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:100'],
                'description' => 'Tamanho máximo (MB) por documento anexado à solicitação',
            ],
            'solicitacao.anexos.mime_permitidos' => [
                'group' => 'solicitacao',
                'type' => 'json',
                'default_value' => '["application/pdf","image/jpeg","image/png"]',
                'validation_rules' => ['required', 'json'],
                'description' => 'Tipos de arquivo aceitos no anexo de documentos da solicitação',
            ],
            // O key usa o domínio técnico 'storage' mas pertence ao grupo de
            // negócio 'solicitacao' (key ≠ group é a norma — security.* fica em
            // 'seguranca'): é o disk dos documentos da solicitação.
            'storage.documentos.disk' => [
                'group' => 'solicitacao',
                'type' => 'string',
                'default_value' => 'local',
                'validation_rules' => ['required', 'string', 'max:50'],
                'description' => 'Disk de armazenamento dos documentos da solicitação (local, s3...) — nunca disk público',
            ],
            'solicitacao.area_poligono.tolerancia_percentual' => [
                'group' => 'solicitacao',
                'type' => 'integer',
                'default_value' => '10',
                'validation_rules' => ['required', 'integer', 'min:0', 'max:100'],
                'description' => 'Tolerância (%) entre a área declarada e a área do polígono antes de alertar (HU-063 RN-004; alerta, não bloqueia)',
            ],
            'solicitacao.prazo_estimado_dias' => [
                'group' => 'solicitacao',
                'type' => 'integer',
                'default_value' => '30',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:365'],
                'description' => 'Prazo estimado (dias) exibido na consulta de protocolo — ressalva de estimativa; medição real depende da HU-129/Fase 15',
            ],
            'solicitacao.atendimento.expiracao_minutos' => [
                'group' => 'solicitacao',
                'type' => 'integer',
                'default_value' => '30',
                'validation_rules' => ['required', 'integer', 'min:5', 'max:240'],
                'description' => 'Expiração (minutos) do vínculo de atendimento presencial assistido (HU-150)',
            ],
            'solicitacao.cancelamento.estados_cancelaveis' => [
                'group' => 'solicitacao',
                'type' => 'json',
                'default_value' => '["rascunho","protocolada"]',
                'validation_rules' => ['required', 'json'],
                'description' => 'Estados em que a solicitação pode ser cancelada pelo requerente (antes da decisão) — definição fina pendente SEDUR (HU-070)',
            ],
            'seguranca.throttle.consulta_protocolo.por_minuto' => [
                'group' => 'seguranca',
                'type' => 'integer',
                'default_value' => '30',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:300'],
                'description' => 'Limite de consultas públicas de protocolo por minuto por IP/assinatura (HU-069)',
            ],
            'features.fluxo_expresso' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita o deferimento/indeferimento automático (fluxo expresso); desligado, toda solicitação protocolada vai para análise técnica (degradação comunicada)',
            ],
            'features.notificacao_resultado_expresso' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a notificação por e-mail do resultado do fluxo expresso ao requerente (sem anexo de TVL; canais plenos no EP11)',
            ],
            'expresso.bap.prazo_horas' => [
                'group' => 'expresso',
                'type' => 'integer',
                'default_value' => '48',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:720'],
                'description' => 'Prazo (horas) sem BAP vinculado antes do indeferimento automático por prazo (HU-134; ativação bloqueada até o Regin — Fase 13)',
            ],
            'expresso.notificacao.assunto_deferida' => [
                'group' => 'expresso',
                'type' => 'string',
                'default_value' => 'Resultado da sua solicitação de viabilidade: deferida',
                'validation_rules' => ['required', 'string', 'max:150'],
                'description' => 'Assunto do e-mail de notificação de deferimento (HU-077 RN-005)',
            ],
            'expresso.notificacao.assunto_indeferida' => [
                'group' => 'expresso',
                'type' => 'string',
                'default_value' => 'Resultado da sua solicitação de viabilidade: indeferida',
                'validation_rules' => ['required', 'string', 'max:150'],
                'description' => 'Assunto do e-mail de notificação de indeferimento (HU-077 RN-005)',
            ],
            'expresso.tvl.prefixo' => [
                'group' => 'expresso',
                'type' => 'string',
                'default_value' => 'TVL',
                'validation_rules' => ['required', 'string', 'max:10'],
                'description' => 'Prefixo do número de produto TVL do deferimento ({prefixo}-AAAA-NNNNNN) — numeração oficial do SAPS a confirmar SEDUR',
            ],
            'expresso.tvl.padding' => [
                'group' => 'expresso',
                'type' => 'integer',
                'default_value' => '6',
                'validation_rules' => ['required', 'integer', 'min:4', 'max:10'],
                'description' => 'Quantidade de dígitos da sequência do número de produto TVL',
            ],
            'features.analise_tecnica' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita o módulo de análise técnica (fila, ficha, decisão humana, TVL PDF); desligado, degrada de forma comunicada',
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
                'description' => 'Percentual do prazo a partir do qual o semáforo do SLA fica amarelo (HU-144 RN-002)',
            ],
            'analise.pendencia.prazo_resposta_dias' => [
                'group' => 'analise',
                'type' => 'integer',
                'default_value' => '15',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:180'],
                'description' => 'Prazo (dias) para o requerente responder a uma pendência antes de expirar (HU-083/084)',
            ],
            'analise.precedentes.janela_meses' => [
                'group' => 'analise',
                'type' => 'integer',
                'default_value' => '12',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:120'],
                'description' => 'Janela temporal (meses) das estatísticas de precedentes do CNAE na zona (HU-142 RN-003)',
            ],
            'analise.precedentes.max_itens' => [
                'group' => 'analise',
                'type' => 'integer',
                'default_value' => '10',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:50'],
                'description' => 'Quantidade máxima de processos precedentes do imóvel exibidos na ficha (HU-142 RN-003)',
            ],
            'analise.tvl.disk' => [
                'group' => 'analise',
                'type' => 'string',
                'default_value' => 'local',
                'validation_rules' => ['required', 'string', 'max:50'],
                'description' => 'Disco de storage onde o TVL PDF é gravado — NUNCA público; download por URL assinada (HU-132)',
            ],
            'analise.tvl.assinatura.modo' => [
                'group' => 'analise',
                'type' => 'string',
                'default_value' => 'imagem',
                'validation_rules' => ['required', 'in:imagem,nenhuma'],
                'description' => 'Modo de assinatura do TVL PDF: imagem do diretor (legado) ou nenhuma; assinatura digital gov.br/ICP é gancho → SEDUR (HU-132 RN-005)',
            ],
            'analise.tvl.assinatura.imagem_path' => [
                'group' => 'analise',
                'type' => 'string',
                'default_value' => '',
                'validation_rules' => ['nullable', 'string', 'max:255'],
                'description' => "Caminho da imagem de assinatura usada no TVL PDF quando o modo é 'imagem'",
            ],
            'analise.tvl.download.ttl_minutos' => [
                'group' => 'analise',
                'type' => 'integer',
                'default_value' => '5',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:1440'],
                'description' => 'Validade (minutos) da URL temporária assinada de download do TVL PDF no backoffice',
            ],
            // Comunicação multicanal (EP11). Os toggles de canal nascem
            // administráveis: e-mail e in-app ligados; WhatsApp DESLIGADO
            // (provedor real bloqueado até a Fase 13 — degradação honesta).
            'features.notificacao_email' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita o canal de e-mail nas notificações de processo',
            ],
            'features.notificacao_in_app' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita o canal in-app (central de notificações) nas notificações de processo',
            ],
            'features.notificacao_whatsapp' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita o canal WhatsApp (provedor real bloqueado até a Fase 13; desligado degrada de forma comunicada)',
            ],
            'notificacoes.mapa_canais' => [
                'group' => 'notificacoes',
                'type' => 'json',
                'default_value' => '{"pendencia_aberta":["email","in_app"],"pendencia_respondida":["in_app"],"pendencia_expirada":["email","in_app"],"prazo_vencendo":["email","in_app"],"escalonamento_sla":["email","in_app"],"resultado":["email","in_app"]}',
                'validation_rules' => ['required', 'json'],
                'description' => 'Canais por tipo de notificação (intersecção com os toggles; WhatsApp fica fora por default)',
            ],
            'notificacoes.vencimento.antecedencia_dias' => [
                'group' => 'notificacoes',
                'type' => 'integer',
                'default_value' => '3',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:60'],
                'description' => 'Dias de antecedência do alerta de vencimento de prazo (HU-093)',
            ],
            'notificacoes.escalonamento.tratamento' => [
                'group' => 'notificacoes',
                'type' => 'json',
                'default_value' => '{"amarelo":"notificar_analista","vencido":"notificar_gestor"}',
                'validation_rules' => ['required', 'json'],
                'description' => 'Tratamento do escalonamento por SLA por faixa (HU-147; default só notifica, sem decisão automática)',
            ],
            'notificacoes.escalonamento.gestor_role' => [
                'group' => 'notificacoes',
                'type' => 'string',
                'default_value' => 'gestor',
                'validation_rules' => ['required', 'string', 'max:50'],
                'description' => "Papel destinatário do escalonamento de SLA (não há 'gestor do setor' no schema — pendência SEDUR; default role gestor)",
            ],
            'notificacoes.pendencia.assunto' => [
                'group' => 'notificacoes',
                'type' => 'string',
                'default_value' => 'Pendência na sua solicitação de viabilidade {protocolo}',
                'validation_rules' => ['required', 'string', 'max:150'],
                'description' => 'Assunto do aviso de pendência (HU-090; placeholders substituíveis)',
            ],
            'notificacoes.pendencia.corpo' => [
                'group' => 'notificacoes',
                'type' => 'string',
                'default_value' => 'Olá! Identificamos uma pendência na sua solicitação de viabilidade {protocolo}. Pendência: {pendencia}. Acesse o portal do SILE para responder dentro do prazo informado.',
                'validation_rules' => ['required', 'string', 'max:2000'],
                'description' => 'Corpo-template do aviso de pendência (HU-090; placeholders {protocolo}/{pendencia})',
            ],
            'integrations.whatsapp.base_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => '',
                'validation_rules' => ['nullable', 'url'],
                'requires_connection_test' => true,
                'description' => 'URL base da API comercial de WhatsApp (provedor real na Fase 13)',
            ],
            'integrations.whatsapp.token' => [
                'group' => 'integracoes',
                'type' => 'string',
                'sensitive' => true,
                'default_value' => null,
                'validation_rules' => ['nullable', 'string', 'max:255'],
                'description' => 'Token/credencial da API de WhatsApp (armazenado criptografado, nunca reexibido)',
            ],
        ];
    }
}
