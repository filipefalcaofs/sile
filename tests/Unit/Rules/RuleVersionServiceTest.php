<?php

namespace Tests\Unit\Rules;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Exceptions\FourEyesViolationException;
use App\Models\Activity;
use App\Models\RuleVersion;
use App\Models\User;
use App\Services\Rules\RuleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Versionamento de regras (HU-019/HU-020/HU-053), espelhando GeoLayerService:
 * o rascunho coexiste com a vigente (base do sandbox HU-143) e a publicação
 * fecha a vigente anterior sem apagá-la, aplica quatro olhos em domínio
 * sensível e audita com a versão de regras (RN-002). Lógica de linhas (datas +
 * status), provada em SQLite — sem PostGIS.
 */
class RuleVersionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): RuleVersionService
    {
        return app(RuleVersionService::class);
    }

    public function test_open_draft_cria_rascunho_sem_mexer_na_vigente(): void
    {
        $autor = User::factory()->create();

        $vigente = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoMunicipal,
            'version' => 'decreto-2018',
            'status' => RuleVersionStatus::Vigente,
            'valid_to' => null,
        ]);

        $rascunho = $this->service()->openDraft(
            RuleDomain::RiscoMunicipal,
            'decreto-2024',
            'docs/dados-oficiais/decreto.csv',
            $autor->id,
        );

        $this->assertSame(RuleVersionStatus::Rascunho, $rascunho->status);
        $this->assertNull($rascunho->valid_from);
        $this->assertNull($rascunho->valid_to);
        $this->assertSame('docs/dados-oficiais/decreto.csv', $rascunho->source);
        $this->assertSame('decreto-2024', $rascunho->rules_version);
        $this->assertSame($autor->id, $rascunho->created_by);

        // A vigente NÃO é tocada — rascunho coexiste com ela.
        $vigente->refresh();
        $this->assertSame(RuleVersionStatus::Vigente, $vigente->status);
        $this->assertNull($vigente->valid_to);
        $this->assertSame(2, RuleVersion::query()->where('domain', 'risco_municipal')->count());
    }

    public function test_open_draft_e_idempotente_por_dominio_e_versao(): void
    {
        $primeiro = $this->service()->openDraft(RuleDomain::RiscoMunicipal, 'decreto-2024', 'origem');
        $segundo = $this->service()->openDraft(RuleDomain::RiscoMunicipal, 'decreto-2024', 'origem');

        $this->assertTrue($primeiro->is($segundo));
        $this->assertSame(1, RuleVersion::query()
            ->where('domain', 'risco_municipal')
            ->where('version', 'decreto-2024')
            ->count());
    }

    public function test_publish_promove_rascunho_e_fecha_vigente_anterior_sem_apagar(): void
    {
        $anterior = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoMunicipal,
            'version' => 'decreto-2018',
            'status' => RuleVersionStatus::Vigente,
            'valid_from' => '2018-01-01',
            'valid_to' => null,
        ]);

        $rascunho = $this->service()->openDraft(RuleDomain::RiscoMunicipal, 'decreto-2024', 'origem');

        $publicada = $this->service()->publish($rascunho, null, Carbon::parse('2024-06-01'));

        $anterior->refresh();

        // A anterior NÃO foi apagada — foi fechada (histórico/reprodução).
        $this->assertSame(2, RuleVersion::query()->where('domain', 'risco_municipal')->count());
        $this->assertSame(RuleVersionStatus::Substituida, $anterior->status);
        $this->assertNotNull($anterior->valid_to);
        $this->assertTrue($anterior->valid_to->equalTo(Carbon::parse('2024-06-01')));

        // A nova é a vigente.
        $this->assertSame(RuleVersionStatus::Vigente, $publicada->status);
        $this->assertTrue($publicada->valid_from->equalTo(Carbon::parse('2024-06-01')));
        $this->assertNull($publicada->valid_to);
        $this->assertNotNull($publicada->published_at);

        $vigente = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->sole();
        $this->assertTrue($vigente->is($publicada));
    }

    public function test_publish_audita_com_versao_de_regras(): void
    {
        $rascunho = $this->service()->openDraft(RuleDomain::RiscoMunicipal, 'decreto-2024', 'origem');

        $this->service()->publish($rascunho, null, Carbon::parse('2024-01-01'));

        $activity = Activity::query()
            ->where('log_name', 'regras')
            ->where('event', 'publicacao-versao')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('sucesso', $activity->result);
        $this->assertSame('decreto-2024', $activity->rules_version);
        $this->assertSame('risco_municipal', $activity->properties['dominio']);
        $this->assertSame('decreto-2024', $activity->properties['versao']);
    }

    public function test_publish_quatro_olhos_rejeita_publicador_igual_ao_autor(): void
    {
        $autor = User::factory()->create();

        $rascunho = $this->service()->openDraft(RuleDomain::RiscoMunicipal, 'decreto-2024', 'origem', $autor->id);

        $this->expectException(FourEyesViolationException::class);

        $this->service()->publish($rascunho, $autor->id);
    }

    public function test_publish_permite_publicador_distinto_em_dominio_sensivel(): void
    {
        $autor = User::factory()->create();
        $revisor = User::factory()->create();

        $rascunho = $this->service()->openDraft(RuleDomain::RiscoMunicipal, 'decreto-2024', 'origem', $autor->id);

        $publicada = $this->service()->publish($rascunho, $revisor->id, Carbon::parse('2024-01-01'));

        $this->assertSame(RuleVersionStatus::Vigente, $publicada->status);
        $this->assertSame($revisor->id, $publicada->published_by);
    }
}
