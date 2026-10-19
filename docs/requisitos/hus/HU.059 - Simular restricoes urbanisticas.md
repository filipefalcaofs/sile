# HU.059 – SIMULAR RESTRIÇÕES URBANÍSTICAS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Quando a consulta tem ponto, por endereço em Salvador ou por inscrição localizada, o resultado mostra o card Território: bairro, via mais próxima, zona urbanística, lote e restrições. Não há tela só de restrições. O sistema ainda não oferece simulação de restrições sem ponto.

Se as restrições foram identificadas, a tela lista os nomes. Se a base está indisponível, mostra esse estado e o motivo, ou o aviso de base pendente de disponibilização pela SEDUR, sem lista inventada. Nos demais casos, informa que não há restrição naquele ponto.

Se a zona não foi identificada, a consulta avisa que o veredito locacional está pendente porque a zona urbanística depende da base oficial. O veredito em si já vem pendente do enquadramento. Consulta só por CNAE, e inscrição sem ponto, não mostram o card nem lista de restrições.

---

## Sumário

- [Épico: EP07 — Consulta Prévia de Viabilidade](#épico)
- [História de Usuário: Simular restrições urbanísticas](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP07 — Consulta Prévia de Viabilidade

**Como** o cidadão **quero** ver bairro, via, zona, lote e restrições do ponto**, de modo que** eu saiba o que a identificação territorial devolveu

## História de Usuário: Simular restrições urbanísticas

**Como** cidadão que consulta por endereço ou por inscrição com ponto resolvido **quero** ver o território daquele ponto**, de modo que** a ausência de ponto não apareça como análise territorial

## Pré-requisitos

- Consulta com ponto em Salvador, por endereço ou por inscrição localizada
- As mesmas informações exigidas nessa consulta

## Dependências

**Passadas**

- HU.054 — Consultar viabilidade por endereço
- HU.055 — Consultar viabilidade por inscrição imobiliária, quando o ponto for resolvido

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Simular restrições urbanísticas

**Caminho no menu:** Serviços > Consultar viabilidade

*[Print da tela](screenshots/portal-viabilidade.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Território identificado**
**Dado que** a consulta tem ponto e território identificado
**Quando** o resultado é exibido
**Então** o card de território mostra bairro, via, zona, lote e as restrições conforme o que foi identificado: itens, base indisponível ou nenhuma restrição

**Cenário 2: Sem ponto**
**Dado que** a consulta é só por CNAE, ou a inscrição não tem ponto
**Quando** o resultado volta
**Então** não há card de território nem lista de restrições urbanísticas

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
