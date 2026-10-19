# HU-013 — Manter perfis

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Criar e ajustar perfis do guard `web` e o conjunto exato de permissões de cada um.

## História de Usuário
**Como** administrador com a permissão `manter-perfis`,
**quero** criar, renomear, marcar permissões e excluir perfis que não sejam protegidos nem tenham usuários,
**para** segregar o que cada papel pode fazer no Viabiliza.

## Atores e permissões
- Toda a tela exige `manter-perfis`. Menu Administração, item Perfis (`/gestao/perfis`).
- Perfil com flag `protegido`, ou o perfil do cidadão, não é renomeado nem excluído.
- O papel de nome `administrador` não pode perder a permissão `acessar-gestao`.

## Fluxo evidenciado
1. A lista busca pelo nome, ordena por nome, pagina em 10, 15, 25 ou 50 (padrão 15) e mostra permissões, quantidade de usuários e se o perfil é estrutural (`RoleProtection`).
2. Com `formato` `csv`, `xlsx` ou `pdf`, exporta o conjunto filtrado pela busca.
3. O catálogo de permissões enviado à tela vem de `permissions` do guard `web`, com rótulos de `PermissionCatalog`.
4. Criar grava nome e guard `web` e sincroniza o conjunto de permissões marcado.
5. Atualizar aplica o conjunto exato marcado (`syncPermissions`). Em perfil protegido, o nome enviado é ignorado na gravação do nome.
6. Excluir recusa perfil protegido e perfil com usuários vinculados. A exclusão bem-sucedida é auditada com o nome do perfil.

## Regras de negócio evidenciadas
- O conjunto marcado na tela substitui as permissões do perfil. O seeder de permissões é aditivo; esta tela não é.
- Perfil protegido não muda de nome e não é excluído. A mensagem de exclusão é «Perfis protegidos do sistema não podem ser excluídos.»
- Perfil com usuário vinculado não é excluído. A mensagem é «Este perfil possui usuários vinculados e não pode ser excluído.»
- O papel `administrador` mantém `acessar-gestao`.
- A auditoria de criação e de atualização registra as permissões (antes e depois, na atualização).

## Validações
- Nome obrigatório, até 255 caracteres, único em `roles.name` no guard `web`. Na edição, ignora o próprio perfil.
- `permissions` é um array. Cada item precisa existir em `permissions.name` no guard `web`.
- Se o perfil é protegido e o nome enviado difere do atual, a validação acrescenta «Perfis protegidos do sistema não podem ser renomeados.»

## Critérios de aceite
### CA-01 — Criar com permissões existentes
**Dado** um usuário com `manter-perfis` e permissões cadastradas no guard `web`,
**Quando** cria um perfil com nome ainda não usado e um subconjunto dessas permissões,
**Então** o perfil passa a ter exatamente esse conjunto.

### CA-02 — Perfil protegido
**Dado** um perfil protegido ou o perfil do cidadão,
**Quando** o administrador tenta renomeá-lo ou excluí-lo,
**Então** o nome permanece e a exclusão é recusada.

### CA-03 — Administrador sem acesso à gestão
**Dado** o papel `administrador`,
**Quando** a atualização omite `acessar-gestao`,
**Então** a gravação é recusada.

### CA-04 — Exclusão com usuários
**Dado** um perfil não protegido com ao menos um usuário,
**Quando** o administrador tenta excluí-lo,
**Então** o perfil permanece.

## Divergências da redação anterior
A redação anterior não distinguia perfil protegido, guard `web` nem a trava do papel administrador. Também misturava a HU com fluxos de processo e integração que esta tela não executa.

## Evidências
- `routes/gestao.php`: `perfis.index`, `store`, `update`, `destroy` com `manter-perfis`.
- `app/Http/Controllers/Gestao/RoleController.php`.
- `StoreRoleRequest`, `UpdateRoleRequest`.
- `app/Support/RoleProtection.php`.
- `resources/js/navigation/gestao-nav.ts`: Perfis em Administração.

## Não evidenciado
- Clonagem de perfil.
- Exclusão de permissão do catálogo do sistema por esta tela. A tela só associa permissões já existentes.
