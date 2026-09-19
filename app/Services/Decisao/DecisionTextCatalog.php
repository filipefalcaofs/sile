<?php

namespace App\Services\Decisao;

use App\Models\DecisionText;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Catálogo dos textos decisórios emitidos em documentos oficiais (TVL,
 * parecer, ficha do cidadão): os ~40 textos que viviam em constantes PHP dos
 * motores passam a ser administráveis por chave estável, com efeito sem
 * deploy (cache invalidado na escrita do model, padrão PropertyType).
 *
 * Resolução de get(): banco (via cache) → defaults() de fábrica. O fallback
 * aos defaults vale com o banco INALCANÇÁVEL (QueryException — build/CI) ou
 * com a chave ainda não seedada; chave desconhecida nas duas fontes lança
 * InvalidArgumentException (chave ausente é bug, não estado de runtime).
 *
 * Templates com placeholders `:nome` interpolados por strtr em render(): o
 * placeholder não fornecido permanece literal no texto (visível, honesto) —
 * nunca inventado nem escondido.
 */
final class DecisionTextCatalog
{
    /** @var array<string, true> */
    private array $reportedMissing = [];

    /**
     * Texto vigente da chave: banco (edição administrada) ou fábrica.
     *
     * @throws InvalidArgumentException Chave desconhecida no banco e nos defaults.
     */
    public function get(string $key): string
    {
        $textos = $this->catalogo();

        if (array_key_exists($key, $textos)) {
            return $textos[$key];
        }

        $defaults = self::defaults();

        if (array_key_exists($key, $defaults)) {
            // Banco alcançável mas sem a chave (deploy sem o seed): o documento
            // sai com o texto de fábrica — correto — mas a edição administrada
            // não está valendo. Reportar uma vez por chave por instância.
            if (! isset($this->reportedMissing[$key])) {
                $this->reportedMissing[$key] = true;
                Log::warning("Texto decisório ausente no banco; emitindo o texto de fábrica: {$key}");
            }

            return $defaults[$key];
        }

        throw new InvalidArgumentException("Texto decisório desconhecido: {$key}");
    }

    /**
     * Interpola os placeholders `:nome` do template. O não fornecido permanece
     * literal — visível no documento, nunca substituído por valor inventado.
     *
     * @param  array<string, string>  $placeholders
     */
    public function render(string $key, array $placeholders = []): string
    {
        return strtr($this->get($key), $placeholders);
    }

    /**
     * Mapa chave → template lido do banco com cache invalidado na escrita do
     * model. Banco inalcançável degrada para os defaults de fábrica — e o
     * fallback é reportado, nunca silencioso (muda o texto de documento oficial).
     *
     * @return array<string, string>
     */
    private function catalogo(): array
    {
        try {
            /** @var array<string, string> */
            return Cache::remember(
                DecisionText::CACHE_KEY,
                (int) config('sile.parameters.cache_ttl', 300),
                fn () => DecisionText::query()->pluck('template', 'key')->all(),
            );
        } catch (QueryException|\Exception $e) {
            report($e);

            return self::defaults();
        }
    }

