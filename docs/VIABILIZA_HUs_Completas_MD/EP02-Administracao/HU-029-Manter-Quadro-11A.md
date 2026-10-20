# HU-029 — Manter Quadro 11A

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Consultar e versionar o Quadro 11A da LOUOS (condições complementares por classe de via), sem alterar a versão vigente no lugar.

## História de Usuário
**Como** mantenedor com `manter-louos`,
**quero** manter o rascunho do Quadro 11A e publicá-lo com outro usuário,
**para** as condições por classe de via entrarem em vigor como nova versão.

## Atores e permissões
- As mesmas do hub de quadros da HU-028. O quadro na URL é `quadro11a`.
- Consulta da aba: `consultar-louos`. Escrita: `manter-louos`.
- Menu Regras, Motor de regras. O cadastro das classes de via usadas na publicação é a HU-024.

## Fluxo evidenciado
1. A consulta vigente lista classe de via, grupo de uso, condições e base legal. A busca atinge classe de via e grupo de uso.
2. O rascunho segue o mesmo ciclo do Quadro 10: abrir ou retomar, incluir, alterar, excluir linha, importar CSV, ver diff, publicar com quatro olhos, descartar e reativar versão publicada.
3. Na publicação, o serviço recusa classe de via referenciada que não exista e esteja ativa no cadastro de vias.
4. O modelo CSV é `classe_via,grupo_uso,condicoes,base_legal`. O exemplo do controller usa classe `VL` e grupo `nR1-01`.
5. O manual de montagem do CSV fica em `/gestao/louos/manual?quadro=quadro11a`.

## Regras de negócio evidenciadas
- Domínio `louos_quadro11a`, rótulo «Quadro 11A da LOUOS (condições complementares pela via)».
- A publicação é sensível: publicador diferente do autor.
- Classe de via fora do cadastro ativo bloqueia a publicação do rascunho.
- A versão vigente consultada não recebe edição direta de linha.

## Validações
- `classe_via` obrigatória, até 50 caracteres.
- `grupo_uso` opcional, até 50.
- `condicoes` opcional, array.
- `base_legal` e `observacao` opcionais.
- CSV `csv` ou `txt`, até 5 MB.
- `quadro` aceito: `quadro11a` (ou `quadro10` no mesmo validador).

## Critérios de aceite
### CA-01 — Lista vigente
**Dado** versão vigente do Quadro 11A e usuário com `consultar-louos`,
**Quando** seleciona `quadro11a` no hub,
**Então** vê classe de via, grupo de uso, condições e base legal dessa versão.

### CA-02 — Rascunho isolado
**Dado** um mantenedor com `manter-louos`,
**Quando** altera uma linha no rascunho,
**Então** a versão vigente permanece até a publicação.

### CA-03 — Via não cadastrada
**Dado** um rascunho que cita classe de via inativa ou inexistente,
**Quando** outro usuário publica,
**Então** a publicação é recusada e a vigente não muda.

### CA-04 — Autor não publica
**Dado** o autor do rascunho,
**Quando** ele mesmo tenta publicar,
**Então** a publicação por quatro olhos é recusada.

## Divergências da redação anterior
A redação anterior repetia as regras genéricas de resultado proibitivo e condicionante na decisão. A tela grava condições por classe de via na versão do quadro. O efeito na decisão automática é do motor que lê a versão vigente. A redação não citava o cadastro de vias como trava da publicação.

## Evidências
- `LouosController::listarQuadro` no ramo `quadro11a`.
- `LouosDraftController` rótulo «Quadro 11A — Condições por classe de via».
- `LouosLinhaRules` ramo `quadro11a`.
- `LouosDraftService::publicar` e a verificação de classes de via ativas.
- `RuleDomain::LouosQuadro11a`.

## Não evidenciado
- Quadro 11 e Quadro 11B neste fluxo.
- Edição da linha na versão vigente, sem rascunho.
