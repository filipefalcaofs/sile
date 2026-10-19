# HU-067 — Validar documentos obrigatórios

## Épica
**EP08 — Solicitação de Viabilidade**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Resolver os documentos obrigatórios do rascunho e impedir o protocolo enquanto algum deles não tiver anexo associado ao requisito.

## História de Usuário
**Como** requerente,  
**quero** ver quais documentos obrigatórios faltam,  
**para** anexá-los antes de protocolar, e que o sistema recuse o protocolo se ainda faltar algum.

## Atores e permissões
- A lista de obrigatórios e de faltantes aparece no wizard do dono em rascunho (`SolicitacaoController::edit`).
- O bloqueio ocorre em `ProtocolarSolicitacaoService`, chamado pelo dono ao protocolar e também pela contingência.
- Solicitação de origem Regin não passa por essa trava no protocolo.

## Fluxo evidenciado
1. `DocumentRequirementResolver::required` une duas fontes, sem duplicar por id:
   - requisitos ativos e marcados como obrigatórios, ligados por `cnae_document_requirement` a algum CNAE do pedido;
   - condicionais de código estável que existam e estejam ativos: `foto-fachada` sempre; `termo-concessao` quando `is_public_area` é verdadeiro.
2. Código base ausente ou inativo é ignorado. O resolver não cria requisito que não esteja cadastrado.
3. `missing` devolve os obrigatórios cujo id ainda não aparece em anexo com `requirement_id` preenchido.
4. O wizard recebe `requisitosObrigatorios` e `requisitosFaltantes`.
5. No protocolo, se `missing` não estiver vazio, `DocumentacaoIncompletaException` volta como flash de erro, com os nomes dos requisitos, e nenhum número é gerado.
6. Na contingência, a mesma exceção desfaz a transação e apaga os arquivos já gravados na tentativa.

## Regras de negócio evidenciadas
- Anexo sem `requirement_id` não cobre requisito.
- A tabela por CNAE pode não ter vínculos. Nesse caso, só entram os condicionais base que existirem e estiverem ativos.
- Foto da fachada é obrigatória somente se o requisito `foto-fachada` estiver cadastrado e ativo.
- Termo de concessão entra somente com área pública e requisito `termo-concessao` ativo.
- Origem Regin não é barrada por documento faltante neste serviço.

## Validações
- Não há FormRequest próprio desta HU. A checagem é de domínio, depois das validações de arquivo da HU-066.
- O protocolo só segue quando `missing` está vazio, salvo origem Regin.

## Critérios de aceite
- Dado um rascunho com `foto-fachada` ativo e sem anexo desse requisito, quando o dono abre o wizard, então o requisito aparece em faltantes e, ao protocolar, o sistema recusa com a lista de nomes, sem número de protocolo.
- Dado anexo com o `requirement_id` da fachada, quando protocola e os demais obrigatórios também estão cobertos, então essa trava não dispara.
- Dado área pública e `termo-concessao` ativo sem anexo, quando protocola, então o termo entra na lista que bloqueia.
- Dado requisito por CNAE inativo, não obrigatório ou sem vínculo com os CNAEs do pedido, quando resolve, então ele não entra nos obrigatórios.
- Dado origem Regin, quando protocola, então `guardRequiredDocuments` não bloqueia por documento faltante.

## Divergências da redação anterior
A validação não confere o conteúdo do arquivo. Ela confere a presença de anexo por requisito. A lista por CNAE depende do cadastro; sem vínculo, o código não inventa exigência além dos códigos base existentes. O canal Regin fica de fora dessa trava.

## Evidências
- `App\Services\Solicitacao\DocumentRequirementResolver`
- `App\Services\Solicitacao\ProtocolarSolicitacaoService::guardRequiredDocuments`
- `App\Http\Controllers\Portal\SolicitacaoController::edit`
- `App\Http\Controllers\Portal\ProtocoloController`
- `App\Http\Controllers\Gestao\ContingenciaController::store`

## Não evidenciado
- Conferência humana ou automática do conteúdo do documento nesta HU.
- Bloqueio documental da origem Regin no `ProtocolarSolicitacaoService`.
- Criação automática de requisito quando o código `foto-fachada` ou `termo-concessao` não está cadastrado.
