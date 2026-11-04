# HU.184 – PARAMETRIZAR A NOMENCLATURA DOS SERVIÇOS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 04/11/2026 | Criação da HU da melhoria entregue entre 16/10 e 04/11/2026 | 1.0 | Sudoeste Informática |

---

## Visão Geral

Os pedidos que chegam da Junta Comercial (JUCEB) trazem o nome de cada serviço do jeito que a Junta escreve. Um mesmo pedido pode trazer vários serviços. Até aqui, o Viabiliza mostrava esses nomes sem tratamento nas listas, nas caixas, na ficha e no filtro de serviço.

Esta HU cria a tela **Nomenclatura dos serviços**, em que a SEDUR define, para cada nome recebido da Junta, o nome exibido no Viabiliza e se o serviço aparece quando vem junto com outros.

1. **De x Para.** Cada linha liga o nome recebido (De) ao nome exibido (Para).
2. **Condição.** O mesmo serviço da Junta pode ter nomes diferentes conforme o que mudou em relação ao último processo da empresa: atividades incluídas, excluídas ou mantidas, ou endereço alterado.
3. **Exibir com outros serviços.** Num processo com mais de um serviço, aparecem só os marcados para exibir.
4. **Serviço novo entra sozinho.** Quando a Junta envia um nome que ainda não está na tela, o sistema cria a linha com o mesmo nome no De e no Para.

---

## Sumário

