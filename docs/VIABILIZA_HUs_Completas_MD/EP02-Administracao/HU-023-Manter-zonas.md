# HU-023 — Manter zonas do motor

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Manter as zonas urbanísticas que o Quadro 10 precisa citar para ser publicado.

## História de Usuário
**Como** mantenedor com `manter-louos`,
**quero** cadastrar a zona e desativá-la quando deixar de valer,
**para** um código digitado errado no Quadro 10 ser bloqueado na publicação.

## Atores e permissões
- A tela exige `manter-louos`. Menu Regras, item Zonas (`/gestao/louos/zonas`).
- Não há permissão separada de zona.

## Fluxo evidenciado
1. A lista devolve todas as zonas, ordenadas por código, sem paginação. Cada item traz código, nome, macrozona e situação.
2. Incluir grava código, nome, macrozona e situação. Zona nova nasce ativa quando a situação não vem. Macrozona em branco vira nulo.
3. Editar altera nome, macrozona e, se vier, a situação. O código enviado é ignorado. Omitir `ativo` preserva a situação atual.
4. A ativação inverte `ativo` e não exclui. Zona desativada continua nos quadros vigentes que já a citam. Publicação nova de rascunho do Quadro 10 que a cite é bloqueada. A mensagem da tela diz isso.

## Regras de negócio evidenciadas
- O código é a chave citada pelas linhas do Quadro 10. Mudar o código é desativar a zona e cadastrar outra.
- Zona inativa não apaga histórico do quadro vigente.
- A publicação do rascunho do Quadro 10 exige que toda zona referenciada exista e esteja ativa (HU-028).

## Validações
- No cadastro, `codigo` obrigatório, até 50, único em `zonas.codigo`, com trim.
- `nome` obrigatório, até 255.
- `macrozona` opcional, até 100.
- `ativo` booleano obrigatório no cadastro e opcional na edição.

## Critérios de aceite
### CA-01 — Cadastro
**Dado** um mantenedor com `manter-louos` e um código ainda não usado,
**Quando** cadastra nome e macrozona,
**Então** a zona nasce ativa se a situação não for enviada.

### CA-02 — Código imutável
**Dado** uma zona gravada,
**Quando** a edição envia outro código,
**Então** o código armazenado permanece.

### CA-03 — Desativar
**Dado** uma zona ativa citada num quadro vigente,
**Quando** o mantenedor desativa,
**Então** a zona fica inativa, o quadro vigente não é alterado e a mensagem informa que publicação nova do Quadro 10 que a cite será bloqueada.

## Divergências da redação anterior
Não havia HU. A capacidade existia no código sem rastreabilidade.

## Evidências
- `routes/gestao.php`: `louos.zonas.index`, `store`, `update`, `ativacao.update`.
- `app/Http/Controllers/Gestao/ZonaController.php`.
- `StoreZonaRequest` e `UpdateZonaRequest`.
- `LouosDraftService::publicar`: recusa zona ausente do cadastro ativo.
- `resources/js/navigation/gestao-nav.ts`: Zonas em Regras.

## Não evidenciado
- Exclusão física da zona.
- Alteração do código depois do cadastro.
- Desenho ou importação da geometria da zona nesta tela. O cadastro é código, nome e macrozona.
