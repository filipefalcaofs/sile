# HU-014 — Manter parâmetros do sistema

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Alterar o catálogo de parâmetros já semeado, com validação própria de cada chave, histórico e quatro olhos quando a governança é de decisão.

## História de Usuário
**Como** administrador com a permissão `manter-parametros`,
**quero** consultar o catálogo por grupo, propor ou gravar um valor e ver o histórico,
**para** mudar prazo, limiar ou credencial sem deploy.

## Atores e permissões
- Listar, exportar, gravar, aprovar, rejeitar e ver histórico exigem `manter-parametros`. Menu Configuração, item Parâmetros (`/gestao/parametros`).
- Em parâmetro de governança `decision`, quem propõe não aprova a própria proposta (`can_approve` é falso para o autor).

## Fluxo evidenciado
1. A tela agrupa os registros de `parameters` por `group` e, dentro do grupo, por chave. Cada item traz tipo, descrição, se é sensível, se exige teste de conexão, valor padrão, valor (nulo quando sensível), se já há valor administrativo, data de atualização, governança e proposta pendente (valor proposto, autor).
2. Com `formato` `csv`, `xlsx` ou `pdf`, exporta o catálogo. Valor sensível não sai em claro.
3. Parâmetro operacional: a gravação atualiza `value`, audita valor anterior e novo e o `saved` do model invalida o cache.
4. Parâmetro sensível com campo vazio não altera o valor. A resposta é «Valor mantido.» No histórico, anterior e novo entram como `[criptografado]`.
5. Parâmetro de governança `decision`: a gravação abre proposta. A mensagem informa que a alteração só vale após aprovação de um segundo usuário. Aprovar aplica o valor vigente. Rejeitar mantém o vigente. Sem proposta pendente, aprovar ou rejeitar devolve erro.
6. O histórico (`/gestao/parametros/{chave}/historico`) lista, da atividade mais recente, valor anterior, valor novo, responsável e data.

## Regras de negócio evidenciadas
- Não há inclusão de parâmetro novo nesta tela. O catálogo é o que já está em `parameters`.
- A validação do valor é a lista `validation_rules` do próprio registro. A chave `portal.perfil_cidadao` acrescenta a regra `PerfilSemAcessoAGestao`.
- Governança `operational` grava na hora. Governança `decision` exige segundo usuário (`ParameterChangeService`).
- Valor sensível não é enviado à tela. A interface recebe `null`.
- A flag `requires_connection_test` é exibida no item. O teste de conexão em si não é executado por este controller.

## Validações
- O corpo aceita `value` nulo ou string.
- Se o parâmetro é sensível e o valor vem vazio, a validação dinâmica é pulada e o valor atual permanece.
- Nos demais casos, o valor precisa satisfazer `validation_rules` da chave. As mensagens usam a descrição do parâmetro como nome do campo.

## Critérios de aceite
### CA-01 — Operacional com efeito sem deploy
**Dado** um parâmetro de governança operacional e um valor que passa nas regras da chave,
**Quando** o administrador grava,
**Então** o valor vigente muda, a auditoria registra anterior e novo, e o cache do parâmetro é invalidado na gravação.

### CA-02 — Sensível em branco
**Dado** um parâmetro sensível que já tem valor,
**Quando** o administrador envia o campo vazio,
**Então** o valor armazenado não muda.

### CA-03 — Quatro olhos
**Dado** um parâmetro de governança `decision`,
**Quando** um usuário grava um valor novo,
**Então** surge proposta pendente e o valor vigente só muda depois que outro usuário aprova.

### CA-04 — Histórico
**Dado** alterações já auditadas no log `parametros` daquele registro,
**Quando** o administrador abre o histórico da chave,
**Então** vê valor anterior, valor novo, responsável e data, com sensível mascarado como `[criptografado]`.

## Divergências da redação anterior
A redação anterior exigia que texto, e-mail, termo, feriado, credencial, taxa, prazo de indeferimento automático e a regra de dias úteis versus corridos fossem todos esta mesma tela, e que todo toggle de integração, expresso, IA e WhatsApp vivesse aqui. O código separa isso: feriados (HU-137), valores TLL (HU-156), termos (HU-157), textos decisórios (HU-158), minutas (HU-159), APIs (HU-160), servidores de e-mail (HU-161) e provedores de IA (HU-163). Esta HU cobre o catálogo `parameters`. Não foi evidenciado, neste controller, um campo próprio de «prazo de 48 horas sem BAP» nem um interruptor genérico de WhatsApp.

## Evidências
- `routes/gestao.php`: `parametros.index`, `parametros.historico`, `parametros.update`, `parametros.aprovar`, `parametros.rejeitar`.
- `app/Http/Controllers/Gestao/ParameterController.php`.
- `app/Http/Requests/Gestao/UpdateParameterRequest.php`.
- `app/Models/Parameter.php` (`isDecision`, tipos `integer`, `decimal`, `boolean`, `json`).
- `app/Enums/ParameterGovernance.php`: `operational` e `decision`.
- `resources/js/navigation/gestao-nav.ts`: Parâmetros em Configuração.

## Não evidenciado
- Criar ou apagar uma chave de parâmetro pela interface.
- Teste de conexão disparado por esta tela. A flag `requires_connection_test` só indica que a chave pede teste; a ação está nas telas de integração.
- Parametrização, nesta tela, da contagem de prazo em dias úteis ou corridos. O calendário que o cálculo de dias úteis desconta é a HU-137.
