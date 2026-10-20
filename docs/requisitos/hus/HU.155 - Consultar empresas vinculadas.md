# HU.155 – CONSULTAR EMPRESAS VINCULADAS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O cidadão lista e abre as empresas com as quais tem ou teve vínculo. Em representação ativa, a lista é a do representado. O total exibido conta só vínculos ativos; a lista inclui também os encerrados. Empresa sem vínculo não aparece, e o detalhe é recusado.

A busca aceita um trecho da razão social ou do nome fantasia, sem diferenciar maiúsculas, ou o início numérico do CNPJ. A ordenação é pela razão social, crescente ou decrescente. A página pode ter 10, 15, 25 ou 50 itens. O detalhe mostra os dados cadastrais, os CNAEs e a lista de vínculos, marcando o da pessoa. Não há lista de todas as empresas do município nem consulta pública sem entrada no portal. Atualizar, gerir CNAEs e encerrar vínculo exigem vínculo ativo.

---

## Sumário

- [Épico: EP03 — Cadastro Empresarial](#épico)
- [História de Usuário: Consultar empresas vinculadas](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP03 — Cadastro Empresarial

**Como** a SEDUR **quero** mostrar ao cidadão só as empresas do próprio vínculo**, de modo que** ele saiba CNPJ, CNAE principal, papel e se o vínculo segue ativo

## História de Usuário: Consultar empresas vinculadas

**Como** cidadão autenticado **quero** ver minhas empresas e o detalhe de cada uma**, de modo que** eu saiba a situação do meu vínculo

## Pré-requisitos

- Cidadão autenticado no portal, com e-mail confirmado e termo aceito
- Para o detalhe, qualquer vínculo com a empresa, ativo ou encerrado

## Dependências

**Passadas**

- HU-151 — empresa e vínculo já existentes
- HU-147 — em representação, a lista é a do representado

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Minhas empresas

**Caminho no menu:** Serviços > Minhas empresas

*[Print da tela](screenshots/portal-empresas.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Lista do próprio usuário**
**Dado que** existem empresas com vínculo do usuário efetivo
**Quando** ele abre Minhas empresas
**Então** vê somente essas empresas, com CNAE principal e a situação do vínculo, inclusive o encerrado

**Cenário 2: Busca e paginação**
**Dado que** a lista está aberta
**Quando** informa busca, ordenação pela razão social ou tamanho de página de 10, 15, 25 ou 50
**Então** o resultado respeita esses filtros; ordenação ou página fora das opções cai no padrão

**Cenário 3: Detalhe e acesso negado**
**Dado que** há vínculo ativo ou encerrado
**Quando** a pessoa abre o detalhe
**Então** vê os dados e os vínculos; sem nenhum vínculo, o acesso é recusado

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
