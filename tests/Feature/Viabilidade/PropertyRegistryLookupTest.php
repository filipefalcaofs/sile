<?php

namespace Tests\Feature\Viabilidade;

use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use App\Services\Realty\UnavailablePropertyRegistryLookup;
use Tests\TestCase;

class PropertyRegistryLookupTest extends TestCase
{
    public function test_provider_real_esta_indisponivel_e_nunca_inventa_ponto(): void
    {
        $lookup = app(PropertyRegistryLookup::class);

        // Binding correto: o provider real resolvido é o INDISPONÍVEL — a base
        // de lotes/Cadastro está pendente SEDUR (HU-033/HU-106).
        $this->assertInstanceOf(UnavailablePropertyRegistryLookup::class, $lookup);

        // Degradação honesta (anti-fachada): sem fonte oficial, NUNCA resolve um
        // ponto — sempre lança unavailable com a inscrição preservada.
        try {
            $lookup->resolve('123456');
            $this->fail('Esperava PropertyRegistryUnavailableException: o provider real nunca inventa ponto.');
        } catch (PropertyRegistryUnavailableException $exception) {
            $this->assertSame('123456', $exception->inscricao);
            $this->assertStringContainsString('pendente da SEDUR', $exception->getMessage());
        }
    }

    public function test_contrato_resolve_inscricao_em_coordenada_com_provider_disponivel(): void
    {
        // Fake que implementa o MESMO contrato: prova que a lógica roda de ponta
        // a ponta quando a base de lotes existir (a Fase 13 troca só o binding).
        $lookup = new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(
                    latitude: -12.97,
                    longitude: -38.50,
                    inscricao: $inscricao,
                    source: 'fake',
                    raw: ['lote' => 'X'],
                );
            }
        };

        $result = $lookup->resolve('999');

        $this->assertInstanceOf(PropertyRegistryResult::class, $result);
        $this->assertSame(-12.97, $result->latitude);
        $this->assertSame(-38.50, $result->longitude);
        $this->assertSame('999', $result->inscricao);
        $this->assertSame('fake', $result->source);
        $this->assertSame([
            'latitude' => -12.97,
            'longitude' => -38.50,
            'inscricao' => '999',
            'source' => 'fake',
            'raw' => ['lote' => 'X'],
            'cadastro' => null,
        ], $result->toArray());
    }

    public function test_excecao_de_inexistencia_existe_no_contrato(): void
    {
        $exception = new PropertyNotFoundException('555');

        $this->assertSame('555', $exception->inscricao);
        $this->assertStringContainsString('555', $exception->getMessage());
    }
}
