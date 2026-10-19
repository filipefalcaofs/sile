# HU.089 – ENCERRAR PROCESSO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Encerrar o processo é concluir a análise com um resultado. Deferido e indeferido saem de em análise. Desativado, cassado e revogado encerram a análise em curso ou substituem um deferimento já emitido. Não geram número de TVL. Fora de em análise ou de deferida, esses três resultados são recusados.

Não existe encerramento sem resultado. Processo deferido não volta a indeferido por esta conclusão. Indeferida e deferida saem da fila do analista: a fila só contém em análise e em pendência. O eixo operacional vai para análise concluída. Resultado que não é deferido desvincula sede ativa de escritório virtual, se houver trava.

Quem conclui pela ficha, pelo decidir ou pela conclusão de análise precisa analisar e concluir a análise. A malha fina exige quem analisa a malha fina e quem conclui a análise, e só baixa encaminhamento que ainda está aberto. Sem encaminhamento aberto, avisa e não muda a situação. No cadastro padrão, Analista Terceirizado não conclui. Administrador analisa a malha fina e não conclui a análise, então não conclui a malha fina por esse caminho.

Processo que já era deferido e recebe desativado, cassado ou revogado muda de situação e não reenvia o parecer à Junta. Encerrar a partir de em análise, criando decisão nova, chama o envio à Junta na hora. O parecer pode ficar em branco nesse caminho, salvo no indeferimento, que exige motivo. O sistema ainda não oferece encerramento por arquivamento sem resultado, cassação automática vinda da REDESIM nem encerramento automático por prazo nesta história.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico)
- [História de Usuário: Encerrar processo](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** quem conclui a análise **quero** encerrar o processo com um resultado**, de modo que** ele saia da fila e o eixo operacional fique concluído

## História de Usuário: Encerrar processo

**Como** analista que analisa e conclui **quero** concluir como deferido, indeferido, desativado, cassado ou revogado**, de modo que** desativado, cassado e revogado também possam substituir um deferimento já emitido

## Pré-requisitos

- Processo em análise, para deferir ou indeferir
- Processo em análise ou já deferido, para desativar, cassar ou revogar
- Quem analisa e quem conclui a análise
- Resultado informado; parecer opcional, até 5000 caracteres, salvo a exigência de motivo no indeferimento

## Dependências

**Passadas**

- HU.086 — Deferir solicitação
- HU.087 — Indeferir solicitação

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Encerrar processo

**Caminho no menu:** Operação > Caixa de entrada

*[Print da tela](screenshots/gestao-fila.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Sem concluir a análise**
**Dado que** a pessoa não conclui a análise
**Quando** tenta encerrar pela ficha, pelo decidir ou pela malha fina
**Então** o sistema recusa

**Cenário 2: Deferir ou indeferir**
**Dado que** o processo está em análise
**Quando** a conclusão defere ou indefere
**Então** o processo sai dessas situações de fila e o eixo operacional fica com a análise concluída

**Cenário 3: Substituir deferimento**
**Dado que** o processo já está deferido
**Quando** a conclusão é desativado, cassado ou revogado
**Então** a situação muda, não nasce TVL e o parecer não é reenviado à Junta

**Cenário 4: Malha fina sem encaminhamento**
**Dado que** a malha fina não tem encaminhamento aberto
**Quando** tenta concluir
**Então** o sistema avisa e não muda a situação

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
