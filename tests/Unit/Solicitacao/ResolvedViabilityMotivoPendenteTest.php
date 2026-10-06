<?php

namespace Tests\Unit\Solicitacao;

use App\Services\Solicitacao\ResolvedViability;
use PHPUnit\Framework\TestCase;

class ResolvedViabilityMotivoPendenteTest extends TestCase
{
    /**
     * @param  list<array<string, mixed>>  $porCnae
     */
    private function viabilidade(array $porCnae): ResolvedViability
    {
        return new ResolvedViability($porCnae, 'pendente', [], null, null);
    }

    public function test_devolve_o_motivo_do_cnae_pendente(): void
    {
        $resolvida = $this->viabilidade([
            ['cnae' => '4712100', 'tendencia' => 'permitido', 'veredito_motivo' => 'Permitido na zona'],
            ['cnae' => '0111301', 'tendencia' => 'pendente', 'veredito_motivo' => 'Atividade sem enquadramento na planilha vigente — segue para análise técnica'],
        ]);

        $this->assertSame(
            'Atividade sem enquadramento na planilha vigente — segue para análise técnica',
            $resolvida->motivoPendente(),
        );
    }

    public function test_prefere_o_motivo_especifico_do_enquadramento(): void
    {
        $resolvida = $this->viabilidade([
            [
                'cnae' => '0111301',
                'tendencia' => 'pendente',
                'veredito_motivo' => 'Atividade sem enquadramento na planilha vigente — segue para análise técnica',
                'enquadramento_motivo' => 'Pergunta 11 sem resposta',
            ],
        ]);

        $this->assertSame('Pergunta 11 sem resposta', $resolvida->motivoPendente());
    }

    public function test_sem_cnae_pendente_ou_sem_motivo_devolve_nulo(): void
    {
        $this->assertNull($this->viabilidade([['cnae' => '1', 'tendencia' => 'permitido', 'veredito_motivo' => 'x']])->motivoPendente());
        $this->assertNull($this->viabilidade([['cnae' => '1', 'tendencia' => 'pendente', 'veredito_motivo' => '  ']])->motivoPendente());
    }
}
