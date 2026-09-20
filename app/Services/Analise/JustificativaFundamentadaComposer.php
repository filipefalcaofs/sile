<?php

namespace App\Services\Analise;

use App\Enums\Fluxo;
use App\Enums\Quadro10Permissao;
use App\Enums\ResultadoViabilidade;
use App\Models\Cnae;
use App\Services\Decisao\DecisionTextCatalog;
use App\Services\Viabilidade\ConsultaViabilidadeResult;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * Redige a justificativa por CNAE no tom de um parecer técnico: só os fatos
 * já produzidos pelo motor (território, enquadramento de uso, Quadros 10/11-A, risco). Nunca
 * inventa zona, grupo, permissão ou desfecho.
 */
class JustificativaFundamentadaComposer
{
    public function __construct(private DecisionTextCatalog $textos) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public function paraConsulta(ConsultaViabilidadeResult $consulta, array $item = []): string
    {
        return $this->redigir($this->fatosDaConsulta($consulta, $item));
    }

    /**
     * @param  array<string, mixed>  $consulta
     * @param  array<string, mixed>  $item
     */
    public function paraSnapshot(array $consulta, array $item = []): string
    {
        return $this->redigir($this->fatosDoSnapshot($consulta, $item));
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function fatosDaConsulta(ConsultaViabilidadeResult $consulta, array $item): array
    {
        $territorio = $consulta->territory;
        $enquadramento = $consulta->enquadramento;
        $risco = $consulta->risco;

        return $this->normalizar([
            'cnae' => $item['cnae'] ?? $consulta->entrada['cnae'] ?? null,
            'cnae_formatado' => $item['cnae_formatado'] ?? $consulta->entrada['cnae_formatado'] ?? null,
            'descricao' => $item['descricao'] ?? null,
            'is_primary' => $item['is_primary'] ?? false,
            'area' => $consulta->entrada['area'] ?? null,
            'bairro' => $territorio?->bairro ?? [],
            'via' => $territorio?->via ?? [],
            'zona' => $territorio?->zona ?? [],
            'enquadramento' => $enquadramento->enquadramento,
            'quadro10' => $enquadramento->quadro10,
            'quadro11a' => $enquadramento->quadro11a,
            'consolidado' => $enquadramento->consolidado,
            'municipal' => $risco->municipal,
            'sanitario' => $risco->sanitario,
            'encaminhamento' => $risco->encaminhamento,
            'fundamentacao' => $consulta->fundamentacao(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $consulta
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function fatosDoSnapshot(array $consulta, array $item): array
    {
        $territorio = is_array($consulta['territorio'] ?? null) ? $consulta['territorio'] : [];
        $enquadramento = is_array($consulta['enquadramento'] ?? null) ? $consulta['enquadramento'] : [];
        $risco = is_array($consulta['risco'] ?? null) ? $consulta['risco'] : [];
        $entrada = is_array($consulta['entrada'] ?? null) ? $consulta['entrada'] : [];
        $fundamentacao = $consulta['fundamentacao'] ?? [];

        return $this->normalizar([
            'cnae' => $item['cnae'] ?? $entrada['cnae'] ?? null,
            'cnae_formatado' => $item['cnae_formatado'] ?? $entrada['cnae_formatado'] ?? null,
            'descricao' => $item['descricao'] ?? null,
            'is_primary' => $item['is_primary'] ?? false,
            'area' => $entrada['area'] ?? null,
            'bairro' => $territorio['bairro'] ?? [],
            'via' => $territorio['via'] ?? [],
            'zona' => $territorio['zona'] ?? [],
            'enquadramento' => $enquadramento['enquadramento'] ?? [],
            'quadro10' => $enquadramento['quadro10'] ?? [],
            'quadro11a' => $enquadramento['quadro11a'] ?? [],
            'consolidado' => $enquadramento['consolidado'] ?? [],
            'municipal' => $risco['municipal'] ?? [],
            'sanitario' => $risco['sanitario'] ?? [],
            'encaminhamento' => $risco['encaminhamento'] ?? [],
            'fundamentacao' => is_array($fundamentacao) ? $fundamentacao : [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $bruto
     * @return array<string, mixed>
     */
    private function normalizar(array $bruto): array
    {
        $cnae = is_string($bruto['cnae'] ?? null) ? $bruto['cnae'] : '';
        $formatado = is_string($bruto['cnae_formatado'] ?? null) && $bruto['cnae_formatado'] !== ''
            ? $bruto['cnae_formatado']
            : $this->formatarCnae($cnae);
        $descricao = is_string($bruto['descricao'] ?? null) && trim($bruto['descricao']) !== ''
            ? trim($bruto['descricao'])
            : $this->descricaoCnae($cnae);

        return [
            'cnae_formatado' => $formatado !== '' ? $formatado : $cnae,
            'descricao' => $descricao,
            'rotulo' => ($bruto['is_primary'] ?? false) ? 'principal' : 'secundária',
            'area' => $this->formatarArea($bruto['area'] ?? null),
            'bairro' => $this->nomeIdentificado($bruto['bairro'] ?? []),
            'via' => $this->nomeIdentificado($bruto['via'] ?? []),
            'zona' => $this->nomeIdentificado($bruto['zona'] ?? []),
            'zona_motivo' => $this->texto($bruto['zona']['motivo'] ?? null),
            'enquadramento' => is_array($bruto['enquadramento'] ?? null) ? $bruto['enquadramento'] : [],
            'quadro10' => is_array($bruto['quadro10'] ?? null) ? $bruto['quadro10'] : [],
            'quadro11a' => is_array($bruto['quadro11a'] ?? null) ? $bruto['quadro11a'] : [],
            'consolidado' => is_array($bruto['consolidado'] ?? null) ? $bruto['consolidado'] : [],
            'municipal' => is_array($bruto['municipal'] ?? null) ? $bruto['municipal'] : [],
            'sanitario' => is_array($bruto['sanitario'] ?? null) ? $bruto['sanitario'] : [],
            'encaminhamento' => is_array($bruto['encaminhamento'] ?? null) ? $bruto['encaminhamento'] : [],
            'fundamentacao' => array_values(array_unique(array_filter(
                $bruto['fundamentacao'] ?? [],
                fn (mixed $ref): bool => is_string($ref) && trim($ref) !== '',
            ))),
        ];
    }

    /**
     * @param  array<string, mixed>  $fatos
     */
    private function redigir(array $fatos): string
    {
        $paragrafos = array_filter([
            $this->objeto($fatos),
            $this->territorio($fatos),
            $this->enquadramentoUso($fatos),
            $this->quadro10($fatos),
            $this->quadro11a($fatos),
            $this->risco($fatos),
            $this->condicionantes($fatos),
            $this->conclusao($fatos),
            $this->fundamentacao($fatos),
        ], fn (?string $paragrafo): bool => $paragrafo !== null && $paragrafo !== '');

        return implode("\n\n", $paragrafos);
    }

    /**
     * @param  array<string, mixed>  $fatos
     */
    private function objeto(array $fatos): string
    {
        $atividade = 'a atividade CNAE '.$fatos['cnae_formatado'];

        if (is_string($fatos['descricao'])) {
            $atividade .= ' — '.$fatos['descricao'];
        }

        $atividade .= ' ('.$fatos['rotulo'].')';

        if (is_string($fatos['area'])) {
            $atividade .= ', com área de '.$fatos['area'].' m²';
        }

        return 'Analisa-se '.$atividade.', em face da Lei nº 9.148/2016 (LOUOS) e do zoneamento urbanístico do Município de Salvador.';
    }

    /**
     * @param  array<string, mixed>  $fatos
     */
    private function territorio(array $fatos): string
    {
        $frases = [];

        if (is_string($fatos['bairro']) && is_string($fatos['zona'])) {
            $frases[] = 'O imóvel situa-se no bairro '.$fatos['bairro'].', zona urbanística '.$fatos['zona'].'.';
        } elseif (is_string($fatos['zona'])) {
            $frases[] = 'A zona urbanística identificada é '.$fatos['zona'].'.';
        } elseif (is_string($fatos['zona_motivo'])) {
            $frases[] = 'A zona urbanística não foi identificada na base oficial: '.$this->encerrar($fatos['zona_motivo']).' Sem zona, o Quadro 10 da LOUOS não autoriza nem proíbe o uso.';
        } elseif (is_string($fatos['bairro'])) {
            $frases[] = 'O imóvel situa-se no bairro '.$fatos['bairro'].'. A zona urbanística não foi identificada.';
        }

        if (is_string($fatos['via'])) {
            $frases[] = 'A via de acesso identificada é '.$fatos['via'].'.';
        }

        return implode(' ', $frases);
    }

    /**
     * @param  array<string, mixed>  $fatos
     */
    private function enquadramentoUso(array $fatos): string
    {
        $uso = $fatos['enquadramento'];
        $status = $uso['status'] ?? null;
        $grupo = $this->texto($uso['grupo'] ?? null);

        if ($status === 'identificado' && $grupo !== null) {
            $subgrupo = $this->texto($uso['subgrupo'] ?? null);
            $detalhe = $subgrupo !== null ? ' (subgrupo '.$subgrupo.')' : '';

            return 'Pelo enquadramento de uso da planilha vigente, a atividade classifica-se no grupo de uso '.$grupo.$detalhe.'. O enquadramento de uso apenas classifica o CNAE segundo a área ocupada; não autoriza a instalação na zona.';
        }

        $motivo = $this->texto($uso['motivo'] ?? null);

        if ($motivo !== null) {
            return 'Pelo enquadramento de uso da planilha vigente, '.$this->iniciarMinusculo($motivo).' Sem classificação de grupo, o Quadro 10 não pode autorizar nem proibir o uso no território.';
        }

        return 'A atividade não foi enquadrada na planilha vigente. Sem grupo de uso, o Quadro 10 não se aplica.';
    }

    /**
     * @param  array<string, mixed>  $fatos
     */
    private function quadro10(array $fatos): string
    {
        $quadro = $fatos['quadro10'];
        $status = $quadro['status'] ?? null;
        $grupo = $this->texto($fatos['enquadramento']['grupo'] ?? null) ?? 'o grupo enquadrado';
        $zona = $fatos['zona'] ?? 'a zona identificada';
        $permissao = $this->rotuloPermissao($quadro['permissao'] ?? null);

        if ($status === 'identificado' && $permissao !== null) {
            return 'Pelo Quadro 10 da LOUOS, o grupo '.$grupo.' é '.mb_strtolower($permissao).' na zona '.$zona.'. É este quadro que autoriza ou proíbe o uso no território.';
        }

        $motivo = $this->texto($quadro['motivo'] ?? null);

        if ($motivo !== null) {
            return 'Quanto ao Quadro 10 da LOUOS, '.$this->iniciarMinusculo($motivo);
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $fatos
     */
    private function quadro11a(array $fatos): ?string
    {
        $quadro = $fatos['quadro11a'];

        if (($quadro['status'] ?? null) !== 'identificado') {
            return null;
        }

        $motivo = $this->texto($quadro['motivo'] ?? null);
        $condicoes = $this->textosCondicionantes(is_array($quadro['condicoes'] ?? null) ? $quadro['condicoes'] : []);

        if ($motivo === null && $condicoes === []) {
            return null;
        }

        $texto = $motivo !== null
            ? $motivo
            : 'O Quadro 11-A da LOUOS condiciona a instalação pela via identificada.';

        if ($condicoes !== []) {
            $texto = $this->encerrar($texto).' Condições incidentes: '.implode('; ', $condicoes).'.';
        }

        return $this->encerrar($texto).' O Quadro 11-A pode vedar o uso (Não), encaminhar à CNLU (R) ou condicionar a instalação pela via.';
    }

    /**
     * @param  array<string, mixed>  $fatos
     */
    private function risco(array $fatos): ?string
    {
        $frases = [];
        $municipal = $fatos['municipal'];
        $sanitario = $fatos['sanitario'];

        if (($municipal['status'] ?? null) === 'classificado') {
            $nivel = $this->rotuloNivel($municipal['nivel_label'] ?? $municipal['nivel'] ?? null);

            if ($nivel !== null) {
                $frases[] = 'Quanto ao risco, o Decreto Municipal nº 32.636/2020 classifica a atividade no nível '.$nivel.' (dimensão municipal).';
            }
        }

        if (($sanitario['status'] ?? null) === 'classificado') {
            $nivel = $this->rotuloNivel($sanitario['nivel_final'] ?? $sanitario['nivel_label'] ?? null);

            if ($nivel !== null) {
                $frases[] = 'Na dimensão sanitária, o nível resultante é '.$nivel.'.';
            }
        }

        $encaminhamento = $fatos['encaminhamento'];
        $fluxo = Fluxo::tryFrom((string) ($encaminhamento['fluxo'] ?? ''))?->label();
        $motivo = $this->texto($encaminhamento['motivo'] ?? null);

        if ($fluxo !== null) {
            $frases[] = $motivo !== null
                ? 'O encaminhamento é '.$fluxo.': '.$this->encerrar($this->humanizarMotivoRisco($motivo))
                : 'O encaminhamento é '.$fluxo.'.';
        }

        return $frases === [] ? null : implode(' ', $frases);
    }

    /**
     * @param  array<string, mixed>  $fatos
     */
    private function condicionantes(array $fatos): ?string
    {
        $textos = $this->textosCondicionantes($fatos['consolidado']['condicionantes'] ?? []);

        if ($textos === []) {
            return null;
        }

        return 'Incidem as seguintes condicionantes, a serem observadas no licenciamento: '.implode('; ', $textos).'.';
    }

    /**
     * @param  array<string, mixed>  $fatos
     */
    private function conclusao(array $fatos): string
    {
        $resultado = (string) ($fatos['consolidado']['resultado'] ?? '');

        $chave = match ($resultado) {
            ResultadoViabilidade::Permitido->value => 'justificativa.conclusao.permitido',
            ResultadoViabilidade::PermitidoComCondicoes->value => 'justificativa.conclusao.permitido_com_condicoes',
            ResultadoViabilidade::NaoPermitido->value => $this->chaveConclusaoNaoPermitido($fatos),
            default => 'justificativa.conclusao.padrao',
        };

        return $this->textos->render($chave, [
            ':zona' => $fatos['zona'] ?? 'a zona identificada',
            ':classe_via' => $this->classeVia($fatos),
        ]);
    }

    /**
     * Atribui o indeferimento ao quadro que de fato vedou, espelhando a
     * precedência do motor: o Quadro 10 proibindo na zona decide primeiro; só
     * quando ele permite é que o veto pode vir da via (Quadro 11-A).
     *
     * @param  array<string, mixed>  $fatos
     */
    private function chaveConclusaoNaoPermitido(array $fatos): string
    {
        if (($fatos['quadro10']['permissao'] ?? null) === Quadro10Permissao::Proibido->value) {
            return 'justificativa.conclusao.nao_permitido';
        }

        if ($this->viaVedada($fatos['quadro11a'])) {
            return 'justificativa.conclusao.nao_permitido_via';
        }

        return 'justificativa.conclusao.nao_permitido';
    }

    /**
     * A via veda o uso quando o Quadro 11-A responde "Não" para a combinação
     * classe viária × grupo (matriz oficial: Não veda, R vai à CNLU).
     *
     * @param  array<string, mixed>  $quadro11a
     */
    private function viaVedada(array $quadro11a): bool
    {
        if (($quadro11a['status'] ?? null) !== 'identificado') {
            return false;
        }

        foreach (($quadro11a['condicoes'] ?? []) as $condicao) {
            if (! is_string($condicao)) {
                continue;
            }

            $norm = strtr(mb_strtolower(trim($condicao)), ['ã' => 'a', 'á' => 'a']);

            if ($norm === 'nao') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $fatos
     */
    private function classeVia(array $fatos): string
    {
        $classeVia = $this->texto($fatos['quadro11a']['classe_via'] ?? null);

        return $classeVia ?? 'identificada';
    }

    /**
     * @param  array<string, mixed>  $fatos
     */
    private function fundamentacao(array $fatos): ?string
    {
        $refs = $fatos['fundamentacao'];

        if ($refs === []) {
            $refs = [$this->textos->get('base_legal.louos')];
        }

        return 'Fundamentação: '.implode('; ', $refs).'.';
    }

    /**
     * @param  list<mixed>|mixed  $condicionantes
     * @return list<string>
     */
    private function textosCondicionantes(mixed $condicionantes): array
    {
        if (! is_array($condicionantes)) {
            return [];
        }

        $textos = [];

        foreach ($condicionantes as $item) {
            if (is_string($item) && trim($item) !== '') {
                $textos[] = $this->encerrar(trim($item), strip: true);

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            if (($item['tipo'] ?? null) === 'vagas' && ($item['exigido'] ?? null) === null) {
                continue;
            }

            $motivo = trim((string) ($item['motivo'] ?? ''));

            if ($motivo !== '') {
                $textos[] = $this->encerrar($motivo, strip: true);
            }
        }

        return array_values(array_unique($textos));
    }

    /**
     * @param  array<string, mixed>  $dimensao
     */
    private function nomeIdentificado(array $dimensao): ?string
    {
        if (($dimensao['status'] ?? null) !== 'identificado') {
            return null;
        }

        return $this->texto($dimensao['nome'] ?? null);
    }

    private function rotuloNivel(mixed $nivel): ?string
    {
        $texto = $this->texto($nivel);

        if ($texto === null) {
            return null;
        }

        return match (mb_strtolower($texto)) {
            'baixo', 'baixo_a' => 'Baixo',
            'medio', 'médio', 'baixo_b' => 'Médio',
            'alto' => 'Alto',
            default => $texto,
        };
    }

    private function humanizarMotivoRisco(string $motivo): string
    {
        return strtr($motivo, [
            'baixo_a' => 'Baixo',
            'baixo_b' => 'Médio',
            'baixo_c' => 'Baixo',
        ]);
    }

    private function rotuloPermissao(mixed $permissao): ?string
    {
        if (! is_string($permissao) || $permissao === '') {
            return null;
        }

        return Quadro10Permissao::tryFrom($permissao)?->label() ?? $permissao;
    }

    private function descricaoCnae(string $cnae): ?string
    {
        $digitos = (string) preg_replace('/\D/', '', $cnae);

        if ($digitos === '') {
            return null;
        }

        try {
            if (! Schema::hasTable((new Cnae)->getTable())) {
                return null;
            }

            $descricao = Cnae::query()->where('code', $digitos)->value('description');
        } catch (QueryException) {
            return null;
        }

        return is_string($descricao) && trim($descricao) !== '' ? trim($descricao) : null;
    }

    private function formatarCnae(string $cnae): string
    {
        $digitos = (string) preg_replace('/\D/', '', $cnae);

        if (strlen($digitos) !== 7) {
            return $cnae;
        }

        return substr($digitos, 0, 4).'-'.substr($digitos, 4, 1).'/'.substr($digitos, 5, 2);
    }

    private function formatarArea(mixed $area): ?string
    {
        if (! is_numeric($area)) {
            return null;
        }

        $numero = (float) $area;

        if (abs($numero - (int) $numero) < 0.001) {
            return (string) (int) $numero;
        }

        return number_format($numero, 2, ',', '.');
    }

    private function texto(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $texto = trim($valor);

        return $texto === '' ? null : $texto;
    }

    private function encerrar(string $texto, bool $strip = false): string
    {
        $texto = rtrim($texto, " \t\n\r\0\x0B.");

        return $strip ? $texto : $texto.'.';
    }

    private function iniciarMinusculo(string $texto): string
    {
        $texto = $this->encerrar($texto);
        $primeira = mb_substr($texto, 0, 1);
        $segunda = mb_substr($texto, 1, 1);

        if ($segunda !== '' && mb_strtoupper($segunda) === $segunda && preg_match('/\p{L}/u', $segunda) === 1) {
            return $texto;
        }

        return mb_strtolower($primeira).mb_substr($texto, 1);
    }
}
