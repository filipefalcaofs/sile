# Template de Card — Padrão SIG para QA (Corretiva)

## Instruções para o Agente de IA QA

Este modelo deve ser preenchido somente com informações verificadas. Ele possui duas partes principais:

1. **RESUMO:** título do card no SIG.
2. **CORPO / DESCRIÇÃO:** conteúdo detalhado do card e das subtarefas.

### Regras obrigatórias

- O card deve representar uma tela ou caminho funcional específico.
- Cada **Task** representa um erro funcional distinto encontrado na mesma tela. Cada Task deve virar uma subtarefa no SIG.
- Uma descrição extensa não deve ser dividida em várias Tasks quando representa o mesmo erro. O limite é definido por erro distinto, não pelo tamanho do texto, pela quantidade de passos ou pela quantidade de evidências.
- Quando houver apenas um erro na tela, manter uma única descrição completa e autoexplicativa; criar uma subtarefa somente se o documento-padrão do projeto exigir.
- Um card pode ter no máximo **6 Tasks**. Se houver mais de 6 erros distintos na mesma tela, não dividir nem cadastrar automaticamente: registrar a necessidade de decisão da QA responsável conforme o padrão da sprint.
- Não misturar erros diferentes na mesma Task.
- Não pular a numeração: usar `Task 1`, `Task 2`, `Task 3` etc.
- Cada Task deve conter, na mesma ordem:
  1. descrição objetiva do erro;
  2. bloco próprio de passos para reprodução;
  3. critério de aceite próprio em Gherkin.
- A descrição deve ser curta, mas completamente autoexplicativa. Uma pessoa sem contexto anterior deve entender apenas pela leitura:
  - onde acessar;
  - qual pré-condição ou massa usar;
  - qual ação executar;
  - o que ocorreu;
  - o que deveria ocorrer;
  - qual impacto foi observado;
  - qual evidência comprova o erro.
- Não usar textos vagos como `erro na tela`, `não funciona`, `ajustar campo` ou `validar regra` sem indicar tela, ação, divergência e resultado esperado.
- Preencher todos os campos entre colchetes. Quando a informação não existir, usar `[NÃO INFORMADO]`; nunca deixar campo vazio nem inventar dados.
- Preservar exatamente nomes de rotas, URLs, ambientes, builds, projetos, sprints, CTs e IDs fornecidos.
- Nunca incluir senha, token, cookie, dado de sessão ou informação pessoal não mascarada.
- Usar dados fictícios, mascarados ou anonimizados no texto e nas evidências.

### Fontes e decisão de defeito

Antes de confirmar um erro, cruzar:

1. HU, regra de negócio e critério de aceite;
2. documento funcional oficial;
3. JSON ou exportação da sprint;
4. Plano de Testes e Documento de Evidências de Teste;
5. código-fonte, somente como fonte auxiliar autorizada;
6. comportamento observado no ambiente testado.

O código-fonte não substitui uma regra funcional documentada. Em caso de conflito, registrar a divergência e não criar corretiva até a decisão da QA responsável.

Não criar card para:

- dúvida, premissa, requisito ausente ou comportamento indefinido;
- indisponibilidade de VPN, internet ou ambiente, quando não houver evidência de defeito do produto;
- credencial salva pelo usuário ou dado de teste incorreto;
- cenário `NÃO APLICÁVEL` formalmente justificado;
- observação textual sem erro funcional confirmado;
- atividade condicional ainda não autorizada.

### Momento de criação

- Executar e consolidar os testes antes de criar corretivas.
- Atualizar o Documento de Erros e o Documento de Evidências de Teste.
- Gerar primeiro esta prévia documental.
- Aguardar a revisão e a autorização explícita da QA responsável.
- Não criar card, Task ou subtarefa automaticamente antes dessa autorização.
- Após a autorização, conferir duplicidade e salvar no SIG. Registrar o número e o link reais retornados pelo sistema; nunca deduzir IDs.

---

## RESUMO (título do card)

```text
[HU, se houver, ou Nome do Projeto] Caminho: [Caminho exato até a tela] ([breve descrição dos erros, resumida em poucas palavras])
```

**Exemplo:**

```text
[Retaguarda CODECON] Caminho: Sistema > Parametrização > Parametrização da IA (Erro 500 ao carregar tela)
```

---

## CORPO / DESCRIÇÃO DO CARD

### Identificação e rastreabilidade

