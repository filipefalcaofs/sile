<?php

namespace App\Services\Analise;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\TratamentoCnaeBinding;
use App\Models\TratamentoPergunta;
use App\Models\ViabilityRequest;
use Illuminate\Support\Collection;

/**
 * Projeta na ficha a pergunta do PRÓPRIO CNAE cadastrada na planilha de
 * tratamento (relatório SEDUR 21/09/2026, item 02): o vínculo do CNAE manda —
 * para o 8211-3/00 é a P4 (escritório virtual/coworking). CNAE sem vínculo
 * cai no comportamento legado (pergunta preferida já respondida na simulação).
 * Nunca inventa: sem resposta, a ficha marca pendente.
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
            'resposta' => $valor === null ? null : $this->rotuloResposta($numero, $valor),
            'pendente' => $valor === null,
        ];
    }

    /**
     * A pergunta é a do próprio CNAE: vínculo da planilha vigente primeiro
     * (respondida, senão a de menor número); sem vínculo, o legado das
     * preferidas já respondidas.
     *
     * @param  array<int, bool>  $respostas
     */
    private function numeroDaPergunta(string $cnae, array $respostas): ?int
    {
        $vinculadas = $this->perguntasVinculadas($cnae);

        if ($vinculadas->isNotEmpty()) {
            return $vinculadas->first(fn (int $numero): bool => array_key_exists($numero, $respostas))
                ?? $vinculadas->first();
        }

        foreach (self::PREFERIDAS as $numero) {
            if (array_key_exists($numero, $respostas)) {
                return $numero;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, int>
     */
    private function perguntasVinculadas(string $cnae): Collection
    {
        $versao = RuleVersion::vigente(RuleDomain::RiscoTratamento)->first();

        if ($versao === null) {
            return collect();
        }

        return TratamentoCnaeBinding::query()
            ->where('rule_version_id', $versao->getKey())
            ->where('cnae', $this->formatarCnae($cnae))
            ->pluck('perguntas')
            ->flatten()
            ->map(fn (mixed $numero): int => (int) $numero)
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    private function rotuloResposta(?int $numero, bool $valor): string
    {
        if ($numero === 11) {
            return $valor ? self::SIM : self::NAO;
        }

        return $valor ? 'Sim.' : 'Não.';
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
