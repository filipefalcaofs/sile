# HU.201 – RENOVAR VIABILIDADE DO VIABILIZA E DO SIMPLIFICA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 04/11/2026 | Criação da HU da melhoria entregue entre 16/10 e 04/11/2026 | 1.0 | Sudoeste Informática |

---

## Visão Geral

Até aqui, a renovação no portal só listava as viabilidades provisórias emitidas pelo Viabiliza para as empresas vinculadas ao cidadão. A viabilidade emitida pelo sistema anterior (Simplifica) continuava naquele sistema.

Esta HU muda a renovação em quatro pontos.

1. **Busca única.** O cidadão digita o CNPJ da empresa ou o número da viabilidade. O sistema procura nas viabilidades do Viabiliza e nas do Simplifica, na mesma tela.
2. **Qualquer cidadão renova.** A busca, a consulta e o protocolo não exigem mais vínculo do cidadão com a empresa. O contato informado na renovação só completa o que a empresa ainda não tem.
3. **Janela de antecedência.** A renovação só pode ser pedida a partir de um número de meses antes do vencimento, definido pela SEDUR nos Parâmetros (padrão 3 meses).
4. **Renovação da viabilidade do Simplifica.** A viabilidade provisória deferida no sistema anterior pode ser renovada no Viabiliza. O sistema confere a titularidade, usa o endereço do imóvel pelo cadastro imobiliário, confere as atividades no catálogo e abre o processo de renovação no Viabiliza, guardando o número e os dados da viabilidade anterior.

---

## Sumário

