<?php

namespace App\Services\Expresso;

use App\Enums\CondicaoServico;
use App\Enums\DecisionOutcome;
use App\Models\ServicoNomenclatura;
use App\Models\TvlSequence;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Gera o número de produto da Viabilidade {prefixo}-{N} (HU-072 RN-007), com N sequencial
 * único e SEM reinício anual, a partir de viabiliza.expresso.tvl.inicio (3000000). O
 * N é o mesmo número que segue à SEFAZ (ViabilityDecision::numeroSefaz), então
 * não pode repetir entre anos. O contador (tvl_sequences, linha CONTADOR_UNICO)
 * é travado com lockForUpdate dentro de uma transação, então incrementado — duas
 * gerações concorrentes serializam no Postgres real (no-op em SQLite; a
 * unicidade lógica roda na suíte SQLite e a concorrência em @group postgis).
 * tvl_product_number não é único: a alteração sem mudança de endereço repete o
 * número da viabilidade anterior (paraProcesso). O gerador NÃO grava em
 * viability_decisions — só sequencia. O caller usa o número dentro da transação
 * da decisão, no deferimento.
 *
 * Prefixo parametrizável (HU-014) com DEFAULT INLINE (não depende do banco nem
 * do seeder): expresso.tvl.prefixo (TVL) e .padding (6, sem efeito acima de 6
 * dígitos).
 *
 * Espelha o ProtocolNumberGenerator.
 */
class TvlNumberGenerator
{
    /** Chave (coluna year) da linha única do contador, que não reinicia por ano. */
    public const CONTADOR_UNICO = 0;

    public function generate(): string
    {
        $number = DB::transaction(function (): int {
            $sequence = TvlSequence::query()
                ->where('year', self::CONTADOR_UNICO)
                ->lockForUpdate()
                ->first()
                ?? TvlSequence::query()->create([
                    'year' => self::CONTADOR_UNICO,
                    'last_number' => self::numeroInicial() - 1,
                ]);

            $sequence->increment('last_number');

            return (int) $sequence->last_number;
        });

        return $this->format($number);
    }

    /**
     * Número do produto da Viabilidade do processo (regra da SEDUR, 04/11). Alteração
     * sem mudança de endereço (atividade incluída ou excluída, revisão, Revisão de
     * Espaço) mantém o número da viabilidade anterior da empresa; alteração de
     * endereço, inscrição, processo sem evento da JUCEB ou empresa sem viabilidade
     * anterior recebem número novo.
     */
    public function paraProcesso(ViabilityRequest $request): string
    {
        if ($this->mantemNumeroAnterior($request)) {
            $anterior = $this->numeroAnterior($request);

            if ($anterior !== null) {
                return $anterior;
            }
        }

        return $this->generate();
    }

    /**
     * Só alteração da JUCEB sem mudança de localização. O evento de endereço com a
     * condição Revisão de Espaço (complemento, CEP, área) não muda o endereço; sem
     * condição detectada vale o que a JUCEB declarou: mudança de endereço.
     */
    private function mantemNumeroAnterior(ViabilityRequest $request): bool
    {
        $eventos = $request->eventos()->get(['nome', 'condicao']);

        if ($eventos->isEmpty()) {
            return false;
        }

        foreach ($eventos as $evento) {
            $chave = ServicoNomenclatura::chaveDe((string) $evento->nome);

            if (str_starts_with($chave, 'inscricao')) {
                return false;
            }

            if (str_starts_with($chave, 'alteracao de endereco') && $evento->condicao !== CondicaoServico::EnderecoEspaco->value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Última viabilidade deferida da empresa (mesmo CNPJ) no Viabiliza; sem ela, o
     * número da viabilidade anterior gravado na comparação de CNAEs (legado, SEFAZ).
     */
    private function numeroAnterior(ViabilityRequest $request): ?string
    {
        $cnpj = (string) $request->company?->cnpj;

        if ($cnpj !== '') {
            $numero = ViabilityDecision::query()
                ->where('outcome', DecisionOutcome::Deferida->value)
                ->whereNotNull('tvl_product_number')
                ->where('viability_request_id', '!=', $request->id)
                ->whereHas('viabilityRequest.company', fn ($empresa) => $empresa->where('cnpj', $cnpj))
                ->latest('id')
                ->value('tvl_product_number');

            if (is_string($numero) && $numero !== '') {
                return $numero;
            }
        }

        $numero = trim((string) ($request->comparacao_cnaes['numero_viabilidade'] ?? ''));

        if (preg_match('/^[A-Za-z]+-\d+$/', $numero) === 1) {
            return $numero;
        }

        return preg_match('/^\d+$/', $numero) === 1 ? $this->format((int) $numero) : null;
    }

    public function format(int $number): string
    {
        $prefix = (string) Settings::get('expresso.tvl.prefixo', config('viabiliza.expresso.tvl.prefixo', 'TVL'));
        $padding = (int) Settings::get('expresso.tvl.padding', config('viabiliza.expresso.tvl.padding', 6));

        return sprintf('%s-%s', $prefix, str_pad((string) $number, $padding, '0', STR_PAD_LEFT));
    }

    public static function numeroInicial(): int
    {
        return (int) config('viabiliza.expresso.tvl.inicio', 3_000_000);
    }
}
