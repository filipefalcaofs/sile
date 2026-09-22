<?php

namespace Tests\Unit\Analise;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Exceptions\FourEyesViolationException;
use App\Models\RuleVersion;
use App\Models\User;
use App\Services\Analise\TllPublicacaoExercicio;
use DomainException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Publicação do exercício TLL: o rascunho só vira vigente com publicador
 * distinto do autor (quatro olhos, domínio sensível).
 */
class TllPublicacaoExercicioTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_publicador_igual_ao_autor_e_rejeitado(): void
    {
        $autor = User::factory()->create();
        RuleVersion::factory()->rascunho()->create([
            'domain' => RuleDomain::TllValores,
            'version' => '2027',
            'created_by' => $autor->id,
        ]);

        $this->expectException(FourEyesViolationException::class);

        app(TllPublicacaoExercicio::class)->publicar(2027, $autor->id);
    }

    public function test_publicador_distinto_promove_e_fecha_anterior(): void
    {
        $autor = User::factory()->create();
        $publicador = User::factory()->create();

        $anterior = RuleVersion::factory()->create([
            'domain' => RuleDomain::TllValores,
            'version' => '2026',
            'status' => RuleVersionStatus::Vigente,
            'valid_to' => null,
        ]);
        RuleVersion::factory()->rascunho()->create([
            'domain' => RuleDomain::TllValores,
            'version' => '2027',
            'created_by' => $autor->id,
        ]);

        $vigente = app(TllPublicacaoExercicio::class)->publicar(2027, $publicador->id);

        $this->assertSame(RuleVersionStatus::Vigente, $vigente->status);
        $this->assertSame($publicador->id, $vigente->published_by);
        $this->assertSame(RuleVersionStatus::Substituida, $anterior->fresh()->status);
    }

    public function test_sem_rascunho_recusa(): void
    {
        $publicador = User::factory()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Não há rascunho do exercício 2027 para publicar.');

        app(TllPublicacaoExercicio::class)->publicar(2027, $publicador->id);
    }
}
