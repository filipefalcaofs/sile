<?php

namespace App\Services\Analise;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\TratamentoCnaeBinding;
use App\Models\TratamentoPergunta;
use App\Models\ViabilityRequest;

/**
 * Projeta a pergunta "A atividade será desenvolvida no local?" na ficha,
 * lendo a resposta já gravada na simulação/planilha. Nunca inventa: sem
 * resposta, a ficha marca pendente.
 */
final class PerguntaLocalFicha
{
    public const PENDENTE = 'Pendente — requerente ainda não respondeu esta pergunta no formulário de solicitação.';

    public const TITULO = 'A atividade será desenvolvida no local?';

    public const SIM = 'Sim, a atividade será desenvolvida no local.';

    public const NAO = 'Não, no local funcionará o escritório da empresa.';

    /** @var list<int> */
    private const PREFERIDAS = [2, 8, 11, 13];

    /**
     * @return array{numero: int|null, pergunta: string, resposta: string|null, pendente: bool}
     */
    public function para(ViabilityRequest $request, string $cnae): array
    {
        $respostas = $request->respostasDaPlanilha($cnae);
        $numero = $this->numeroDaPergunta($cnae, $respostas);
        $valor = $numero !== null && array_key_exists($numero, $respostas)
            ? (bool) $respostas[$numero]
            : null;

        return [
            'numero' => $numero,
            'pergunta' => $this->titulo($numero),
            'resposta' => $valor === null ? null : ($valor ? self::SIM : self::NAO),
            'pendente' => $valor === null,
        ];
    }

    /**
     * @param  array<int, bool>  $respostas
     */
    private function numeroDaPergunta(string $cnae, array $respostas): ?int
    {
        $versao = RuleVersion::vigente(RuleDomain::RiscoTratamento)->first();

        $perguntas = $versao === null
            ? collect()
            : TratamentoCnaeBinding::query()
                ->where('rule_version_id', $versao->getKey())
                ->where('cnae', $this->formatarCnae($cnae))
                ->pluck('perguntas')
                ->flatten()
                ->map(fn (mixed $numero): int => (int) $numero)
                ->filter()
                ->unique()
                ->values();

        foreach (self::PREFERIDAS as $numero) {
            if ($perguntas->contains($numero) || array_key_exists($numero, $respostas)) {
                return $numero;
            }
        }

        return null;
    }

    private function titulo(?int $numero): string
    {
        if ($numero === null) {
            return self::TITULO;
        }

        $versao = RuleVersion::vigente(RuleDomain::RiscoTratamento)->first();

        if ($versao === null) {
            return self::TITULO;
        }

        $texto = TratamentoPergunta::query()
            ->where('rule_version_id', $versao->getKey())
            ->where('numero', $numero)
            ->value('texto');

        if (! is_string($texto) || $texto === '') {
            return self::TITULO;
        }

        $primeira = trim(explode("\n", $texto)[0]);

        return $primeira !== '' ? rtrim($primeira, '?').'?' : self::TITULO;
    }

    private function formatarCnae(string $cnae): string
    {
        $digitos = preg_replace('/\D/', '', $cnae) ?? '';

        if (strlen($digitos) < 7) {
            return $cnae;
        }

        return substr($digitos, 0, 4).'-'.substr($digitos, 4, 1).'/'.substr($digitos, 5, 2);
    }
}
