# CRUD dos Quadros da LOUOS sobre rascunho versionado — design

**Data:** 2026-08-18 · **Revisão:** 2 (importação em lote por upload de CSV incorporada)
**Origem:** Pedido direto do usuário — "CRUD que permita inserir, alterar e excluir Quadro LOUOS" + "importar o quadro, para não precisar colocar linha por linha".
**Status:** APROVADO (decisões tomadas com o usuário via brainstorming: rascunho único compartilhado por Quadro; escopo = 4 Quadros; abordagem A — rascunho materializado; importação = upload CSV para dentro do rascunho).

## 1. Problema

A tela `/gestao/louos` hoje só **consulta** a versão vigente dos 4 Quadros e **publica** uma nova versão por quatro olhos com alterações digitadas manualmente num modal (`PublishQuadroVersionModal`). Não há como:

- inserir uma linha nova com UX de formulário;
- alterar uma linha existente a partir dela mesma (hoje é preciso redigitar a chave natural inteira);
- **excluir** uma linha (o `LouosMaintenanceService` só faz upsert — exclusão não existe);
- **importar em lote pela interface** — os `LouosQuadro*ImportService` existem, mas só rodam via seeders/CLI lendo CSV de `database/data/louos/`; o mantenedor não consegue subir uma planilha pela tela.

## 2. Restrição de domínio (por que não é um CRUD in-place)

Os Quadros são dado-regra versionado (HU-046, RN-002): toda decisão do motor registra a versão do Quadro usada; a anterior é preservada como histórico; a publicação exige quatro olhos (autor ≠ publicador, `RuleDomain::isSensitive()`). Edição destrutiva da vigente está proibida — o CRUD opera sobre um **rascunho** e a vigente só muda na publicação.

## 3. Decisões tomadas

| Decisão | Escolha | Alternativa rejeitada |
|---|---|---|
| Modelo do CRUD | Rascunho + publicação por quatro olhos | Publicação imediata por operação (histórico inflado); CRUD direto na vigente (quebra HU-046) |
| Ciclo do rascunho | **Único e compartilhado por Quadro** — qualquer mantenedor retoma; publicador ≠ autor | Rascunho por usuário (conflitos de publicação) |
| Escopo | **4 Quadros** (7, 10, 11, 11A) com formulário específico por Quadro | Apenas Quadro 7 |
| Implementação | **A — rascunho materializado**: ao abrir, copia as linhas da vigente para o rascunho (mesma mecânica de `LouosMaintenanceService::copyRows`); CRUD = Eloquent nas linhas tipadas do rascunho | B — rascunho como delta (merge complexo, reescrita do service) |
| Importação em lote | **Upload de CSV na tela do rascunho**, reusando os `LouosQuadro*ImportService` existentes (upsert por chave natural + relatório de rejeições) | .xlsx (camada extra de conversão — fica como evolução futura); apenas Artisan (sem autonomia do admin) |

## 4. Arquitetura

### 4.1 Backend

**`LouosDraftService`** (`app/Services/Louos/`) — novo service coeso com `LouosMaintenanceService`:

- `abrirOuRetomar(RuleDomain $domain, string $version, int $userId): RuleVersion` — retoma o rascunho aberto do domínio (status `rascunho`, mais recente) ou abre um novo via `RuleVersionService::openDraft` e **materializa**: copia as linhas da vigente para o rascunho (reusa a lógica de cópia por Quadro — extrair de `LouosMaintenanceService` para reuso sem duplicar).
- `inserirLinha(RuleVersion $draft, array $dados): Model` — cria linha na tabela tipada do rascunho; rejeita chave natural duplicada **dentro do rascunho**.
- `alterarLinha(Model $linha, array $dados): Model` — atualiza linha do rascunho; rejeita colisão de chave natural com outra linha do rascunho.
- `excluirLinha(Model $linha): void` — remove a linha do rascunho (exclusão real, mas só no rascunho — a vigente permanece intacta).
- `publicar(RuleVersion $draft, int $publisherId): RuleVersion` — delega a `RuleVersionService::publish` (quatro olhos já enforced em domínio sensível).
- `descartar(RuleVersion $draft): void` — apaga as linhas tipadas do rascunho e o cabeçalho; auditado.
- `importarCsv(RuleVersion $draft, string $csvPath): array` — delega ao `LouosQuadro{7,10,11}ImportService` correspondente ao domínio, que já valida cabeçalho/linhas, faz upsert por chave natural **dentro da versão** (aqui, o rascunho) e retorna o relatório `{lidos, importados, atualizados, rejeitados}`. Nenhuma lógica de parsing nova — reuso dos services já testados.

Guardas: toda operação exige `$draft->status === Rascunho` e `domain` coerente; linha só é alterável/excluível se pertencer ao rascunho (`rule_version_id`). Auditoria (`AuditService`, log `louos`) em cada mutação, na importação (com o resumo do relatório) e no descarte; a publicação já é auditada pelo `RuleVersionService` (RN-002).

