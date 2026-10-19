# HU-138 — Manter setores

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Manter os setores da SEDUR e o vínculo dos analistas que cobrem cada um.

## História de Usuário
**Como** administrador com `manter-setores`,
**quero** cadastrar setores, inativá-los e definir quais analistas pertencem a cada um,
**para** a caixa de análise distribuir processos por setor.

## Atores e permissões
- A tela exige `manter-setores`. Menu Administração, item Setores (`/gestao/setores`).
- A lista de candidatos ao vínculo traz usuários ativos cujo papel tem a permissão `analisar-processos`.

## Fluxo evidenciado
1. A lista busca pelo nome, filtra situação, ordena por nome e pagina em 10, 15, 25 ou 50 (padrão 15). Cada setor mostra a contagem de analistas, a contagem de processos e os analistas vinculados.
2. Incluir e editar gravam nome e situação. Setor novo nasce ativo quando a situação não é enviada.
3. A ativação inverte `active`. Não há exclusão. Se o setor ativo tem processos em `em_analise` ou `em_pendencia`, a inativação ocorre mesmo assim e a resposta traz aviso com a quantidade, pedindo redistribuição pela distribuição. O histórico e o vínculo permanecem.
4. Sincronizar analistas grava o conjunto exato de `analyst_ids`. Tirar um analista deste setor não o remove de outro setor. A auditoria registra os identificadores antes e depois.

## Regras de negócio evidenciadas
- O nome do setor é único.
- Setor não é excluído. Inativo deixa de receber distribuição nova e continua consultável.
- Um analista pode estar em mais de um setor: o sync de um setor não mexe nos vínculos dos demais.
- A redistribuição dos processos em aberto não acontece nesta tela.

## Validações
- `name` obrigatório, até 255 caracteres, único em `sectors.name`. Na edição, ignora o próprio setor. O nome é trimado.
- `active` booleano obrigatório.
- `analyst_ids` é um array. Cada id precisa existir em `users.id`. A validação da rota não reconfere a permissão `analisar-processos`; a lista oferecida pela tela é que filtra esses usuários ativos.

## Critérios de aceite
### CA-01 — Cadastro
**Dado** um administrador com `manter-setores` e um nome ainda não usado,
**Quando** cadastra o setor,
**Então** o setor entra ativo, salvo se `active` vier falso.

### CA-02 — Nome repetido
**Dado** um setor já cadastrado,
**Quando** outro cadastro repete o nome,
**Então** a validação recusa o nome.

### CA-03 — Inativar com processo aberto
**Dado** um setor ativo com processo em análise ou em pendência,
**Quando** o administrador inativa,
**Então** o setor fica inativo, os processos continuam nele e a resposta avisa a quantidade em aberto.

### CA-04 — Vários setores
**Dado** um analista já vinculado ao setor A,
**Quando** o administrador sincroniza o setor B incluindo esse analista,
**Então** o analista permanece no setor A e passa a constar também no setor B.

## Divergências da redação anterior
A redação anterior dizia que setor com processo pendente não pode ser excluído e que a inativação exige destino definido para os processos em aberto. O código não exclui setor. A inativação com processo em análise ou pendência é permitida e só avisa; o destino é a redistribuição em outra tela.

## Evidências
- `routes/gestao.php`: `setores.index`, `store`, `update`, `ativacao.update`, `analistas.update`.
- `app/Http/Controllers/Gestao/SectorController.php`.
- `app/Http/Requests/Gestao/SectorRequest.php`.
- `resources/js/navigation/gestao-nav.ts`: Setores em Administração.

## Não evidenciado
- Exclusão de setor.
- Redistribuição automática dos processos ao inativar.
- Bloqueio da inativação enquanto houver processo aberto.
