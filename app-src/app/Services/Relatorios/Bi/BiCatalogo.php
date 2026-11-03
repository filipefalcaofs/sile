<?php

namespace App\Services\Relatorios\Bi;

use Closure;

/**
 * Catálogo dos indicadores da API do BI: cada slug aponta para um método dos
 * serviços de agregação e declara os parâmetros extras que aceita além dos
 * filtros comuns (data_de, data_ate, setor, analista, bairro, cnae, categoria).
 */
class BiCatalogo
{
    public const GRUPOS = [
        'estrategico' => 'Indicadores estratégicos',
        'economico' => 'Inteligência econômica',
        'territorial' => 'Inteligência territorial',
        'operacional' => 'Eficiência operacional',
        'automacao' => 'Automação do licenciamento',
    ];

    public function __construct(
        private readonly EstrategicoBi $estrategico,
        private readonly EconomicoBi $economico,
        private readonly TerritorialBi $territorial,
        private readonly OperacionalBi $operacional,
        private readonly AutomacaoBi $automacao,
    ) {}

    /**
     * @return array<string, array{grupo: string, titulo: string, descricao: string, parametros: list<string>, resolver: Closure(BiConsulta): mixed}>
     */
    public function indicadores(): array
    {
        return [
            // 1. Estratégicos
            'resumo-executivo' => $this->item('estrategico', 'Resumo executivo', 'Protocoladas, em análise, deferidas, indeferidas, taxa de deferimento, tempo médio e processos fora do prazo em um único objeto.', [], fn (BiConsulta $c) => $this->estrategico->resumoExecutivo($c)),
            'solicitacoes-protocoladas' => $this->item('estrategico', 'Total de solicitações protocoladas', 'Demanda recebida pela SEDUR, por período. Exclui rascunhos e canceladas.', ['agrupamento'], fn (BiConsulta $c) => $this->estrategico->protocoladas($c)),
            'solicitacoes-em-analise' => $this->item('estrategico', 'Solicitações em análise', 'Estoque atual de processos ainda não concluídos, por situação. Não usa o período.', [], fn (BiConsulta $c) => $this->estrategico->emAnalise($c)),
            'decisoes' => $this->item('estrategico', 'Viabilidades deferidas e indeferidas', 'Decisões por período e desfecho, com a taxa de deferimento de cada período.', ['agrupamento'], fn (BiConsulta $c) => $this->estrategico->decisoesPorPeriodo($c)),
            'taxa-deferimento' => $this->item('estrategico', 'Taxa de deferimento', 'Deferidas e indeferidas sobre o total decidido no período. Taxa nula quando não há decisão.', [], fn (BiConsulta $c) => $this->estrategico->taxaDeferimento($c)),
            'tempo-medio-analise' => $this->item('estrategico', 'Tempo médio de análise', 'Tempo do protocolo à decisão, em horas úteis: média, mediana e percentil 90.', [], fn (BiConsulta $c) => $this->estrategico->tempoMedioAnalise($c)),
            'processos-fora-do-prazo' => $this->item('estrategico', 'Processos fora do prazo', 'Processos em andamento com prazo vencido, vencendo, por etapa, e decisões tomadas depois do prazo.', [], fn (BiConsulta $c) => $this->estrategico->foraDoPrazo($c)),
            'solicitacoes-por-bairro' => $this->item('estrategico', 'Solicitações por bairro', 'Distribuição territorial da demanda, com deferidas, indeferidas e taxa de deferimento.', ['limite'], fn (BiConsulta $c) => $this->estrategico->porBairro($c)),
            'atividades-mais-solicitadas' => $this->item('estrategico', 'Atividades econômicas mais solicitadas', 'CNAEs com maior volume de solicitações.', ['limite'], fn (BiConsulta $c) => $this->estrategico->cnaes($c)),
            'solicitacoes-por-risco' => $this->item('estrategico', 'Solicitações por risco', 'Distribuição por classificação de risco (nível do decreto municipal quando há; senão a categoria derivada).', [], fn (BiConsulta $c) => $this->estrategico->porRisco($c)),

            // 2. Inteligência econômica
            'ranking-cnaes' => $this->item('economico', 'Ranking dos CNAEs mais solicitados', 'CNAEs por procura, com deferidas, indeferidas e taxas.', ['limite'], fn (BiConsulta $c) => $this->economico->ranking($c)),
            'evolucao-cnaes' => $this->item('economico', 'Evolução das atividades por CNAE', 'Solicitações por período nos CNAEs mais procurados do recorte (limite = quantos CNAEs).', ['agrupamento', 'limite'], fn (BiConsulta $c) => $this->economico->evolucao($c)),
            'cnaes-por-bairro' => $this->item('economico', 'CNAEs por bairro', 'Quais atividades são procuradas em cada região.', ['limite'], fn (BiConsulta $c) => $this->economico->cnaesPorBairro($c)),
            'solicitacoes-por-porte-area' => $this->item('economico', 'Solicitações por porte de área', 'Distribuição da área pretendida em faixas de m², com o desfecho das decisões.', [], fn (BiConsulta $c) => $this->economico->porPorteArea($c)),
            'atividades-maior-indeferimento' => $this->item('economico', 'Atividades com maior taxa de indeferimento', 'CNAEs com maior taxa de indeferimento, entre os que têm ao menos `min_decididas` decisões.', ['limite', 'min_decididas'], fn (BiConsulta $c) => $this->economico->maiorIndeferimento($c)),
            'atividades-por-risco' => $this->item('economico', 'Atividades por classificação de risco', 'Composição de risco por CNAE principal.', ['limite'], fn (BiConsulta $c) => $this->economico->atividadesPorRisco($c)),
            'novas-solicitacoes-por-regiao' => $this->item('economico', 'Novas solicitações por região', 'Demanda por bairro no período e no período anterior de mesmo tamanho (sem período: últimos 30 dias).', ['limite'], fn (BiConsulta $c) => $this->territorial->comparativo($c)),

            // 3. Inteligência territorial
            'mapa-concentracao' => $this->item('territorial', 'Mapa de concentração de solicitações', 'Células de grade com latitude, longitude e total. `precisao` = casas decimais (2 a 5; 3 ≈ 110 m).', ['precisao', 'limite'], fn (BiConsulta $c) => $this->territorial->mapaConcentracao($c)),
            'mapa-decisoes' => $this->item('territorial', 'Mapa de deferimentos e indeferimentos', 'Células de grade com total, deferidas, indeferidas e taxa de indeferimento.', ['precisao', 'limite'], fn (BiConsulta $c) => $this->territorial->mapaDecisoes($c)),
            'solicitacoes-por-zona' => $this->item('territorial', 'Solicitações por zona urbanística', 'Demanda e decisões por zona oficial identificada no imóvel.', ['limite'], fn (BiConsulta $c) => $this->territorial->porZona($c)),
            'cnaes-predominantes-por-bairro' => $this->item('territorial', 'CNAEs predominantes por bairro', 'Os CNAEs mais frequentes em cada bairro (limite = quantos por bairro) e sua participação.', ['limite'], fn (BiConsulta $c) => $this->territorial->cnaesPredominantes($c)),
            'restricoes-recorrentes' => $this->item('territorial', 'Restrições urbanísticas mais recorrentes', 'Fundamentos legais mais citados nas decisões e o peso de cada um nos indeferimentos.', ['limite'], fn (BiConsulta $c) => $this->territorial->restricoes($c)),
            'saturacao-locacional' => $this->item('territorial', 'Saturação locacional por bairro e CNAE', 'Estabelecimentos deferidos contra a capacidade recomendada. `situacao`: ok, saturando, saturado ou sem_capacidade.', ['situacao', 'limite'], fn (BiConsulta $c) => $this->territorial->saturacao($c)),
            'evolucao-territorial' => $this->item('territorial', 'Evolução territorial da demanda', 'Solicitações por período nos bairros de maior demanda (limite = quantos bairros).', ['agrupamento', 'limite'], fn (BiConsulta $c) => $this->territorial->evolucao($c)),

            // 4. Eficiência operacional
            'tempo-conclusao' => $this->item('operacional', 'Tempo de conclusão', 'Média, mediana e percentil 90 em horas úteis, no geral e por modalidade (expresso e análise técnica).', [], fn (BiConsulta $c) => $this->operacional->tempoConclusao($c)),
            'tempo-por-etapa' => $this->item('operacional', 'Tempo por etapa', 'Tempo médio em horas úteis em cada etapa do processo.', [], fn (BiConsulta $c) => $this->operacional->tempoPorEtapa($c)),
            'processos-dentro-sla' => $this->item('operacional', 'Processos dentro do SLA', 'Decisões tomadas dentro do prazo sobre as que tinham prazo.', [], fn (BiConsulta $c) => $this->operacional->dentroDoSla($c)),
            'processos-sla-vencido' => $this->item('operacional', 'Processos com SLA vencido', 'Em andamento vencidos e vencendo, por etapa.', [], fn (BiConsulta $c) => $this->operacional->slaVencido($c)),
            'envelhecimento-fila' => $this->item('operacional', 'Envelhecimento da fila', 'Processos em aberto por dias desde o protocolo e por avanço sobre o prazo.', [], fn (BiConsulta $c) => $this->operacional->envelhecimentoFila($c)),
            'carga-por-analista-setor' => $this->item('operacional', 'Processos por analista e setor', 'Processos em análise ou em convite por setor e analista (analista identificado por código interno).', [], fn (BiConsulta $c) => $this->operacional->cargaPorAnalistaSetor($c)),
            'produtividade-analista' => $this->item('operacional', 'Produtividade por analista', 'Decisões técnicas por analista no período, com desfecho e tempo médio.', [], fn (BiConsulta $c) => $this->operacional->produtividade($c)),
            'tempo-resposta-requerente' => $this->item('operacional', 'Tempo de resposta do requerente', 'Demora do requerente nos convites contra a demora administrativa (espera e análise).', [], fn (BiConsulta $c) => $this->operacional->tempoRespostaRequerente($c)),
            'ranking-gargalos' => $this->item('operacional', 'Ranking dos maiores gargalos', 'Etapas onde as horas úteis dos processos decididos mais se acumulam.', [], fn (BiConsulta $c) => $this->operacional->gargalos($c)),

            // 5. Automação
            'taxa-automacao' => $this->item('automacao', 'Taxa de automação', 'Decisões do fluxo expresso sobre o total de decisões.', [], fn (BiConsulta $c) => $this->automacao->taxaAutomacao($c)),
            'decisoes-automaticas' => $this->item('automacao', 'Decisões automáticas', 'Decisões concluídas sem análise humana, por período e desfecho.', ['agrupamento'], fn (BiConsulta $c) => $this->automacao->decisoesAutomaticas($c)),
            'decisoes-analise-tecnica' => $this->item('automacao', 'Decisões com análise técnica', 'Decisões que exigiram atuação humana, por período e desfecho.', ['agrupamento'], fn (BiConsulta $c) => $this->automacao->decisoesTecnicas($c)),
            'tempo-expresso-x-tecnico' => $this->item('automacao', 'Tempo do fluxo expresso × técnico', 'Média, mediana e percentil 90 em horas úteis de cada modalidade.', [], fn (BiConsulta $c) => $this->automacao->tempoPorModalidade($c)),
            'nao-concluidos-expresso' => $this->item('automacao', 'Processos não concluídos pelo expresso', 'Elegíveis ao expresso que não saíram decididos por ele, e os encaminhados à análise.', [], fn (BiConsulta $c) => $this->automacao->naoConcluidosPeloExpresso($c)),
            'motivos-queda-expresso' => $this->item('automacao', 'Motivos de queda do fluxo expresso', 'Condições que levam o processo à análise técnica.', [], fn (BiConsulta $c) => $this->automacao->motivosQueda($c)),
            'evolucao-automacao' => $this->item('automacao', 'Evolução da automação', 'Decisões automáticas e técnicas por período, com a taxa de automação.', ['agrupamento'], fn (BiConsulta $c) => $this->automacao->evolucao($c)),
        ];
    }

    /**
     * @param  list<string>  $parametros
     * @param  Closure(BiConsulta): mixed  $resolver
     * @return array{grupo: string, titulo: string, descricao: string, parametros: list<string>, resolver: Closure(BiConsulta): mixed}
     */
    private function item(string $grupo, string $titulo, string $descricao, array $parametros, Closure $resolver): array
    {
        return compact('grupo', 'titulo', 'descricao', 'parametros', 'resolver');
    }
}
