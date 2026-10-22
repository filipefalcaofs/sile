<?php

namespace Tests\Unit\Louos;

use App\Support\Louos\ViaLocalPorCentralidade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ViaLocalPorCentralidadeTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function zonasDeCentralidade(): array
    {
        return [
            'ZCMe' => ['ZCMe'],
            'ZCMu' => ['ZCMu'],
            'ZCLMe' => ['ZCLMe'],
            'ZCLMu' => ['ZCLMu'],
            'caixa e espaços diferentes' => [' zclmu '],
        ];
    }

    #[DataProvider('zonasDeCentralidade')]
    public function test_libera_uso_nao_residencial_em_via_local_nas_zonas_de_centralidade(string $zona): void
    {
        $this->assertTrue(ViaLocalPorCentralidade::libera($zona, 'VL', '4712-1/00', true));
    }

    public function test_nao_libera_fora_das_zonas_de_centralidade(): void
    {
        $this->assertFalse(ViaLocalPorCentralidade::libera('ZR-1', 'VL', '4712-1/00', true));
        $this->assertFalse(ViaLocalPorCentralidade::libera(null, 'VL', '4712-1/00', true));
    }

    public function test_nao_libera_quando_o_uso_nao_e_nao_residencial(): void
    {
        $this->assertFalse(ViaLocalPorCentralidade::libera('ZCMe', 'VL', '4712-1/00', false));
    }

    public function test_so_vale_para_via_local(): void
    {
        $this->assertFalse(ViaLocalPorCentralidade::libera('ZCMe', 'VC I', '4712-1/00', true));
        $this->assertFalse(ViaLocalPorCentralidade::libera('ZCMe', null, '4712-1/00', true));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function atividadesVedadasEmViaLocal(): array
    {
        return [
            'alojamento de animais domésticos' => ['9609-2/07'],
            'sem máscara' => ['9609207'],
            'adestramento de cães de guarda' => ['8011-1/02'],
        ];
    }

    #[DataProvider('atividadesVedadasEmViaLocal')]
    public function test_adestramento_e_hospedagem_de_animais_continuam_vedados_em_via_local(string $cnae): void
    {
        $this->assertFalse(ViaLocalPorCentralidade::libera('ZCMe', 'VL', $cnae, true));
    }

    public function test_banho_e_tosa_nao_e_vedado_pela_observacao(): void
    {
        $this->assertTrue(ViaLocalPorCentralidade::libera('ZCMe', 'VL', '9609-2/08', true));
    }
}
