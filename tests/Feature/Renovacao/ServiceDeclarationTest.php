<?php

namespace Tests\Feature\Renovacao;

use App\Models\ServiceDeclaration;
use App\Models\ServiceDeclarationAcceptance;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use Database\Seeders\ServiceDeclarationSeeder;
use Database\Seeders\ViabilityServiceTypeSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ServiceDeclarationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lista_so_as_declaracoes_vigentes_do_tipo(): void
    {
        $tipo = ViabilityServiceType::factory()->create(['code' => 'renovacao-tvl']);
        $vigente = ServiceDeclaration::factory()->create([
            'service_type_id' => $tipo->id,
            'body' => 'Declaro que os dados do TVL permanecem os mesmos.',
            'version' => 2,
            'required' => true,
            'valid_from' => now()->subDay(),
            'valid_to' => null,
        ]);
        ServiceDeclaration::factory()->create([
            'service_type_id' => $tipo->id,
            'version' => 1,
            'valid_from' => now()->subYear(),
            'valid_to' => now()->subDay(),
        ]);

        $vigentes = ServiceDeclaration::vigentesPara($tipo->id);

        $this->assertCount(1, $vigentes);
        $this->assertTrue($vigentes->first()->is($vigente));
    }

    public function test_aceite_guarda_a_versao_exibida(): void
    {
        $tipo = ViabilityServiceType::factory()->create(['code' => 'renovacao-tvl']);
        $declaracao = ServiceDeclaration::factory()->create([
            'service_type_id' => $tipo->id,
            'version' => 4,
        ]);
        $solicitacao = ViabilityRequest::factory()->create();
        $usuario = User::factory()->create();

        $aceite = ServiceDeclarationAcceptance::create([
            'service_declaration_id' => $declaracao->id,
            'viability_request_id' => $solicitacao->id,
            'user_id' => $usuario->id,
            'version' => $declaracao->version,
            'accepted_at' => now(),
        ]);

        $this->assertSame(4, $aceite->fresh()->version);
        $this->assertTrue($aceite->declaration->is($declaracao));
    }

    public function test_seeder_publica_declaracao_obrigatoria_da_renovacao(): void
    {
        $this->seed(ViabilityServiceTypeSeeder::class);
        $this->seed(ServiceDeclarationSeeder::class);

        $tipo = ViabilityServiceType::query()->where('code', 'renovacao-tvl')->firstOrFail();
        $vigentes = ServiceDeclaration::vigentesPara($tipo->id);

        $this->assertTrue($vigentes->isNotEmpty());
        $this->assertTrue($vigentes->every(fn (ServiceDeclaration $declaracao): bool => $declaracao->required));
    }
}
