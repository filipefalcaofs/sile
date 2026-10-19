# HU-064 — Informar atividade econômica

## Épica
**EP08 — Solicitação de Viabilidade**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Gravar exatamente uma atividade principal ativa da tabela de CNAE no rascunho, com a intenção incluir ou excluir quando o tipo de serviço é alteração de atividade.

## História de Usuário
**Como** requerente dono do rascunho,  
**quero** indicar a atividade principal,  
**para** que a simulação e o protocolo usem um CNAE oficial ativo.

## Atores e permissões
- Dono efetivo, status rascunho (`ViabilityRequestPolicy::update`).
- Rota `PUT portal/solicitacoes/{solicitacao}/atividades`, sob autenticação do portal, LGPD, representação e `portal.solicitacao`.

## Fluxo evidenciado
1. O pedido informa `principal_cnae_id` e, opcionalmente, complementares e exclusões.
2. Em transação, o pivot `viability_request_cnaes` é sincronizado de uma vez: o principal com `is_primary` verdadeiro e os demais com falso. A intenção vai no mesmo payload.
3. A intenção só é preenchida quando o tipo de serviço tem código `alteracao-atividade`. Nesse caso, CNAE listado em `exclusoes` fica `excluir`; os demais submetidos ficam `incluir`. Nos outros tipos, a intenção permanece nula.
4. A mudança invalida a simulação e gera auditoria `solicitacao-cnaes` com o antes e o depois (código, principal e intenção).
5. O protocolo exige que exista CNAE principal. Sem ele, a protocolização para antes de consumir número.

## Regras de negócio evidenciadas
- Há exatamente uma atividade principal por sincronização do conjunto enviado.
- A principal precisa existir e estar ativa em `cnaes`.
- A principal não pode constar também em `complementares`.
- Só se marca exclusão de um CNAE que esteja entre os submetidos (principal ou complementar).
- Regras de escritório virtual neste mesmo pedido: abrigado em inscrição com sede ativa só aceita CNAE da lista vigente; recusa de abrigo e CNAE de sede em abrigado geram erro; constituição de sede em inscrição já travada por outro titular é recusada; a sede titular pode alterar as próprias atividades; CNAE da sede fora do gatilho e do Anexo A é recusado quando a lista do Anexo A está disponível. Sem lista vigente, o passo não bloqueia por Anexo A.
- Confirmação de perda da condição de sede e a pergunta de sede (`wants_virtual_office_hq`) só mudam se a chave vier no pedido; ausência preserva o valor gravado.

## Validações
- `principal_cnae_id` obrigatório, inteiro, CNAE ativo.
- `exclusoes` opcional; cada item inteiro, distinto e existente em `cnaes` (a exigência de ativo chega porque o id também precisa estar em principal ou complementares, e esses exigem ativo).
- Mensagens de atividade obrigatória, CNAE inativo ou inexistente, principal repetida nos complementares e exclusão fora do conjunto submetido.

## Critérios de aceite
- Dado um rascunho e um CNAE ativo, quando o dono salva as atividades, então o pivot fica com esse CNAE como principal e a simulação anterior é invalidada.
- Dado tipo de serviço `alteracao-atividade` e o CNAE principal em `exclusoes`, quando salva, então a intenção gravada é `excluir`.
- Dado outro tipo de serviço, quando salva, então a intenção do pivot fica nula.
- Dado CNAE inativo, principal ausente ou principal repetida nos complementares, quando envia, então a validação recusa.
- Dado rascunho sem CNAE principal, quando protocola, então o protocolo bloqueia por atividade principal pendente.

## Divergências da redação anterior
A atividade não é texto livre nem escolha fora da tabela. A intenção por CNAE existe só na alteração de atividade. O mesmo pedido aplica restrições de escritório virtual que a redação genérica não descrevia.

## Evidências
- `App\Http\Controllers\Portal\SolicitacaoAtividadeController::update`
- `App\Http\Requests\Portal\UpdateSolicitacaoAtividadesRequest`
- `App\Services\Solicitacao\ProtocolarSolicitacaoService::guardMinimumData`
- `routes/portal.php` (`portal.solicitacoes.atividades`)

## Não evidenciado
- Cadastro de CNAE fora da tabela oficial.
- Intenção incluir/excluir em primeiro estabelecimento ou renovação.
- Exclusão de CNAE inativo no cadastro. O código deixa isso como decisão pendente e, na validação atual, o CNAE submetido precisa estar ativo.
