<?php

namespace Tests\Feature\Renovacao;

use App\Enums\AnalysisCategory;
use App\Enums\TvlKind;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\ServiceDeclaration;
use App\Models\ServiceDeclarationAcceptance;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Renovacao\RenovacaoProtocoloService;
use DomainException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RenovacaoProtocoloServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_protocolar_cria_renovacao_semi_expressa_sem_alterar_endereco(): void
    {
        [$origem, $declaracao, $ator] = $this->cenarioElegivel();

        $renovacao = app(RenovacaoProtocoloService::class)->protocolar(
            $origem,
            $ator,
            ['email' => 'novo@example.com', 'phone' => '71999990000', 'mobile' => '71988880000'],
            [$declaracao->id],
        );

        $this->assertTrue($renovacao->isRenewal());
        $this->assertSame($origem->id, $renovacao->renews_viability_request_id);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $renovacao->status);
        $this->assertSame(AnalysisCategory::SemiExpresso, $renovacao->analysis_category);
        $this->assertNotNull($renovacao->protocol_number);
        $this->assertSame($origem->address_street, $renovacao->address_street);
        $this->assertSame(
            $origem->cnaes()->pluck('cnaes.id')->all(),
            $renovacao->cnaes()->pluck('cnaes.id')->all(),
        );
        $this->assertSame('novo@example.com', $origem->company->fresh()->email);
        $this->assertSame('71999990000', $origem->company->fresh()->phone);
        $this->assertTrue(
            ServiceDeclarationAcceptance::query()
                ->where('viability_request_id', $renovacao->id)
                ->where('version', $declaracao->version)
                ->exists(),
        );
    }

    public function test_recusa_quando_falta_declaracao_obrigatoria(): void
    {
        [$origem, , $ator] = $this->cenarioElegivel();

        $this->expectException(DomainException::class);

        app(RenovacaoProtocoloService::class)->protocolar($origem, $ator, [], []);
    }

    public function test_recusa_quando_nao_elegivel(): void
    {
        $origem = ViabilityRequest::factory()->create();
        $ator = User::factory()->create();

        $this->expectException(DomainException::class);

        app(RenovacaoProtocoloService::class)->protocolar($origem, $ator, [], []);
    }

    /**
     * @return array{0: ViabilityRequest, 1: ServiceDeclaration, 2: User}
     */
    private function cenarioElegivel(): array
    {
        $tipo = ViabilityServiceType::factory()->create(['code' => 'renovacao-tvl']);
        $origem = ViabilityRequest::factory()->create([
            'address_street' => 'Rua do TVL',
        ]);
        $origem->forceFill(['status' => ViabilityRequestStatus::Deferida])->save();
        ViabilityDecision::factory()->create([
            'viability_request_id' => $origem->id,
            'tvl_kind' => TvlKind::Provisoria,
            'tvl_valid_until' => '2027-09-28',
        ]);
        $cnae = Cnae::factory()->create();
        $origem->cnaes()->attach($cnae->id, ['is_primary' => true]);
        $declaracao = ServiceDeclaration::factory()->create([
            'service_type_id' => $tipo->id,
            'version' => 1,
            'required' => true,
        ]);

        return [$origem->fresh(), $declaracao, User::factory()->create()];
    }
}
