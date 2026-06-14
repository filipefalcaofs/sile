<?php

namespace Tests\Feature\Seeders;

use App\Enums\CompanySource;
use App\Enums\RuleDomain;
use App\Models\Activity;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro11CondicaoVia;
use App\Models\LouosQuadro7Faixa;
use App\Models\Parameter;
use App\Models\RiskClassification;
use App\Models\RiskCondicionante;
use App\Models\RuleVersion;
use App\Models\SanitaryRiskClassification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_completo_prepara_ambiente_de_desenvolvimento(): void
    {
        $this->seed();

        $this->assertSame(4, Role::query()->count());
        $this->assertNotNull(LegalTerm::current('lgpd'));
        $this->assertSame(1331, Cnae::query()->count());
        $this->assertSame(48, Parameter::query()->count());
        $this->assertTrue(
            Activity::query()
                ->where('log_name', 'cnaes')
                ->where('event', 'importacao-oficial')
                ->exists()
        );

        // Classificação de risco municipal (Decreto 32.636/2020): versão
        // vigente única + 1.331 classificações carregadas e auditadas.
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoMunicipal)->count());
        $this->assertSame(1331, RiskClassification::query()->count());
        $this->assertTrue(
            Activity::query()
                ->where('log_name', 'risco')
                ->where('event', 'importacao-classificacao-municipal')
                ->exists()
        );

        // Classificação de risco sanitário (VISA): dimensão SEPARADA, versão
        // vigente própria + 261 classificações e 67 condicionantes-pergunta.
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoSanitario)->count());
        $this->assertSame(261, SanitaryRiskClassification::query()->count());
        $this->assertSame(67, RiskCondicionante::query()->count());
        $this->assertTrue(
            Activity::query()
                ->where('log_name', 'risco')
                ->where('event', 'importacao-classificacao-sanitaria')
                ->exists()
        );

        // Quadros da LOUOS (Lei 9.148/2016): cada Quadro publica uma versão
        // vigente própria; o Quadro 7 carrega as 40 faixas reais e a importação
        // é auditada (RN-002). Quadros 10/11/11A modelados (carga oficial
        // pendente SEDUR), mas já versionados.
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro7)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro10)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro11)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro11a)->count());
        $this->assertSame(40, LouosQuadro7Faixa::query()->count());
        $this->assertGreaterThan(0, LouosQuadro10Permissao::query()->count());
        $this->assertGreaterThan(0, LouosQuadro11CondicaoVia::query()->count());
        $this->assertTrue(
            Activity::query()
                ->where('log_name', 'louos')
                ->where('event', 'importacao-quadro7')
                ->exists()
        );

        $admin = User::query()->where('email', 'admin@sile.dev')->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('administrador'));
        $this->assertNotNull($admin->email_verified_at);
    }

    public function test_seed_cria_cidadao_dev_com_empresas(): void
    {
        $this->seed();

        $cidadao = User::query()->where('email', 'cidadao@sile.dev')->first();

        $this->assertNotNull($cidadao);
        $this->assertTrue($cidadao->hasRole('cidadao'));
        $this->assertNotNull($cidadao->email_verified_at);
        $this->assertTrue(
            LegalTermAcceptance::query()
                ->where('user_id', $cidadao->id)
                ->where('legal_term_id', LegalTerm::current('lgpd')->id)
                ->exists()
        );

        $this->assertGreaterThanOrEqual(3, Company::query()->count());
        $this->assertTrue(Company::query()->where('source', CompanySource::Manual)->exists());
        $this->assertTrue(Company::query()->where('source', CompanySource::Redesim)->exists());

        $vinculosAtivos = CompanyUser::query()
            ->where('user_id', $cidadao->id)
            ->whereNull('ended_at')
            ->count();
        $this->assertGreaterThanOrEqual(2, $vinculosAtivos);

        $temPrincipal = Company::query()
            ->whereHas('links', fn ($q) => $q->where('user_id', $cidadao->id)->whereNull('ended_at'))
            ->whereHas('cnaes', fn ($q) => $q->where('company_cnae.is_primary', true))
            ->exists();
        $this->assertTrue($temPrincipal);
    }

    public function test_seed_e_idempotente(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(1, User::query()->where('email', 'admin@sile.dev')->count());
        $this->assertSame(1, User::query()->where('email', 'cidadao@sile.dev')->count());
        $this->assertSame(4, Role::query()->count());
        $this->assertSame(1331, Cnae::query()->count());
        $this->assertSame(48, Parameter::query()->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoMunicipal)->count());
        $this->assertSame(1331, RiskClassification::query()->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoSanitario)->count());
        $this->assertSame(261, SanitaryRiskClassification::query()->count());
        $this->assertSame(67, RiskCondicionante::query()->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro7)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro10)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro11)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro11a)->count());
        $this->assertSame(40, LouosQuadro7Faixa::query()->count());
        $this->assertSame(3, Company::query()->count());
        $this->assertSame(
            2,
            CompanyUser::query()
                ->where('user_id', User::query()->where('email', 'cidadao@sile.dev')->value('id'))
                ->whereNull('ended_at')
                ->count()
        );
    }

    public function test_seed_preserva_valor_de_parametro_administrado(): void
    {
        $this->seed();

        Parameter::query()
            ->where('key', 'ui.access_history.per_page')
            ->first()
            ->update(['value' => '7']);

        $this->seed();

        $this->assertSame(
            '7',
            Parameter::query()->where('key', 'ui.access_history.per_page')->first()->value
        );
    }

    public function test_admin_dev_acessa_gestao_apos_aceitar_termo(): void
    {
        $this->seed();

        // Ambientes independentes: a gestão tem login próprio (guard gestao).
        $this->post('/gestao/login', [
            'email' => 'admin@sile.dev',
            'password' => 'password',
        ])->assertRedirect('/gestao');

        $this->assertAuthenticated('gestao');

        $this->get('/gestao')->assertRedirect(route('portal.termo-lgpd.show'));

        $this->post('/portal/termo-lgpd', ['accepted' => true]);

        $this->get('/gestao')->assertOk();
    }
}
