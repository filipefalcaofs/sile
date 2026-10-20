# HU-012 — Manter termos legais

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Versionar os termos legais. O termo publicado não é alterado nem apagado.

## História de Usuário
**Como** administrador com `manter-parametros`,
**quero** redigir um rascunho, publicá-lo e, quando o texto mudar, abrir a versão seguinte,
**para** o aceite do usuário continuar apontando para a versão que ele viu.

## Atores e permissões
- A tela exige `manter-parametros`. Menu Configuração, item Termos legais (`/gestao/termos-legais`).
- O aceite do termo no login do console é outro fluxo (`TermoLgpdController`, fora desta HU).

## Fluxo evidenciado
1. A lista, sem paginação, ordena por tipo e pela versão decrescente. Cada linha traz tipo, versão, título, conteúdo, data de publicação e status: `rascunho`, `vigente` ou `substituido`. Vigente é a maior versão já publicada daquele tipo cuja data de publicação já chegou.
2. Incluir cria rascunho. A versão é o máximo do tipo mais um. O usuário não informa versão. `published_at` nasce nulo.
3. Editar título e conteúdo só vale para rascunho. Termo publicado devolve «Termos publicados são imutáveis — crie uma nova versão.» O tipo não muda na edição.
4. Publicar grava `published_at` com a data atual. Publicar de novo um termo já publicado devolve a mesma mensagem de imutabilidade.
5. Excluir só remove rascunho. Termo publicado não é excluído.

## Regras de negócio evidenciadas
- A versão é calculada por tipo e não vem do formulário.
- O aceite referencia a versão publicada. Editar essa versão está bloqueado no controller.
- Publicar torna a versão a vigente para novos aceites. Aceites anteriores permanecem na versão antiga. O controller declara que o middleware volta a exigir o aceite da nova versão.
- Tipos já usados aparecem na lista `tipos` para reutilização. Tipo novo é aceito se cumprir o padrão do código.

## Validações
- No cadastro, `type` obrigatório, até 50, padrão `^[a-z0-9_]+$`.
- `title` obrigatório, até 255.
- `content` obrigatório.
- Na edição, só título e conteúdo. Tipo, versão e data de publicação enviados são ignorados.

## Critérios de aceite
### CA-01 — Próxima versão
**Dado** um tipo que já tem versão 2,
**Quando** o administrador cria outro rascunho desse tipo,
**Então** o rascunho nasce como versão 3, sem data de publicação.

### CA-02 — Publicado imutável
**Dado** um termo com `published_at` preenchido,
**Quando** o administrador tenta editar ou excluir,
**Então** o registro permanece e a mensagem pede uma nova versão.

### CA-03 — Publicar rascunho
**Dado** um rascunho,
**Quando** o administrador publica,
**Então** a data de publicação é gravada e, se for a maior versão publicada do tipo, o status da lista passa a vigente.

## Divergências da redação anterior
Não havia HU. A capacidade existia no código sem rastreabilidade.

## Evidências
- `routes/gestao.php`: `termos-legais.index`, `store`, `update`, `publicar.update`, `destroy`.
- `app/Http/Controllers/Gestao/LegalTermController.php`.
- `StoreLegalTermRequest` e `UpdateLegalTermRequest`.
- `resources/js/navigation/gestao-nav.ts`: Termos legais em Configuração.

## Não evidenciado
- Edição ou exclusão de termo já publicado.
- Versão digitada pelo usuário.
- O aceite em si. Quem aceita o termo no console usa `TermoLgpdController`, não esta tela.
