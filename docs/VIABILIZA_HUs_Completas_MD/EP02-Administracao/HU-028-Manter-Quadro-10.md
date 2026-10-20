# HU-028 — Manter Quadro 10

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Consultar a versão vigente do Quadro 10 da LOUOS e alterar suas permissões por zona somente por rascunho publicado com quatro olhos.

## História de Usuário
**Como** mantenedor com `manter-louos`,
**quero** abrir um rascunho do Quadro 10, editar linhas, importar CSV e publicar com outro usuário,
**para** a permissão por zona mudar sem reescrever a versão que já decidiu processos.

## Atores e permissões
- Consultar o hub na aba Quadros exige `consultar-louos` (a rota também aceita `consultar-cnaes`, e a aba reconfere `consultar-louos`).
- Abrir rascunho, gravar linha, importar CSV, publicar, descartar, ativar versão e a publicação direta por `alteracoes` exigem `manter-louos`.
- Menu Regras, item Motor de regras. O rascunho fica em `/gestao/louos/rascunho?quadro=quadro10`.

## Fluxo evidenciado
1. A aba Quadros resume Quadro 10 e Quadro 11A (versão, vigência e total de linhas) e lista a vigente do quadro selecionado, com histórico de versões que não são rascunho.
2. A busca do Quadro 10 atinge zona e grupo de uso. Cada linha traz zona, grupo de uso, subgrupo, permissão (`permitido`, `permitido_condicionado`, `proibido`), referência de condicionante e base legal.
3. Abrir rascunho copia a vigente quando ainda não há rascunho. Se já há, a abertura retoma o existente. A versão é obrigatória só na abertura nova e é única no domínio.
4. No rascunho o mantenedor inclui, altera e exclui linha, importa CSV (opção de substituir) e baixa o modelo. O diff compara o rascunho com a vigente. `canPublish` é verdadeiro quando o usuário autenticado não é o autor.
5. Publicar exige publicador diferente do autor. No Quadro 10, o serviço recusa a publicação se alguma zona referenciada não existir e estiver ativa no cadastro de zonas (HU-023). A vigente anterior permanece.
6. Ativar uma versão já publicada fecha a vigente sem apagá-la. Rascunho não é ativado.
7. Descartar o rascunho não altera a vigente.
8. Existe também publicação que recebe `quadro`, `version`, `author_id` e `alteracoes` e cria versão nova, igualmente com autor diferente do publicador.

## Regras de negócio evidenciadas
- A vigente não é editada no lugar. Toda edição de linha ocorre no rascunho.
- A permissão aceita o enum interno e, na importação, os sinais da matriz oficial (S, N, S(c) e equivalentes).
- Zona citada no rascunho e ausente do cadastro ativo bloqueia a publicação, com a lista das zonas ausentes.
- A consulta do hub é auditada no log `louos`, evento `consulta-quadros`.

## Validações
- Quadro restrito a `quadro10` ou `quadro11a`.
- Linha do Quadro 10: zona e grupo de uso obrigatórios (até 50), subgrupo opcional (até 50), permissão obrigatória no enum `Quadro10Permissao`, referência de condicionante opcional (até 50), base legal e observação opcionais.
- CSV: arquivo `csv` ou `txt`, até 5 MB; `substituir` booleano opcional. Cabeçalho do modelo: `zona,grupo_uso,subgrupo,permissao,condicionante_ref,base_legal`.
- Versão do rascunho ou da publicação: obrigatória na publicação direta, até 255, única no domínio `louos_quadro10`. `author_id` precisa existir em `users`.

## Critérios de aceite
### CA-01 — Consulta da vigente
**Dado** um usuário com `consultar-louos` e versão vigente do Quadro 10,
**Quando** abre o hub no quadro `quadro10`,
**Então** vê as permissões por zona dessa versão e o histórico publicado.

### CA-02 — Edição só no rascunho
**Dado** um mantenedor com `manter-louos`,
**Quando** inclui ou altera uma linha,
**Então** a mudança fica no rascunho e a versão vigente conserva as linhas anteriores.

### CA-03 — Quatro olhos
**Dado** um rascunho cujo autor é o usuário autenticado,
**Quando** esse usuário tenta publicar,
**Então** a publicação é recusada e a mensagem pede publicador diferente do autor.

### CA-04 — Zona inexistente
**Dado** um rascunho do Quadro 10 que cita zona ausente do cadastro ativo,
**Quando** outro usuário publica,
**Então** a publicação falha e a vigente não muda.

## Divergências da redação anterior
A redação anterior dizia que resultado proibitivo impede deferimento automático e que resultado condicionado gera condicionante na decisão. Isso é efeito do motor ao aplicar a versão vigente, não regra desta tela de manutenção. A tela grava `permissao` e `condicionante_ref`; não emite a decisão. A redação também não descrevia rascunho, CSV, quatro olhos nem a trava de zona cadastrada.

## Evidências
- `LouosController`, `LouosDraftController`, `LouosDraftService`.
- `LouosLinhaRules`, `PublishLouosVersionRequest`, `StoreLouosLinhaRequest`, `ImportLouosCsvRequest`, `OpenLouosDraftRequest`.
- `app/Enums/Quadro10Permissao.php` e `RuleDomain::LouosQuadro10`.
- `routes/gestao.php`: prefixo `louos` com `consultar-louos|consultar-cnaes` e `manter-louos`.

## Não evidenciado
- Edição destrutiva da versão vigente.
- Quadro 7 e Quadro 11 neste mesmo mapa. O mapa de quadros do rascunho contém só `quadro10` e `quadro11a`.
- A aba Regras territoriais (`TerritorialRuleController`) é outro fluxo do mesmo hub, com rascunho e quatro olhos próprios, e não faz parte desta HU.
