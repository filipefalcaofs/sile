# HU.183 – MANTER FICHAS DE ANÁLISE POR ANALISTA E HISTÓRICO DE TRAMITAÇÃO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 23/10/2026 | Criação da HU para validação da regra com a SEDUR | 1.0 | Sudoeste Informática |

---

## Visão Geral

Hoje o processo tem uma ficha de análise vigente, e a nova revisão copia a última ficha, seja de quem for. Quando o processo passa de um analista para outro, o segundo recebe os dados do primeiro como se fossem seus, e fica difícil dizer quem registrou o quê.

Esta HU muda esse comportamento. A ficha passa a pertencer ao analista que a preencheu. O processo tem um histórico compartilhado de tramitação e uma decisão formal, separados da ficha.

1. **Cada analista tem as suas fichas.** Um analista pode abrir, preencher, salvar e finalizar quantas fichas precisar no mesmo processo.
2. **Pré-preenchimento só com a ficha do próprio analista.** A nova ficha parte da última ficha dele no processo. Sem ficha anterior dele, abre em branco, mesmo que outro analista já tenha preenchido uma.
3. **Nada se perde.** Abrir uma ficha nova não apaga nem altera a ficha anterior, de ninguém. Todas ficam consultáveis na tramitação do processo.
4. **Análise não é decisão.** A última ficha criada não é, por isso, o parecer conclusivo. Quem pode concluir o processo emite a decisão, depois de ler as fichas anteriores e de fazer a própria avaliação.
5. **Analista terceirizado analisa, mas não conclui.** Ele registra o parecer técnico e encaminha o processo a quem conclui.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico-ep10--análise-técnica-sedur)
- [História de Usuário](#história-de-usuário-manter-fichas-de-análise-por-analista)
- [Regras](#regras)
- [Fluxos esperados](#fluxos-esperados)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Comportamento Esperado](#comportamento-esperado)
- [Pontos para validação da SEDUR](#pontos-para-validação-da-sedur)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** analista **quero** registrar a minha análise do processo sem sobrescrever a de outro analista **, de modo que** cada parecer fique com o seu autor e o histórico do processo possa ser conferido

## História de Usuário: Manter fichas de análise por analista

**Como** analista **quero** abrir uma ficha só minha, partindo da minha última ficha do processo, e consultar na tramitação as fichas dos outros analistas **, de modo que** eu aproveite o que já escrevi, não herde dados que não são meus e a gestão consiga identificar quem fez cada análise e quem tomou cada decisão

**Como** chefia que conclui a análise **quero** ler todas as fichas anteriores e emitir a minha decisão **, de modo que** o resultado do processo seja um ato meu, separado do parecer de quem analisou antes

**Como** auditor ou gestor **quero** reconstituir quem fez cada ação no processo **, de modo que** seja possível apurar responsabilidades quando houver decisão equivocada ou pareceres divergentes

---

## Regras

| Nº | Regra |
|:---:|---|
| RN-01 | Cada analista pode ter várias fichas no mesmo processo, dentro das atribuições do seu perfil |
| RN-02 | Ao abrir uma ficha nova, o sistema procura a última ficha do próprio usuário no processo. Se existir, usa os dados dela como ponto de partida. Se não existir, abre em branco |
| RN-03 | O pré-preenchimento nunca altera a ficha de origem |
| RN-04 | Os dados de ficha de outro analista não pré-preenchem a ficha do usuário. O analista pode consultá-los na tramitação, mas não os recebe copiados |
| RN-05 | Abrir uma ficha nova não apaga, altera nem substitui nenhuma ficha anterior |
| RN-06 | Se abrir a ficha nova encerra a anterior do mesmo analista, o encerramento aparece na tramitação como mudança de situação. O conteúdo anterior continua visível |
| RN-07 | Toda ficha salva ou finalizada fica na tramitação com: processo, analista, data e hora de criação e de cada alteração relevante, conteúdo no momento do salvamento ou da finalização, situação da ficha e a ação feita com o responsável por ela |
| RN-08 | O sistema distingue três coisas: o **histórico** (todas as fichas e versões), a **análise vigente** (a ficha que representa a análise atual) e o **parecer conclusivo** (a decisão formal) |
| RN-09 | A última ficha criada não é automaticamente o parecer conclusivo. A análise vigente e a decisão formal são controladas separadamente |
| RN-10 | O resultado do processo (deferimento, indeferimento, cassação ou anulação) é registrado por quem tem permissão de concluir a análise. A decisão guarda o nome de quem a emitiu |
| RN-11 | O parecer de um analista anterior não se confunde com a decisão de outro usuário, mesmo quando os dois divergem |
| RN-12 | Analista Terceirizado preenche ficha, salva, finaliza a ficha e registra a conclusão técnica. Ele não finaliza o processo. O sistema recusa a finalização dele na tela e em qualquer outro caminho de acesso, e não só esconde o botão |
| RN-13 | O Analista Terceirizado pode encaminhar o processo para quem conclui |
| RN-14 | Abrir uma ficha nova não altera decisão já registrada |
| RN-15 | Se o processo já tem decisão, voltar a analisá-lo segue uma regra explícita de reabertura. Essa reabertura não apaga a decisão tomada |

**Situação da ficha.** A HU.074 já prevê rascunho e finalizada. As demais situações que a SEDUR quiser ver na tramitação (por exemplo, encerrada) entram conforme a resposta ao ponto 3 da validação.

**Diferença para a HU.074.** A HU.074 diz que a nova revisão copia a última. Pela RN-02, passa a copiar a última do próprio analista. As outras regras da HU.074 (finalizar uma vez, ficha finalizada não recebe rascunho, finalizar não decide o processo) continuam valendo.

---

## Fluxos esperados

**Fluxo A — O mesmo analista abre outra ficha**

1. O Analista A abre uma ficha.
2. Preenche e salva, ou finaliza.
3. Abre outra ficha para o mesmo processo.
4. A nova ficha vem com os dados da última ficha do Analista A, e a anterior permanece como estava.

**Fluxo B — Outro analista assume o processo**

1. O Analista A registra a análise.
2. O processo passa ao Analista B.
3. O Analista B abre uma ficha nova, em branco.
4. As fichas de A e de B ficam consultáveis na tramitação.

**Fluxo C — Analista terceirizado e conclusão**

1. O Analista Terceirizado registra o parecer técnico.
2. Encaminha o processo a quem conclui.
3. Quem conclui lê as fichas, faz a sua análise e emite a decisão.
4. O histórico guarda as duas atuações e mostra quem decidiu.

---

## Pré-requisitos

- Atribuição de analisar processos, para abrir e preencher ficha
- Atribuição de concluir a análise, para emitir a decisão do processo
- Processo em análise

## Dependências

**Passadas**

- HU.072 — Assumir análise
- HU.074 — Preencher ficha de análise
- HU.078 — Emitir parecer
- HU.089 — Reabrir análise

**Futuras**

- Tela de tramitação com a lista de fichas por analista, a comparação entre elas e a indicação da análise vigente e da decisão formal

---

## Comportamento Esperado

**Cenário 1: Várias fichas do mesmo analista**
**Dado que** o analista tem ficha finalizada no processo
**Quando** abre uma nova ficha
**Então** o sistema cria uma ficha nova
**E** a ficha finalizada continua como estava

**Cenário 2: Pré-preenchimento com a própria ficha**
**Dado que** o analista tem ficha anterior no processo
**Quando** abre uma nova ficha
**Então** os campos vêm preenchidos com a última ficha dele
**E** a ficha de origem não muda

**Cenário 3: Primeira ficha do analista**
**Dado que** outro analista já preencheu ficha no processo
**E** o usuário nunca abriu ficha nele
**Quando** o usuário abre uma ficha
**Então** a ficha abre em branco
**E** nenhum dado da ficha do outro analista é copiado

**Cenário 4: Consulta às fichas de outros analistas**
**Dado que** há fichas de mais de um analista no processo
**Quando** o usuário abre a tramitação
**Então** vê todas as fichas permitidas ao seu perfil, com o autor e as datas
**E** pode abrir cada uma para leitura

**Cenário 5: Registro na tramitação**
**Dado que** o analista salvou ou finalizou uma ficha
**Quando** a tramitação é consultada
**Então** aparece o registro com analista, data e hora, situação e o conteúdo da ficha naquele momento

**Cenário 6: Encerramento da ficha anterior**
**Dado que** abrir a ficha nova encerra a anterior
**Quando** a ficha nova é aberta
**Então** a tramitação mostra o encerramento como mudança de situação
**E** o conteúdo da ficha anterior continua consultável

**Cenário 7: Analista terceirizado tenta finalizar o processo**
**Dado que** o usuário é Analista Terceirizado
**Quando** tenta finalizar o processo, pela tela ou por outro caminho de acesso
**Então** a finalização é recusada
**E** nada muda no processo

**Cenário 8: Analista terceirizado encaminha para conclusão**
**Dado que** o Analista Terceirizado finalizou a sua ficha
**Quando** encaminha o processo a quem conclui
**Então** o processo segue para essa pessoa
**E** a tramitação registra a ficha e o encaminhamento em nome dele

**Cenário 9: Decisão de quem conclui**
**Dado que** o usuário pode concluir a análise
**E** há fichas de outros analistas no processo
**Quando** ele registra a decisão
**Então** a decisão fica com o nome dele
**E** os pareceres anteriores seguem consultáveis, mesmo que divirjam do resultado

**Cenário 10: Última ficha não é decisão**
**Dado que** existe ficha mais recente que a decisão registrada
**Quando** o processo é consultado
**Então** o resultado formal continua sendo o da decisão
**E** a ficha mais nova aparece como análise, sem alterar a decisão

**Cenário 11: Processo já decidido**
**Dado que** o processo tem decisão registrada
**Quando** alguém quer analisá-lo de novo
**Então** só é possível pela regra de reabertura
**E** a decisão anterior permanece no histórico

---

## Cenários de teste para homologação

| # | Situação | Como testar | Resultado esperado |
|:---:|---|---|---|
| H1 | Mesmo analista, duas fichas | Analista A finaliza uma ficha e abre outra | Nova ficha com os dados da anterior; a anterior intacta |
| H2 | Outro analista | Analista B abre ficha em processo com ficha de A | Ficha em branco; fichas de A e B na tramitação |
| H3 | Finalização pelo terceirizado | Terceirizado tenta concluir o processo | Recusado |
| H4 | Conclusão por quem pode | Chefia emite a decisão com fichas de A e B | Decisão no nome da chefia; fichas preservadas |
| H5 | Pareceres divergentes | Decisão contrária à ficha de A | As duas aparecem, cada uma com o seu autor |
| H6 | Ficha após decisão | Abrir ficha em processo decidido | Decisão inalterada |

---

## Pontos para validação da SEDUR

1. **Quem vê as fichas dos outros.** Todo analista com acesso ao processo vê as fichas de todos, ou só a chefia e quem conclui? (Todos / Só chefia e quem conclui)
2. **Ficha anterior do mesmo analista.** Abrir a ficha nova encerra a anterior, ou as duas ficam abertas? (Encerra / Ficam abertas)
3. **Situações da ficha.** Além de rascunho e finalizada, a tramitação mostra "encerrada" quando uma ficha é substituída por outra? (Sim / Não)
4. **Análise vigente.** Quem define qual ficha é a análise vigente: a última finalizada, a do responsável atual pelo processo ou a escolha de quem conclui?
5. **Reabrir processo decidido.** Quem pode reabrir um processo já decidido, em que situações e com que justificativa? O sistema hoje não reabre análise concluída.
6. **Devolução de "Para conclusão".** Quem pode devolver um processo de "Para conclusão" para "Em análise"? Hoje qualquer perfil que analisa pode, inclusive o terceirizado, e só por acesso direto, sem botão.
7. **Terceirizado.** Além de finalizar o processo, há outra ação de quem conclui que o terceirizado também não pode fazer, como a justificativa à Junta?

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
