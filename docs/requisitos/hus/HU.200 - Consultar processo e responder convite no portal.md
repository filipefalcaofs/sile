# HU.200 – CONSULTAR PROCESSO E RESPONDER CONVITE NO PORTAL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 04/11/2026 | Criação da HU da melhoria entregue entre 16/10 e 04/11/2026 | 1.0 | Sudoeste Informática |

---

## Visão Geral

O portal do cidadão passa a oferecer dois serviços próprios, além da renovação: **Consultar processo** e **Responder convite**. Os dois aparecem no menu do portal e cada um tem o seu interruptor na aba Portal dos Parâmetros.

1. **Consultar processo pelo número.** Qualquer cidadão que entrou no portal digita o número do processo, o número da viabilidade (TVL) ou o protocolo BAP. O sistema procura no Viabiliza e, se não achar, no sistema anterior (Simplifica). O resultado mostra o que um extrato de andamento mostraria: números, serviço, empresa, data de protocolo, situação e as etapas da tramitação. Não mostra CPF, contato, endereço, texto do convite nem parecer.
2. **Consultar os processos de um CNPJ.** Se o cidadão digita um CNPJ, o sistema lista os processos da empresa nos dois sistemas. Cada item abre a consulta pelo número.
3. **Convites para responder.** Uma lista reúne os convites abertos e dentro do prazo dos processos em que o cidadão é requerente ou solicitante declarado na Junta Comercial (REGIN).
4. **Responder convite com anexo.** A resposta ao convite pode levar um arquivo PDF, JPG ou PNG. O analista e o cidadão baixam esse anexo depois, cada um na sua tela do processo.

---

## Sumário

