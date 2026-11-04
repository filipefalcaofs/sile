# HU.205 – CONSULTAR TVLS E PROCESSOS PELA API DO LEGADO SIMPLIFICA
**PROJETO: API do legado Simplifica**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 04/11/2026 | Criação da HU da melhoria entregue entre 16/10 e 04/11/2026 | 1.0 | Sudoeste Informática |

---

## Visão Geral

Muitas viabilidades de Salvador foram emitidas no Simplifica, antes do Viabiliza. O cidadão que vai renovar uma TVL antiga, o analista que precisa conferir um processo antigo e a Minha Área do SAD precisam desses dados. Esta HU descreve as consultas que a API do legado Simplifica oferece aos sistemas consumidores e às pessoas com sessão do usuário.

1. **TVLs.** Lista as TVLs deferidas de um CNPJ, de uma inscrição imobiliária ou de um número de TVL, e mostra o detalhe de uma TVL pelo número. Cada TVL vem com a situação calculada (vigente, a vencer ou vencida) e os dias que faltam para vencer.
2. **Processos por chave.** Lista os processos de um número de processo, de um CPF ou CNPJ, de uma inscrição imobiliária ou de um número de TVL.
3. **Pesquisa de processos.** Pesquisa com vários filtros combinados e resultado em páginas, usada pela consulta de processos do Simplifica na gestão do Viabiliza.
4. **Detalhe do processo.** Mostra o processo com solicitante, parecer, fichas de análise, TVLs, histórico e localização.

Quem usa cada consulta:

| Consulta | Quem usa |
|---|---|
| TVLs por CNPJ, inscrição ou número | Viabiliza, na renovação de viabilidade do Simplifica e na comparação de atividades |
| Detalhe da TVL | Viabiliza, ao protocolar a renovação |
| Processos por chave | Minha Área do SAD e Viabiliza, na consulta por número |
| Pesquisa de processos | Viabiliza, na consulta de processos do Simplifica na gestão |
| Detalhe do processo | Viabiliza, na ficha do processo do Simplifica |

Todas as consultas só leem o banco do Simplifica. Nenhuma altera dado do legado.

---

## Sumário

