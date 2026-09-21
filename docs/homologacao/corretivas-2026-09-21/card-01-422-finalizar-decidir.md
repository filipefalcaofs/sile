# Prévia de Card de Corretiva — SIG

## RESUMO (título do card)

```text
[Viabiliza SILE] Caminho: Gestão > Ficha de análise > Finalizar processo / Decidir (HTTP 422 ao concluir a decisão)
```

---

## CORPO / DESCRIÇÃO DO CARD

### Identificação e rastreabilidade

| Campo | Preenchimento |
|---|---|
| Projeto exato | [NÃO INFORMADO] |
| Sprint exata | [NÃO INFORMADO] |
| Card pai / HU de origem | [NÃO INFORMADO] |
| CT(s) correspondente(s) | [NÃO INFORMADO] — erro reportado nos relatórios do cliente (itens 12 de 19/09 e 07 de 21/09) |
| Plano de Testes | Relatório de Regras - Ambiente de Análise de Viabilidade (19/09/2026) e Relatório de Teste - Regras (21/09/2026) |
| Documento de Evidências de Teste | `2026-09-21_Relatorio-Teste-Regras.pdf` (print "Oops! An Error Occurred / 422 Unprocessable Content") |
| Documento de Erros revisado | `2026-09-19_Relatorio-Regras-Ambiente-Analise-Viabilidade.pdf` e `2026-09-21_Relatorio-Teste-Regras.pdf` |
| Status da prévia | `Aguardando revisão da QA responsável` |
| Autorização para cadastrar | [NÃO INFORMADO] |

### Cenário de Teste

```text
[NÃO INFORMADO] — erro reportado no item 07 do Relatório de Teste - Regras de 21/09/2026 (mesmo bloqueio do item 12 do relatório de 19/09/2026)
```

### Caminho

```text
Gestão > Caixa de entrada (Fila de trabalho) > Processo 5921000030-00068994/2024 > Ficha de análise > Finalizar processo / Decidir
```

### Regra de negócio e fontes

| Regra / critério | Fonte funcional | Fonte técnica auxiliar | Resultado da conferência |
|---|---|---|---|
| O analista deve conseguir finalizar a ficha e deferir/indeferir o processo conforme as decisões registradas | Relatório de Teste - Regras 21/09/2026, item 07: "conseguir finalizar / deferir conforme a ficha" | `app/Services/Analise/AnaliseTecnicaDecisionService.php:227-236`; `app/Http/Controllers/Gestao/AnalysisRecordController.php:188-191` | Confirmada |

### Descrição

**Task 1:** Na ficha de análise do processo 5921000030-00068994/2024 (VIA-2026-000047, CNAE 8211-3/00, sede de escritório virtual), ao acionar a finalização/decisão do processo, o sistema devolve a tela de erro "Oops! An Error Occurred / 422 Unprocessable Content" e a decisão não é registrada. O esperado é que o processo seja finalizado e decidido conforme as escolhas da ficha. O mesmo bloqueio já havia sido reportado em 19/09 (sem opção de deferir visível) e persiste no reteste de 21/09, agora com o erro HTTP exposto. Impacto: o analista não consegue concluir nenhum processo na ficha, bloqueando o fechamento da validação da planilha de regras. Evidência: print no relatório de 21/09 (item 07).

### Passos para Reprodução

**Task 1:**

1. Acessar o ambiente de análise com perfil de analista
2. Abrir a ficha de análise do processo 5921000030-00068994/2024 (VIA-2026-000047)
3. Preencher a ficha e acionar a finalização do processo (ou a decisão, com a ficha finalizada)
4. Observar o retorno

### Resultado observado e esperado

| Task | Resultado obtido | Resultado esperado | Impacto |
|---|---|---|---|
| Task 1 | Tela de erro "Oops! An Error Occurred / 422 Unprocessable Content"; decisão não registrada | Processo finalizado e decidido conforme a ficha, com mensagens de validação claras quando algum dado estiver faltando | Analista impossibilitado de concluir processos; validação da planilha de regras bloqueada |

### Ambiente

| Ambiente | URL base | Build observado |
|---|---|---|
| Análise (homologação) | http://144.22.212.3:8082/gestao/login | [NÃO INFORMADO] |

### Massa e pré-condições

| Item | Detalhe |
|---|---|
| Perfil de acesso | Analista da SEDUR (QA do cliente) |
| Massa utilizada | Processo 5921000030-00068994/2024 (VIA-2026-000047), CNAE 8211-3/00, imóvel Edificação Comercial 81 m², zona ZCMe-1/01 |
| Pré-condições | Processo em análise com ficha aberta para o analista |
| Dependências | Motor de regras (já validado pelo cliente em 21/09 para este processo) |
| Banco / log de referência | [NÃO INFORMADO] |

### Evidências

| Task | Tipo | Arquivo / Link | Tela correta? | Inserida no Doc de Evidências? |
|---|---|---|---|---|
| Task 1 | Print | `2026-09-21_Relatorio-Teste-Regras.pdf` (item 07) | Sim | Sim |

### Critério de Aceite

**Task 1:**

```gherkin
Dado que o analista está na ficha de análise de um processo em análise
Quando todas as decisões por CNAE estão registradas e ele aciona "Finalizar processo" e "Decidir"
Então o processo é concluído com o desfecho correspondente às decisões da ficha
E nenhum erro HTTP 422 é exibido
E quando algum CNAE estiver sem decisão, o sistema aponta na própria tela quais atividades faltam decidir
```

### Classificação

| Campo | Valor |
|---|---|
| Severidade | Crítica |
| Prioridade SIG | Urgente |
| Frequência | Sempre |
| Reprodutibilidade | Sempre |
| Origem | `Cliente` |
| Atividade | `Retrabalho / Correção de erros` |
| Tempo previsto | `0` |

### Impedimento — somente na fase autorizada

`[NÃO APLICÁVEL — não registrar impedimento nesta fase]`

### Subtarefas no SIG

| Task | Subtarefa criada? | ID real | Link | Representa um único erro? |
|---|---|---|---|---|
| Task 1 | Aguardando | [NÃO INFORMADO] | [NÃO INFORMADO] | Sim |
