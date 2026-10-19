# HU-155 — Manter requisitos documentais

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Manter o requisito documental e os CNAEs ativos aos quais ele se vincula.

## História de Usuário
**Como** administrador com `manter-requisitos-documentais`,
**quero** cadastrar o requisito, marcar se é obrigatório e escolher os CNAEs,
**para** a validação documental cobrar o que estiver vinculado à atividade.

## Atores e permissões
- A tela exige `manter-requisitos-documentais`. Menu Regras, item Requisitos documentais (`/gestao/requisitos-documentais`).
- A busca de CNAEs do vínculo reusa o `CnaeSearchController` e só devolve CNAEs ativos.

## Fluxo evidenciado
1. A lista busca por código ou nome, filtra situação, ordena por código (ou outra coluna da whitelist) e pagina em 10, 15, 25 ou 50. O padrão vem de `ui.requisitos_documentais.per_page` (15 se ausente).
2. Cada item traz código, nome, descrição, obrigatoriedade, situação, instruções de validação e os CNAEs vinculados (id, código formatado e descrição).
3. Incluir grava código, nome, descrição, obrigatoriedade e instruções. Checkbox de obrigatoriedade ausente vira falso.
4. Editar altera nome, descrição, obrigatoriedade e instruções. Código e situação não entram nessa gravação.
5. A ativação inverte `active`. Não há exclusão.
6. Sincronizar CNAEs grava o conjunto exato de ids. Só id de CNAE ativo passa. A auditoria registra os códigos antes e depois, no log `solicitacoes`, evento `requisito-cnaes`.

## Regras de negócio evidenciadas
- A obrigatoriedade por CNAE é dado administrável, consumido pelo resolver da validação documental.
- CNAE inativo não entra no vínculo por esta rota.
- Desativar o requisito preserva o registro.

## Validações
- No cadastro, `code` obrigatório, até 50, único em `document_requirements.code`, com trim.
- `name` obrigatório, até 255.
- `description` opcional, até 1000.
- `required` booleano obrigatório.
- `validation_instructions` opcional, até 5000.
- Na edição, as mesmas regras sem o código.
- `cnae_ids` array; cada id existe em `cnaes.id` com `active` verdadeiro.

## Critérios de aceite
### CA-01 — Requisito obrigatório
**Dado** um usuário com `manter-requisitos-documentais` e um código novo,
**Quando** cadastra com `required` verdadeiro,
**Então** o requisito aparece na lista como obrigatório.

### CA-02 — Vínculo só com CNAE ativo
**Dado** um requisito e um CNAE inativo,
**Quando** a sincronização envia o id desse CNAE,
**Então** a validação recusa o id.

### CA-03 — Conjunto exato
**Dado** um requisito com CNAEs A e B,
**Quando** a sincronização envia só o CNAE ativo A,
**Então** B deixa de estar vinculado e a auditoria registra os códigos anteriores e os posteriores.

## Divergências da redação anterior
Não havia HU. A capacidade existia no código sem rastreabilidade.

## Evidências
- `routes/gestao.php`: `requisitos-documentais.index`, `cnaes-disponiveis`, `store`, `update`, `toggle`, `cnaes`.
- `app/Http/Controllers/Gestao/DocumentRequirementController.php`.
- `StoreDocumentRequirementRequest` e `UpdateDocumentRequirementRequest`.
- `resources/js/navigation/gestao-nav.ts`: Requisitos documentais em Regras.

## Não evidenciado
- Exclusão física do requisito.
- Vínculo com CNAE inativo.
