# HU-028 — Encerrar vínculo empresarial

## Épica
**EP03 — Cadastro Empresarial**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Encerrar o vínculo ativo do próprio usuário com a empresa, sem apagar o histórico.

## História de Usuário
**Como** cidadão com vínculo ativo,  
**quero** encerrar meu vínculo com a empresa,  
**para** deixar de alterá-la, mantendo o registro do período em que estive vinculado.

## Atores e permissões
- Portal autenticado, e-mail verificado e termo LGPD aceito.
- Policy `endLink`: vínculo ativo do usuário efetivo. O encerramento é sempre o vínculo dessa pessoa, não o de outro usuário.
- Em representação, encerra o vínculo do representado.

## Fluxo evidenciado
1. No detalhe, com `abilities.endLink`, o botão "Encerrar meu vínculo" abre confirmação. O motivo é opcional.
2. `DELETE /portal/empresas/{company}/vinculo` com `ended_reason`.
3. `CompanyLinkController` localiza o vínculo ativo do usuário efetivo, grava `ended_at` e o motivo, e não exclui a linha.
4. Auditoria de negócio `empresas` / `encerramento-vinculo` com empresa, vínculo e motivo. O modelo do vínculo também registra a atualização técnica.
5. Redireciona para a lista com "Vínculo com a empresa encerrado."
6. A empresa continua visível. Atualização, CNAEs e novo encerramento exigem vínculo ativo e deixam de valer para esse usuário.

## Regras de negócio evidenciadas
- Não há exclusão física do vínculo nem da empresa.
- Se o vínculo encerrado for o do último responsável ativo, a operação é recusada: "A empresa não pode ficar sem responsável ativo."
- A contagem olha só o papel responsável. Um procurador pode encerrar o próprio vínculo enquanto existir outro responsável ativo.
- Motivo com mais de 255 caracteres é recusado. Motivo vazio é gravado como nulo.

## Validações
`EndCompanyLinkRequest`:
- Autorização pela policy `endLink`.
- `ended_reason`: opcional, texto, máximo 255.
- Regra posterior: bloqueia o encerramento do último responsável ativo.

## Critérios de aceite
### CA-01 — Encerramento do próprio vínculo
**Dado** vínculo ativo e, se o papel for responsável, outro responsável ativo,  
**Quando** o cidadão confirma o encerramento,  
**Então** o vínculo recebe data de fim e motivo, permanece no histórico e a empresa deixa de ser editável por ele.

### CA-02 — Último responsável
**Dado** que o usuário é o único responsável ativo,  
**Quando** tenta encerrar,  
**Então** o sistema recusa com "A empresa não pode ficar sem responsável ativo."

### CA-03 — Sem vínculo ativo
**Dado** usuário sem vínculo ativo,  
**Quando** chama a rota de encerramento,  
**Então** a policy recusa.

## Divergências da redação anterior
A redação anterior tratava o encerramento como ação genérica autorizada, sem a proteção do último responsável e sem deixar explícito que o registro permanece. O código só encerra o vínculo do usuário efetivo e impede a empresa de ficar sem responsável ativo.

## Evidências
- `app/Http/Controllers/Portal/CompanyLinkController.php`
- `app/Http/Requests/Portal/EndCompanyLinkRequest.php`
- `app/Policies/CompanyPolicy.php`
- `app/Enums/CompanyLinkRole.php`
- `resources/js/pages/portal/empresas/detalhe.tsx`
- `routes/portal.php` (`portal.empresas.vinculo.destroy`)

## Não evidenciado
- Exclusão da empresa.
- Encerramento do vínculo de outra pessoa pela tela do portal.
- Reativação do vínculo encerrado.
