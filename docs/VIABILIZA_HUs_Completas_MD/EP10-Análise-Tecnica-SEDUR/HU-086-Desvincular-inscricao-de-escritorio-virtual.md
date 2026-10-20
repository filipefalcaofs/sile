# HU-086 — Desvincular inscrição de escritório virtual

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 20/10/2026, com o código-fonte como referência.

## Objetivo
Liberar a inscrição imobiliária de uma sede de escritório virtual e avisar os abrigados que perderam o vínculo.

## História de Usuário
**Como** gestor com a permissão de emitir TVL,
**quero** desvincular a inscrição da sede de escritório virtual de um processo, informando o motivo,
**para que** a inscrição fique livre e os abrigados sejam avisados.

## Atores e permissões
- Exige a permissão `emitir-tvl` (`POST /gestao/processos/{id}/desvincular-inscricao`).
- Os abrigados são os requerentes cuja decisão aprovada usa a inscrição como escritório virtual.

## Fluxo evidenciado
1. Ao deferir a sede de escritório virtual (HU-079), o sistema cria o bloqueio da inscrição imobiliária.
2. O gestor informa o motivo, obrigatório e com até 2.000 caracteres, e confirma a desvinculação do processo da sede.
3. O sistema procura o bloqueio ativo da sede. Sem ele, responde «Não há inscrição de sede ativa para desvincular neste processo.»
4. O sistema desativa o bloqueio e grava a data de liberação. A inscrição volta a ficar livre, e os abrigados deixam de estar vinculados, porque o vínculo deriva do bloqueio ativo.
5. O sistema registra a comunicação devida à SEFAZ como pendência reprocessável. O envio depende da integração (HU-109) e a falha no envio não desfaz a desvinculação.
6. O sistema grava na auditoria o evento `ev-desvinculacao`, com inscrição, sede, motivo, abrigados e usuário.
7. Depois de confirmada, o sistema notifica cada abrigado com o link da solicitação dele.
8. A resposta é «Inscrição desvinculada da sede. N abrigado(s) notificado(s).»

## Regras de negócio evidenciadas
- A desvinculação não cassa automaticamente a decisão dos abrigados: eles só são avisados.
- O motivo é obrigatório e fica na auditoria e no aviso aos abrigados.
- A desvinculação vale uma vez por bloqueio: sem bloqueio ativo, nada é alterado.
- A comunicação à SEFAZ nasce como pendência e é enviada quando a integração estiver disponível.

## Validações
- Motivo: obrigatório, texto de até 2.000 caracteres. Mensagem: «Informe o motivo da desvinculação da inscrição.»

## Critérios de aceite
### CA-01 — Desvinculação com motivo
**Dado** há um processo de sede com bloqueio de inscrição ativo e um gestor com `emitir-tvl`,
**Quando** o gestor informa o motivo e confirma,
**Então** o bloqueio fica inativo, a inscrição é liberada, os abrigados são notificados e a auditoria registra o motivo.

### CA-02 — Motivo obrigatório
**Dado** o gestor não informa o motivo,
**Quando** confirma a desvinculação,
**Então** a ação é recusada com a mensagem do campo e nada é alterado.

### CA-03 — Sem inscrição ativa
**Dado** há um processo cuja sede não tem bloqueio ativo,
**Quando** o gestor tenta desvincular,
**Então** o sistema informa que não há inscrição de sede ativa e nada é alterado.

## Evidências
- `routes/gestao.php`: `processos.desvincular-inscricao`, com `permission:emitir-tvl`.
- `app/Http/Controllers/Gestao/EscritorioVirtualDesvinculacaoController.php`, `DesvincularInscricaoRequest` e `app/Services/EscritorioVirtual/DesvincularInscricaoService.php`.
- `app/Models/VirtualOfficeInscriptionLock.php` e `app/Notifications/AbrigadoDesvinculadoNotification.php`.
- `tests/Feature/EscritorioVirtual/DesvincularInscricaoEndpointTest.php` e `InscriptionLockTest.php`.

## Não evidenciado
- Botão ou tela de desvinculação na ficha do processo.
- Desvinculação automática por mudança de endereço ou por cassação.
- Envio efetivo à SEFAZ (integração bloqueada).

