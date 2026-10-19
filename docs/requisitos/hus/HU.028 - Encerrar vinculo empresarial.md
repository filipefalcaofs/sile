# HU.028 – ENCERRAR VÍNCULO EMPRESARIAL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O cidadão encerra o próprio vínculo ativo com a empresa, sem apagar o histórico nem a empresa. O botão "Encerrar meu vínculo" pede confirmação. O motivo é opcional, com até 255 caracteres; vazio fica sem motivo. Em representação, encerra o vínculo do representado. Não encerra o vínculo de outra pessoa e não reativa um vínculo já encerrado.

Se o usuário for o único responsável ativo, o encerramento é recusado: a empresa não pode ficar sem responsável ativo. Um procurador pode encerrar o próprio vínculo enquanto existir outro responsável ativo. Depois do encerramento, a empresa continua visível, mas atualização, CNAEs e novo encerramento deixam de valer para essa pessoa.

---

## Sumário

- [Épico: EP03 — Cadastro Empresarial](#épico)
- [História de Usuário: Encerrar vínculo empresarial](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP03 — Cadastro Empresarial

**Como** a SEDUR **quero** encerrar o vínculo sem apagar a empresa**, de modo que** o período vinculado permaneça no histórico e a empresa não fique sem responsável

## História de Usuário: Encerrar vínculo empresarial

**Como** cidadão com vínculo ativo **quero** encerrar meu vínculo com a empresa**, de modo que** eu deixe de alterá-la e o registro do período permaneça

## Pré-requisitos

- Cidadão autenticado no portal, com e-mail confirmado e termo aceito
- Vínculo ativo do próprio usuário efetivo
- Se o papel for responsável, existir outro responsável ativo

## Dependências

**Passadas**

- HU-023 — vínculo ativo já criado
- HU-027 — o detalhe da empresa, de onde a ação parte

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Encerrar meu vínculo

**Caminho no menu:** Serviços > Minhas empresas > detalhe da empresa

*[Print da tela](screenshots/portal-empresas.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Encerramento do próprio vínculo**
**Dado que** há vínculo ativo e, se o papel for responsável, outro responsável ativo
**Quando** o cidadão confirma o encerramento
**Então** o vínculo recebe data de fim e motivo, permanece no histórico, a empresa deixa de ser editável por ele e a mensagem é "Vínculo com a empresa encerrado."

**Cenário 2: Último responsável**
**Dado que** o usuário é o único responsável ativo
**Quando** tenta encerrar
**Então** o sistema recusa com "A empresa não pode ficar sem responsável ativo."

**Cenário 3: Sem vínculo ativo**
**Dado que** o usuário não tem vínculo ativo
**Quando** tenta encerrar
**Então** o sistema recusa

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
