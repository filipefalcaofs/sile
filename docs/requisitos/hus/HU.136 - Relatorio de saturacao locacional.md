# HU.136 – RELATÓRIO DE SATURAÇÃO LOCACIONAL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O gestor compara os estabelecimentos deferidos por bairro e CNAE com a capacidade recomendada e vê a situação do par. O bairro usado é o oficial, identificado pelo ponto do imóvel; sem essa identificação, vale o digitado no endereço. O filtro de bairro segue a mesma regra. A capacidade vem da configuração. Valor que não seja número positivo é ignorado. Sem capacidade, a situação é sem capacidade e o percentual fica sem valor. Com capacidade, o percentual diante dos cortes configurados classifica o par como em ordem, saturando ou saturado. O corte de alerta tem padrão de 80 por cento e o de saturado tem padrão de 100 por cento. O nome do corte superior classifica a leitura; não trava protocolo novo. O resumo conta pares avaliados, saturados, saturando e sem capacidade. O relatório não indefere nem bloqueia solicitação. A exportação traz as solicitações filtradas, não a matriz de saturação. Os filtros comuns incluem bairro e CNAE. A consulta fica registrada.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico-ep15--relatórios-e-indicadores)
- [História de Usuário: Relatório de saturação locacional](#história-de-usuário-relatório-de-saturação-locacional)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Saturação locacional](#tela-1--saturação-locacional)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor da SEDUR **quero** ver a concentração por bairro e CNAE**, de modo que** o par sem capacidade apareça explícito, sem percentual inventado.

## História de Usuário: Relatório de saturação locacional

**Como** gestor autorizado a consultar relatórios **quero** ler quais pares estão acima da capacidade cadastrada**, de modo que** saturado seja só uma classificação e não um impedimento de novo protocolo.

## Pré-requisitos

- Gestor autorizado a consultar relatórios
- Estabelecimentos deferidos no recorte
- Capacidade numérica e positiva, quando o par for classificado por percentual

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Saturação locacional

**Caminho no menu:** Relatórios > Saturação locacional

*[Print da tela](screenshots/gestao-saturacao.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Par saturado**
**Dado que** o par bairro e CNAE tem capacidade e o percentual atinge ou passa o corte superior
**Quando** o gestor abre o relatório
**Então** a situação do par é saturado

**Cenário 2: Par saturando**
**Dado que** o percentual fica entre o alerta e o corte superior
**Quando** o relatório é montado
**Então** a situação do par é saturando

**Cenário 3: Sem capacidade**
**Dado que** o CNAE não tem capacidade válida
**Quando** o par é classificado
**Então** a situação é sem capacidade e o percentual fica sem valor

**Cenário 4: Consulta registrada**
**Dado que** o gestor abre o relatório
**Quando** a consulta é feita
**Então** a abertura fica registrada e nenhum protocolo é impedido por estar saturado

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