**Controller** — estender `LouosController` (já é o controller dos Quadros; manter coeso) com as ações de rascunho, ou `LouosDraftController` dedicado se o arquivo crescer demais — decidir no plano pelo tamanho. Rotas em `routes/gestao.php`, grupo `permission:manter-louos` existente:

| Método/rota | Ação |
|---|---|
| `POST louos/rascunho` | abrir/retomar rascunho (body: `quadro`, `version` — exigida só na abertura) |
| `GET louos/rascunho` | estado do rascunho do quadro selecionado (para a tela) |
| `POST louos/rascunho/linhas` | inserir linha (body: `quadro` + campos) |
| `PUT louos/rascunho/linhas/{id}` | alterar linha (body: `quadro` + campos) |
| `DELETE louos/rascunho/linhas/{id}` | excluir linha (body/query: `quadro`) |
| `PUT louos/rascunho/publicar` | publicar (quatro olhos: usuário logado ≠ autor do rascunho) |
| `DELETE louos/rascunho` | descartar rascunho (body: `quadro`) |
| `POST louos/rascunho/importar` | upload de CSV para o rascunho (multipart: `quadro` + `arquivo`); retorna o relatório de importação |
| `GET louos/modelo-csv` | download do modelo CSV com o cabeçalho exato do Quadro (query: `quadro`) |

O upload é validado (CSV, tamanho máximo razoável, cabeçalho exigido pelo service) e processado **sincronamente** — os Quadros têm volume pequeno (centenas a poucos milhares de linhas); se um import real mostrar lentidão, a fila entra como evolução, não agora (YAGNI).

**Form Requests** — `StoreLouosDraftLinhaRequest` / `UpdateLouosDraftLinhaRequest` com regras por Quadro espelhando `PublishLouosVersionRequest::alteracaoRules` (extrair para trait/local compartilhado para não duplicar); `OpenLouosDraftRequest` (`quadro` + `version` única no domínio).

### 4.2 Frontend (`resources/js/pages/gestao/louos/index.tsx`)

- Sem rascunho: tela atual de consulta + botão **"Editar Quadro"** (permission `manter-louos`) que abre modal pedindo o identificador da nova versão (ex.: `quadro7-rev3`) e cria/retoma o rascunho.
- Com rascunho ativo: banner "Rascunho `X` em edição — autor: Y. A versão vigente não é alterada até a publicação."; a tabela passa a listar as **linhas do rascunho**; ações por linha: **editar** (modal pré-preenchido) e **excluir** (confirmação); botão **"Nova linha"**; ações do rascunho: **Importar CSV** (upload + relatório de lidos/importados/atualizados/rejeitados com motivo linha a linha), **Baixar modelo CSV**, **Publicar** (bloqueado para o autor — quatro olhos comunicado na tela e enforced no backend) e **Descartar** (confirmação).
- Formulário de linha reusa os metadados `ALTERACAO_FIELDS` já existentes na página (campos por Quadro), extraindo o modal de linha para componente próprio se a página crescer demais.
- A publicação pelo modal antigo (`PublishQuadroVersionModal`) **permanece** — fluxo de alterações em lote continua válido.

### 4.3 Erros e degradação controlada

- Chave natural duplicada no rascunho → 422 com mensagem clara.
- Publicação pelo autor → `FourEyesViolationException` já existente → flash.error (padrão do controller atual).
- Operação sem rascunho aberto / sobre linha da vigente → 409/422 comunicado, nunca silencioso.
- Quadro sem versão vigente: rascunho abre vazio (materialização copia zero linhas) — CRUD e importação funcionam normalmente.
- CSV com cabeçalho inesperado → erro comunicado com o cabeçalho esperado; linhas inválidas não bloqueiam o arquivo — entram no relatório de rejeitados (comportamento já existente dos services).

## 5. Testes (TDD — feature tests PHPUnit)

1. Abrir rascunho materializa as linhas da vigente; retomar é idempotente (não duplica).
2. Inserir/alterar/excluir linha em cada um dos 4 Quadros persiste **só no rascunho** — a vigente e a consulta pública seguem intactas até publicar.
3. Chave natural duplicada no rascunho é rejeitada (422).
4. Publicar exige quatro olhos (autor ≠ publicador) e promove o rascunho a vigente, fechando a anterior; linha excluída some da nova versão.
5. Descartar remove rascunho + linhas e não toca a vigente.
6. Permissão: `consultar-louos` não acessa rotas de rascunho; `manter-louos` acessa.
7. Auditoria registrada em inserir/alterar/excluir/descartar/publicar/importar.
8. Importação CSV no rascunho: upsert por chave natural, relatório com rejeitados, vigente intacta; cabeçalho errado é rejeitado com mensagem; download do modelo CSV por Quadro.

## 6. Não-objetivos

- Não altera o motor de enquadramento nem o sandbox (HU-143) — ambos já leem por versão.
- Não cria versionamento paralelo nem mexe no `RuleVersionService` (só reuso).
- Não altera os `LouosQuadro*ImportService` (só reuso) nem os seeders/CSVs de `database/data/louos/`.
- Não aceita .xlsx nesta entrega (evolução futura via maatwebsite/excel, se a SEDUR pedir).
