# HU-068 — Atendimento presencial assistido

## Épica
**EP08 — Solicitação de Viabilidade**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Abrir, no console, um atendimento em nome de um cidadão já cadastrado e criar o rascunho da solicitação com o cidadão como requerente e o atendente como autor.

## História de Usuário
**Como** atendente com permissão de atendimento presencial,  
**quero** localizar o cidadão pelo CPF e abrir a solicitação em nome dele,  
**para** registrar o pedido de quem está no balcão e já tem conta no portal.

## Atores e permissões
- Permissão `atendimento-presencial` e middleware `ResolveAssistedAttendance` em `gestao/atendimento`.
- O cidadão precisa ser um `User` com o CPF informado. O sistema não cria conta.
- O atendente não inicia atendimento para o próprio usuário.
- A empresa da solicitação precisa ter vínculo ativo do cidadão atendido.

## Fluxo evidenciado
1. A estação lista o atendimento da sessão, se houver: nome, CPF mascarado (`•••.•••.•••-` e os dois últimos dígitos), expiração, empresas do cidadão e tipos de serviço ativos.
2. `POST` com o CPF localiza o usuário, cria `AssistedAttendance` com início agora e expiração em `solicitacao.atendimento.expiracao_minutos` (padrão 30), guarda o id na sessão e audita `atendimento-iniciado`.
3. Com atendimento ativo, `POST gestao/atendimento/solicitacoes` cria `ViabilityRequest` de origem portal, tipo de serviço, empresa, `requester_user_id` do cidadão, `created_by_user_id` do atendente e `assisted_attendance_id`. Redireciona de volta à estação. Não abre o wizard.
4. Sem atendimento ativo na representação, a abertura volta com erro pedindo reabertura.
5. Encerrar tira o id da sessão, preenche `ended_at` se ainda estiver vazio, limpa a representação de atendimento e audita `atendimento-encerrado`.

## Regras de negócio evidenciadas
- Sem conta prévia, o atendimento não começa. A mensagem orienta o cidadão a criar a conta.
- O vínculo tem expiração. Fora dela, a abertura da solicitação é recusada.
- A solicitação deste fluxo é rascunho de origem portal. Não protocola, não anexa documento e não responde pendência nesta tela.
- O CPF exibido na estação é mascarado.

## Validações
- CPF obrigatório e válido na abertura do atendimento.
- Na solicitação: tipo de serviço ativo e obrigatório; empresa existente e com vínculo ativo do cidadão.
- Mensagens de cidadão inexistente, autoatendimento, atendimento expirado, tipo inválido, empresa inválida e ausência de vínculo.

## Critérios de aceite
- Dado atendente autorizado e CPF de outro usuário cadastrado, quando inicia, então o atendimento fica na sessão, com expiração, e a auditoria registra o início.
- Dado atendimento ativo, tipo ativo e empresa com vínculo do cidadão, quando abre a solicitação, então o rascunho tem o cidadão como requerente, o atendente como autor e o id do atendimento.
- Dado CPF sem cadastro, CPF do próprio atendente, atendimento expirado ou empresa sem vínculo do cidadão, quando tenta a ação correspondente, então a validação ou o retorno de erro impede o efeito.
- Dado atendimento em curso, quando encerra, então `ended_at` é preenchido e a sessão deixa de apontar o atendimento.

## Divergências da redação anterior
A redação pedia operar o portal inteiro em nome do cidadão: consultar protocolo, responder convite, anexar documento e imprimir comprovante. A estação implementada inicia o atendimento, cria o rascunho e encerra. Não há, nesta tela, continuação do wizard, consulta de protocolo, resposta de pendência nem emissão de comprovante.

## Evidências
- `App\Http\Controllers\Gestao\AssistedAttendanceController`
- `resources/js/pages/gestao/atendimento/index.tsx`
- `routes/gestao.php` (middleware `permission:atendimento-presencial`)
- Parâmetro `solicitacao.atendimento.expiracao_minutos`

## Não evidenciado
- Criação de conta do cidadão no balcão.
- Protocolo, anexo, simulação ou resposta de convite a partir da estação.
- Impressão ou envio de comprovante das ações do atendimento.
- Preenchimento do restante do wizard sem voltar ao fluxo do portal pelo próprio cidadão.
