<?php

namespace App\Services\Sefaz;

use App\Models\Cnae;

/**
 * Compara, só pelo código CNAE, o pedido de alteração de atividade com a
 * viabilidade vinculada ao CNPJ: incluídas, excluídas, mantidas e códigos fora
 * da lista oficial. Não grava nem consulta a SEFAZ; quem chama traz os dois lados.
 */
class ComparacaoCnaesViabilidadeService
{
    /**
     * @param  list<string>  $codigosDoPedido
     * @param  list<string>|null  $codigosDaViabilidade  null quando o CNPJ não tem viabilidade vinculada
     */
    public function comparar(array $codigosDoPedido, ?array $codigosDaViabilidade, ?string $numeroViabilidade = null): ComparacaoCnaesViabilidade
    {
        $pedido = $this->normalizar($codigosDoPedido);
        // O REGIN cria CNAE com seção 'REGIN' quando o código não está na carga
        // oficial; esse registro provisório não conta como código válido.
        $existentes = Cnae::query()->whereIn('code', $pedido)->where('section_description', '!=', 'REGIN')->pluck('code')->all();
        $validos = array_values(array_intersect($pedido, $existentes));
        $invalidas = array_values(array_diff($pedido, $existentes));

        if ($codigosDaViabilidade === null) {
            return new ComparacaoCnaesViabilidade(incluidas: $validos, invalidas: $invalidas, semViabilidade: true);
        }

        $viabilidade = $this->normalizar($codigosDaViabilidade);

        return new ComparacaoCnaesViabilidade(
            incluidas: array_values(array_diff($validos, $viabilidade)),
            excluidas: array_values(array_diff($viabilidade, $pedido)),
            mantidas: array_values(array_intersect($validos, $viabilidade)),
            invalidas: $invalidas,
            numeroViabilidade: $numeroViabilidade,
        );
    }

    /**
     * @param  list<string>  $codigos
     * @return list<string>
     */
    private function normalizar(array $codigos): array
    {
        $digitos = array_map(fn (string $codigo): string => (string) preg_replace('/\D/', '', $codigo), $codigos);

        return array_values(array_unique(array_filter($digitos, fn (string $codigo): bool => $codigo !== '')));
    }
}
