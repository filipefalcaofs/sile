<?php

namespace Tests\Feature\Seeders;

use App\Models\Parameter;
use Database\Seeders\ParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParameterSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_cria_catalogo_completo(): void
    {
        $this->seed(ParameterSeeder::class);

        $this->assertSame(88, Parameter::query()->count());
        $this->assertSame(
            ['abuso', 'analise', 'expresso', 'features', 'geo', 'ia', 'integracoes', 'louos', 'notificacoes', 'relatorios', 'retencao', 'risco', 'seguranca', 'solicitacao', 'ui'],
            Parameter::query()->distinct()->orderBy('group')->pluck('group')->all(),
        );

        $maxAttempts = Parameter::query()->where('key', 'security.login.max_attempts')->first();

        $this->assertNotNull($maxAttempts);
        $this->assertSame('integer', $maxAttempts->type);
        $this->assertSame('5', $maxAttempts->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:20'], $maxAttempts->validation_rules);
        $this->assertNull($maxAttempts->value);
    }

    public function test_seeder_registra_parametros_do_cadastro_empresarial(): void
    {
        $this->seed(ParameterSeeder::class);

        $cnpjLookup = Parameter::query()->where('key', 'features.cnpj_lookup')->first();

        $this->assertNotNull($cnpjLookup);
        $this->assertSame('features', $cnpjLookup->group);
        $this->assertSame('boolean', $cnpjLookup->type);
        $this->assertSame('1', $cnpjLookup->default_value);
        $this->assertSame(['required', 'boolean'], $cnpjLookup->validation_rules);

        $baseUrl = Parameter::query()->where('key', 'integrations.cnpj_lookup.base_url')->first();

        $this->assertNotNull($baseUrl);
        $this->assertSame('integracoes', $baseUrl->group);
        $this->assertSame('string', $baseUrl->type);
        $this->assertSame('https://brasilapi.com.br/api/cnpj/v1', $baseUrl->default_value);
        $this->assertSame(['required', 'url'], $baseUrl->validation_rules);
        $this->assertTrue($baseUrl->requires_connection_test);
    }

    public function test_seeder_registra_parametros_da_fundacao_assincrona(): void
    {
        $this->seed(ParameterSeeder::class);

        $retencao = Parameter::query()->where('key', 'retencao.access_logs.dias')->first();

        $this->assertNotNull($retencao);
        $this->assertSame('retencao', $retencao->group);
        $this->assertSame('integer', $retencao->type);
        $this->assertSame('365', $retencao->default_value);
        $this->assertSame(['required', 'integer', 'min:30', 'max:3650'], $retencao->validation_rules);
        $this->assertNull($retencao->value);

        $throttle = Parameter::query()->where('key', 'seguranca.throttle.cnpj_lookup.por_minuto')->first();

        $this->assertNotNull($throttle);
        $this->assertSame('seguranca', $throttle->group);
        $this->assertSame('integer', $throttle->type);
        $this->assertSame('30', $throttle->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:300'], $throttle->validation_rules);

        // timeout/retries/backoff_ms são constantes técnicas de HTTP — ficam em
        // config/sile.php e não aparecem no painel de parâmetros do administrador.
        $this->assertNull(Parameter::query()->where('key', 'integrations.cnpj_lookup.retries')->first());
        $this->assertNull(Parameter::query()->where('key', 'integrations.cnpj_lookup.timeout')->first());
        $this->assertNull(Parameter::query()->where('key', 'integrations.cnpj_lookup.backoff_ms')->first());
    }

    public function test_seeder_registra_parametros_do_login_govbr(): void
    {
        $this->seed(ParameterSeeder::class);

        $toggle = Parameter::query()->where('key', 'features.govbr_login')->first();

        $this->assertNotNull($toggle);
        $this->assertSame('features', $toggle->group);
        $this->assertSame('boolean', $toggle->type);
        $this->assertSame('0', $toggle->default_value);
        $this->assertFalse($toggle->sensitive);

        $baseUrl = Parameter::query()->where('key', 'integrations.govbr.base_url')->first();

        $this->assertNotNull($baseUrl);
        $this->assertSame('integracoes', $baseUrl->group);
        $this->assertSame('https://sso.staging.acesso.gov.br', $baseUrl->default_value);
        $this->assertTrue($baseUrl->requires_connection_test);

        $clientId = Parameter::query()->where('key', 'integrations.govbr.client_id')->first();

        $this->assertNotNull($clientId);
        $this->assertTrue($clientId->sensitive);
        $this->assertNull($clientId->default_value);

        $clientSecret = Parameter::query()->where('key', 'integrations.govbr.client_secret')->first();

        $this->assertNotNull($clientSecret);
        $this->assertTrue($clientSecret->sensitive);
        $this->assertNull($clientSecret->default_value);

        $minimumLevel = Parameter::query()->where('key', 'security.govbr.minimum_level')->first();

        $this->assertNotNull($minimumLevel);
        $this->assertSame('seguranca', $minimumLevel->group);
        $this->assertSame('bronze', $minimumLevel->default_value);
        $this->assertSame(['required', 'in:bronze,prata,ouro'], $minimumLevel->validation_rules);
    }

    public function test_seeder_registra_parametros_do_georreferenciamento(): void
    {
        $this->seed(ParameterSeeder::class);

        $toggle = Parameter::query()->where('key', 'features.geocoding')->first();

        $this->assertNotNull($toggle);
        $this->assertSame('features', $toggle->group);
        $this->assertSame('boolean', $toggle->type);
        $this->assertSame('1', $toggle->default_value);
        $this->assertSame(['required', 'boolean'], $toggle->validation_rules);
        $this->assertNull($toggle->value);

        $baseUrl = Parameter::query()->where('key', 'integrations.geocoding.base_url')->first();

        $this->assertNotNull($baseUrl);
        $this->assertSame('integracoes', $baseUrl->group);
        $this->assertSame('string', $baseUrl->type);
        $this->assertSame('https://nominatim.openstreetmap.org', $baseUrl->default_value);
        $this->assertSame(['required', 'url'], $baseUrl->validation_rules);
        $this->assertTrue($baseUrl->requires_connection_test);

        $throttle = Parameter::query()->where('key', 'seguranca.throttle.geocoding.por_minuto')->first();

        $this->assertNotNull($throttle);
        $this->assertSame('seguranca', $throttle->group);
        $this->assertSame('integer', $throttle->type);
        $this->assertSame('60', $throttle->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:300'], $throttle->validation_rules);

        $sobreposicao = Parameter::query()->where('key', 'geo.validacao.sobreposicao_minima')->first();

        $this->assertNotNull($sobreposicao);
        $this->assertSame('geo', $sobreposicao->group);
        $this->assertSame('integer', $sobreposicao->type);
        $this->assertSame('50', $sobreposicao->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:100'], $sobreposicao->validation_rules);
        $this->assertNull($sobreposicao->value);
    }

    public function test_paginacoes_tecnicas_nao_estao_no_catalogo(): void
    {
        $this->seed(ParameterSeeder::class);

        // Tamanhos de página de listagem são constantes técnicas de UI —
        // ficam em config/sile.php e não poluem o painel do administrador.
        $keysAusentes = [
            'ui.access_history.per_page',
            'ui.cnaes.per_page',
            'ui.users.per_page',
            'ui.companies.per_page',
            'ui.email_logs.per_page',
            'ui.auditoria.per_page',
        ];

        foreach ($keysAusentes as $key) {
            $this->assertNull(
                Parameter::query()->where('key', $key)->first(),
                "O parâmetro {$key} não deve estar no catálogo administrável.",
            );
        }
    }

    public function test_seeder_mantem_flag_sensivel_em_reseed(): void
    {
        $this->seed(ParameterSeeder::class);
        $this->seed(ParameterSeeder::class);

        $clientSecret = Parameter::query()->where('key', 'integrations.govbr.client_secret')->first();

        $this->assertTrue($clientSecret->sensitive);
    }

    public function test_seeder_preserva_valor_administrado(): void
    {
        $this->seed(ParameterSeeder::class);

        Parameter::query()
            ->where('key', 'security.login.max_attempts')
            ->first()
            ->update(['value' => '3']);

        $this->seed(ParameterSeeder::class);

        $parameter = Parameter::query()->where('key', 'security.login.max_attempts')->first();

        $this->assertSame('3', $parameter->value);
        $this->assertSame('Tentativas de login antes do bloqueio temporário', $parameter->description);
    }

    public function test_seeder_registra_parametros_de_encaminhamento_de_risco(): void
    {
        $this->seed(ParameterSeeder::class);

        $mapa = Parameter::query()->where('key', 'risco.mapa_encaminhamento')->first();

        $this->assertNotNull($mapa);
        $this->assertSame('risco', $mapa->group);
        $this->assertSame('json', $mapa->type);
        $this->assertStringContainsString('baixo_a', $mapa->default_value);
        $this->assertStringContainsString('expresso', $mapa->default_value);
        $this->assertStringContainsString('alto', $mapa->default_value);
        $this->assertStringContainsString('analise', $mapa->default_value);
        $this->assertSame(['required', 'json'], $mapa->validation_rules);
        $this->assertNull($mapa->value);

        // O parâmetro json é decodificado para array em typedValue() — o
        // consumidor (motor 06-05) sempre recebe o mapa como array.
        $this->assertSame(
            ['baixo_a' => 'expresso', 'baixo_b' => 'expresso', 'alto' => 'analise'],
            $mapa->typedValue(),
        );

        $dimensao = Parameter::query()->where('key', 'risco.dimensao_tvl')->first();

        $this->assertNotNull($dimensao);
        $this->assertSame('risco', $dimensao->group);
        $this->assertSame('string', $dimensao->type);
        $this->assertSame('municipal', $dimensao->default_value);
        $this->assertSame(['required', 'in:municipal,sanitario'], $dimensao->validation_rules);
        $this->assertNull($dimensao->value);
    }

    public function test_seeder_registra_parametros_do_motor_louos(): void
    {
        $this->seed(ParameterSeeder::class);

        $vagas = Parameter::query()->where('key', 'louos.vagas.exigencia_por_grupo')->first();

        $this->assertNotNull($vagas);
        $this->assertSame('louos', $vagas->group);
        $this->assertSame('json', $vagas->type);
        $this->assertSame('{}', $vagas->default_value);
        $this->assertSame(['required', 'json'], $vagas->validation_rules);
        $this->assertNull($vagas->value);
        $this->assertSame([], $vagas->typedValue());

        $sandbox = Parameter::query()->where('key', 'louos.sandbox.amostra_padrao')->first();

        $this->assertNotNull($sandbox);
        $this->assertSame('louos', $sandbox->group);
        $this->assertSame('integer', $sandbox->type);
        $this->assertSame('50', $sandbox->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:1000'], $sandbox->validation_rules);
        $this->assertNull($sandbox->value);
        $this->assertSame(50, $sandbox->typedValue());
    }

    public function test_seeder_registra_parametros_da_consulta_de_viabilidade(): void
    {
        $this->seed(ParameterSeeder::class);

        $toggle = Parameter::query()->where('key', 'features.consulta_viabilidade')->first();
        $this->assertNotNull($toggle);
        $this->assertSame('features', $toggle->group);
        $this->assertSame('boolean', $toggle->type);
        $this->assertSame('1', $toggle->default_value);
        $this->assertSame(['required', 'boolean'], $toggle->validation_rules);
        $this->assertNull($toggle->value);

        $throttle = Parameter::query()->where('key', 'seguranca.throttle.consulta_viabilidade.por_minuto')->first();
        $this->assertNotNull($throttle);
        $this->assertSame('seguranca', $throttle->group);
        $this->assertSame('integer', $throttle->type);
        $this->assertSame('20', $throttle->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:300'], $throttle->validation_rules);
        $this->assertNull($throttle->value);
    }

    public function test_seeder_registra_parametros_da_solicitacao_de_viabilidade(): void
    {
        $this->seed(ParameterSeeder::class);

        $toggle = Parameter::query()->where('key', 'features.solicitacao_viabilidade')->first();
        $this->assertNotNull($toggle);
        $this->assertSame('features', $toggle->group);
        $this->assertSame('boolean', $toggle->type);
        $this->assertSame('1', $toggle->default_value);
        $this->assertSame(['required', 'boolean'], $toggle->validation_rules);
        $this->assertNull($toggle->value);

        $prefixo = Parameter::query()->where('key', 'solicitacao.protocolo.prefixo')->first();
        $this->assertNotNull($prefixo);
        $this->assertSame('solicitacao', $prefixo->group);
        $this->assertSame('string', $prefixo->type);
        $this->assertSame('VIA', $prefixo->default_value);
        $this->assertSame(['required', 'string', 'max:10'], $prefixo->validation_rules);
        $this->assertNull($prefixo->value);

        $mimes = Parameter::query()->where('key', 'solicitacao.anexos.mime_permitidos')->first();
        $this->assertNotNull($mimes);
        $this->assertSame('solicitacao', $mimes->group);
        $this->assertSame('json', $mimes->type);
        $this->assertSame(['required', 'json'], $mimes->validation_rules);
        $this->assertSame(['application/pdf', 'image/jpeg', 'image/png'], $mimes->typedValue());

        // storage.documentos.disk é constante de infraestrutura — fica em config/sile.php.
        $this->assertNull(Parameter::query()->where('key', 'storage.documentos.disk')->first());

        $throttle = Parameter::query()->where('key', 'seguranca.throttle.consulta_protocolo.por_minuto')->first();
        $this->assertNotNull($throttle);
        $this->assertSame('seguranca', $throttle->group);
        $this->assertSame('integer', $throttle->type);
        $this->assertSame('30', $throttle->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:300'], $throttle->validation_rules);
        $this->assertNull($throttle->value);
    }

    public function test_seeder_registra_parametro_de_estados_cancelaveis(): void
    {
        $this->seed(ParameterSeeder::class);

        $estados = Parameter::query()->where('key', 'solicitacao.cancelamento.estados_cancelaveis')->first();

        $this->assertNotNull($estados);
        $this->assertSame('solicitacao', $estados->group);
        $this->assertSame('json', $estados->type);
        $this->assertSame('["rascunho","protocolada"]', $estados->default_value);
        $this->assertSame(['required', 'json'], $estados->validation_rules);
        $this->assertNull($estados->value);

        // O parâmetro json é decodificado para array em typedValue() — o
        // serviço de cancelamento sempre recebe a lista como array.
        $this->assertSame(['rascunho', 'protocolada'], $estados->typedValue());
    }

    public function test_seeder_registra_parametros_do_fluxo_expresso(): void
    {
        $this->seed(ParameterSeeder::class);

        $toggle = Parameter::query()->where('key', 'features.fluxo_expresso')->first();
        $this->assertNotNull($toggle);
        $this->assertSame('features', $toggle->group);
        $this->assertSame('boolean', $toggle->type);
        $this->assertSame('1', $toggle->default_value);
        $this->assertSame(['required', 'boolean'], $toggle->validation_rules);
        $this->assertNull($toggle->value);

        $notificacao = Parameter::query()->where('key', 'features.notificacao_resultado_expresso')->first();
        $this->assertNotNull($notificacao);
        $this->assertSame('features', $notificacao->group);
        $this->assertSame('boolean', $notificacao->type);
        $this->assertSame('1', $notificacao->default_value);
        $this->assertSame(['required', 'boolean'], $notificacao->validation_rules);
        $this->assertNull($notificacao->value);

        $prazoBap = Parameter::query()->where('key', 'expresso.bap.prazo_horas')->first();
        $this->assertNotNull($prazoBap);
        $this->assertSame('expresso', $prazoBap->group);
        $this->assertSame('integer', $prazoBap->type);
        $this->assertSame('48', $prazoBap->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:720'], $prazoBap->validation_rules);
        $this->assertNull($prazoBap->value);
        $this->assertSame(48, $prazoBap->typedValue());

        $assuntoDeferida = Parameter::query()->where('key', 'expresso.notificacao.assunto_deferida')->first();
        $this->assertNotNull($assuntoDeferida);
        $this->assertSame('expresso', $assuntoDeferida->group);
        $this->assertSame('string', $assuntoDeferida->type);
        $this->assertSame('Resultado da sua solicitação de viabilidade: deferida', $assuntoDeferida->default_value);
        $this->assertSame(['required', 'string', 'max:150'], $assuntoDeferida->validation_rules);
        $this->assertNull($assuntoDeferida->value);

        $assuntoIndeferida = Parameter::query()->where('key', 'expresso.notificacao.assunto_indeferida')->first();
        $this->assertNotNull($assuntoIndeferida);
        $this->assertSame('expresso', $assuntoIndeferida->group);
        $this->assertSame('string', $assuntoIndeferida->type);
        $this->assertSame('Resultado da sua solicitação de viabilidade: indeferida', $assuntoIndeferida->default_value);
        $this->assertSame(['required', 'string', 'max:150'], $assuntoIndeferida->validation_rules);
        $this->assertNull($assuntoIndeferida->value);

        $prefixoTvl = Parameter::query()->where('key', 'expresso.tvl.prefixo')->first();
        $this->assertNotNull($prefixoTvl);
        $this->assertSame('expresso', $prefixoTvl->group);
        $this->assertSame('string', $prefixoTvl->type);
        $this->assertSame('TVL', $prefixoTvl->default_value);
        $this->assertSame(['required', 'string', 'max:10'], $prefixoTvl->validation_rules);
        $this->assertNull($prefixoTvl->value);

        // expresso.tvl.padding é constante de formatação — fica em config/sile.php.
        $this->assertNull(Parameter::query()->where('key', 'expresso.tvl.padding')->first());
    }

    public function test_seeder_registra_parametros_da_analise_tecnica(): void
    {
        $this->seed(ParameterSeeder::class);

        $toggle = Parameter::query()->where('key', 'features.analise_tecnica')->first();
        $this->assertNotNull($toggle);
        $this->assertSame('features', $toggle->group);
        $this->assertSame('boolean', $toggle->type);
        $this->assertSame('1', $toggle->default_value);
        $this->assertSame(['required', 'boolean'], $toggle->validation_rules);
        $this->assertNull($toggle->value);

        $distribuicao = Parameter::query()->where('key', 'analise.sla.distribuicao_dias')->first();
        $this->assertNotNull($distribuicao);
        $this->assertSame('analise', $distribuicao->group);
        $this->assertSame('integer', $distribuicao->type);
        $this->assertSame('2', $distribuicao->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:60'], $distribuicao->validation_rules);
        $this->assertNull($distribuicao->value);

        $analise = Parameter::query()->where('key', 'analise.sla.analise_dias')->first();
        $this->assertNotNull($analise);
        $this->assertSame('analise', $analise->group);
        $this->assertSame('integer', $analise->type);
        $this->assertSame('10', $analise->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:180'], $analise->validation_rules);
        $this->assertNull($analise->value);

        $semaforo = Parameter::query()->where('key', 'analise.sla.semaforo.amarelo_percentual')->first();
        $this->assertNotNull($semaforo);
        $this->assertSame('analise', $semaforo->group);
        $this->assertSame('integer', $semaforo->type);
        $this->assertSame('80', $semaforo->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:99'], $semaforo->validation_rules);
        $this->assertNull($semaforo->value);

        $pendencia = Parameter::query()->where('key', 'analise.pendencia.prazo_resposta_dias')->first();
        $this->assertNotNull($pendencia);
        $this->assertSame('analise', $pendencia->group);
        $this->assertSame('integer', $pendencia->type);
        $this->assertSame('15', $pendencia->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:180'], $pendencia->validation_rules);
        $this->assertNull($pendencia->value);

        $janela = Parameter::query()->where('key', 'analise.precedentes.janela_meses')->first();
        $this->assertNotNull($janela);
        $this->assertSame('analise', $janela->group);
        $this->assertSame('integer', $janela->type);
        $this->assertSame('12', $janela->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:120'], $janela->validation_rules);
        $this->assertNull($janela->value);
        $this->assertSame(12, $janela->typedValue());

        $maxItens = Parameter::query()->where('key', 'analise.precedentes.max_itens')->first();
        $this->assertNotNull($maxItens);
        $this->assertSame('analise', $maxItens->group);
        $this->assertSame('integer', $maxItens->type);
        $this->assertSame('10', $maxItens->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:50'], $maxItens->validation_rules);
        $this->assertNull($maxItens->value);

        // analise.tvl.disk, analise.tvl.assinatura.imagem_path e
        // analise.tvl.download.ttl_minutos são constantes de infraestrutura/
        // formatação — ficam em config/sile.php, não no catálogo administrável.
        $this->assertNull(Parameter::query()->where('key', 'analise.tvl.disk')->first());
        $this->assertNull(Parameter::query()->where('key', 'analise.tvl.assinatura.imagem_path')->first());
        $this->assertNull(Parameter::query()->where('key', 'analise.tvl.download.ttl_minutos')->first());

        $modo = Parameter::query()->where('key', 'analise.tvl.assinatura.modo')->first();
        $this->assertNotNull($modo);
        $this->assertSame('analise', $modo->group);
        $this->assertSame('string', $modo->type);
        $this->assertSame('imagem', $modo->default_value);
        $this->assertSame(['required', 'in:imagem,nenhuma'], $modo->validation_rules);
        $this->assertNull($modo->value);
    }

    public function test_seeder_registra_parametros_de_notificacoes(): void
    {
        $this->seed(ParameterSeeder::class);

        // Toggles de canal (HU-014): e-mail e in-app nascem ligados; WhatsApp
        // nasce DESLIGADO (bloqueio honesto — provedor real só na Fase 13).
        $email = Parameter::query()->where('key', 'features.notificacao_email')->first();
        $this->assertNotNull($email);
        $this->assertSame('features', $email->group);
        $this->assertSame('boolean', $email->type);
        $this->assertSame('1', $email->default_value);
        $this->assertSame(['required', 'boolean'], $email->validation_rules);
        $this->assertNull($email->value);

        $inApp = Parameter::query()->where('key', 'features.notificacao_in_app')->first();
        $this->assertNotNull($inApp);
        $this->assertSame('features', $inApp->group);
        $this->assertSame('boolean', $inApp->type);
        $this->assertSame('1', $inApp->default_value);
        $this->assertSame(['required', 'boolean'], $inApp->validation_rules);
        $this->assertNull($inApp->value);

        $whatsapp = Parameter::query()->where('key', 'features.notificacao_whatsapp')->first();
        $this->assertNotNull($whatsapp);
        $this->assertSame('features', $whatsapp->group);
        $this->assertSame('boolean', $whatsapp->type);
        $this->assertSame('0', $whatsapp->default_value);
        $this->assertSame(['required', 'boolean'], $whatsapp->validation_rules);
        $this->assertFalse($whatsapp->typedValue());
        $this->assertNull($whatsapp->value);

        // Grupo NOVO notificacoes: mapa de canais por tipo (intersecção com os
        // toggles; WhatsApp fora por default).
        $mapaCanais = Parameter::query()->where('key', 'notificacoes.mapa_canais')->first();
        $this->assertNotNull($mapaCanais);
        $this->assertSame('notificacoes', $mapaCanais->group);
        $this->assertSame('json', $mapaCanais->type);
        $this->assertSame(['required', 'json'], $mapaCanais->validation_rules);
        $this->assertNull($mapaCanais->value);
        // O parâmetro json é decodificado para array em typedValue() — o
        // dispatcher (11-04) sempre recebe o mapa como array, nunca string.
        $this->assertSame(
            [
                'pendencia_aberta' => ['email', 'in_app'],
                'pendencia_respondida' => ['in_app'],
                'pendencia_expirada' => ['email', 'in_app'],
                'prazo_vencendo' => ['email', 'in_app'],
                'escalonamento_sla' => ['email', 'in_app'],
                'resultado' => ['email', 'in_app'],
            ],
            $mapaCanais->typedValue(),
        );

        $antecedencia = Parameter::query()->where('key', 'notificacoes.vencimento.antecedencia_dias')->first();
        $this->assertNotNull($antecedencia);
        $this->assertSame('notificacoes', $antecedencia->group);
        $this->assertSame('integer', $antecedencia->type);
        $this->assertSame('3', $antecedencia->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:60'], $antecedencia->validation_rules);
        $this->assertNull($antecedencia->value);
        $this->assertSame(3, $antecedencia->typedValue());

        $tratamento = Parameter::query()->where('key', 'notificacoes.escalonamento.tratamento')->first();
        $this->assertNotNull($tratamento);
        $this->assertSame('notificacoes', $tratamento->group);
        $this->assertSame('json', $tratamento->type);
        $this->assertSame(['required', 'json'], $tratamento->validation_rules);
        $this->assertNull($tratamento->value);
        // Default só NOTIFICA (sem decisão automática — HU-147).
        $this->assertSame(
            ['amarelo' => 'notificar_analista', 'vencido' => 'notificar_gestor'],
            $tratamento->typedValue(),
        );

        $gestorRole = Parameter::query()->where('key', 'notificacoes.escalonamento.gestor_role')->first();
        $this->assertNotNull($gestorRole);
        $this->assertSame('notificacoes', $gestorRole->group);
        $this->assertSame('string', $gestorRole->type);
        $this->assertSame('gestor', $gestorRole->default_value);
        $this->assertSame(['required', 'string', 'max:50'], $gestorRole->validation_rules);
        $this->assertNull($gestorRole->value);

        $assunto = Parameter::query()->where('key', 'notificacoes.pendencia.assunto')->first();
        $this->assertNotNull($assunto);
        $this->assertSame('notificacoes', $assunto->group);
        $this->assertSame('string', $assunto->type);
        $this->assertSame('Pendência na sua solicitação de viabilidade {protocolo}', $assunto->default_value);
        $this->assertSame(['required', 'string', 'max:150'], $assunto->validation_rules);
        $this->assertNull($assunto->value);

        $corpo = Parameter::query()->where('key', 'notificacoes.pendencia.corpo')->first();
        $this->assertNotNull($corpo);
        $this->assertSame('notificacoes', $corpo->group);
        $this->assertSame('string', $corpo->type);
        $this->assertSame(['required', 'string', 'max:2000'], $corpo->validation_rules);
        // Template pt-BR honesto com os placeholders substituíveis.
        $this->assertStringContainsString('{protocolo}', $corpo->default_value);
        $this->assertStringContainsString('{pendencia}', $corpo->default_value);
        $this->assertNull($corpo->value);

        // Credenciais do WhatsApp (integração com teste de conexão + segredo
        // criptografado), prontas para a Fase 13 ligar só o binding.
        $baseUrl = Parameter::query()->where('key', 'integrations.whatsapp.base_url')->first();
        $this->assertNotNull($baseUrl);
        $this->assertSame('integracoes', $baseUrl->group);
        $this->assertSame('string', $baseUrl->type);
        $this->assertSame('', $baseUrl->default_value);
        $this->assertSame(['nullable', 'url'], $baseUrl->validation_rules);
        $this->assertTrue($baseUrl->requires_connection_test);
        $this->assertFalse($baseUrl->sensitive);
        $this->assertNull($baseUrl->value);

        $token = Parameter::query()->where('key', 'integrations.whatsapp.token')->first();
        $this->assertNotNull($token);
        $this->assertSame('integracoes', $token->group);
        $this->assertSame('string', $token->type);
        $this->assertNull($token->default_value);
        $this->assertSame(['nullable', 'string', 'max:255'], $token->validation_rules);
        $this->assertTrue($token->sensitive);
        $this->assertNull($token->value);
    }

    public function test_seeder_registra_parametros_de_auditoria_e_abuso(): void
    {
        $this->seed(ParameterSeeder::class);

        // Toggle da detecção de abuso (HU-149): nasce DESLIGADO (nunca pune).
        $toggle = Parameter::query()->where('key', 'features.deteccao_abuso')->first();
        $this->assertNotNull($toggle);
        $this->assertSame('features', $toggle->group);
        $this->assertSame('boolean', $toggle->type);
        $this->assertSame('0', $toggle->default_value);
        $this->assertSame(['required', 'boolean'], $toggle->validation_rules);
        $this->assertFalse($toggle->typedValue());
        $this->assertNull($toggle->value);

        $janela = Parameter::query()->where('key', 'abuso.janela_dias')->first();
        $this->assertNotNull($janela);
        $this->assertSame('abuso', $janela->group);
        $this->assertSame('integer', $janela->type);
        $this->assertSame('30', $janela->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:365'], $janela->validation_rules);
        $this->assertSame(30, $janela->typedValue());

        $volumeCnpj = Parameter::query()->where('key', 'abuso.volume_cnpj.limite')->first();
        $this->assertNotNull($volumeCnpj);
        $this->assertSame('abuso', $volumeCnpj->group);
        $this->assertSame('integer', $volumeCnpj->type);
        $this->assertSame('5', $volumeCnpj->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:1000'], $volumeCnpj->validation_rules);

        $volumeContador = Parameter::query()->where('key', 'abuso.volume_contador.limite')->first();
        $this->assertNotNull($volumeContador);
        $this->assertSame('abuso', $volumeContador->group);
        $this->assertSame('integer', $volumeContador->type);
        $this->assertSame('20', $volumeContador->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:1000'], $volumeContador->validation_rules);

        $escritorio = Parameter::query()->where('key', 'abuso.escritorio_virtual.limite')->first();
        $this->assertNotNull($escritorio);
        $this->assertSame('abuso', $escritorio->group);
        $this->assertSame('integer', $escritorio->type);
        $this->assertSame('3', $escritorio->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:1000'], $escritorio->validation_rules);

        $severidade = Parameter::query()->where('key', 'abuso.severidade_malha_fina')->first();
        $this->assertNotNull($severidade);
        $this->assertSame('abuso', $severidade->group);
        $this->assertSame('string', $severidade->type);
        $this->assertSame('alta', $severidade->default_value);
        $this->assertSame(['required', 'in:baixa,media,alta'], $severidade->validation_rules);
        $this->assertNull($severidade->value);
    }

    public function test_seeder_registra_parametros_de_relatorios(): void
    {
        $this->seed(ParameterSeeder::class);

        $limiar = Parameter::query()->where('key', 'relatorios.export.assincrono_limiar_linhas')->first();
        $this->assertNotNull($limiar);
        $this->assertSame('relatorios', $limiar->group);
        $this->assertSame('integer', $limiar->type);
        $this->assertSame('5000', $limiar->default_value);
        $this->assertSame(['required', 'integer', 'min:100', 'max:1000000'], $limiar->validation_rules);
        $this->assertNull($limiar->value);
        $this->assertSame(5000, $limiar->typedValue());

        $formatos = Parameter::query()->where('key', 'relatorios.export.formatos_habilitados')->first();
        $this->assertNotNull($formatos);
        $this->assertSame('relatorios', $formatos->group);
        $this->assertSame('json', $formatos->type);
        $this->assertSame('["csv","xlsx","pdf"]', $formatos->default_value);
        $this->assertSame(['required', 'json'], $formatos->validation_rules);
        $this->assertNull($formatos->value);
        // O parâmetro json é decodificado para array em typedValue() — as telas
        // de gestão recebem a lista de formatos como array, nunca string.
        $this->assertSame(['csv', 'xlsx', 'pdf'], $formatos->typedValue());

        $retencao = Parameter::query()->where('key', 'relatorios.export.retencao_dias')->first();
        $this->assertNotNull($retencao);
        $this->assertSame('relatorios', $retencao->group);
        $this->assertSame('integer', $retencao->type);
        $this->assertSame('7', $retencao->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:365'], $retencao->validation_rules);
        $this->assertNull($retencao->value);

        // Meta da taxa de resposta expressa: nasce SEM valor (pendência SEDUR) —
        // default null, degradação honesta "meta não definida" (nunca inventada).
        $metaTaxa = Parameter::query()->where('key', 'relatorios.expresso.meta_taxa')->first();
        $this->assertNotNull($metaTaxa);
        $this->assertSame('relatorios', $metaTaxa->group);
        $this->assertSame('string', $metaTaxa->type);
        $this->assertNull($metaTaxa->default_value);
        $this->assertSame(['nullable', 'numeric', 'min:0', 'max:100'], $metaTaxa->validation_rules);
        $this->assertNull($metaTaxa->value);

        $janela = Parameter::query()->where('key', 'relatorios.expresso.janela_dias')->first();
        $this->assertNotNull($janela);
        $this->assertSame('relatorios', $janela->group);
        $this->assertSame('integer', $janela->type);
        $this->assertSame('30', $janela->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:365'], $janela->validation_rules);
        $this->assertNull($janela->value);
        $this->assertSame(30, $janela->typedValue());
    }

    public function test_seeder_registra_parametros_de_saturacao(): void
    {
        $this->seed(ParameterSeeder::class);

        $capacidades = Parameter::query()->where('key', 'relatorios.saturacao.capacidades')->first();
        $this->assertNotNull($capacidades);
        $this->assertSame('relatorios', $capacidades->group);
        $this->assertSame('json', $capacidades->type);
        $this->assertSame('{}', $capacidades->default_value);
        $this->assertSame(['required', 'json'], $capacidades->validation_rules);
        $this->assertSame([], $capacidades->typedValue());

        $alerta = Parameter::query()->where('key', 'relatorios.saturacao.alerta_percentual')->first();
        $this->assertNotNull($alerta);
        $this->assertSame('relatorios', $alerta->group);
        $this->assertSame('integer', $alerta->type);
        $this->assertSame('80', $alerta->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:100'], $alerta->validation_rules);
        $this->assertSame(80, $alerta->typedValue());

        $bloqueio = Parameter::query()->where('key', 'relatorios.saturacao.bloqueio_percentual')->first();
        $this->assertNotNull($bloqueio);
        $this->assertSame('relatorios', $bloqueio->group);
        $this->assertSame('integer', $bloqueio->type);
        $this->assertSame('100', $bloqueio->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:200'], $bloqueio->validation_rules);
        $this->assertSame(100, $bloqueio->typedValue());
    }

    public function test_seeder_registra_parametros_da_auditoria_preditiva(): void
    {
        $this->seed(ParameterSeeder::class);

        $toggle = Parameter::query()->where('key', 'features.ia_auditoria_preditiva')->first();
        $this->assertNotNull($toggle);
        $this->assertSame('ia', $toggle->group);
        $this->assertSame('boolean', $toggle->type);
        $this->assertSame('0', $toggle->default_value);
        $this->assertSame(['required', 'boolean'], $toggle->validation_rules);
        $this->assertFalse($toggle->typedValue());
        $this->assertNull($toggle->value);

        $janela = Parameter::query()->where('key', 'ia.auditoria_preditiva.janela_dias')->first();
        $this->assertNotNull($janela);
        $this->assertSame('ia', $janela->group);
        $this->assertSame('integer', $janela->type);
        $this->assertSame('30', $janela->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:365'], $janela->validation_rules);
        $this->assertSame(30, $janela->typedValue());

        $limiar = Parameter::query()->where('key', 'ia.auditoria_preditiva.limiar_score')->first();
        $this->assertNotNull($limiar);
        $this->assertSame('ia', $limiar->group);
        $this->assertSame('integer', $limiar->type);
        $this->assertSame('70', $limiar->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:100'], $limiar->validation_rules);
        $this->assertSame(70, $limiar->typedValue());
    }

    public function test_seeder_registra_toggles_de_ia(): void
    {
        $this->seed(ParameterSeeder::class);

        // Funções de IA (Fase 14 — HU-014 aplicada à IA): 7 toggles por função,
        // grupo 'ia', TODOS nascem DESLIGADOS (0). A fundação multi-provider
        // (Onda 0) não liga função nenhuma — cada onda liga a sua ao entregar;
        // desligado degrada controlado (a função some/avisa), nunca falha silenciosa.
        $toggles = [
            'features.ia_ocr',
            'features.ia_classificacao',
            'features.ia_inconsistencias',
            'features.ia_resumo',
            'features.ia_parecer',
            'features.ia_explicacao',
            'features.ia_assistente',
        ];

        foreach ($toggles as $key) {
            $toggle = Parameter::query()->where('key', $key)->first();

            $this->assertNotNull($toggle, "Esperava o toggle {$key} registrado.");
            $this->assertSame('ia', $toggle->group);
            $this->assertSame('boolean', $toggle->type);
            $this->assertSame('0', $toggle->default_value, "O toggle {$key} deve nascer desligado.");
            $this->assertSame(['required', 'boolean'], $toggle->validation_rules);
            $this->assertFalse($toggle->typedValue(), "O toggle {$key} deve resolver para false por padrão.");
            $this->assertFalse($toggle->sensitive);
            $this->assertNull($toggle->value);
        }
    }

    public function test_seeder_e_idempotente(): void
    {
        $this->seed(ParameterSeeder::class);
        $this->seed(ParameterSeeder::class);

        $this->assertSame(88, Parameter::query()->count());
    }
}
