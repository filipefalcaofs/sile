# HU-069 — Consultar protocolo

## Épica
**EP08 — Solicitação de Viabilidade**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Permitir que o requerente acompanhe a própria solicitação e que um terceiro veja, por link assinado e temporário, só o número, o status público, a data de protocolo, a linha do tempo pública e o prazo estimado.

## História de Usuário
**Como** requerente,  
**quero** consultar o andamento do protocolo e compartilhar um link público de acompanhamento,  
**para** ver a situação do pedido sem expor os dados completos a quem recebe o link.

## Atores e permissões
- Consulta autenticada: dono efetivo, qualquer status (`ViabilityRequestPolicy::view`). Terceiro autenticado recebe 403.
- Rota `GET portal/solicitacoes/{solicitacao}`, com autenticação do portal, LGPD e representação. Não depende de `portal.solicitacao`.
- Consulta pública: sem login, `GET portal/protocolo/{solicitacao}` com middleware `signed` e `throttle:consulta-protocolo`. Assinatura inválida ou expirada responde 403.

## Fluxo evidenciado
1. Na consulta autenticada, o sistema audita `consulta-protocolo` e mostra protocolo, status (rótulo interno e público), datas, tipo de serviço, empresa e CNPJ formatado, área, endereço resumido e CNAEs.
2. A linha do tempo sai das transições reais. No modo autenticado, cada etapa traz rótulo, data, status técnico e motivo. Pendências: em rascunho, concluir e protocolar; em pendência, há convite aguardando ação; nos demais status, lista vazia.
3. Se já existe número, a página recebe link assinado com validade `viabiliza.solicitacao.consulta_publica.assinatura_ttl_dias` (padrão 30 dias), lido de config. Em rascunho sem número, o link é nulo.
4. A página autenticada oferece o download do comprovante (HU-068) e, quando há decisão, o parecer e a carga opcional de explicação por IA.
5. A consulta pública audita `consulta-protocolo-publica` e devolve só número, status público, data de protocolo, etapas com rótulo e data, e o prazo estimado. Não envia empresa, CNPJ, endereço, CNAE, motivo interno, ator nem pendências.
6. O prazo estimado usa `solicitacao.prazo_estimado_dias` (padrão 30), com texto de que é estimativa parametrizada e não vinculante. Status cancelada, deferida ou indeferida não traz prazo.

## Regras de negócio evidenciadas
- A linha do tempo não é inventada: só existem etapas que foram transições gravadas.
- O link público não mostra dado de empresa, endereço ou anexo.
- Toda consulta, autenticada ou pública, gera auditoria. Na pública, o causer fica nulo.
- A explicação por IA, quando carregada, é sugestão lida do ledger e não altera a decisão.

## Validações
- Autorização por policy na consulta autenticada.
- Assinatura e throttle na consulta pública.
- Filtro de status da lista (HU-061) é outra tela; esta HU é o detalhe do protocolo.

## Critérios de aceite
- Dado o requerente efetivo, quando abre a solicitação, então vê os dados do processo, a linha do tempo com motivo e a auditoria da consulta.
- Dado solicitação com número, quando a página autenticada é montada, então existe link temporário assinado. Dado rascunho sem número, então o link é nulo.
- Dado o link válido, quando um visitante sem login abre a rota pública, então vê número, rótulo público, data de protocolo, etapas sem motivo e o prazo com ressalva, e a consulta é auditada.
- Dado link expirado ou adulterado, quando abre a rota pública, então a resposta é 403.
- Dado outro usuário autenticado, quando abre a consulta, então a policy nega.

## Divergências da redação anterior
A consulta pública não pede protocolo digitado numa busca anônima aberta. O acesso público é o link assinado gerado para quem já é o dono. O prazo exibido é parâmetro com ressalva, não medição por etapa.

## Evidências
- `App\Http\Controllers\Portal\ConsultaProtocoloController`
- `App\Http\Controllers\Portal\ConsultaProtocoloPublicaController`
- `App\Services\Solicitacao\TimelineSolicitacao`
- `routes/portal.php` (`portal.solicitacoes.show`, `portal.protocolo.publico`)
- `resources/js/pages/portal/solicitacoes/protocolo.tsx`

## Não evidenciado
- Consulta pública por digitação do número, sem assinatura.
- Prazo calculado pelo histórico real de processos equivalentes.
- Exibição de CPF, CNPJ, endereço ou anexo no payload público.
