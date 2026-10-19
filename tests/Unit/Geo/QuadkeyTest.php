<?php

namespace Tests\Unit\Geo;

use App\Services\Geo\Edificacoes\Quadkey;
use PHPUnit\Framework\TestCase;

class QuadkeyTest extends TestCase
{
    public function test_bbox_de_salvador_nivel_9(): void
    {
        $quadkeys = Quadkey::cobrindo(-38.70, -13.02, -38.29, -12.73);

        $this->assertNotEmpty($quadkeys);
        $this->assertSame($quadkeys, array_values(array_unique($quadkeys)));

        foreach ($quadkeys as $quadkey) {
            $this->assertMatchesRegularExpression('/^[0-3]{9}$/', $quadkey);
        }

        foreach (Quadkey::cobrindo(-38.501, -12.971, -38.499, -12.969) as $doCentro) {
            $this->assertContains($doCentro, $quadkeys);
        }
    }

    public function test_ponto_conhecido(): void
    {
        $this->assertSame(['1'], Quadkey::cobrindo(0.0001, 0.0001, 0.0002, 0.0002, 1));
        $this->assertSame(['2'], Quadkey::cobrindo(-0.0002, -0.0002, -0.0001, -0.0001, 1));
        $this->assertSame(['0', '1', '2', '3'], Quadkey::cobrindo(-1, -1, 1, 1, 1));
    }
}