| Campo | Preenchimento |
|---|---|
| Projeto exato | [NOME EXATO DO PROJETO] |
| Sprint exata | [NOME EXATA DA SPRINT] |
| Card pai / HU de origem | [ID E TÍTULO REAIS] |
| CT(s) correspondente(s) | [CÓDIGOS DOS CTs] |
| Plano de Testes | [CAMINHO OU LINK REAL] |
| Documento de Evidências de Teste | [CAMINHO OU LINK REAL] |
| Documento de Erros revisado | [CAMINHO OU LINK REAL] |
| Status da prévia | `Aguardando revisão da QA responsável` |
| Autorização para cadastrar | [NÃO INFORMADO] |

### Cenário de Teste

```text
CT [código do cenário correspondente]
```

Se o card cobrir mais de um CT, listar todos e indicar claramente qual Task se relaciona a cada CT.

### Caminho

```text
[Caminho exato até a tela onde o erro ocorre]
```

### Regra de negócio e fontes

| Regra / critério | Fonte funcional | Fonte técnica auxiliar | Resultado da conferência |
|---|---|---|---|
| [RN/CA] | [HU, requisito ou documento] | [arquivo/classe/método ou NÃO INFORMADO] | [Confirmada / Divergente / NÃO INFORMADO] |

> O código-fonte é evidência auxiliar. Não substituir a regra funcional por uma inferência feita somente no código.

### Descrição

> Regra: uma Task = um erro distinto. Se houver N erros distintos na tela, criar N Tasks, respeitando o máximo de 6.

**Task 1:** [descrição objetiva do erro — onde, ação, o que ocorreu, o que deveria ocorrer, impacto, evidência]

**Task 2:** [descrição objetiva do erro]

**Task 3:** [descrição objetiva do erro]

> Remover Tasks não utilizadas.

### Passos para Reprodução

**Task 1:**

1. [passo 1]
2. [passo 2]
3. [passo N]

**Task 2:**

1. [passo 1]
2. [passo N]

> Cada Task possui seu próprio bloco de passos numerado de forma independente.

### Resultado observado e esperado

| Task | Resultado obtido | Resultado esperado | Impacto |
|---|---|---|---|
| Task 1 | [o que aconteceu] | [o que deveria acontecer] | [impacto observado] |
| Task 2 | [o que aconteceu] | [o que deveria acontecer] | [impacto observado] |

### Ambiente

| Ambiente | URL base | Build observado |
|---|---|---|
| [Homologação / Produção / Dev] | [URL] | [build ou NÃO INFORMADO] |

### Massa e pré-condições

| Item | Detalhe |
|---|---|
| Perfil de acesso | [perfil usado no teste] |
| Massa utilizada | [dados mascarados — CPF fictício, nome fictício etc.] |
| Pré-condições | [o que precisa estar configurado antes] |
| Dependências | [outros módulos, integrações, permissões] |
| Banco / log de referência | [caminho ou NÃO INFORMADO] |

### Evidências

| Task | Tipo | Arquivo / Link | Tela correta? | Inserida no Doc de Evidências? |
|---|---|---|---|---|
| Task 1 | [Print / Vídeo / Log] | [nome ou link] | [Sim / Não] | [Sim / Não] |
| Task 2 | [Print / Vídeo / Log] | [nome ou link] | [Sim / Não] | [Sim / Não] |

### Critério de Aceite

**Task 1:**

```gherkin
Dado que [pré-condição]
Quando [ação do usuário]
Então [resultado esperado]
```

**Task 2:**

```gherkin
Dado que [pré-condição]
Quando [ação do usuário]
Então [resultado esperado]
```

> Cada Task possui seu próprio bloco Gherkin. Não unificar critérios de Tasks diferentes.

### Classificação

| Campo | Valor |
|---|---|
| Severidade | [Crítica / Alta / Média / Baixa / Melhoria] |
| Prioridade SIG | [Urgente / Alta / Média / Baixa] |
| Frequência | [Sempre / Às vezes / Raramente] |
| Reprodutibilidade | [Sempre / Intermitente / Não reproduzível] |
| Origem | `Equipe de Teste` |
| Atividade | `Retrabalho / Correção de erros` |
| Tempo previsto | `0` |

### Impedimento — somente na fase autorizada

> **Na fase de execução de teste:** registrar evolução `TESTE CONCLUIDO` — não registrar impedimento prematuramente.
>
> **Após corretiva real criada no pai (se a fase exigir):**
> `Card: #[número real do card corretivo criado no SIG]`
>
> **Caso contrário:**
> `[NÃO APLICÁVEL — não registrar impedimento nesta fase]`

### Subtarefas no SIG

| Task | Subtarefa criada? | ID real | Link | Representa um único erro? |
|---|---|---|---|---|
| Task 1 | [Sim / Não / Aguardando] | [NÃO INFORMADO] | [NÃO INFORMADO] | [Sim / Não] |
| Task 2 | [Sim / Não / Aguardando] | [NÃO INFORMADO] | [NÃO INFORMADO] | [Sim / Não] |