    /**
     * Textos de fábrica — o conteúdo EXATO das constantes que viviam no código
     * (verbatim, incluindo pontuação e espaços), extraído na Fase 4. Fonte
     * única: o DecisionTextSeeder lê daqui, sem duplicar strings. A paridade
     * byte-idêntica com as constantes é golden test permanente
     * (DecisionTextSeederTest).
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            // Bases legais citadas nas fundamentações.
            'base_legal.louos' => 'Lei nº 9.148/2016 (LOUOS)',
            'base_legal.risco_municipal' => 'Decreto Municipal nº 32.636/2020',

            // LOUOS — motivos das dimensões e do consolidado
            // (LouosEnquadramentoService, constantes das linhas 38-62).
            'louos.motivo.sem_enquadramento_consolidado' => 'Atividade sem enquadramento na planilha vigente — segue para análise técnica',
            'louos.motivo.proibido' => 'Atividade proibida na zona pelo Quadro 10',
            'louos.motivo.permitido' => 'Atividade permitida na zona, sem condicionantes incidentes',
            'louos.motivo.permitido_com_condicoes' => 'Atividade permitida na zona mediante observância das condicionantes',
            'louos.motivo.zona_pendente' => 'Permissão por zona pendente da base oficial (SEDUR)',
            'louos.motivo.sem_enquadramento' => 'Sem enquadramento de uso não há permissão a verificar',
            'louos.motivo.quadro10_sem_versao' => 'Quadro 10 sem versão vigente',
            'louos.motivo.zona_sem_regra' => 'Combinação zona × grupo de uso sem regra no Quadro 10 vigente',
            'louos.motivo.via_pendente' => 'Condições pela via dependem da classificação viária LOUOS (pendente SEDUR)',
            'louos.motivo.via_sem_atributo' => 'Via identificada, porém sem o atributo de classificação viária LOUOS (pendente SEDUR)',
            'louos.motivo.via_sem_versao' => 'Quadro de condições pela via sem versão vigente',
            'louos.motivo.via_sem_regra' => 'Classe viária × grupo de uso sem regra no quadro de via vigente',
            'louos.motivo.via_vedada' => 'Uso vedado na classe da via pelo Quadro 11A da LOUOS',
            'louos.motivo.via_cnlu' => 'Quadro 11A encaminha à CNLU (R)',

            // LOUOS — templates dos motivos compostos (sprintf → :nome; a
            // formatação de CNAE/área/faixa permanece em código). O template
            // pai carrega o ponto final da :condicao — o motor aplica
            // rtrim(..., '.') ao embutir o motivo, exatamente como hoje.
            'louos.template.enquadramento' => 'O CNAE :cnae com área :area m² enquadra-se no grupo :grupo:subgrupo da LOUOS (:codigo_louos). O enquadramento não autoriza o uso na zona — só define o grupo.',
            'louos.template.quadro10' => 'O grupo :grupo é :permissao na zona :zona segundo o Quadro 10 da LOUOS — é este quadro que permite ou proíbe o uso no território.',
            'louos.template.permitido' => 'Permitido: o CNAE :cnae (área :area m²) classificou-se no grupo :grupo pelo enquadramento da planilha vigente e esse grupo é permitido na zona :zona pelo Quadro 10. :condicao.',
            'louos.template.nao_permitido' => 'Não permitido: o CNAE :cnae (área :area m²) classificou-se no grupo :grupo pelo enquadramento da planilha vigente e esse grupo é proibido na zona :zona pelo Quadro 10. :condicao.',
            'louos.template.quadro_via' => 'O grupo :grupo na classe viária :classe_via tem condições de instalação pelo Quadro 11-A da LOUOS. O Quadro 11-A pode vedar o uso (Não), encaminhar à CNLU (R) ou condicionar a instalação pela via.',

            // Consulta pública de viabilidade (ConsultaViabilidadeService).
            'consulta.aviso.zona_pendente' => 'Veredito locacional pendente: zona urbanística pendente da base oficial (SEDUR).',
            'consulta.aviso.cnae_sem_local' => 'Consulta por CNAE não avalia o local: o veredito locacional depende do endereço/zona. Para a viabilidade locacional, consulte por endereço.',
            'consulta.aviso.inscricao_indisponivel' => 'Resolução por inscrição imobiliária indisponível (base de lotes pendente SEDUR). Resultado sem análise territorial; consulte por endereço para o veredito locacional.',

            // Análise técnica — pré-análise e justificativa fundamentada.
            'analise.pre_analise.intro' => 'Analisa-se o requerimento à luz da Lei nº 9.148/2016 (LOUOS) e das regras de risco aplicáveis. Veredito locacional consolidado: :resultado.',
            'justificativa.conclusao.permitido' => 'Diante do enquadramento acima, manifesta-se pelo deferimento desta atividade, por ser locacionalmente permitida na zona :zona, sem condicionantes urbanísticas incidentes.',
            'justificativa.conclusao.permitido_com_condicoes' => 'Diante do enquadramento acima, manifesta-se pelo deferimento desta atividade na zona :zona, condicionado ao cumprimento das exigências urbanísticas incidentes.',
            'justificativa.conclusao.nao_permitido' => 'Diante do enquadramento acima, manifesta-se pelo indeferimento desta atividade, por ser o uso proibido na zona :zona segundo o Quadro 10 da LOUOS.',
            'justificativa.conclusao.padrao' => 'Não há elementos suficientes para deferir ou indeferir. Encaminha-se a atividade à análise técnica, sem sugerir desfecho locacional.',

            // Explicabilidade da decisão (DecisionExplanationService) — títulos.
            'explicacao.titulo.entrada' => 'Entrada',
            'explicacao.titulo.risco' => 'Classificação de risco',
            'explicacao.titulo.enquadramento' => 'LOUOS — Enquadramento de uso',
            'explicacao.titulo.quadro10' => 'LOUOS — Quadro 10 (permissão na zona)',
            'explicacao.titulo.quadro11a' => 'LOUOS — Quadro 11-A (condições pela via)',
            'explicacao.titulo.consolidacao' => 'Consolidação do veredito locacional',
            'explicacao.titulo.desfecho' => 'Desfecho',

            // Explicabilidade da decisão — motivos dos passos não registrados
            // (decisão legada, sem decision_trace).
            'explicacao.motivo.nao_registrado' => 'não registrado nesta decisão',
            'explicacao.motivo.risco_nao_registrado' => 'O Decreto nº 32.636/2020 classifica o risco do CNAE e define se o processo vai ao fluxo expresso ou à análise técnica. O nível e o encaminhamento desta decisão não foram gravados.',
            'explicacao.motivo.enquadramento_nao_registrado' => 'O enquadramento classifica o uso (CNAE × perguntas × área → grupo). O grupo desta decisão não foi gravado.',
            'explicacao.motivo.quadro10_nao_registrado' => 'O Quadro 10 permite ou proíbe o grupo na zona. A permissão e a zona desta decisão não foram gravadas.',
            'explicacao.motivo.quadro11a_nao_registrado' => 'O Quadro 11-A condiciona a instalação pela via (classe viária × grupo). Não permite nem proíbe o uso. As condições desta decisão não foram gravadas.',
            'explicacao.motivo.permitido_so_enquadramento' => 'O registro cita o enquadramento da LOUOS como fundamento do veredito permitido. O enquadramento só classifica o uso. Quem permite ou proíbe na zona é o Quadro 10. Grupo e zona não foram gravados nesta decisão.',
            'explicacao.motivo.permitido_com_quadro10' => 'O registro cita o Quadro 10 da LOUOS (permissão do grupo na zona). Os detalhes (grupo, faixa e zona) não foram gravados nesta decisão.',
        ];
    }
}
