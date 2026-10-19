# HU-061 — Criar solicitação de viabilidade

## Épica
**EP08 — Solicitação de Viabilidade**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Abrir um rascunho de solicitação de viabilidade no portal, vinculado a um tipo de serviço ativo e a uma empresa com vínculo ativo do requerente efetivo.

## História de Usuário
**Como** cidadão autenticado no portal,  
**quero** criar um rascunho de solicitação escolhendo o tipo de serviço e a empresa,  
**para** continuar o preenchimento e, depois, protocolar o pedido.

## Atores e permissões
- Requerente autenticado no portal (`auth:web`, e-mail verificado, termo LGPD aceito).
- Em representação ativa, o requerente gravado é o representado; `created_by_user_id` é o usuário da sessão.
- A listagem mostra somente solicitações em que `requester_user_id` é o usuário efetivo.
- A empresa precisa ter vínculo ativo (`ended_at` nulo) desse usuário. Não há permissão Spatie específica: o escopo é a posse da solicitação (`ViabilityRequestPolicy`).
- Criar e abrir o wizard passam pelo middleware `portal.solicitacao` (`SILE_PORTAL_SOLICITACAO` / `features.portal_solicitacao`). Desligado, a rota redireciona ao painel com aviso e, em JSON, responde 503. Listar solicitações já existentes não passa por esse middleware.

## Fluxo evidenciado
1. O requerente abre `GET portal/solicitacoes/nova`. A tela recebe os tipos de serviço ativos e as empresas com vínculo ativo.
2. O `POST portal/solicitacoes` cria `ViabilityRequest` com origem `portal`, tipo de serviço, empresa, requerente efetivo e autor da sessão. O status nasce como rascunho.
3. Antes de gravar, o sistema procura reincidência da mesma empresa. Se houver, a criação segue e a resposta leva um alerta com o processo anterior.
4. O redirecionamento volta para `GET portal/solicitacoes`, com a mensagem de rascunho criado.
5. A lista filtra por protocolo ou razão social, por status do enum, ordena por `protocol_number` ou `created_at` e pagina em 10, 15, 25 ou 50 (padrão 15). Valor de status ou ordenação fora da lista é ignorado.
6. Cada item indica se pode continuar a edição (somente rascunho) e se pode ser cancelado (estados do parâmetro `solicitacao.cancelamento.estados_cancelaveis`).

## Regras de negócio evidenciadas
- A solicitação criada por este fluxo nasce com origem portal, não Regin e não contingência.
- Só tipo de serviço ativo entra no formulário e na validação.
- Reincidência por CNPJ (mesma empresa, não cancelada, criada na janela `solicitacao.duplicidade.janela_dias` — padrão 180 dias — ou ainda protocolada) gera alerta. Não impede o novo rascunho.
- A detecção por inscrição imobiliária não está neste serviço.
- O wizard de rascunho existente (`GET portal/solicitacoes/{id}/editar`) exige a policy `update`: dono efetivo e status rascunho.

## Validações
- `service_type_id` obrigatório, inteiro, existente e ativo.
- `company_id` obrigatório, inteiro, existente e com vínculo ativo do usuário efetivo.
- Mensagens: tipo inválido ou inativo; empresa inválida; ausência de vínculo ativo.

## Critérios de aceite
- Dado um cidadão autenticado com vínculo ativo na empresa e um tipo de serviço ativo, quando ele confirma a criação, então surge um rascunho de origem portal, com ele como requerente, e a lista exibe o sucesso.
- Dado que já existe solicitação não cancelada da mesma empresa dentro da janela ou ainda protocolada, quando a nova é criada, então o rascunho é gravado e a sessão recebe o alerta do processo anterior.
- Dado empresa sem vínculo ativo, tipo inativo ou usuário que não é o requerente, quando tenta criar ou editar, então a validação ou a policy recusa a ação.
- Dado `features.portal_solicitacao` desligado, quando acessa a rota de criação, então recebe o aviso de indisponibilidade e o rascunho não é criado.

## Divergências da redação anterior
A redação genérica tratava a abertura como processo já oficial, com origem Regin ou portal e consulta a bases externas. O código desta HU só grava o rascunho no portal. A origem Regin não nasce aqui. A reincidência não bloqueia.

## Evidências
- `App\Http\Controllers\Portal\SolicitacaoController` (`create`, `store`, `index`, `edit`)
- `App\Http\Requests\Portal\StoreSolicitacaoRequest`
- `App\Policies\ViabilityRequestPolicy`
- `App\Services\Solicitacao\DuplicateRequestDetector::detect`
- `App\Http\Middleware\EnsurePortalSolicitacaoEnabled`
- `routes/portal.php` (`portal.solicitacoes.create`, `store`, `index`, `edit`)

## Não evidenciado
- Abertura de solicitação com origem Regin neste controller.
- Bloqueio da criação por duplicidade.
- Detecção de reincidência por inscrição imobiliária.
- Leitura, no `store`, do parâmetro `features.solicitacao_viabilidade`. O interruptor que a rota usa é `features.portal_solicitacao`. A prop `solicitacaoEnabled` enviada à tela está fixa em verdadeiro.
