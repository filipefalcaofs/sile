# HU.204 – CONTROLAR O ACESSO À API DO LEGADO SIMPLIFICA
**PROJETO: API do legado Simplifica**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 04/11/2026 | Criação da HU da melhoria entregue entre 16/10 e 04/11/2026 | 1.0 | Sudoeste Informática |

---

## Visão Geral

Os dados do Simplifica (processos e TVLs emitidas antes do Viabiliza) passaram a ser lidos por outros sistemas: o Viabiliza, a Minha Área do SAD e a consulta web do legado. Para isso foi criada a API do legado Simplifica, uma aplicação própria que só lê o banco do Simplifica e nunca grava nele.

Esta HU trata de quem pode usar essa API e de como o uso é acompanhado.

1. **Sistema consumidor com chave de acesso.** Cada sistema que consulta o legado recebe uma chave de acesso própria, com validade de 90 dias e com as permissões de consulta que precisa.
2. **Pessoa com sessão de 8 horas.** Uma pessoa cadastrada na consulta web do legado pode se identificar com usuário e senha e receber uma sessão do usuário que vale 8 horas.
3. **Administração do acesso.** O administrador lista, inclui, altera permissões e revoga sistemas consumidores, e encerra as sessões de uma pessoa quando a conta dela muda na consulta web.
4. **Registro de uso.** Toda chamada fica registrada com quem chamou, o que consultou, o resultado e o endereço de origem. O administrador consulta esse registro por período e por filtros.

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

**Como** administrador da API do legado **quero** decidir quais sistemas e pessoas podem consultar os dados do Simplifica, e com quais permissões **, de modo que** os dados do legado só sejam lidos por quem foi autorizado e cada consulta possa ser conferida depois

## Histórias de Usuário

**Como** administrador **quero** cadastrar um sistema consumidor e entregar a ele uma chave de acesso com validade e permissões de consulta **, de modo que** o Viabiliza e a Minha Área do SAD consultem o legado sem usar a conta de uma pessoa

**Como** administrador **quero** alterar as permissões de consulta de um sistema consumidor ou revogar a chave dele **, de modo que** eu retire o acesso de um sistema sem precisar mexer nos outros

**Como** pessoa cadastrada na consulta web do legado **quero** me identificar com usuário e senha e receber uma sessão do usuário **, de modo que** eu consulte processos e TVLs do Simplifica com as permissões da minha conta

**Como** administrador **quero** encerrar as sessões de uma pessoa **, de modo que** quem teve a senha trocada, a conta bloqueada ou o perfil alterado perca o acesso na hora, sem esperar as 8 horas

**Como** administrador **quero** consultar o registro de uso da API por período, sistema, pessoa, consulta e resultado **, de modo que** eu acompanhe quem consultou o quê e investigue erros e acessos recusados

---

## Regras

