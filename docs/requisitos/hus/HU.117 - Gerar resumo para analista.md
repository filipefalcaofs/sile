# HU.117 – GERAR RESUMO PARA ANALISTA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Na ficha de análise, o analista recebe uma síntese do processo no mesmo pedido do cartão, para o texto estar disponível na hora. A síntese é sugestão: não preenche o parecer oficial, não grava a decisão e não conclui a análise. Ficha já finalizada não é reescrita por essa leitura. Há também a ação de gerar o resumo de novo.

O interruptor é o mesmo do resumo do cidadão. Com a função desligada ou sem provedor de texto, o cartão não exibe texto gerado na hora. Conteúdo já resumido de forma equivalente não chama o provedor outra vez. Recarregar a ficha não deixa gerações esperando em segundo plano. O registro de auditoria desta função continua sendo o do resumo do processo.

---

## Sumário

- [Épico: EP14 — Inteligência Artificial](#épico)
- [História de Usuário: Gerar resumo para analista](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP14 — Inteligência Artificial

**Como** analista **quero** uma síntese do processo na ficha**, de modo que** eu leia o caso mais rápido sem concluir a análise

## História de Usuário: Gerar resumo para analista

**Como** analista **quero** o resumo no cartão da ficha, no mesmo momento**, de modo que** a sugestão não altere a ficha nem a decisão

## Pré-requisitos

- Ficha de análise aberta por quem analisa processos
- Função de resumo ligada e provedor de texto ativo, para gerar texto novo

## Dependências

**Passadas**

- Processo em análise

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Resumo na ficha

**Caminho no menu:** Operação > Caixa de entrada, na ficha de análise do processo

*[Print da tela](screenshots/gestao-fila.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Função ligada**
**Dado que** a função de resumo está ligada
**Quando** o analista abre o cartão
**Então** o resumo é gravado ou reutilizado na hora e o processo não é concluído

**Cenário 2: Função desligada**
**Dado que** a função está desligada ou não há provedor de texto
**Quando** o analista abre o cartão
**Então** não aparece texto gerado na hora

**Cenário 3: Recarregar a ficha**
**Dado que** o resumo equivalente já existe
**Quando** o analista recarrega a ficha
**Então** o provedor não é chamado de novo e não ficam gerações aguardando

**Cenário 4: Ficha finalizada**
**Dado que** a revisão da ficha já foi finalizada
**Quando** o resumo é lido
**Então** a ficha finalizada não é reescrita

---

## Aprovação do Requisito

A Sudoeste Informática esclarece que o desenvolvimento das funcionalidades descritas neste documento foi realizado de acordo com os requisitos e especificações fornecidos pelo cliente. Nossa equipe seguiu rigorosamente as instruções recebidas, garantindo que o sistema esteja alinhado com o que foi solicitado.

Para evitar possíveis retrabalhos ou ajustes futuros, sugerimos que o cliente revise atentamente os requisitos apresentados, verificando se eles atendem plenamente às suas necessidades e expectativas, bem como se estão em conformidade com eventuais normas aplicáveis ao seu negócio.

Reafirmamos nosso compromisso de entregar um software de qualidade que agregue valor às operações do cliente, e a validação cuidadosa dos requisitos é um passo importante para garantir o sucesso do projeto.

| Data | Setor | Nome | Assinatura |
|------|-------|------|------------|
|      |       |      |            |
|      |       |      |            |
|      |       |      |            |
