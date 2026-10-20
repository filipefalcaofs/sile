# HU-020 — Manter tipos de serviço

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Manter os tipos de serviço da solicitação de viabilidade.

## História de Usuário
**Como** administrador com `manter-tipos-servico`,
**quero** cadastrar, corrigir e desativar tipos de serviço,
**para** a solicitação oferecer só os tipos ativos.

## Atores e permissões
- Listar e gravar exigem `manter-tipos-servico`. Não há permissão só de consulta.
- Menu Regras, item Tipos de serviço (`/gestao/tipos-servico`).

## Fluxo evidenciado
1. A lista busca por código ou nome, filtra situação, ordena por código e pagina em 10, 15, 25 ou 50 (padrão 15).
2. Cada item traz código, nome, pista de fluxo e situação.
3. Incluir grava código, nome, pista de fluxo e situação. Tipo novo nasce ativo quando a situação não vem no envio.
4. Editar altera nome, pista de fluxo e situação. O código enviado é ignorado.
5. A ativação inverte `active`. Não há exclusão. Tipo inativo sai da seleção do requerente e o registro permanece.

## Regras de negócio evidenciadas
- O código é livre, único e obrigatório. O formato oficial dos códigos está registrado no Form Request como pendência da SEDUR.
- Código não muda depois do cadastro.
- Desativar não apaga tipo já referenciado por solicitação.

## Validações
- No cadastro, `code` obrigatório, até 50 caracteres, único em `viability_service_types.code`, com trim.
- `name` obrigatório, até 255.
- `flow_hint` opcional, até 255.
- `active` booleano obrigatório.
- Na edição, as mesmas regras de nome, pista e situação, sem o código.

## Critérios de aceite
### CA-01 — Cadastro
**Dado** um usuário com `manter-tipos-servico` e um código ainda não usado,
**Quando** cadastra nome e, se quiser, a pista de fluxo,
**Então** o tipo entra na lista e nasce ativo se a situação não for enviada.

### CA-02 — Código imutável
**Dado** um tipo já gravado,
**Quando** a edição envia outro código,
**Então** o código armazenado não muda.

### CA-03 — Desativar sem excluir
**Dado** um tipo ativo,
**Quando** o administrador aciona a ativação,
**Então** o tipo fica inativo e continua na lista quando o filtro inclui inativos.

## Divergências da redação anterior
Não havia HU. A capacidade existia no código sem rastreabilidade.

## Evidências
- `routes/gestao.php`: `tipos-servico.index`, `store`, `update`, `ativacao.update`.
- `app/Http/Controllers/Gestao/ViabilityServiceTypeController.php`.
- `StoreViabilityServiceTypeRequest` e `UpdateViabilityServiceTypeRequest`.
- `resources/js/navigation/gestao-nav.ts`: Tipos de serviço em Regras.

## Não evidenciado
- Exclusão física do tipo.
- Catálogo fechado de códigos imposto pela SEDUR. O código aceito é texto único de até 50 caracteres.
