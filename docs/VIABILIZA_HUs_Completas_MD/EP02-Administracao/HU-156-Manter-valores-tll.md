# HU-156 — Manter valores TLL

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Manter a tabela de valores da TLL por exercício, com geração de rascunho do exercício seguinte e publicação por quatro olhos.

## História de Usuário
**Como** administrador com `manter-parametros`,
**quero** cadastrar o valor da TLL, gerar o exercício pelo fator do decreto e publicar com outro usuário,
**para** o cálculo usar a tabela do exercício publicado.

## Atores e permissões
- A tela exige `manter-parametros`. Menu Configuração, item Valores TLL (`/gestao/tll`).
- O autor do rascunho do exercício não vê `pode_publicar` verdadeiro para si.

## Fluxo evidenciado
1. A lista filtra por texto, situação e exercício, pagina em 10, 15, 25 ou 50 e ordena pelo exercício mais recente, código TLL e especificação.
2. Cada valor traz código TLL, especificação, exercício, valor, taxa de serviço, código SEDUR, código e nome do serviço SEFAZ, situação e quantidade de CNAEs vinculados.
3. A página também lista as versões do domínio `tll_valores` (exercício, status, autor, fonte e se o usuário atual pode publicar).
4. Incluir e editar gravam a linha. Valor novo nasce ativo quando a situação não vem. Não há exclusão: a ativação inverte `active`.
5. A consulta `tll.cnaes` devolve, em JSON paginado, CNAE e denominação ligados àquele valor.
6. Gerar exercício propaga origem para destino com fator e decreto, em rascunho. A mensagem pede revisão e publicação por um segundo usuário.
7. Publicar o exercício chama `TllPublicacaoExercicio`. Autor igual ao publicador ou outra violação de quatro olhos volta como erro e não publica.

## Regras de negócio evidenciadas
- A chave da linha é código TLL, exercício e especificação.
- Inativar tira o valor do cálculo e preserva o registro. O controller declara que o `TllCalculoService` usa o valor ativo no cálculo do DAM.
- O exercício gerado não entra em vigor no momento da propagação. Entra na publicação.
- Esta HU não evidencia a chamada à SEFAZ. Evidencia a tabela e os códigos que a tela guarda para esse envio.

## Validações
- `codigo_tll` obrigatório, até 20, único na combinação com exercício e especificação.
- `especificacao` opcional, até 255.
- `exercicio` inteiro obrigatório, de 2000 a 2200.
- `valor` numérico obrigatório, mínimo 0.
- `taxa_servico` numérica opcional, mínimo 0.
- `codigo_tll_sefaz` e `codigo_servico_sefaz` opcionais, até 30.
- `servico_sefaz` opcional, até 255.
- `active` booleano obrigatório.
- Propagação: exercício destino maior que a origem, ambos na mesma faixa; `fator` numérico maior que 0 e no máximo 2; `decreto` obrigatório, até 120.

## Critérios de aceite
### CA-01 — Chave única
**Dado** um valor já gravado para um código, exercício e especificação,
**Quando** o administrador cadastra a mesma combinação,
**Então** a validação informa que já existe valor para esse código, exercício e especificação.

### CA-02 — Inativar
**Dado** um valor ativo,
**Quando** o administrador aciona a ativação,
**Então** o valor fica inativo e o registro continua na lista.

### CA-03 — Rascunho do exercício
**Dado** um exercício de origem e um destino posterior, com fator entre 0 e 2 exclusive no zero,
**Quando** o administrador gera o exercício,
**Então** o resultado fica em rascunho e a mensagem pede um segundo usuário para publicar.

### CA-04 — Quatro olhos
**Dado** um rascunho de exercício cujo autor é o usuário autenticado,
**Quando** esse usuário tenta publicar,
**Então** a publicação é recusada.

## Divergências da redação anterior
Não havia HU. A capacidade existia no código sem rastreabilidade.

## Evidências
- `routes/gestao.php`: `tll.index`, `store`, `update`, `ativacao.update`, `cnaes`, `exercicios.store`, `exercicios.publicar`.
- `app/Http/Controllers/Gestao/TllValorController.php`.
- `TllValorRequest` e `PropagacaoTllRequest`.
- `resources/js/navigation/gestao-nav.ts`: Valores TLL em Configuração.

## Não evidenciado
- Exclusão física do valor.
- Emissão do DAM ou homologação da SEFAZ nesta tela.
- Publicação do exercício pelo mesmo usuário que gerou o rascunho.
