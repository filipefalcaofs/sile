# HU-166 — Manter vias do motor

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Manter as classes de via que o Quadro 11A precisa citar para ser publicado.

## História de Usuário
**Como** mantenedor com `manter-louos`,
**quero** cadastrar a classe de via e desativá-la quando deixar de valer,
**para** a publicação do Quadro 11A recusar classe inexistente ou inativa.

## Atores e permissões
- A tela exige `manter-louos`. Menu Regras, item Vias (`/gestao/louos/vias`).

## Fluxo evidenciado
1. A lista devolve todas as vias, ordenadas por código, sem paginação. Cada item traz código, nome e situação.
2. Incluir grava código, nome e situação. Via nova nasce ativa quando a situação não vem.
3. Editar altera o nome e, se vier, a situação. O código enviado é ignorado. Omitir `ativo` preserva a situação.
4. A ativação inverte `ativo` e não exclui. Quadros vigentes que já citam a classe permanecem. Publicação nova de rascunho do Quadro 11A que cite via inativa é bloqueada. A mensagem da tela diz isso.

## Regras de negócio evidenciadas
- O código é a chave citada pelas linhas do Quadro 11A.
- Via inativa não reescreve o quadro vigente.
- A publicação do rascunho do Quadro 11A exige classe existente e ativa (HU-018).

## Validações
- No cadastro, `codigo` obrigatório, até 50, único em `vias.codigo`, com trim.
- `nome` obrigatório, até 255.
- `ativo` booleano obrigatório no cadastro e opcional na edição.

## Critérios de aceite
### CA-01 — Cadastro
**Dado** um mantenedor com `manter-louos` e um código ainda não usado,
**Quando** cadastra o nome,
**Então** a via nasce ativa se a situação não for enviada.

### CA-02 — Código imutável
**Dado** uma via gravada,
**Quando** a edição envia outro código,
**Então** o código armazenado permanece.

### CA-03 — Desativar
**Dado** uma via ativa,
**Quando** o mantenedor desativa,
**Então** a via fica inativa e a mensagem informa que publicação nova do Quadro 11A que a cite será bloqueada, com os quadros vigentes preservados.

## Divergências da redação anterior
Não havia HU. A capacidade existia no código sem rastreabilidade.

## Evidências
- `routes/gestao.php`: `louos.vias.index`, `store`, `update`, `ativacao.update`.
- `app/Http/Controllers/Gestao/ViaController.php`.
- `StoreViaRequest` e `UpdateViaRequest`.
- `LouosDraftService::publicar`: recusa classe de via ausente do cadastro ativo.
- `resources/js/navigation/gestao-nav.ts`: Vias em Regras.

## Não evidenciado
- Exclusão física da via.
- Alteração do código depois do cadastro.
- Logradouro geográfico ou CODLOG neste cadastro. A tela guarda código e nome da classe de via do Quadro 11A.
