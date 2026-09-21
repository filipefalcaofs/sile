# Prévia de Card de Corretiva — SIG

## RESUMO (título do card)

```text
[Viabiliza SILE] Caminho: Gestão > Ficha de análise > Decisão do analista (sugestão automática, decisão pré-marcada, pergunta de outro CNAE e parecer pré-preenchido)
```

---

## CORPO / DESCRIÇÃO DO CARD

### Identificação e rastreabilidade

| Campo | Preenchimento |
|---|---|
| Projeto exato | [NÃO INFORMADO] |
| Sprint exata | [NÃO INFORMADO] |
| Card pai / HU de origem | [NÃO INFORMADO] |
| CT(s) correspondente(s) | [NÃO INFORMADO] — erros reportados no relatório do cliente (itens 02 e 04 de 21/09) |
| Plano de Testes | Relatório de Teste - Regras (21/09/2026) |
| Documento de Evidências de Teste | `2026-09-21_Relatorio-Teste-Regras.pdf` (prints da ficha de análise do processo 68994/2024) |
| Documento de Erros revisado | `2026-09-21_Relatorio-Teste-Regras.pdf` |
| Status da prévia | `Aguardando revisão da QA responsável` |
| Autorização para cadastrar | [NÃO INFORMADO] |

### Cenário de Teste

```text
[NÃO INFORMADO] — erros reportados nos itens 02 e 04 do Relatório de Teste - Regras de 21/09/2026
```

### Caminho

```text
Gestão > Caixa de entrada (Fila de trabalho) > Processo 5921000030-00068994/2024 > Ficha de análise > Decisão do analista / Parecer técnico
```

### Regra de negócio e fontes

| Regra / critério | Fonte funcional | Fonte técnica auxiliar | Resultado da conferência |
|---|---|---|---|
| O sistema não sugere decisão ao analista | Relatório 21/09, item 02 | `resources/js/pages/gestao/ficha-analise/show.tsx:1491-1514` | Confirmada |
| Deferido/Indeferido não vêm pré-marcados | Relatório 21/09, item 02 | `app/Services/Analise/PreAnaliseService.php:214-216` | Confirmada |
| A pergunta exibida é a do CNAE da ficha (8211-3/00 → pergunta de escritório virtual/coworking) | Relatório 21/09, item 02 | `app/Services/Analise/PerguntaLocalFicha.php:20-99` | Confirmada |
| Parecer técnico nasce em branco; rótulo do serviço fica junto do CNAE | Relatório 21/09, item 04; usabilidade item 27 | `app/Services/Analise/PreAnaliseService.php:122,282-302` | Confirmada |

### Descrição

**Task 1:** Na ficha de análise do processo 5921000030-00068994/2024 (CNAE 8211-3/00), o sistema exibe o texto "ESPECIALISTA SUGERE Deferido" acima da decisão do analista. O esperado é que o sistema não sugira decisão: a escolha é exclusivamente humana. Evidência: print no relatório de 21/09 (item 02).

**Task 2:** Na mesma ficha, a aba "Decisão do analista" já abre com a opção "Deferido" marcada. O esperado é que Deferido/Indeferido venham desmarcados, exigindo ação explícita do analista para cada atividade. Evidência: print no relatório de 21/09 (item 02).

**Task 3:** A pergunta exibida na ficha do CNAE 8211-3/00 é "A atividade será desenvolvida no local?", que não pertence a este CNAE. O esperado é exibir a pergunta cadastrada para o próprio CNAE na planilha de tratamento (P4 — escritório virtual/coworking), com a resposta correspondente. Evidência: print no relatório de 21/09 (item 02).

**Task 4:** O parecer técnico abre preenchido pelo sistema. O esperado é o parecer em branco para redação do analista; o conteúdo gerado pelo sistema permanece disponível como justificativa por atividade, não como parecer. Evidência: print no relatório de 21/09 (item 04 — corpo do parecer preenchido).

**Task 5:** O rótulo do serviço ("Sede de Escritório Virtual") aparece no bloco do parecer técnico. O esperado é que o rótulo do serviço fique junto do CNAE correspondente, não no parecer. Evidência: print no relatório de 21/09 (item 04 — título em destaque no parecer).

### Passos para Reprodução

**Task 1:**

1. Acessar o ambiente de análise com perfil de analista
2. Abrir a ficha de análise do processo 5921000030-00068994/2024
3. Observar o bloco de decisão do CNAE 8211-3/00

**Task 2:**

1. Acessar a mesma ficha
2. Abrir a aba "Decisão do analista"
3. Observar que "Deferido" já está marcado sem ação do analista