- [Épico: EP02 — Administração](#épico-ep02--administração)
- [História de Usuário](#história-de-usuário-parametrizar-a-nomenclatura-dos-serviços)
- [Regras](#regras)
- [Interface de Usuário](#interface-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Comportamento Esperado](#comportamento-esperado)
- [Cenários de teste para homologação](#cenários-de-teste-para-homologação)
- [Pontos para validação da SEDUR](#pontos-para-validação-da-sedur)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** SEDUR **quero** definir como os serviços enviados pela Junta Comercial aparecem no Viabiliza **, de modo que** analistas e gestores vejam nomes padronizados e só os serviços que importam para a análise

## História de Usuário: Parametrizar a nomenclatura dos serviços

**Como** administrador com a atribuição de parametrizar a nomenclatura dos serviços **quero** consultar, incluir e alterar o nome exibido de cada serviço recebido da Junta **, de modo que** as listas, as caixas, a ficha e o filtro de serviço usem o nome adotado pela SEDUR

**Como** administrador **quero** marcar quais serviços aparecem quando o processo traz mais de um **, de modo que** o processo mostre só os serviços relevantes

**Como** administrador **quero** dar nomes diferentes ao mesmo serviço conforme o que mudou em relação ao último processo da empresa **, de modo que** o nome exibido diga se houve inclusão ou exclusão de atividade, ou mudança de endereço

---

## Regras

| Nº | Regra |
|:---:|---|
| RN-01 | A tela fica em Administração, no item Nomenclatura dos serviços, e exige a atribuição "Parametrizar nomenclatura dos serviços". A atribuição foi dada aos perfis que já cadastravam os tipos de serviço |
| RN-02 | Cada linha tem: nome recebido (De), condição, nome exibido (Para) e se o serviço aparece quando vem junto com outros (Exibir ou Oculto) |
| RN-03 | O nome recebido (De) e o nome exibido (Para) são obrigatórios e têm até 255 caracteres. Espaços no início e no fim são retirados |
| RN-04 | Não pode haver duas linhas com o mesmo nome recebido e a mesma condição. A comparação ignora maiúsculas, acentos e espaços repetidos. Mensagem: "Já existe uma linha para este serviço." |
| RN-05 | A condição é opcional. As opções são: (a) Caso sistema identifique que está sendo incluído algum CNAE; (b) Caso sistema identifique que está sendo incluído e também excluindo algum CNAE; (c) Caso o sistema identifique que o pedido não altera nenhum CNAE; (d) Caso o sistema identifique que está sendo alterado o: codlog / logradouro / nº de porta ou métrico / Bairro; (e) Caso o sistema identifique que está sendo alterado o Complemento / Sub-unidade / nº Sub-unidade / CEP |
| RN-06 | Na inclusão, se a opção de exibir não for informada, a linha nasce marcada para exibir |
| RN-07 | Na alteração, só mudam o nome exibido (Para) e a opção de exibir. O nome recebido e a condição identificam a linha e não mudam |
| RN-08 | O botão Exibir ou Ocultar inverte a opção da linha, sem abrir o formulário. Mensagens: "Serviço passa a ser exibido." ou "Serviço passa a ficar oculto." |
| RN-09 | Não há exclusão de linha |
| RN-10 | Quando a Junta envia um nome de serviço que ainda não existe na tela, o sistema cria a linha sozinho, com o mesmo nome no De e no Para, sem condição e marcada como Oculto |
| RN-11 | Nome exibido do processo: para cada serviço recebido, vale o Para da linha correspondente. Sem linha, vale o nome recebido. Nomes iguais aparecem uma vez só |
| RN-12 | Processo com mais de um serviço mostra só os serviços marcados para exibir. Se nenhum estiver marcado, mostra todos |
| RN-13 | Processo sem serviço recebido da Junta mostra o tipo de serviço interno do Viabiliza |
| RN-14 | Escolha da linha condicional: o sistema compara o processo com o último processo da mesma empresa no Viabiliza. Se o serviço tem linhas de condição de atividade, compara os CNAEs: algum CNAE retirado leva à condição (b); senão, algum CNAE novo leva à (a); sem mudança, à (c). Se o serviço tem só linhas de condição de endereço, compara o endereço: mudança de codlog, logradouro, número ou bairro leva à (d); mudança de complemento ou CEP leva à (e) |
| RN-15 | Sem processo anterior da empresa no Viabiliza, as condições de atividade usam a viabilidade anterior da empresa em outra fonte (sistema anterior ou SEFAZ). Sem essa viabilidade, ou com a fonte fora do ar, vale a linha sem condição. As condições de endereço precisam de processo anterior no Viabiliza |
| RN-16 | O nome exibido vale nas listas de processos, nas caixas, na ficha de análise, na consulta de vistoria, nos resultados do fluxo expresso e no filtro Serviço |
| RN-17 | O filtro Serviço oferece um item por nome exibido, sem repetir nomes que só diferem por maiúscula ou acento, seguido dos tipos de serviço internos. Por pedido da SEDUR de 03/11, alguns tipos internos não aparecem no filtro, e "Viabilidade locacional" é pesquisado junto com "Viabilidade de Localização" |
| RN-18 | Inclusão, alteração e troca de exibição ficam na trilha de auditoria, com o usuário e a data |

**Diferença para a HU.020.** A HU.020 mantém os tipos de serviço internos do Viabiliza. Esta HU trata o nome dos serviços enviados pela Junta. Os dois cadastros convivem: o tipo interno só aparece no processo quando não há serviço recebido da Junta.

---

## Interface de Usuário

**Lista: Nomenclatura dos serviços**

| Elemento | Descrição |
|---|---|
| Texto de apoio | "Nome exibido no Viabiliza para cada serviço enviado pela JUCEB. Num processo com mais de um serviço, aparecem só os marcados como Exibir; se nenhum estiver marcado, aparecem todos. Serviço novo entra aqui sozinho, com o mesmo nome." |
| Busca | Pelo nome recebido ou pelo nome exibido |
| Filtro Exibição | Exibir ou Oculto |
| Coluna Nome recebido (De) | Nome enviado pela Junta. Ordenável |
| Coluna Nome exibido (Para) | Nome mostrado no Viabiliza, com a condição abaixo, quando houver. Ordenável |
| Coluna Com outros serviços | Exibe ou Oculto |
| Ações | Editar; Exibir ou Ocultar |
| Itens por página | 10, 15, 25 ou 50, no padrão de 15 |
| Botão de inclusão | Abre o formulário de nova linha |

**Formulário de inclusão**

| Campo | Obrigatório | Observação |
|---|:---:|---|
| Nome recebido (De) | Sim | Sugere os nomes já recebidos da Junta |
| Condição | Não | "Sem condição" ou uma das cinco condições da RN-05 |
| Nome exibido (Para) | Sim | |
| Quando vier junto com outros serviços | Não | Exibir ou Oculto |

**Formulário de edição:** Nome exibido (Para), obrigatório, e Quando vier junto com outros serviços. Botão "Salvar alterações".

---

## Pré-requisitos

- Atribuição "Parametrizar nomenclatura dos serviços"
- Serviços recebidos da Junta Comercial, para que a lista sugira os nomes e crie as linhas novas

## Dependências

**Passadas**

- HU.020 — Manter tipos de serviço
- HU.073 — Consultar processo
- HU.106 — Integrar com Junta Comercial
- HU.098 — Consultar trilha de auditoria

**Futuras**

- Nenhuma

---

## Comportamento Esperado

**Cenário 1: Incluir linha**
**Dado que** o administrador tem a atribuição
**Quando** informa o nome recebido, o nome exibido e salva
**Então** a linha é gravada
**E** aparece a mensagem "Nomenclatura cadastrada com sucesso."

**Cenário 2: Linha repetida**
**Dado que** já existe linha para o nome recebido com a mesma condição
**Quando** o administrador tenta incluir o mesmo nome, ainda que com outra caixa ou sem acento
**Então** a inclusão é recusada com "Já existe uma linha para este serviço."

**Cenário 3: Alterar nome exibido**
**Dado que** a linha existe
**Quando** o administrador muda o nome exibido e salva
**Então** o novo nome passa a aparecer nos processos com esse serviço
**E** aparece a mensagem "Nomenclatura atualizada com sucesso."

**Cenário 4: Ocultar serviço**
**Dado que** um processo tem dois serviços e os dois estão marcados para exibir
**Quando** o administrador oculta um deles
**Então** o processo passa a mostrar só o outro

**Cenário 5: Nenhum serviço marcado**
**Dado que** o processo tem mais de um serviço e nenhum está marcado para exibir
**Quando** o processo é consultado
**Então** aparecem todos os serviços, cada um com o nome exibido

**Cenário 6: Serviço novo da Junta**
**Dado que** a Junta envia um nome de serviço que não está na tela
**Quando** o pedido é recebido
**Então** a linha é criada com o mesmo nome no De e no Para, marcada como Oculto

**Cenário 7: Linha condicional por atividade**
**Dado que** o serviço tem linhas para inclusão, exclusão e manutenção de CNAE
**E** a empresa tem processo anterior no Viabiliza
**Quando** o novo pedido inclui um CNAE e não retira nenhum
**Então** o processo mostra o nome exibido da linha de inclusão de CNAE

**Cenário 8: Linha condicional por endereço**
**Dado que** o serviço tem linhas de condição de endereço
**Quando** o novo pedido muda só o CEP em relação ao processo anterior da empresa
**Então** o processo mostra o nome exibido da linha de complemento, subunidade e CEP

**Cenário 9: Sem base de comparação**
**Dado que** a empresa não tem processo anterior nem viabilidade em outra fonte
**Quando** o pedido é recebido
**Então** vale a linha sem condição do serviço

**Cenário 10: Acesso negado**
**Dado que** o usuário não tem a atribuição
**Quando** tenta abrir a tela
**Então** o acesso é negado

---

## Cenários de teste para homologação

| # | Situação | Como testar | Resultado esperado |
|:---:|---|---|---|
| H1 | Inclusão | Incluir linha com De e Para | Linha na lista e mensagem de sucesso |
| H2 | Duplicidade | Incluir o mesmo De, mesma condição, com outra caixa | Recusado |
| H3 | Mesmo De, outra condição | Incluir o mesmo De com condição diferente | Aceito |
| H4 | Alteração | Mudar o Para de uma linha | Processos com o serviço mostram o novo nome |
| H5 | Exibir ou ocultar | Ocultar um dos serviços de um processo com dois | Processo mostra só o serviço exibido |
| H6 | Filtro Serviço | Pesquisar processos pelo nome exibido | Lista traz os processos com aquele serviço |
| H7 | Serviço novo | Receber pedido com nome inédito | Linha criada como Oculto, De igual ao Para |
| H8 | Sem atribuição | Usuário sem a atribuição abre a tela | Acesso negado |

---

## Pontos para validação da SEDUR

1. **Texto da condição (b).** O texto diz "incluído e também excluindo algum CNAE", mas o sistema aplica essa linha sempre que algum CNAE é retirado, haja inclusão ou não. Manter a regra e ajustar o texto, ou exigir inclusão e exclusão ao mesmo tempo?
2. **Condição (e).** O texto cita complemento, subunidade, número da subunidade e CEP. O sistema compara complemento e CEP. Subunidade e número da subunidade devem entrar na comparação?
3. **Serviço novo oculto.** O serviço novo entra marcado como Oculto. Se for o único serviço do processo, ele aparece pela RN-12. Se vier com outros já marcados, fica escondido até a SEDUR revisar a linha. Está de acordo?
4. **Itens retirados do filtro.** Os tipos internos retirados do filtro Serviço e a equivalência de "Viabilidade locacional" são regra fixa, sem tela. Novos ajustes desse tipo continuam por pedido à Sudoeste?

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
