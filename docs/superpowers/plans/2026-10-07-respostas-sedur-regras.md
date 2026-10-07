# Respostas da SEDUR às dúvidas da planilha — Plano de implementação

> **Para executores:** use superpowers:executing-plans. Passos com checkbox (`- [ ]`). Cada tarefa fecha com teste verde e commit.

**Objetivo:** aplicar no motor as respostas da SEDUR de 07/10/2026: CNLU, regras de bloqueio, regras compostas e respostas de múltipla escolha.

**Arquitetura:** a lista CNLU passa a casar por subgrupo normalizado, com o Quadro 10 acima da CNLU. As respostas ganham o valor `RespostaPlanilha` (bool, opção, conjunto de opções). Os ramos curados ganham `condicoes` e `acao` em json, e as 12 regras viram dado curado carregado pelo seed.

**Stack:** Laravel 13, PHPUnit 12, Inertia React 19.

**Spec:** `docs/superpowers/specs/2026-10-07-respostas-sedur-regras-design.md`

## Restrições globais
- Não enquadrar nunca indefere sozinho; vai para análise.
- Mensagens de bloqueio e condicionantes saem do `DecisionTextCatalog`.
- Resposta booleana legada continua válida (true = opção 1, false = opção 2 em Sim/Não).
- Golden files de risco e Louos continuam passando.
- Sem integração SAPS/SIGS.

## Tarefas
1. **Lista CNLU da SEDUR.** `PlanilhaCnluParser`: `nRa` vira `nRa-02`; itens "(subgrupo 2)" entram; `aguardando` vazio e nota `cnlu_aguardando_sedur` removida. Atualizar `PlanilhaCnluParserTest`, `PlanilhaWorkbookCompletudeTest`, `TratamentoComplementoTest` (15 códigos).
2. **Casamento por subgrupo normalizado.** Criar `CnluLista::contem(string $subgrupo, iterable $codigos): bool` (ignora espaços e "(subgrupo N)"; casa igual ou prefixo `codigo-`). `TratamentoRamoResolver::naListaCnlu` usa a classe. Teste unitário com `ID3- 07`, `ID3-02 (subgrupo 2)`, `ID3-13 (subgrupo 1`, `nR3-01` fora, `nR4-04` dentro.
3. **Quadro 10 acima da CNLU.** `TratamentoRamoResult` ganha `cnlu`; `enquadrarQuadro10` e `consolidar` avaliam o grupo da linha CNLU: zona proibindo = `nao_permitido`; permitindo, condicionando ou indisponível = `pendente` (motivo CNLU). Testes em `LouosConsolidacaoTest`-style.
4. **Regra geral.** Teste de que enquadramento não resolvido (pergunta sem resposta, área ausente, tipo ausente, sem regra) consolida `pendente` e o expresso encaminha à análise, sem decisão `Indeferida`.
5. **Modelo de resposta.** Criar `RespostaPlanilha`; migration `treatment_questions.tipo` e `opcoes`; importador (CSV e xlsx) preenche; `perguntasExibidas` devolve `tipo` e `opcoes`.
6. **Motor com respostas ricas.** Tipos em `TratamentoRamoInput`, `RiscoInput`, `ConsultaViabilidade*`, `ViabilityRequest::respostasDaPlanilha`, `PerguntaLocalFicha`; `ramoCurado` compara via `RespostaPlanilha`.
7. **Entradas.** FormRequests do portal e da simulação; `ConsultaViabilidadeController::respostasInformadas`; `RecebimentoProcesso` e `ReginProcessoProtocolador`; `ReginProtocoloSimulacaoService`; `consulta.tsx` e `simulacao-regin.tsx` renderizam por tipo.
8. **Condições e ações de ramo.** Migration `condicoes` e `acao` em `treatment_regra_ramos`; model; `CondicaoRamo` (avalia perguntas, categoria, tipo de imóvel, faixa, cnae único); `TratamentoRamoResolver` aplica `acao` (enquadrar código, segundo enquadramento, grupo forçado, fluxo, crítica, bloqueio com mensagem).
9. **Categoria da empresa e imóvel residencial.** Entradas do resolver: `categoriaEmpresa` (natureza jurídica grupo 1 = órgão público, parâmetro) e `TipoImovel::residencial()`; `SolicitacaoViabilityResolver` e consulta pública as informam.
10. **Ramos curados.** `regras-fluxo-sedur.json` com os ramos de 29 (P13 e P14), 30, 31, 37, 38, 39, 53 e 55, cada um restrito aos CNAEs da regra; seed carrega; pendências só das regras sem ramo (40 e 43, que esperam as opções da P1). Um teste por ramo.
11. **Bloqueios indeferem logo após o cadastro.** `FluxoExpressoService` trata `bloqueia` do ramo como `nao_permitido`, com a mensagem do catálogo como justificativa.
12. **Regras 41 e 42.** Conferir `SedeEscritorioVirtualGatilho` e a trava de inscrição contra a regra (mesma inscrição, só com "quer ser sede = Sim"), completar o que faltar (mensagens, condicionantes, atividades permitidas do Anexo A) com testes.
13. **Verificação.** Suítes afetadas, `pint`, build do frontend, `tsc`, resultado real.
