# HU-169 — Anexar documentos

## Épica
**EP08 — Solicitação de Viabilidade**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Anexar, baixar e remover arquivos do rascunho, com hash do conteúdo, disco não público e um arquivo por requisito quando o requisito é informado.

## História de Usuário
**Como** requerente dono do rascunho,  
**quero** anexar os documentos do pedido e baixar o que enviei,  
**para** cumprir os requisitos documentais antes do protocolo.

## Atores e permissões
- Incluir e remover: dono efetivo e status rascunho (`ViabilityRequestPolicy::update`).
- Baixar: dono efetivo em qualquer status (`view`). Terceiro recebe 403.
- Documento de outra solicitação na URL responde 404.
- Rotas sob autenticação do portal. Incluir e remover também passam por `portal.solicitacao`. O download não passa por esse middleware.

## Fluxo evidenciado
1. `POST portal/solicitacoes/{solicitacao}/documentos` recebe o arquivo e, opcionalmente, `requirement_id`.
2. O SHA-256 é calculado antes de mover o arquivo. O arquivo vai para o disco `storage.documentos.disk` (padrão `local`), no diretório `solicitacoes/{id}`.
3. Se o requisito foi informado, o anexo anterior do mesmo requisito é apagado do disco e da tabela. Fica um documento por requisito.
4. A linha guarda disco, caminho, nome original, MIME, tamanho, hash e usuário que enviou. Auditoria `documento-anexado`.
5. `GET .../documentos/{documento}/download` faz streaming autenticado, com o MIME gravado, e audita `documento-download`.
6. `DELETE .../documentos/{documento}` apaga o arquivo e a linha, audita `documento-removido` e só funciona em rascunho.

## Regras de negócio evidenciadas
- O disco não é público. O acesso é o streaming da rota autenticada.
- Anexo sem requisito é aceito (avulso).
- Novo arquivo do mesmo requisito substitui o anterior antes do protocolo.
- Tipos permitidos vêm de `viabiliza.solicitacao.anexos.mime_permitidos` (padrão PDF, JPEG e PNG).
- Tamanho máximo vem de `solicitacao.anexos.max_mb` (padrão 10 MB), aplicado em kilobytes na validação.
- O wizard lista os anexos já gravados com a URL de download.

## Validações
- `file` obrigatório, arquivo, MIME na lista e tamanho até o máximo.
- `requirement_id` opcional, inteiro e existente em `document_requirements`.
- Mensagens de arquivo ausente, inválido, tipo não permitido, tamanho excedido e requisito inexistente.

## Critérios de aceite
- Dado o dono de um rascunho e um PDF dentro do limite, quando anexa, então o arquivo fica no disco configurado, com SHA-256, e a auditoria registra o envio.
- Dado um segundo arquivo para o mesmo `requirement_id`, quando anexa, então o anterior é removido e permanece o novo.
- Dado o dono, quando baixa, então recebe o stream do arquivo gravado. Dado outro usuário, quando baixa, então a policy nega.
- Dado solicitação que não está em rascunho, quando tenta incluir ou remover, então a policy nega. O download do dono continua permitido.
- Dado tipo ou tamanho fora da regra, quando envia, então a validação recusa e nada é gravado.

## Divergências da redação anterior
Não há análise de conteúdo do arquivo neste fluxo, nem aprovação documental do analista. A substituição é por requisito, antes do protocolo. O download não usa URL pública.

## Evidências
- `App\Http\Controllers\Portal\SolicitacaoDocumentoController`
- `App\Http\Requests\Portal\StoreSolicitacaoDocumentoRequest`
- `App\Http\Controllers\Portal\SolicitacaoController::anexosConfig` e `wizardDraftPayload`
- `routes/portal.php` (`portal.solicitacoes.documentos.store`, `download`, `destroy`)

## Não evidenciado
- Antivírus, OCR ou conferência de autenticidade do arquivo neste controller.
- Anexo depois do protocolo pelo requerente.
- Disco público ou link permanente sem autenticação.
