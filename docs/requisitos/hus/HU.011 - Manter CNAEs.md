# HU.011 – MANTER CNAES
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O administrador consulta, cadastra, altera, ativa, desativa e exclui subclasses CNAE. Na mesma ficha fica o grau de risco municipal vigente daquela subclasse: Baixo, Médio risco ou Alto. Quem só consulta a lista e a exportação não abre a ficha para alterar.

A subclasse tem sete dígitos, no padrão de exibição com traço e barra, e a hierarquia de seção, divisão, grupo e classe. CNAE novo nasce ativo. Não há importação da planilha do IBGE nesta tela: o cadastro é um registro por vez, sem segunda pessoa para publicar. A ficha não traz perguntas de condicionante sanitária. Se não houver versão vigente da classificação municipal, criar ou editar é recusado.

---

## Sumário

- [Épico: EP02 — Administração](#épico)
- [História de Usuário: Manter CNAEs](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** a SEDUR **quero** manter a tabela de subclasses CNAE e o grau de risco municipal**, de modo que** o motor use uma base de atividades atualizada

## História de Usuário: Manter CNAEs

**Como** administrador **quero** consultar, cadastrar, alterar, ativar ou desativar e excluir subclasses CNAE, com o grau de risco na ficha**, de modo que** a classificação vigente da atividade fique registrada

## Pré-requisitos

- Consulta e exportação: quem pode consultar CNAEs
- Criar, editar, mudar a situação e excluir: administrador que mantém CNAEs
- Existir versão vigente do risco municipal para gravar a classificação
- Subclasse com exatamente sete dígitos, ainda não usada; denominação e descrições da hierarquia com até 255 caracteres
- Grau obrigatório: Baixo, Médio risco ou Alto
- Informar se exige responsável técnico, se exige responsável técnico apenas no Alto, se possui fator multiplicador e se exige o detalhamento desse fator

## Dependências

**Passadas**

- HU-020 — o grau de risco municipal é gravado nesta mesma ficha

**Futuras**

- A publicação em lote da classificação, com outra pessoa publicando, fica na importação oficial da planilha, fora desta tela

---

## Interface de Usuário

### Tela 1 — CNAEs

**Caminho no menu:** Regras > CNAEs

*[Print da tela](screenshots/gestao-cnaes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Listar e exportar**
**Dado que** a pessoa pode consultar CNAEs
**Quando** abre a lista e informa busca, situação, ordenação ou pede exportação
**Então** a lista respeita o filtro e, na exportação, entrega CSV, planilha ou PDF desse conjunto

**Cenário 2: Cadastrar com risco vigente**
**Dado que** existe versão vigente de risco municipal e a pessoa mantém CNAEs
**Quando** grava uma subclasse de sete dígitos ainda não cadastrada, com hierarquia, indicadores e grau de risco
**Então** o CNAE é criado ativo e a classificação fica na versão vigente

**Cenário 3: Recusa sem versão de risco**
**Dado que** não há versão vigente de risco municipal
**Quando** a pessoa tenta cadastrar ou atualizar um CNAE
**Então** a gravação não ocorre e a tela informa que não há versão vigente para registrar a classificação

**Cenário 4: Exclusão com vínculo**
**Dado que** o CNAE está vinculado a empresa
**Quando** o administrador tenta excluí-lo
**Então** o registro permanece e a mensagem informa que CNAE vinculado a empresas não pode ser excluído; a desativação continua disponível

**Cenário 5: Troca da subclasse**
**Dado que** a classificação municipal, a classificação sanitária, a condicionante de risco ou o CNAE de escritório virtual aponta para a subclasse atual
**Quando** a edição grava outra subclasse de sete dígitos
**Então** esses vínculos passam a usar a subclasse nova

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
