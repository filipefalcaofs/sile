# Prévia de Card de Corretiva — SIG

## RESUMO (título do card)

```text
[Viabiliza SILE] Caminho: Gestão > Ficha de análise > Cards de CNAE / Fundamentação (decreto desatualizado, texto da Vigilância Sanitária e risco sanitário "não classificado")
```

---

## CORPO / DESCRIÇÃO DO CARD

### Identificação e rastreabilidade

| Campo | Preenchimento |
|---|---|
| Projeto exato | [NÃO INFORMADO] |
| Sprint exata | [NÃO INFORMADO] |
| Card pai / HU de origem | [NÃO INFORMADO] |
| CT(s) correspondente(s) | [NÃO INFORMADO] — erros reportados no relatório do cliente (itens 08 e 09 de 21/09; item 10 do relatório de usabilidade) |
| Plano de Testes | Relatório de Teste - Regras (21/09/2026) e Relatório de Usabilidade (19/09/2026) |
| Documento de Evidências de Teste | `2026-09-21_Relatorio-Teste-Regras.pdf` (prints dos CNAEs 4721-1/04, 4771-7/01 e 8650-0/99) |
| Documento de Erros revisado | `2026-09-21_Relatorio-Teste-Regras.pdf` e `2026-09-19_Relatorio-Usabilidade-Ambiente-Analise-Viabilidade.pdf` |
| Status da prévia | `Aguardando revisão da QA responsável` |
| Autorização para cadastrar | [NÃO INFORMADO] |

### Cenário de Teste

```text
[NÃO INFORMADO] — erros reportados nos itens 08 e 09 do Relatório de Teste - Regras de 21/09/2026 e no item 10 do Relatório de Usabilidade de 19/09/2026
```

### Caminho

```text
Gestão > Caixa de entrada (Fila de trabalho) > Processo > Ficha de análise > Cards de CNAE (fundamentação e classificação de risco)
```

### Regra de negócio e fontes

| Regra / critério | Fonte funcional | Fonte técnica auxiliar | Resultado da conferência |
|---|---|---|---|
| Decreto de risco vigente é o 41.758/2026 | Relatório de usabilidade 19/09, item 10; relatório 21/09, itens 08–09 | `database/seeders/RiscoMunicipalSeeder.php:14-41` (vigente: 32.636/2020) | Confirmada |
| Texto da Vigilância Sanitária não entra na fundamentação de nenhum CNAE; só o risco | Relatório 21/09, item 09 | `app/Services/Risco/RiscoClassificationService.php:384-385` | Confirmada |
| Risco sanitário segue o mesmo decreto; não exibir "não classificado" | Relatório de usabilidade 19/09, item 10 | `resources/js/components/viabilidade/resultado-viabilidade.tsx:260-265` | Confirmada |

### Descrição

**Task 1:** Nos cards de CNAE da ficha de análise (ex.: CNAE 8650-0/99 no processo em análise; CNAEs 4721-1/04 e 4771-7/01 no reteste), a fundamentação exibida cita o Decreto 32.636/2020 e a "planilha-20-08-26". O decreto de classificação de risco vigente é o Decreto Municipal nº 41.758/2026. O esperado é que toda referência legal de risco exibida aponte para o decreto vigente. Evidência: prints no relatório de 21/09 (itens 08 e 09).

**Task 2:** A fundamentação do risco inclui o texto "Classificação de risco sanitário (Vigilância Sanitária)". O esperado é que a fundamentação não contenha texto da VISA: a classificação sanitária já é exibida no próprio card como Risco. Evidência: print no relatório de 21/09 (item 09).

**Task 3:** O risco sanitário é exibido como "não classificado" nos cards/fichas. O esperado é que a classificação de risco sanitário siga o mesmo decreto vigente (41.758/2026), sem a marcação "não classificado". Evidência: relatório de usabilidade de 19/09 (item 10).

### Passos para Reprodução

**Task 1:**

1. Acessar o ambiente de análise com perfil de analista
2. Abrir a ficha de análise de um processo com CNAE classificado por risco (ex.: 8650-0/99)
3. Observar a fundamentação legal exibida no card do CNAE

**Task 2:**

1. Acessar a mesma ficha
2. Observar a presença do texto "Classificação de risco sanitário (Vigilância Sanitária)" na fundamentação

**Task 3:**

1. Acessar a mesma ficha (ou o resultado de uma simulação/viabilidade)
2. Observar o campo de risco sanitário exibido como "não classificado"

### Resultado observado e esperado

| Task | Resultado obtido | Resultado esperado | Impacto |
|---|---|---|---|
| Task 1 | Fundamentação cita Decreto 32.636/2020 e "planilha-20-08-26" | Fundamentação cita o Decreto 41.758/2026 | Fundamentação legal incorreta em decisões com efeito jurídico |
| Task 2 | Texto da Vigilância Sanitária dentro da fundamentação | Fundamentação sem texto da VISA; risco sanitário só no campo Risco | Fundamentação com conteúdo indevido |
| Task 3 | Risco sanitário exibido como "não classificado" | Classificação conforme o decreto vigente | Informação de risco incorreta para o analista e para o documento |

### Ambiente

| Ambiente | URL base | Build observado |
|---|---|---|
| Análise (homologação) | http://144.22.212.3:8082/gestao/login | [NÃO INFORMADO] |

### Massa e pré-condições

| Item | Detalhe |
|---|---|
| Perfil de acesso | Analista da SEDUR (QA do cliente) |
| Massa utilizada | Processos em análise com CNAEs 8650-0/99, 4721-1/04 e 4771-7/01 |
| Pré-condições | Classificação de risco carregada no ambiente |
| Dependências | Conteúdo oficial do Decreto 41.758/2026 (confirmar com a SEDUR se a tabela de classificação mudou ou apenas o instrumento legal) |
| Banco / log de referência | [NÃO INFORMADO] |

### Evidências

| Task | Tipo | Arquivo / Link | Tela correta? | Inserida no Doc de Evidências? |
|---|---|---|---|---|
| Task 1 | Print | `2026-09-21_Relatorio-Teste-Regras.pdf` (itens 08–09) | Sim | Sim |
| Task 2 | Print | `2026-09-21_Relatorio-Teste-Regras.pdf` (item 09) | Sim | Sim |
| Task 3 | Print | `2026-09-19_Relatorio-Usabilidade-Ambiente-Analise-Viabilidade.pdf` (item 10) | Sim | Sim |

### Critério de Aceite

**Task 1:**

```gherkin
Dado que a ficha de análise exibe a fundamentação de um CNAE
Quando a classificação de risco é apresentada
Então a referência legal citada é o Decreto Municipal nº 41.758/2026
E nenhuma menção ao Decreto 32.636/2020 ou à "planilha-20-08-26" é exibida
```

**Task 2:**

```gherkin
Dado que a ficha de análise exibe a fundamentação de qualquer CNAE
Quando a fundamentação é apresentada
Então ela não contém texto da Vigilância Sanitária
E a classificação sanitária aparece apenas no campo Risco do card
```

**Task 3:**

```gherkin
Dado que um CNAE possui classificação de risco sanitário
Quando o card ou a ficha é exibido
Então o risco sanitário apresenta a classificação do decreto vigente
E nunca o texto "não classificado"
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
