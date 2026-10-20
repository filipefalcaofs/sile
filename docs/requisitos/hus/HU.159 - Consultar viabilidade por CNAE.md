# HU.159 – CONSULTAR VIABILIDADE POR CNAE
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

Na aba CNAE de Consultar viabilidade, o cidadão informa o CNAE e, se quiser, a área. Não há endereço nem inscrição. O sistema classifica o risco e enquadra a atividade sem avaliar o local. Não há mapa nem território.

A tela avisa que a consulta por CNAE não avalia o local e que o veredito locacional depende do endereço. O veredito fica pendente. Perguntas da planilha do CNAE podem ser respondidas, como nas outras consultas. A área alimenta o enquadramento; sem área, o sistema usa zero. Tipo de imóvel e categoria da empresa não entram nesta consulta pública.

A consulta fica registrada. O histórico pessoal só existe para quem está logado. O sistema ainda não oferece restrições de zona, via ou lote nesta consulta, nem abertura de solicitação a partir dela.

---

## Sumário

- [Épico: EP07 — Consulta Prévia de Viabilidade](#épico)
- [História de Usuário: Consultar viabilidade por CNAE](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP07 — Consulta Prévia de Viabilidade

**Como** o cidadão **quero** consultar risco e enquadramento só pela atividade**, de modo que** eu saiba que o local não foi avaliado

## História de Usuário: Consultar viabilidade por CNAE

**Como** cidadão, com ou sem login, **quero** informar o CNAE e, se quiser, a área**, de modo que** eu veja o risco e o enquadramento sem um veredito de endereço

## Pré-requisitos

- CNAE obrigatório, até 14 caracteres
- Área opcional, numérica, de 0 a 9999999
- Consulta aberta ao público, na aba CNAE

## Dependências

**Passadas**

- Classificação de risco e enquadramento da atividade

**Futuras**

- Consulta por endereço, quando o cidadão quiser o veredito do local

---

## Interface de Usuário

### Tela 1 — Consultar viabilidade por CNAE

**Caminho no menu:** Serviços > Consultar viabilidade

*[Print da tela](screenshots/portal-viabilidade.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Resultado sem local**
**Dado que** o CNAE foi informado
**Quando** o cidadão consulta pela aba CNAE
**Então** vê risco, enquadramento e o aviso de que a consulta não avalia o local, sem território e sem mapa

**Cenário 2: CNAE ausente**
**Dado que** o CNAE não foi informado
**Quando** a consulta é enviada
**Então** o sistema recusa e pede o CNAE da atividade pretendida

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