- [Épico: EP11 — Pendências e Comunicação](#épico-ep11--pendências-e-comunicação)
- [Histórias de Usuário](#história-de-usuário-consultar-processo-pelo-número)
- [Regras](#regras)
- [Fluxos esperados](#fluxos-esperados)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Comportamento Esperado](#comportamento-esperado)
- [Cenários de teste para homologação](#cenários-de-teste-para-homologação)
- [Pontos para validação da SEDUR](#pontos-para-validação-da-sedur)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP11 — Pendências e Comunicação

**Como** cidadão **quero** acompanhar um processo de viabilidade e responder aos pedidos da SEDUR pelo portal **, de modo que** eu saiba em que pé está o processo e a análise siga sem depender de atendimento presencial

## História de Usuário: Consultar processo pelo número

**Como** cidadão **quero** digitar o número que tenho em mãos (do processo, da viabilidade ou do BAP) e ver a situação e a tramitação **, de modo que** eu acompanhe o processo mesmo sem ser o requerente e mesmo que ele tenha sido aberto no sistema anterior

## História de Usuário: Consultar os processos de uma empresa

**Como** cidadão **quero** digitar o CNPJ da empresa e ver a lista dos processos dela no Viabiliza e no sistema anterior **, de modo que** eu encontre o processo quando não sei o número

## História de Usuário: Consultar convites para responder

**Como** requerente ou solicitante declarado **quero** ver numa só lista os convites que posso responder agora **, de modo que** eu não perca o prazo de nenhum pedido da SEDUR

## História de Usuário: Responder convite com anexo

**Como** requerente ou solicitante declarado **quero** responder o convite com um texto e, se precisar, com um documento anexo **, de modo que** eu entregue a informação pedida de uma vez

**Como** analista **quero** baixar o documento que o cidadão anexou à resposta **, de modo que** eu use o documento na análise

---

## Regras

| Nº | Regra |
|:---:|---|
| RN-01 | A consulta de processo exige que o cidadão tenha entrado no portal. Não exige vínculo com o processo nem com a empresa |
| RN-02 | O campo aceita CNPJ, número do processo, número da viabilidade (TVL) ou protocolo BAP. Espaços são ignorados e letras maiúsculas e minúsculas são equivalentes |
| RN-03 | No Viabiliza, a busca considera o número do processo, o protocolo BAP e o número da viabilidade. Rascunho não é processo e não aparece. Se mais de um processo casar, vale o que casa pelo número do processo e, depois, o mais recente |
| RN-04 | Sem resultado no Viabiliza, o sistema procura no sistema anterior (Simplifica) quando o número tem formato dele: número do processo no formato 0000000000-00000000/0000 (ou só os 22 dígitos), número de TVL de 6 a 10 dígitos ou protocolo BAP |
| RN-05 | Pelo número da TVL do sistema anterior, o sistema localiza o processo que a gerou e mostra esse processo |
| RN-06 | O resultado mostra: origem (Viabiliza ou Sistema anterior), número do processo, número da viabilidade, protocolo BAP, serviço, razão social e CNPJ da empresa, data de protocolo, situação pública e as etapas da tramitação com data |
| RN-07 | A consulta não mostra CPF, contato, endereço, atividades, texto do convite, parecer nem quem atuou no processo |
| RN-08 | No processo do sistema anterior, as etapas mostram a data e a situação de cada andamento. O texto das mensagens do histórico não é mostrado, porque pode ser dirigido ao requerente |
| RN-09 | Se o processo do Viabiliza tem convite aberto e no prazo, a consulta avisa que há convite aguardando resposta e mostra o prazo. O botão de responder só aparece para o requerente ou o solicitante declarado do REGIN |
| RN-10 | Com CNPJ (14 dígitos, com ou sem pontuação), o sistema lista os processos da empresa no Viabiliza e no sistema anterior, até 50 de cada sistema, com origem, número, empresa, situação e data. Rascunho não entra |
| RN-11 | Se o sistema anterior não responde, o cidadão recebe o aviso de que ele está indisponível. Os processos do Viabiliza continuam aparecendo |
| RN-12 | Número não encontrado em nenhum dos dois sistemas mostra "Processo não encontrado", com a orientação de conferir o número ou usar o CNPJ |
| RN-13 | Cada consulta encontrada fica registrada na trilha de auditoria, inclusive a consulta por CNPJ e a de processo do sistema anterior |
| RN-14 | O mesmo cidadão pode fazer até 30 consultas de processo por minuto. Acima disso, o sistema pede que aguarde |
| RN-15 | A lista de convites traz os convites abertos e dentro do prazo (ou sem prazo) dos processos em que o cidadão é requerente ou solicitante declarado do REGIN, ordenados pelo prazo mais próximo. Mostra número do processo, empresa, descrição do pedido e prazo |
| RN-16 | Sócio que não é requerente nem solicitante declarado não vê o convite nessa lista e não responde |
| RN-17 | A resposta ao convite é obrigatória e tem até 5000 caracteres |
| RN-18 | A resposta pode levar um anexo opcional, em PDF, JPG ou PNG, com tamanho até o limite de anexos definido pela SEDUR nos Parâmetros (padrão 10 MB). A tela informa os tipos aceitos e o limite |
| RN-19 | Só responde o convite quem é requerente do processo ou solicitante declarado do REGIN. Outra pessoa não acessa o convite |
| RN-20 | Só é possível responder convite aberto de processo em pendência. Fora disso, nada é gravado e o cidadão recebe o aviso |
| RN-21 | Ao responder, o convite fica respondido, o processo volta para análise e o analista responsável é avisado. Se a gravação falhar, o anexo também não fica guardado |
| RN-22 | O anexo da resposta pode ser baixado pelo cidadão que pode ver o processo no portal e pelo usuário da gestão que consulta o processo. Anexo de outro convite ou de outro processo não é encontrado |
| RN-23 | Cada serviço tem o seu interruptor na aba Portal dos Parâmetros (Consultar processo e Responder convite) e só vale com o interruptor geral dos serviços do portal ligado. Desligado, o serviço fica indisponível com aviso |

**Diferença para a HU.175.** A HU.175 trata da consulta do próprio processo em Minhas solicitações e do link temporário. Esta HU acrescenta a consulta digitando o número ou o CNPJ, aberta a qualquer cidadão logado, que a HU.175 registrava como ainda não oferecida, e inclui os processos do sistema anterior.

**Diferença para a HU.177.** A HU.177 diz que só o requerente responde e que não há resposta com anexo. Pela RN-18 e pela RN-19, a resposta passa a aceitar um anexo e o solicitante declarado do REGIN também responde.

---

## Fluxos esperados

**Fluxo A: Consulta pelo número**

1. O cidadão abre Consultar processo.
2. Digita o número do processo, da viabilidade ou o protocolo BAP.
3. O sistema procura no Viabiliza e, sem resultado, no sistema anterior.
4. Mostra o resumo do processo e a tramitação.

**Fluxo B: Consulta pelo CNPJ**

1. O cidadão digita o CNPJ da empresa.
2. O sistema lista os processos da empresa nos dois sistemas.
3. O cidadão abre um item e vê a consulta pelo número.

**Fluxo C: Resposta ao convite**

1. O cidadão abre Responder convite e vê os convites abertos.
2. Escolhe um convite, escreve a resposta e, se quiser, anexa um arquivo.
3. Envia. O processo volta para análise e o analista é avisado.
4. O analista abre o processo na gestão e baixa o anexo.

---

## Pré-requisitos

- Cidadão com entrada no portal
- Interruptor geral dos serviços do portal ligado, e o interruptor do serviço correspondente ligado
- Para responder: convite aberto em processo em pendência, e o cidadão ser requerente ou solicitante declarado do REGIN
- Para consultar o sistema anterior: o serviço de consulta do Simplifica disponível

## Dependências

**Passadas**

- HU.087 — Solicitar pendência
- HU.088 — Notificar pendência
- HU.110 — Vincular protocolo BAP
- HU.175 — Consultar protocolo
- HU.177 — Responder pendência
- HU.199 — Entrar no portal pelo Portal de Serviços da SEDUR
- HU.205 — Consultar TVLs e processos pela API do legado Simplifica

**Futuras**

- Nenhuma

---

## Comportamento Esperado

**Cenário 1: Processo do Viabiliza encontrado**
**Dado que** o cidadão entrou no portal
**Quando** digita o número de um processo do Viabiliza
**Então** vê o número, a viabilidade, o BAP, o serviço, a empresa, a data de protocolo, a situação e a tramitação
**E** não vê CPF, contato, endereço, texto do convite nem parecer

**Cenário 2: Busca pelo número da viabilidade ou do BAP**
**Dado que** o cidadão tem só o número da viabilidade ou o protocolo BAP
**Quando** digita esse número
**Então** o sistema mostra o processo correspondente

**Cenário 3: Processo do sistema anterior**
**Dado que** o número não existe no Viabiliza
**E** tem o formato do sistema anterior
**Quando** o cidadão consulta
**Então** o sistema mostra o processo do Simplifica, marcado como "Sistema anterior"
**E** as etapas trazem só data e situação

**Cenário 4: Sistema anterior fora do ar**
**Dado que** o Simplifica não responde
**Quando** o cidadão consulta um número do sistema anterior
**Então** recebe o aviso de que o sistema anterior está indisponível

**Cenário 5: Consulta por CNPJ**
**Dado que** a empresa tem processos nos dois sistemas
**Quando** o cidadão digita o CNPJ
**Então** vê a lista com os processos dos dois sistemas e a origem de cada um
**E** cada item abre a consulta pelo número

**Cenário 6: Número não encontrado**
**Dado que** o número não existe em nenhum dos dois sistemas
**Quando** o cidadão consulta
**Então** vê "Processo não encontrado" com a orientação de conferir o número ou usar o CNPJ

**Cenário 7: Convite aberto visto por terceiro**
**Dado que** o processo tem convite aberto
**E** o cidadão não é requerente nem solicitante declarado
**Quando** consulta o processo
**Então** vê o aviso de convite aguardando resposta e o prazo
**E** não vê o botão de responder

**Cenário 8: Lista de convites**
**Dado que** o cidadão é requerente ou solicitante declarado em processos com convite aberto e no prazo
**Quando** abre Responder convite
**Então** vê esses convites, do prazo mais próximo para o mais distante

**Cenário 9: Resposta com anexo**
**Dado que** o convite está aberto e o processo em pendência
**Quando** o cidadão envia a resposta com um PDF dentro do limite
**Então** o convite fica respondido e o processo volta para análise
**E** o analista é avisado e consegue baixar o anexo

**Cenário 10: Anexo inválido**
**Dado que** o cidadão anexa arquivo de tipo não aceito ou acima do limite
**Quando** envia a resposta
**Então** o sistema recusa com a mensagem do motivo
**E** nada é gravado

**Cenário 11: Convite já respondido**
**Dado que** o convite não está mais aberto
**Quando** o cidadão tenta respondê-lo
**Então** recebe o aviso de que o convite não pode ser respondido
**E** nada é gravado

---

## Cenários de teste para homologação

| # | Situação | Como testar | Resultado esperado |
|:---:|---|---|---|
| H1 | Número do processo do Viabiliza | Consultar com o número de um processo protocolado | Resumo e tramitação, sem dados pessoais |
| H2 | Número da viabilidade | Consultar com o número de uma TVL do Viabiliza | Mesmo processo do H1 |
| H3 | Processo do Simplifica | Consultar com número no formato 0000000000-00000000/0000 | Processo marcado "Sistema anterior" |
| H4 | TVL do Simplifica | Consultar com o número de uma TVL do sistema anterior | Processo que gerou a TVL |
| H5 | CNPJ | Consultar com o CNPJ de empresa com processos nos dois sistemas | Lista com as duas origens |
| H6 | Rascunho | Consultar o número de um rascunho | Não encontrado |
| H7 | Terceiro com convite aberto | Outro cidadão consulta processo com convite | Aviso de convite, sem botão de responder |
| H8 | Lista de convites | Requerente abre Responder convite | Só convites abertos e no prazo dos seus processos |
| H9 | Resposta com anexo | Responder com PDF de 2 MB | Processo em análise; anexo baixável na gestão |
| H10 | Anexo de tipo errado | Responder com arquivo .docx | Recusado com mensagem |
| H11 | Sócio sem papel de solicitante | Sócio tenta responder convite | Sem acesso ao convite |
| H12 | Serviço desligado | Desligar Consultar processo nos Parâmetros | Serviço indisponível com aviso |

---

## Pontos para validação da SEDUR

1. **Consulta aberta.** Qualquer cidadão logado vê, pelo número ou pelo CNPJ, a empresa, a situação e a tramitação de processos de outras pessoas. A SEDUR confirma esse nível de exposição? (Confirma / Restringir ao requerente e aos vinculados)
2. **Limite da lista por CNPJ.** A lista traz até 50 processos de cada sistema. O limite atende? (Atende / Outro valor)
3. **Sócio.** Sócio que não é requerente nem solicitante declarado não responde convite. A SEDUR confirma? (Confirma / Sócio também responde)
4. **Quantidade de anexos.** A resposta aceita um anexo. É suficiente, ou o cidadão precisa enviar mais de um documento por resposta? (Um / Vários)

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
