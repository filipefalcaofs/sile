# HU.024 – ATUALIZAR DADOS EMPRESARIAIS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Quem tem vínculo ativo altera razão social, endereço e contato da empresa. O CNPJ não muda, mesmo se for enviado de novo. Origem, protocolo da REDESIM e data de sincronização não entram neste formulário. CNAEs e o encerramento do vínculo têm caminhos próprios.

Vínculo encerrado permite ver o detalhe e impede a alteração. Quem não tem nenhum vínculo não abre o detalhe. Em representação, vale o vínculo do representado. Não há aprovação da SEDUR antes de gravar. A alteração fica registrada.

---

## Sumário

- [Épico: EP03 — Cadastro Empresarial](#épico)
- [História de Usuário: Atualizar dados empresariais](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP03 — Cadastro Empresarial

**Como** a SEDUR **quero** que o responsável atualize os dados complementares da empresa**, de modo que** o cadastro usado nas solicitações permaneça correto sem mudar o CNPJ

## História de Usuário: Atualizar dados empresariais

**Como** cidadão com vínculo ativo **quero** atualizar razão social, endereço e contato**, de modo que** o cadastro da empresa siga atualizado

## Pré-requisitos

- Cidadão autenticado no portal, com e-mail confirmado e termo aceito
- Vínculo ativo com a empresa; em representação, o vínculo do representado
- Razão social obrigatória; demais campos cadastrais opcionais, com os mesmos limites do cadastro

## Dependências

**Passadas**

- HU-023 — empresa já cadastrada e vínculo de responsável, ou outro vínculo ativo

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Dados da empresa

**Caminho no menu:** Serviços > Minhas empresas > detalhe da empresa

*[Print da tela](screenshots/portal-empresas.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Atualização com vínculo ativo**
**Dado que** o usuário efetivo tem vínculo ativo
**Quando** altera a razão social ou os dados complementares válidos
**Então** a empresa é atualizada, o CNPJ permanece o mesmo e a mensagem é "Dados da empresa atualizados com sucesso."

**Cenário 2: Sem vínculo ativo**
**Dado que** só existe vínculo encerrado, ou nenhum vínculo
**Quando** há tentativa de atualizar
**Então** o sistema recusa a alteração; sem nenhum vínculo, o detalhe também não abre

**Cenário 3: Origem e protocolo fora do formulário**
**Dado que** a empresa veio da REDESIM
**Quando** os dados complementares são salvos
**Então** origem, protocolo e data de sincronização não são alterados por esta tela

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