**Task 3:**

1. Acessar a mesma ficha
2. Localizar a pergunta exibida para o CNAE 8211-3/00
3. Comparar com a pergunta cadastrada para o CNAE na planilha de tratamento (P4)

**Task 4:**

1. Acessar a mesma ficha
2. Observar o campo de parecer técnico já preenchido

**Task 5:**

1. Acessar a mesma ficha
2. Observar o título "Sede de Escritório Virtual" dentro do bloco do parecer técnico

### Resultado observado e esperado

| Task | Resultado obtido | Resultado esperado | Impacto |
|---|---|---|---|
| Task 1 | Texto "ESPECIALISTA SUGERE Deferido" visível | Nenhuma sugestão de decisão exibida | Induz a decisão do analista; compromete a autonomia da análise |
| Task 2 | "Deferido" pré-marcado | Opções desmarcadas até ação do analista | Decisão pode ser registrada sem deliberação real |
| Task 3 | Pergunta genérica de outro contexto | Pergunta do próprio CNAE (P4) com sua resposta | Enquadramento não reflete a regra da atividade |
| Task 4 | Parecer preenchido pelo sistema | Parecer em branco | Parecer deixa de ser manifestação do analista |
| Task 5 | Rótulo do serviço dentro do parecer | Rótulo junto do CNAE | Confunde o objeto do parecer |

### Ambiente

| Ambiente | URL base | Build observado |
|---|---|---|
| Análise (homologação) | http://144.22.212.3:8082/gestao/login | [NÃO INFORMADO] |

### Massa e pré-condições

| Item | Detalhe |
|---|---|
| Perfil de acesso | Analista da SEDUR (QA do cliente) |
| Massa utilizada | Processo 5921000030-00068994/2024 (VIA-2026-000047), CNAE 8211-3/00 |
| Pré-condições | Processo em análise; CNAE 8211-3/00 com pergunta P4 cadastrada na planilha de tratamento |
| Dependências | Planilha de regras/tratamento da SEDUR carregada |
| Banco / log de referência | [NÃO INFORMADO] |

### Evidências

| Task | Tipo | Arquivo / Link | Tela correta? | Inserida no Doc de Evidências? |
|---|---|---|---|---|
| Task 1 | Print | `2026-09-21_Relatorio-Teste-Regras.pdf` (item 02) | Sim | Sim |
| Task 2 | Print | `2026-09-21_Relatorio-Teste-Regras.pdf` (item 02) | Sim | Sim |
| Task 3 | Print | `2026-09-21_Relatorio-Teste-Regras.pdf` (item 02) | Sim | Sim |
| Task 4 | Print | `2026-09-21_Relatorio-Teste-Regras.pdf` (item 04) | Sim | Sim |
| Task 5 | Print | `2026-09-21_Relatorio-Teste-Regras.pdf` (item 04) | Sim | Sim |

### Critério de Aceite

**Task 1:**

```gherkin
Dado que o analista abre a ficha de análise de um processo em análise
Quando a ficha é exibida
Então nenhum texto de sugestão de decisão do sistema é apresentado
```

**Task 2:**

```gherkin
Dado que o analista abre a aba "Decisão do analista"
Quando nenhuma decisão foi registrada por ele
Então as opções Deferido e Indeferido estão desmarcadas para todas as atividades
```

**Task 3:**

```gherkin
Dado que o CNAE da ficha possui pergunta própria cadastrada na planilha de tratamento
Quando a ficha é exibida
Então a pergunta e a resposta apresentadas são as cadastradas para aquele CNAE
```

**Task 4:**

```gherkin
Dado que o analista abre a ficha de análise
Quando o parecer técnico ainda não foi redigido
Então o campo de parecer está em branco
```

**Task 5:**

```gherkin
Dado que a ficha exibe uma atividade com serviço associado
Quando o parecer técnico é apresentado
Então o rótulo do serviço aparece junto do CNAE correspondente e não dentro do parecer
```

### Classificação

| Campo | Valor |
|---|---|
| Severidade | Alta |
| Prioridade SIG | Alta |
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
| Task 2 | Aguardando | [NÃO INFORMADO] | [NÃO INFORMADO] | Sim |
| Task 3 | Aguardando | [NÃO INFORMADO] | [NÃO INFORMADO] | Sim |
| Task 4 | Aguardando | [NÃO INFORMADO] | [NÃO INFORMADO] | Sim |
| Task 5 | Aguardando | [NÃO INFORMADO] | [NÃO INFORMADO] | Sim |
