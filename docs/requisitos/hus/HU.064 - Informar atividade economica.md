# HU.064 – INFORMAR ATIVIDADE ECONÔMICA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

No rascunho, o requerente indica exatamente uma atividade principal ativa do cadastro de CNAE. A atividade não é texto livre. A mudança invalida a simulação anterior e fica registrada, com o que havia antes e o que ficou.

A intenção de incluir ou excluir só é preenchida quando o tipo de serviço é alteração de atividade. Nesse caso, CNAE marcado para exclusão fica como excluir; os demais enviados ficam como incluir. Nos outros tipos, a intenção fica em branco. O sistema ainda não oferece essa intenção em primeiro estabelecimento ou renovação, nem cadastro de CNAE fora da lista oficial.

A principal não pode repetir entre as complementares. Só se exclui um CNAE que esteja entre os enviados. Sem atividade principal, o protocolo para antes de gerar número.

No mesmo envio valem as regras de escritório virtual: abrigado em inscrição com sede ativa só aceita CNAE da lista vigente; abrigo recusado e CNAE de sede em abrigado são recusados; constituir sede em inscrição já travada por outro titular é recusado; a sede titular pode alterar as próprias atividades; CNAE da sede fora do gatilho e do anexo permitido é recusado quando essa lista está disponível. Sem lista vigente, o passo não bloqueia por esse anexo. A confirmação de perda da condição de sede e a pergunta de sede só mudam se vierem no envio; se não vierem, o valor gravado permanece.

---

## Sumário

- [Épico: EP08 — Solicitação de Viabilidade](#épico)
- [História de Usuário: Informar atividade econômica](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP08 — Solicitação de Viabilidade

**Como** o requerente **quero** indicar a atividade principal do rascunho**, de modo que** a simulação e o protocolo usem um CNAE oficial ativo

## História de Usuário: Informar atividade econômica

**Como** requerente dono do rascunho **quero** gravar uma atividade principal ativa**, de modo que** a alteração de atividade registre incluir ou excluir e os demais serviços deixem a intenção em branco

## Pré-requisitos

- Rascunho da própria solicitação
- CNAE principal existente e ativo
- Tipo de serviço alteração de atividade, quando a intenção for incluir ou excluir

## Dependências

**Passadas**

- HU.061 — Criar solicitação de viabilidade
- Cadastro de CNAEs ativos

**Futuras**

- HU.065 — Informar CNAEs complementares, no mesmo envio
- HU.068 — Protocolar solicitação, que exige atividade principal

---

## Interface de Usuário

### Tela 1 — Informar atividade econômica

**Caminho no menu:** Serviços > Minhas solicitações

*[Print da tela](screenshots/portal-nova.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Atividade principal gravada**
**Dado que** o rascunho tem um CNAE ativo
**Quando** o dono salva as atividades
**Então** esse CNAE fica como principal e a simulação anterior é invalidada

**Cenário 2: Alteração de atividade com exclusão**
**Dado que** o tipo de serviço é alteração de atividade e o CNAE principal está marcado para exclusão
**Quando** salva
**Então** a intenção gravada é excluir

**Cenário 3: Outro tipo de serviço**
**Dado que** o tipo de serviço não é alteração de atividade
**Quando** salva
**Então** a intenção fica em branco

**Cenário 4: CNAE inválido**
**Dado que** o CNAE está inativo, a principal está ausente ou a principal se repete nas complementares
**Quando** envia
**Então** o sistema recusa

**Cenário 5: Protocolo sem atividade principal**
**Dado que** o rascunho não tem CNAE principal
**Quando** protocola
**Então** o protocolo bloqueia por atividade principal pendente

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
