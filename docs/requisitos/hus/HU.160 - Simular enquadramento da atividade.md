# HU.160 – SIMULAR ENQUADRAMENTO DA ATIVIDADE
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O enquadramento de uso aparece no resultado da consulta prévia, por endereço, por CNAE ou por inscrição. Não há uma tela só para simular o enquadramento. O sistema ainda não oferece simulação isolada do Quadro 7.

A seção Enquadramento de uso mostra o que o motor devolveu. Quando a atividade é identificada, aparecem grupo, subgrupo, código da LOUOS e versão da regra, se vierem preenchidos. Quando não há enquadramento, a tela mostra Sem enquadramento e o motivo, ou o aviso de CNAE sem ramo na planilha vigente. Quando a planilha está indisponível, a tela mostra esse estado e o motivo, ou Planilha de tratamento indisponível.

O veredito do local é o consolidado desse enquadramento. A consulta não recalcula permitido ou não permitido por conta própria. Sem território, o enquadramento ainda roda, e o veredito do local fica o que o motor devolver sem zona. A fundamentação legal junta as referências do enquadramento com as do risco, sem repetir.

---

## Sumário

- [Épico: EP07 — Consulta Prévia de Viabilidade](#épico)
- [História de Usuário: Simular enquadramento da atividade](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP07 — Consulta Prévia de Viabilidade

**Como** o cidadão **quero** ver o enquadramento da atividade na consulta prévia**, de modo que** eu saiba o grupo de uso da planilha vigente e o veredito que o sistema propagou

## História de Usuário: Simular enquadramento da atividade

**Como** cidadão que consulta a viabilidade **quero** ver o enquadramento de uso no resultado**, de modo que** eu não precise de uma simulação separada

## Pré-requisitos

- Consulta prévia concluída por endereço, CNAE ou inscrição
- Respostas da planilha, quando enviadas, válidas para o ramo do CNAE

## Dependências

**Passadas**

- HU.157, HU.158 ou HU.159, conforme a forma da consulta
- Planilha de tratamento vigente

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Simular enquadramento da atividade

**Caminho no menu:** Serviços > Consultar viabilidade

*[Print da tela](screenshots/portal-viabilidade.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Enquadramento no resultado**
**Dado que** a consulta prévia foi concluída
**Quando** o resultado é exibido
**Então** a seção de enquadramento mostra identificado, não encontrado ou indisponível, e o veredito do local corresponde a esse enquadramento

**Cenário 2: Sem simulação isolada**
**Dado que** o cidadão procura uma simulação só do enquadramento
**Quando** percorre o portal
**Então** o sistema ainda não oferece essa simulação isolada: o enquadramento só aparece como parte do resultado da consulta

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