| Nº | Regra |
|:---:|---|
| RN-01 | A API só lê os dados do Simplifica. Nenhuma função desta HU grava no banco do legado |
| RN-02 | Toda consulta de processos e de TVLs exige uma chave de acesso de sistema consumidor ou uma sessão do usuário. Sem nenhuma das duas, a chamada é recusada por falta de identificação |
| RN-03 | Existem três permissões: consulta de TVLs, consulta de processos e administração do acesso. Cada função exige a sua. Quem não tem a permissão tem a chamada recusada por falta de permissão |
| RN-04 | Ao incluir um sistema consumidor, o administrador informa o nome (até 120 caracteres) e ao menos uma permissão de consulta. A chave de acesso aparece uma única vez, na resposta da inclusão. Depois disso, ninguém consegue vê-la de novo |
| RN-05 | A chave de acesso vale 90 dias a partir da inclusão. Vencida, as chamadas são recusadas por chave vencida |
| RN-06 | A permissão de administração não pode ser concedida pela tela de administração. Ela só é dada pela equipe técnica, diretamente no servidor da API |
| RN-07 | Permissão que não existe é recusada |
| RN-08 | Na alteração, a lista de permissões informada substitui a anterior. Sistema consumidor revogado não aceita alteração |
| RN-09 | O sistema consumidor usado pela própria administração mantém a permissão de administração mesmo que o administrador tente retirá-la, e não pode ser revogado pela tela |
| RN-10 | A revogação não apaga o sistema consumidor. Ele continua na lista com a data da revogação, e as chamadas com a chave dele passam a ser recusadas por acesso revogado Revogar de novo mantém a data original |
| RN-11 | A lista de sistemas consumidores mostra nome, permissões, validade, data da revogação, último uso e qual deles é o da administração |
| RN-12 | A pessoa se identifica com usuário e senha da consulta web do legado. A API confere a senha com a consulta web e não guarda a senha |
| RN-13 | Senha conferida, a API abre uma sessão do usuário válida por 8 horas, com as permissões de consulta que a conta da pessoa tem na consulta web. A sessão nunca recebe a permissão de administração |
| RN-14 | Recusas da identificação: usuário ou senha errados ("Credenciais inválidas."); conta registrada e ainda não liberada ("Sua conta foi registrada e aguarda liberação do administrador."); autenticação institucional fora do ar ("Autenticação institucional indisponível no momento. Tente novamente em instantes."); consulta web fora do ar ("Não foi possível confirmar a credencial.") |
| RN-15 | A cada uso da sessão, a API confere com a consulta web se a conta continua ativa e aprovada. A resposta vale por 1 minuto. Conta inativa, não aprovada ou consulta web fora do ar: a chamada é recusada por acesso revogado |
| RN-16 | O administrador pode encerrar de uma vez todas as sessões abertas de uma ou mais pessoas. A consulta web faz isso sozinha quando a senha é trocada ou redefinida, quando a conta é alterada pelo administrador e quando o perfil muda. Se a API estiver fora do ar nesse momento, a consulta web avisa que as sessões seguem válidas até expirarem |
| RN-17 | Limite de uso: 60 chamadas por minuto para cada sistema consumidor ou sessão. Acima disso, a chamada é recusada com "Limite de 60 requisições por minuto excedido." |
| RN-18 | Limite de identificação: 10 tentativas por minuto por endereço de origem e 10 por usuário informado. Acima disso: "Limite de tentativas de autenticação excedido." |
| RN-19 | Toda chamada fica no registro de uso: data e hora, quem chamou (sistema consumidor, pessoa ou anônimo) e o nome, a consulta feita, o tipo do dado pesquisado, os 4 últimos caracteres desse dado, o resultado, a mensagem de erro quando houver e o endereço de origem |
| RN-20 | O registro de uso nunca guarda o CPF, o CNPJ, a inscrição ou o número completo pesquisado, nem a senha. Na identificação, guarda só os 4 últimos caracteres do usuário informado |
| RN-21 | A consulta do registro de uso filtra por período, sistema consumidor, pessoa, consulta, resultado e "somente chamadas com erro". Sem período, mostra as últimas 24 horas. Só com a data final, mostra as 24 horas anteriores a ela. Data inicial depois da final é recusada ("A data inicial não pode ser posterior à data final.") |
| RN-22 | O registro de uso vem do mais recente para o mais antigo, com 50 linhas por página e o total de linhas |
| RN-23 | A própria consulta ao registro de uso e as ações de administração também ficam registradas |

**Diferença para a HU.102.** A HU.102 trata do registro das integrações do Viabiliza, consultado na gestão do Viabiliza. O registro desta HU é da API do legado e é consultado pela administração da consulta web do legado.

---

## Fluxos esperados

**Fluxo A — Cadastrar um sistema consumidor**

1. O administrador abre a administração de sistemas consumidores na consulta web do legado.
2. Informa o nome e marca as permissões de consulta (TVLs, processos ou as duas).
3. A API gera a chave de acesso e mostra uma única vez, com a validade.
4. O administrador entrega a chave à equipe do sistema consumidor.

**Fluxo B — Pessoa consulta o legado**

1. A pessoa informa usuário e senha.
2. A API confere com a consulta web e abre a sessão do usuário por 8 horas.
3. A pessoa consulta processos e TVLs conforme as permissões da sua conta.
4. Se a conta for bloqueada, a próxima consulta é recusada.

**Fluxo C — Retirar um acesso**

1. O administrador revoga o sistema consumidor, ou encerra as sessões da pessoa.
2. As chamadas seguintes com aquela chave ou sessão são recusadas.
3. O sistema consumidor continua na lista, com a data da revogação.

**Fluxo D — Acompanhar o uso**

1. O administrador abre o registro de uso.
2. Escolhe o período e os filtros.
3. Vê cada chamada com quem chamou, o que consultou e o resultado.

---

## Pré-requisitos

- Permissão de administração do acesso, para as funções de administração
- Conta ativa e aprovada na consulta web do legado, para abrir sessão do usuário
- Consulta web do legado no ar, para conferir a senha e a situação da conta

## Dependências

**Passadas**

- HU.102 — Consultar log de integrações
- HU.146 — Consultar histórico de acessos

**Futuras**

- HU.205 — Consultar TVLs e processos pela API do legado Simplifica
- HU.201 — Renovar viabilidade do Viabiliza e do Simplifica
- HU.202 — Consultar processos do Simplifica na gestão

---

## Comportamento Esperado

**Cenário 1: Inclusão de sistema consumidor**
**Dado que** o administrador informou nome e ao menos uma permissão de consulta
**Quando** confirma a inclusão
**Então** a API mostra a chave de acesso uma única vez
**E** a validade fica 90 dias à frente

**Cenário 2: Inclusão sem permissão**
**Dado que** o administrador não marcou nenhuma permissão de consulta
**Quando** confirma a inclusão
**Então** a inclusão é recusada com o pedido de informar ao menos uma permissão de consulta