- [Épico: EP08 — Solicitação de Viabilidade](#épico-ep08--solicitação-de-viabilidade)
- [Histórias de Usuário](#história-de-usuário-buscar-viabilidade-para-renovar)
- [Regras](#regras)
- [Fluxos esperados](#fluxos-esperados)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Comportamento Esperado](#comportamento-esperado)
- [Cenários de teste para homologação](#cenários-de-teste-para-homologação)
- [Pontos para validação da SEDUR](#pontos-para-validação-da-sedur)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP08 — Solicitação de Viabilidade

**Como** cidadão **quero** renovar pelo portal a viabilidade provisória da empresa, tenha ela sido emitida pelo Viabiliza ou pelo sistema anterior **, de modo que** a empresa continue regular sem precisar ir à SEDUR

## História de Usuário: Buscar viabilidade para renovar

**Como** cidadão **quero** buscar pelo CNPJ ou pelo número da viabilidade e ver, para cada viabilidade encontrada, se ela pode ser renovada e, se não puder, o motivo **, de modo que** eu saiba o que fazer antes de iniciar o pedido

## História de Usuário: Renovar viabilidade do Viabiliza

**Como** cidadão **quero** renovar a viabilidade provisória emitida pelo Viabiliza, sem precisar ter vínculo com a empresa no portal **, de modo que** o contador ou o responsável que estiver com o número consiga fazer o pedido

## História de Usuário: Renovar viabilidade do Simplifica

**Como** cidadão **quero** renovar no Viabiliza a viabilidade provisória emitida pelo sistema anterior **, de modo que** eu não dependa do Simplifica para manter a empresa regular

**Como** analista **quero** receber a renovação da viabilidade do Simplifica com o número e os dados da viabilidade anterior **, de modo que** eu analise o pedido sabendo de onde ele veio

---

## Regras

| Nº | Regra |
|:---:|---|
| RN-01 | A busca aceita o CNPJ (14 dígitos, com ou sem pontuação) ou o número da viabilidade. Termo sem número reconhecível mostra "Número não reconhecido" |
| RN-02 | Com CNPJ, o sistema lista as viabilidades deferidas do Viabiliza dessa empresa e as provisórias do Simplifica gravadas para esse CNPJ |
| RN-03 | Com número, o sistema procura a viabilidade do Viabiliza com esse número. Se o número tem 7 dígitos ou o formato de processo do Simplifica (0000000000-00000000/0000), também procura no Simplifica a viabilidade ou as viabilidades daquele processo |
| RN-04 | A busca, a consulta e o protocolo não exigem vínculo do cidadão com a empresa |
| RN-05 | Cada viabilidade encontrada mostra origem, número, empresa, CNPJ, endereço, validade, tipo e, quando não pode ser renovada, o motivo. Só a elegível abre o pedido |
| RN-06 | Se o Simplifica não responde, o cidadão recebe o aviso de que as viabilidades do sistema anterior estão indisponíveis. As do Viabiliza continuam aparecendo |
| RN-07 | Janela de antecedência: a renovação só pode ser pedida a partir de N meses antes do vencimento, com N definido nos Parâmetros (padrão 3, entre 1 e 24). Antes disso, o motivo informa a data a partir da qual o pedido é aceito. Depois de vencida, esta regra não impede a renovação |
| RN-08 | Viabilidade do Viabiliza: só a provisória deferida com número pode ser renovada. Definitiva não pode. Continuam valendo o intervalo mínimo entre renovações (padrão 3 meses) e o impedimento enquanto outra renovação da mesma viabilidade estiver protocolada, em análise ou em pendência |
| RN-09 | Viabilidade do Simplifica: só a provisória deferida pode ser renovada. A viabilidade precisa ter atividades registradas, e todas precisam existir ativas no catálogo de CNAE do Viabiliza. Se alguma não existir, o motivo orienta procurar a SEDUR |
| RN-10 | Viabilidade do Simplifica já renovada no sistema anterior dentro da janela da vigência atual não pode ser renovada de novo. Pedido de renovação feito antes da abertura da janela não conta |
| RN-11 | Viabilidade do Simplifica já renovada no Viabiliza não pode ser renovada de novo. Se a renovação for recente (dentro do intervalo mínimo), o motivo informa o intervalo. Renovação dela protocolada, em análise ou em pendência impede outra |
| RN-12 | Viabilidade do Simplifica vencida pode ser renovada enquanto o parâmetro "permite renovar vencida" estiver ligado (padrão ligado). Desligado, o motivo é "Viabilidade vencida não pode ser renovada" |
| RN-13 | Titularidade da viabilidade do Simplifica com CNPJ gravado: o CNPJ da renovação é o gravado. Viabilidade de outro CNPJ não aparece na busca, para não revelar a sua existência |
| RN-14 | Titularidade da viabilidade do Simplifica sem CNPJ gravado: vale o CNPJ da busca. O sistema confere que a razão social do cadastro do CNPJ é igual à da viabilidade (sem considerar acentos, pontuação e sufixos como LTDA, ME, EPP, EIRELI e S.A.) e que o endereço do CNPJ é o do imóvel da viabilidade (mesmo CEP, ou mesmo logradouro e número). Sem conferir, a viabilidade não aparece. Se a consulta do CNPJ ou do cadastro imobiliário falhar, o pedido é bloqueado com aviso para tentar de novo |
| RN-15 | Viabilidade do Simplifica sem CNPJ gravado e buscada pelo número, sem CNPJ: a renovação segue só pela viabilidade, sem conferência de titularidade |
| RN-16 | O assistente da renovação mostra a viabilidade de origem em modo leitura: número, empresa, CNPJ, endereço, atividades, validade e tipo. Endereço e atividades não são editáveis |
| RN-17 | Endereço da renovação do Simplifica: é o do imóvel no cadastro imobiliário, pela inscrição da viabilidade. Sem inscrição, vale o endereço registrado no Simplifica. Se a inscrição não for encontrada ou o cadastro estiver fora do ar, o pedido é bloqueado com o motivo |
| RN-18 | Contato: e-mail, telefone e celular são obrigatórios. O formulário vem preenchido com o e-mail e o telefone de quem está renovando, nunca com os da empresa |
| RN-19 | O contato informado só preenche o e-mail e o telefone da empresa se ela ainda não os tem. Nunca troca o contato já cadastrado |
| RN-20 | As declarações obrigatórias vigentes do tipo Renovação de viabilidade locacional precisam estar marcadas |
| RN-21 | Na renovação do Simplifica, a empresa é a do Viabiliza com o mesmo CNPJ. Se não existir, o sistema a cria com a razão social da viabilidade (ou do cadastro do CNPJ) e o endereço do imóvel, marcada como vinda do sistema anterior |
| RN-22 | As atividades da viabilidade do Simplifica entram na renovação na mesma ordem. A primeira é a principal |
| RN-23 | O processo de renovação do Simplifica guarda o número da viabilidade anterior e os dados dela no momento do pedido |
| RN-24 | Dois pedidos ao mesmo tempo para a mesma viabilidade do Simplifica ou para a mesma empresa não geram dois processos. O segundo recebe o aviso de que já existe protocolo em andamento. A elegibilidade é conferida de novo no momento de gravar |
| RN-25 | Ao protocolar, a renovação (do Viabiliza ou do Simplifica) recebe número, fica protocolada e segue para análise semi-expressa, sem deferimento automático. O território é completado como no protocolo comum. Com setor de triagem ativo configurado, vai para essa caixa |
| RN-26 | O serviço tem interruptor próprio na aba Portal dos Parâmetros e só vale com o interruptor geral dos serviços do portal ligado |

**Diferença para a HU.174.** A HU.174 exige vínculo ativo com a empresa, lista só as viabilidades do Viabiliza das empresas do usuário, pede só telefone e deixa a viabilidade do sistema anterior naquele sistema. Pelas RN-01 a RN-04, RN-07, RN-09 a RN-18 e RN-21 a RN-24, qualquer cidadão busca e renova, a viabilidade do Simplifica passa a ser renovada no Viabiliza, e-mail, telefone e celular passam a obrigatórios e vale a janela de antecedência. A consulta do CNPJ na base da SEDUR dentro da renovação sai do portal (ver HU.203).

---

## Fluxos esperados

**Fluxo A: Renovação de viabilidade do Viabiliza**

1. O cidadão abre Renovação de Viabilidade e digita o CNPJ ou o número.
2. O sistema mostra a viabilidade, elegível ou com o motivo.
3. O cidadão abre a elegível, confere os dados, informa o contato e marca as declarações.
4. Protocola. O processo segue para análise semi-expressa.

**Fluxo B: Renovação de viabilidade do Simplifica pelo CNPJ**

1. O cidadão digita o CNPJ.
2. O sistema busca as provisórias do Simplifica e confere a titularidade de cada uma.
3. Mostra as que pertencem ao CNPJ, com a situação e os dias para vencer.
4. O cidadão abre a elegível e protocola como no Fluxo A.
5. O processo nasce no Viabiliza com o número e os dados da viabilidade anterior.

**Fluxo C: Renovação de viabilidade do Simplifica pelo número**

1. O cidadão digita o número da viabilidade (7 dígitos) ou do processo do Simplifica.
2. O sistema mostra a viabilidade com o CNPJ gravado nela, se houver.
3. O cidadão segue como no Fluxo A.

---

## Pré-requisitos

- Cidadão com entrada no portal
- Interruptor geral dos serviços do portal e interruptor de Renovar viabilidade ligados
- Tipo de serviço Renovação de viabilidade locacional cadastrado
- Para a viabilidade do Simplifica: serviço de consulta do Simplifica, consulta de CNPJ e cadastro imobiliário disponíveis

## Dependências

**Passadas**

- HU.069 — Encaminhar para análise técnica
- HU.107 — Integrar com Cadastro Imobiliário
- HU.150 — Consultar dados do CNPJ
- HU.174 — Renovar viabilidade
- HU.199 — Entrar no portal pelo Portal de Serviços da SEDUR
- HU.205 — Consultar TVLs e processos pela API do legado Simplifica

**Futuras**

- Nenhuma

---

## Comportamento Esperado

**Cenário 1: Busca por CNPJ com viabilidades dos dois sistemas**
**Dado que** a empresa tem viabilidade provisória no Viabiliza e no Simplifica
**Quando** o cidadão busca pelo CNPJ
**Então** vê as duas, cada uma com a origem, a validade e se pode ser renovada

**Cenário 2: Cidadão sem vínculo com a empresa**
**Dado que** o cidadão não tem vínculo com a empresa
**Quando** busca e protocola a renovação de viabilidade elegível
**Então** o pedido é aceito

**Cenário 3: Antes da janela**
**Dado que** a viabilidade vence daqui a mais de N meses
**Quando** o cidadão a encontra na busca
**Então** ela aparece como não renovável
**E** o motivo informa a data a partir da qual a renovação pode ser pedida

**Cenário 4: Viabilidade do Simplifica de outro CNPJ**
**Dado que** a viabilidade do Simplifica tem CNPJ gravado diferente do buscado
**Quando** o cidadão busca pelo CNPJ
**Então** a viabilidade não aparece

**Cenário 5: Viabilidade do Simplifica sem CNPJ gravado**
**Dado que** a viabilidade do Simplifica não tem CNPJ gravado
**Quando** o cidadão busca pelo CNPJ da empresa
**Então** ela só aparece se a razão social e o endereço do CNPJ conferirem com os da viabilidade e do imóvel

**Cenário 6: Atividade fora do catálogo**
**Dado que** uma atividade da viabilidade do Simplifica não existe ativa no catálogo
**Quando** o cidadão tenta renovar
**Então** vê o motivo com o código da atividade e a orientação de procurar a SEDUR

**Cenário 7: Viabilidade já renovada**
**Dado que** a viabilidade do Simplifica já tem renovação deferida ou em andamento no Viabiliza
**Quando** o cidadão a encontra
**Então** ela aparece como não renovável, com o motivo

**Cenário 8: Protocolo da renovação do Simplifica**
**Dado que** a viabilidade do Simplifica é elegível
**Quando** o cidadão informa e-mail, telefone e celular, marca as declarações e protocola
**Então** nasce processo de renovação no Viabiliza, protocolado e em análise semi-expressa
**E** o processo guarda o número e os dados da viabilidade anterior

**Cenário 9: Empresa nova**
**Dado que** o CNPJ da viabilidade do Simplifica não existe no Viabiliza
**Quando** a renovação é protocolada
**Então** o sistema cria a empresa com a razão social da viabilidade e o endereço do imóvel

**Cenário 10: Contato da empresa preservado**
**Dado que** a empresa já tem e-mail e telefone
**Quando** o cidadão protocola com outro contato
**Então** o contato da empresa não muda

**Cenário 11: Pedido em duplicidade**
**Dado que** dois pedidos da mesma viabilidade chegam ao mesmo tempo
**Quando** são protocolados
**Então** só um processo é criado
**E** o outro recebe o aviso de protocolo em andamento

**Cenário 12: Simplifica fora do ar**
**Dado que** o Simplifica não responde
**Quando** o cidadão busca
**Então** vê o aviso de indisponibilidade do sistema anterior
**E** as viabilidades do Viabiliza continuam aparecendo

---

## Cenários de teste para homologação

| # | Situação | Como testar | Resultado esperado |
|:---:|---|---|---|
| H1 | Busca por CNPJ | Buscar CNPJ com viabilidades nos dois sistemas | As duas listadas, com origem |
| H2 | Busca por número do Simplifica | Buscar viabilidade de 7 dígitos do Simplifica | Viabilidade listada |
| H3 | Sem vínculo | Cidadão sem empresa vinculada renova viabilidade elegível | Protocolo aceito |
| H4 | Fora da janela | Viabilidade com vencimento além de 3 meses | Não renovável, com a data de abertura |
| H5 | Definitiva | Buscar viabilidade definitiva | Não renovável |
| H6 | Outro titular | Viabilidade do Simplifica com CNPJ gravado diferente | Não aparece |
| H7 | Atividade inativa | Viabilidade do Simplifica com CNAE fora do catálogo | Não renovável, com o código |
| H8 | Protocolo do Simplifica | Renovar viabilidade elegível do Simplifica | Processo em análise semi-expressa com o número anterior |
| H9 | Contato | Protocolar sem celular | Recusado: "Informe o celular." |
| H10 | Contato da empresa | Protocolar com e-mail diferente do da empresa | E-mail da empresa inalterado |
| H11 | Renovação em andamento | Tentar renovar de novo a mesma viabilidade | Não renovável, aguardando conclusão |
| H12 | Parâmetro de antecedência | Mudar para 6 meses e buscar viabilidade que vence em 5 | Renovável |

---

## Pontos para validação da SEDUR

1. **Renovação sem vínculo.** Qualquer cidadão logado pode renovar a viabilidade de qualquer empresa, desde que tenha o CNPJ ou o número. A SEDUR confirma? (Confirma / Exigir vínculo)
2. **Viabilidade do Simplifica sem CNPJ.** Quando a viabilidade não tem CNPJ gravado e o cidadão busca só pelo número, a renovação segue sem conferência de titularidade. A SEDUR aceita? (Aceita / Exigir CNPJ)
3. **Viabilidade vencida.** A do Simplifica vencida é renovável enquanto o parâmetro estiver ligado, sem atraso máximo; a do Viabiliza vencida também é renovável. Há prazo máximo depois do vencimento? (Sem limite / Informar prazo)
4. **Conferência por razão social.** A conferência exige razão social igual, sem aproximação. Uma empresa que mudou de nome não passa e precisa procurar a SEDUR. A SEDUR confirma? (Confirma / Outra regra)
5. **Contato.** Telefone e celular são os dois obrigatórios. Pode bastar um deles? (Os dois / Um deles)

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
