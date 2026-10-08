<?php

namespace Tests\Unit\Louos;

use App\Services\Louos\CondicionanteEscritorioVirtual;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CondicionanteEscritorioVirtualTest extends TestCase
{
    private const APOIO = 'Os serviços constantes na CNAE 8211-3/00 referem-se aos serviços de apoio administrativo, não estando autorizada a prestação de serviço de escritório virtual.';

    private const COWORKING = 'Trata-se de Coworking, não estando autorizada a prestação de serviço de escritório virtual.';

    /**
     * @return array<string, array{0: string, 1: array<int, mixed>, 2: ?string}>
     */
    public static function casos(): array
    {
        return [
            'não, arquivamento (opção 1)' => ['8211-3/00', [4 => false, 5 => [1]], self::APOIO],
            'não, folha de pagamento (opção 4)' => ['8211-3/00', [4 => false, 5 => [4]], self::APOIO],
            'não, apoio misturado com coworking' => ['8211-3/00', [4 => false, 5 => [2, 5]], self::APOIO],
            'não, só coworking' => ['8211-3/00', [4 => false, 5 => [5]], self::COWORKING],
            'sim: a condicionante da sede vem do deferimento' => ['8211-3/00', [4 => true], null],
            'não, sem resposta da pergunta 5' => ['8211-3/00', [4 => false], null],
            'pergunta 4 sem resposta' => ['8211-3/00', [5 => [1]], null],
            'outro CNAE' => ['4711-3/02', [4 => false, 5 => [1]], null],
            'CNAE só com dígitos' => ['8211300', [4 => false, 5 => [5]], self::COWORKING],
        ];
    }

    /**
     * @param  array<int, mixed>  $respostas
     */
    #[DataProvider('casos')]
    public function test_condicionante_conforme_as_respostas_das_perguntas_4_e_5(string $cnae, array $respostas, ?string $esperada): void
    {
        $this->assertSame($esperada, app(CondicionanteEscritorioVirtual::class)->texto($cnae, $respostas));
    }
}
