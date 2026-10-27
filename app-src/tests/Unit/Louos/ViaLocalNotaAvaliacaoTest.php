<?php

namespace Tests\Unit\Louos;

use App\Support\Louos\ViaLocalPorCentralidade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Nota e observação (a) do Quadro 11A: o resultado diz se a nota liberou a Via
 * Local, se a observação a vedou ou se nenhuma das duas se aplica, e a lista de
 * CNAEs vedados pode vir da configuração.
 */
class ViaLocalNotaAvaliacaoTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function zonasDaNota(): array
    {
        return ['ZCMu' => ['ZCMu'], 'ZCMe' => ['ZCMe'], 'ZCLMu' => ['ZCLMu'], 'ZCLMe' => ['ZCLMe']];
    }

    #[DataProvider('zonasDaNota')]
    public function test_atividade_comum_e_liberada_pela_nota(string $zona): void
    {
        $this->assertSame(ViaLocalPorCentralidade::LIBERADA, ViaLocalPorCentralidade::avaliar($zona, 'VL', '4712-1/00', true));
    }

    #[DataProvider('zonasDaNota')]
    public function test_hospedagem_e_adestramento_sao_vedados_pela_observacao_em_qualquer_zona_da_nota(string $zona): void
    {
        $this->assertSame(ViaLocalPorCentralidade::VEDADA_PELA_OBSERVACAO, ViaLocalPorCentralidade::avaliar($zona, 'VL', '9609-2/07', true));
        $this->assertSame(ViaLocalPorCentralidade::VEDADA_PELA_OBSERVACAO, ViaLocalPorCentralidade::avaliar($zona, 'VL', '8011102', true));
    }

    public function test_fora_da_nota_nao_ha_avaliacao(): void
    {
        $this->assertNull(ViaLocalPorCentralidade::avaliar('ZR-1', 'VL', '9609-2/07', true), 'zona fora da nota');
        $this->assertNull(ViaLocalPorCentralidade::avaliar('ZCMe', 'VC I', '4712-1/00', true), 'via que não é local');
        $this->assertNull(ViaLocalPorCentralidade::avaliar('ZCMe', 'VL', '4712-1/00', false), 'uso residencial');
    }

    public function test_cnae_fora_da_lista_nao_e_vedado_por_inferencia(): void
    {
        $this->assertSame(ViaLocalPorCentralidade::LIBERADA, ViaLocalPorCentralidade::avaliar('ZCMe', 'VL', '9609-2/08', true));
    }

    public function test_lista_de_cnaes_vedados_vem_da_configuracao(): void
    {
        $lista = ['9609208'];

        $this->assertSame(ViaLocalPorCentralidade::VEDADA_PELA_OBSERVACAO, ViaLocalPorCentralidade::avaliar('ZCMe', 'VL', '9609-2/08', true, $lista));
        $this->assertSame(ViaLocalPorCentralidade::LIBERADA, ViaLocalPorCentralidade::avaliar('ZCMe', 'VL', '9609-2/07', true, $lista));
        $this->assertSame(ViaLocalPorCentralidade::LIBERADA, ViaLocalPorCentralidade::avaliar('ZCMe', 'VL', '9609-2/07', true, []));
    }

    public function test_cnaes_da_configuracao_aceitam_texto_separado_por_virgula_e_mascara(): void
    {
        $this->assertSame(['9609207', '8011102', '9609208'], ViaLocalPorCentralidade::cnaesDaConfiguracao(' 9609-2/07, 8011102;;9609-2/08 , '));
        $this->assertSame([], ViaLocalPorCentralidade::cnaesDaConfiguracao(''));
        $this->assertSame([], ViaLocalPorCentralidade::cnaesDaConfiguracao(null));
    }

    public function test_libera_continua_igual_para_quem_so_quer_saber_se_liberou(): void
    {
        $this->assertTrue(ViaLocalPorCentralidade::libera('ZCMe', 'VL', '4712-1/00', true));
        $this->assertFalse(ViaLocalPorCentralidade::libera('ZCMe', 'VL', '9609-2/07', true));
    }
}