**Cenário 3: Tentativa de conceder administração pela tela**
**Dado que** o administrador marcou a permissão de administração em um sistema consumidor novo
**Quando** confirma a inclusão
**Então** a inclusão é recusada
**E** nenhuma chave é gerada

**Cenário 4: Chave vencida**
**Dado que** a chave do sistema consumidor passou dos 90 dias
**Quando** o sistema faz uma consulta
**Então** a consulta é recusada por chave vencida

**Cenário 5: Revogação**
**Dado que** o administrador revogou um sistema consumidor
**Quando** esse sistema faz uma consulta
**Então** a consulta é recusada por acesso revogado
**E** o sistema consumidor segue na lista com a data da revogação

**Cenário 6: Proteção do acesso da administração**
**Dado que** o administrador tenta revogar o sistema consumidor que ele mesmo está usando
**Quando** confirma
**Então** a revogação é recusada
**E** retirar a permissão de administração desse sistema não tem efeito

**Cenário 7: Permissão insuficiente**
**Dado que** o sistema consumidor só tem a permissão de consulta de TVLs
**Quando** consulta processos
**Então** a consulta é recusada por falta de permissão

**Cenário 8: Identificação da pessoa**
**Dado que** a pessoa tem conta ativa e aprovada na consulta web
**Quando** informa usuário e senha corretos
**Então** recebe uma sessão do usuário que vale 8 horas
**E** as permissões da sessão são as da conta dela, sem a de administração

**Cenário 9: Conta aguardando liberação**
**Dado que** a conta da pessoa foi registrada e não foi liberada
**Quando** ela informa usuário e senha
**Então** recebe "Sua conta foi registrada e aguarda liberação do administrador."

**Cenário 10: Conta bloqueada durante a sessão**
**Dado que** a pessoa tem sessão aberta
**E** a conta dela foi desativada na consulta web
**Quando** ela faz a próxima consulta, passado até 1 minuto
**Então** a consulta é recusada por acesso revogado

**Cenário 11: Encerramento de sessões**
**Dado que** o administrador trocou a senha ou o perfil de uma pessoa na consulta web
**Quando** a troca é salva
**Então** todas as sessões abertas dessa pessoa na API são encerradas

**Cenário 12: Limite de chamadas**
**Dado que** um sistema consumidor fez 60 chamadas no mesmo minuto
**Quando** faz a 61ª
**Então** recebe "Limite de 60 requisições por minuto excedido."

**Cenário 13: Registro de uso padrão**
**Dado que** o administrador não informou período
**Quando** abre o registro de uso
**Então** vê as chamadas das últimas 24 horas, da mais recente para a mais antiga, 50 por página

**Cenário 14: Dado pesquisado protegido**
**Dado que** um sistema consultou processos por CNPJ
**Quando** o administrador abre o registro de uso
**Então** a linha mostra o tipo "CNPJ" e só os 4 últimos dígitos

---

## Cenários de teste para homologação

| # | Situação | Como testar | Resultado esperado |
|:---:|---|---|---|
| H1 | Incluir sistema consumidor | Incluir com nome e permissão de processos | Chave mostrada uma vez; validade de 90 dias |
| H2 | Consultar sem chave | Chamar a consulta de processos sem chave | Recusada por falta de identificação |
| H3 | Permissão errada | Sistema só com TVLs consulta processos | Recusada por falta de permissão |
| H4 | Alterar permissões | Trocar a permissão de TVLs por processos | A nova lista substitui a antiga |
| H5 | Revogar | Revogar e repetir a consulta | Recusada por acesso revogado; sistema segue na lista |
| H6 | Proteger a administração | Revogar o próprio sistema da administração | Recusado |
| H7 | Sessão da pessoa | Identificar-se com conta ativa | Sessão de 8 horas com as permissões da conta |
| H8 | Senha errada | Informar senha errada | "Credenciais inválidas." |
| H9 | Bloqueio | Desativar a conta e consultar de novo após 1 minuto | Recusada por acesso revogado |
| H10 | Troca de senha | Redefinir a senha na consulta web | Sessões abertas da pessoa encerradas |
| H11 | Registro de uso | Filtrar "somente chamadas com erro" no dia | Só chamadas recusadas, com a mensagem |
| H12 | Período inválido | Data inicial depois da final | "A data inicial não pode ser posterior à data final." |

---

## Pontos para validação da SEDUR

1. **Renovação da chave.** A chave vale 90 dias e a API não tem função para prorrogá-la. Ao vencer, o administrador inclui um novo sistema consumidor e troca a chave no sistema que consulta. A SEDUR aceita esse procedimento ou quer prorrogação? (Aceita / Quer prorrogação)
2. **Aviso de vencimento.** A API não avisa que a chave está perto de vencer. A SEDUR quer um aviso? Com quantos dias de antecedência e para quem?
3. **Guarda do registro de uso.** O registro de uso não é apagado automaticamente. Por quanto tempo a SEDUR quer manter esse registro?
4. **Responsável pela administração.** Quem na SEDUR ou na Sudoeste terá a permissão de administração do acesso?

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