- [Épico: EP13 — Integrações](#épico-ep13--integrações)
- [Histórias de Usuário](#histórias-de-usuário)
- [Regras](#regras)
- [Fluxos esperados](#fluxos-esperados)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Comportamento Esperado](#comportamento-esperado)
- [Cenários de teste para homologação](#cenários-de-teste-para-homologação)
- [Pontos para validação da SEDUR](#pontos-para-validação-da-sedur)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP13 — Integrações

**Como** sistema consumidor autorizado **quero** consultar TVLs e processos do Simplifica por uma chave conhecida **, de modo que** o cidadão e o servidor vejam no Viabiliza e no SAD o que foi emitido antes do Viabiliza, sem acesso direto ao banco do legado

## Histórias de Usuário

**Como** Viabiliza **quero** listar as TVLs deferidas de um CNPJ, de uma inscrição ou de um número, com a situação e os dias para vencer **, de modo que** o cidadão saiba se pode renovar uma viabilidade do Simplifica

**Como** Viabiliza **quero** consultar o detalhe de uma TVL pelo número, com atividades, endereço, marcações e renovações **, de modo que** a renovação seja protocolada com os dados da TVL original

**Como** Minha Área do SAD **quero** listar os processos do Simplifica de um CPF ou CNPJ, de uma inscrição, de um número de processo ou de um número de TVL **, de modo que** o cidadão veja os seus processos antigos junto com os novos

**Como** analista da SEDUR, pela gestão do Viabiliza, **quero** pesquisar processos do Simplifica combinando filtros e abrir o detalhe de cada um **, de modo que** eu confira o histórico de um imóvel ou de uma empresa sem sair do Viabiliza

---

## Regras

| Nº | Regra |
|:---:|---|
| RN-01 | As consultas de TVL exigem a permissão de consulta de TVLs. As consultas de processo exigem a permissão de consulta de processos. As regras de acesso estão na HU.204 |
| RN-02 | A lista de TVLs aceita exatamente uma chave: CNPJ (14 dígitos), inscrição imobiliária (1 a 12 dígitos) ou número da TVL (1 a 18 dígitos). Pontos, barras e traços são ignorados. Nenhuma chave, ou mais de uma, é recusada com o pedido de informar exatamente uma |
| RN-03 | A lista de TVLs tem duas formas: só as provisórias deferidas, ou as deferidas de qualquer tipo (provisória, definitiva ou pré-operacional) |
| RN-04 | Um número de TVL pode ter vários registros no Simplifica. A consulta usa o registro vigente de cada número: o de validade mais distante e, no empate, o mais recente |
| RN-05 | Na busca por inscrição, os zeros à esquerda são ignorados dos dois lados |
| RN-06 | O titular da TVL é o CNPJ gravado na TVL. Se ele não tiver 14 dígitos, vale o documento do requerente do processo e, depois, o do outorgante. A resposta diz de onde o titular veio. A busca por CNPJ usa esse titular |
| RN-07 | A lista de TVLs devolve no máximo 50 TVLs, em ordem de número. Se houver mais, a resposta avisa que a lista foi cortada |
| RN-08 | Cada TVL traz: número, tipo, deferida, ativa, emissão, validade, situação, dias para vencer, se foi renovada depois, data da última renovação, titular, razão social, inscrição imobiliária e endereço (logradouro, número, bairro e CEP) |
| RN-09 | A situação compara as datas de calendário de hoje, no horário de Salvador, com a validade. Validade já passada: **vencida**, com dias para vencer negativos. Validade dentro da janela: **a vencer**. Depois da janela: **vigente**. TVL sem validade não tem situação |
| RN-10 | A janela de "a vencer" é de 90 dias. Na lista de TVLs, o sistema consumidor pode pedir outra janela, de 1 a 365 dias. No detalhe da TVL e no detalhe do processo, vale sempre a janela padrão |
| RN-11 | "Renovada depois" fica marcada quando existe registro mais novo do mesmo número de TVL ou pedido de renovação desse número no Simplifica |
| RN-12 | O detalhe da TVL aceita o número (1 a 18 dígitos), de qualquer tipo e situação de deferimento. Além dos dados da lista, traz o número do processo, complemento, zona, via, as marcações (sede, MEI, escritório virtual, culto religioso, edifício comercial), condicionante adicional, as atividades (código CNAE e descrição) e as renovações (data do pedido e data do pagamento). Número inexistente: "TVL não encontrada." |
| RN-13 | A lista de processos por chave aceita exatamente uma chave: número do processo (até 50 caracteres), inscrição imobiliária (1 a 12 dígitos), documento (CPF com 11 ou CNPJ com 14 dígitos) ou número da TVL (1 a 18 dígitos). Nenhuma chave, ou mais de uma, é recusada com o pedido de informar exatamente uma |
| RN-14 | O número do processo é procurado no número interno do Simplifica e no protocolo da Junta (REGIN), vigente ou indeferido, sem diferença entre maiúsculas e minúsculas |
| RN-15 | O documento é procurado no requerente do processo e no CNPJ das TVLs do processo, com ou sem máscara |
| RN-16 | A lista de processos devolve no máximo 50 processos, do mais recente ao mais antigo. Cada processo traz número, protocolo da Junta, data de criação, situação (nome e se é final) e os números das TVLs. A lista não traz solicitante nem ficha |
| RN-17 | O protocolo mostrado é o vigente. Se não houver, mostra o protocolo indeferido |
| RN-18 | A pesquisa de processos exige ao menos um filtro ("Informe ao menos um filtro.") |
| RN-19 | Filtros da pesquisa: busca geral, número do processo, protocolo, número da TVL, CPF ou CNPJ, inscrição, empresa, CNAE, complemento, logradouro, bairro, CEP, grupo de situação, situação, situação da análise (deferido, não deferido ou sem conclusão), fluxo (expresso ou análise), categoria, serviço, setor, analista e período do protocolo |
| RN-20 | Cada filtro informado restringe os demais. Os filtros de texto (busca, empresa, CNAE, complemento, logradouro, bairro) pedem de 3 a 200 caracteres e procuram o trecho em qualquer parte. O protocolo pede ao menos 3 caracteres. O CEP pede 8 dígitos |
| RN-21 | A busca geral procura o trecho no número do processo, no protocolo da Junta, na inscrição, na razão social, no número da TVL e, quando tem dígitos, no CNPJ da TVL e no documento do requerente |
| RN-22 | O filtro de empresa procura na razão social da TVL e no nome do requerente. O filtro de CNAE procura no código e na descrição das atividades |
| RN-23 | O período do protocolo usa datas no formato AAAA-MM-DD e inclui o dia final inteiro. Data final antes da inicial é recusada |
| RN-24 | A pesquisa vem do processo mais recente para o mais antigo, com até 50 por página (padrão 50) e informa total, página, itens por página e número de páginas. Cada item é igual ao da lista de processos |
| RN-25 | O detalhe do processo aceita o número interno ou o protocolo da Junta, vigente ou indeferido. Número inexistente: "Processo não encontrado." |
| RN-26 | O detalhe do processo traz, além da lista: solicitante (nome e documento, sem e-mail e sem telefone), localização (latitude e longitude, vazia quando o ponto não é numérico), parecer da ficha mais recente (deferido, texto, data, tipo e validade), fichas da mais antiga para a mais recente, TVLs com a situação e o escritório virtual (sede ou abrigado), e o histórico (data, título, mensagem, situação e quem registrou) |
| RN-27 | Cada ficha traz: data de atualização, finalizada, deferida, parecer, número da TVL, condicionante, a conferência de razão social, espaço, porte, CEP, bairro e renovação (deferido ou em análise) e as atividades (código, descrição, deferida, em análise, incluída, excluída, enquadrada e enquadramento) |
| RN-28 | Os anexos do processo não são devolvidos |
| RN-29 | Erros de preenchimento são recusados com a mensagem do campo. Toda consulta fica no registro de uso da HU.204 |

---

## Fluxos esperados

**Fluxo A — Renovação de TVL do Simplifica no Viabiliza**

1. O cidadão informa o CNPJ ou o número da TVL no Viabiliza.
2. O Viabiliza consulta as TVLs deferidas dessa chave.
3. Cada TVL volta com a situação e os dias para vencer.
4. Ao protocolar, o Viabiliza consulta o detalhe da TVL para levar atividades, endereço e marcações.

**Fluxo B — Processos antigos na Minha Área do SAD**

1. O cidadão entra na Minha Área do SAD.
2. O SAD consulta os processos do Simplifica pelo documento dele.
3. O SAD mostra até 50 processos, do mais recente ao mais antigo.

**Fluxo C — Pesquisa na gestão do Viabiliza**

1. O analista escolhe a fonte Simplifica na consulta de processos.
2. Informa um ou mais filtros.
3. O Viabiliza pede a página à API e mostra o total.
4. O analista abre um processo e vê o detalhe.

---

## Pré-requisitos

- Sistema consumidor ou sessão do usuário com a permissão de consulta correspondente (HU.204)
- Banco do Simplifica disponível para leitura

## Dependências

**Passadas**

- HU.174 — Renovar viabilidade
- HU.175 — Consultar protocolo

**Futuras**

- HU.204 — Controlar o acesso à API do legado Simplifica
- HU.201 — Renovar viabilidade do Viabiliza e do Simplifica
- HU.202 — Consultar processos do Simplifica na gestão
- HU.200 — Consultar processo e responder convite no portal

---

## Comportamento Esperado

**Cenário 1: TVLs de um CNPJ**
**Dado que** o CNPJ tem TVLs deferidas no Simplifica
**Quando** o sistema consumidor consulta as TVLs desse CNPJ
**Então** recebe as TVLs com o registro vigente de cada número
**E** cada uma traz situação e dias para vencer

**Cenário 2: Só provisórias**
**Dado que** o CNPJ tem uma TVL provisória e uma definitiva, ambas deferidas
**Quando** o sistema consulta só as provisórias
**Então** recebe apenas a provisória

**Cenário 3: Situação a vencer**
**Dado que** a validade da TVL está a 30 dias de hoje
**Quando** a TVL é consultada com a janela padrão
**Então** a situação é "a vencer" e os dias para vencer são 30

**Cenário 4: Situação vencida**
**Dado que** a validade da TVL foi há 10 dias
**Quando** a TVL é consultada
**Então** a situação é "vencida" e os dias para vencer são -10

**Cenário 5: Chave da TVL em duplicidade**
**Dado que** o sistema informou CNPJ e número da TVL na mesma consulta
**Quando** consulta
**Então** a consulta é recusada pedindo exatamente uma chave

**Cenário 6: Mais de 50 TVLs**
**Dado que** a chave tem mais de 50 TVLs
**Quando** o sistema consulta
**Então** recebe as 50 primeiras em ordem de número
**E** a resposta avisa que a lista foi cortada

**Cenário 7: Detalhe da TVL inexistente**
**Dado que** o número não existe no Simplifica
**Quando** o sistema consulta o detalhe
**Então** recebe "TVL não encontrada."

**Cenário 8: Processos por documento**
**Dado que** o CPF é requerente de processos no Simplifica
**Quando** o SAD consulta os processos desse CPF
**Então** recebe até 50 processos, do mais recente ao mais antigo
**E** cada um traz número, protocolo, data, situação e números das TVLs

**Cenário 9: Processo pelo protocolo indeferido**
**Dado que** o processo tem só protocolo indeferido na Junta
**Quando** o sistema consulta por esse protocolo
**Então** encontra o processo
**E** o protocolo mostrado é o indeferido

**Cenário 10: Pesquisa sem filtro**
**Dado que** nenhum filtro foi informado
**Quando** o sistema pesquisa
**Então** a pesquisa é recusada com "Informe ao menos um filtro."

**Cenário 11: Pesquisa combinada**
**Dado que** o analista informou bairro e período do protocolo
**Quando** o Viabiliza pesquisa
**Então** só vêm processos que atendem aos dois filtros
**E** a resposta traz total e número de páginas

**Cenário 12: Texto curto**
**Dado que** o filtro de empresa tem 2 caracteres
**Quando** o sistema pesquisa
**Então** a pesquisa é recusada com "A empresa deve ter no mínimo 3 caracteres."

**Cenário 13: Detalhe do processo**
**Dado que** o processo existe
**Quando** o Viabiliza consulta o detalhe
**Então** recebe solicitante, localização, parecer, fichas, TVLs e histórico
**E** não recebe anexos, e-mail nem telefone do solicitante

---

## Cenários de teste para homologação

| # | Situação | Como testar | Resultado esperado |
|:---:|---|---|---|
| H1 | TVLs por CNPJ | Consultar um CNPJ com TVL deferida | TVL com situação e dias para vencer |
| H2 | Inscrição com zeros | Consultar a inscrição com e sem zeros à esquerda | Mesmo resultado |
| H3 | Janela personalizada | Consultar com janela de 30 dias uma TVL que vence em 60 | Situação "vigente" |
| H4 | Detalhe da TVL | Consultar um número existente | Atividades, marcações e renovações |
| H5 | TVL inexistente | Consultar número que não existe | "TVL não encontrada." |
| H6 | Processos por CNPJ | Consultar processos de um CNPJ com e sem máscara | Mesmo resultado, até 50 processos |
| H7 | Duas chaves | Informar inscrição e documento juntos | Recusado pedindo exatamente uma chave |
| H8 | Pesquisa paginada | Pesquisar um bairro com muitos processos | 50 por página, com total e páginas |
| H9 | Período invertido | Data final antes da inicial | Recusado |
| H10 | Detalhe do processo | Abrir processo com ficha e TVL | Parecer, fichas, TVLs e histórico |
| H11 | Processo inexistente | Consultar número que não existe | "Processo não encontrado." |

---

## Pontos para validação da SEDUR

1. **Janela de "a vencer".** O padrão é 90 dias. A SEDUR confirma esse prazo para a renovação de TVLs do Simplifica? (Confirma / Outro prazo)
2. **Limite de 50 itens.** A lista de TVLs e a lista de processos por chave param em 50 itens, do mais recente ou em ordem de número. Há empresa ou imóvel com mais de 50 registros que precise de lista completa? (Não / Sim, qual)
3. **Titular da TVL.** Quando a TVL não tem CNPJ válido, o titular passa a ser o requerente e, depois, o outorgante. A SEDUR concorda com essa ordem?

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
