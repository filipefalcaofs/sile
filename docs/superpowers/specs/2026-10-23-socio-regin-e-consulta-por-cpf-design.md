# Sócio do REGIN vê o processo e consulta de processos por CPF para o Portal de Serviços

Data: 23/10/2026. Continua `2026-10-23-entrada-pelo-portal-de-servicos-design.md` (mesma branch `feat/entrada-portal-servicos`).

## Intenção

Na reunião de 23/10/2026 ficou combinado que o Portal de Serviços da SEDUR lista os processos do CPF na área logada e que o botão Detalhar abre o processo no Viabiliza. Dois obstáculos:

1. Os processos que chegam do REGIN/JUCEB não têm solicitante no Viabiliza (`requester_user_id` nulo — decisão de 15/10/2026: não inventar solicitante a partir de sócio ou contador). Como só o solicitante vê o processo, o Detalhar não abre nenhum deles, e eles são a maior parte do volume.
2. O Portal não tem de onde tirar a lista de processos de viabilidade de um CPF.

Decisão do usuário (23/10/2026): **o sócio cujo CPF está no processo do REGIN pode ver o processo pelo Detalhar, sem virar solicitante**; e o Viabiliza oferece **a consulta de processos por CPF** para o Portal.

## Objetivos

1. Guardar, por processo, os CPFs das pessoas que podem vê-lo além do solicitante: os sócios do REGIN (`RUC_PROF`) e o solicitante declarado em `RUC_GENERAL` na empresa a constituir (`viability_requests.solicitante_cpf`, já gravado).
2. A política `view` do processo passa a valer para o solicitante (como hoje) **ou** para quem tem o CPF vinculado ao processo. `update`, `protocol` e `cancel` continuam só do solicitante.
3. Os processos vinculados pelo CPF aparecem também em Minhas solicitações do cidadão (senão ele abre pelo Detalhar e não acha o processo ao voltar para a lista).
4. Consulta `GET /api_integracao/portal-servicos/processos` com o token da sessão do cidadão no Portal: devolve os processos de viabilidade do CPF daquela sessão, para o Portal montar a lista e o Detalhar.
5. Preencher os vínculos dos processos do REGIN já existentes a partir dos recebimentos guardados.

## Fora do escopo

- Sócio virar solicitante, editar, protocolar ou cancelar.
- Contador, procurador ou outros papéis do RUC além de sócio.
- Fluxo do autônomo e da MEI.
- Mudanças no Portal de Serviços (lado Sudoeste/SEDUR).

## O que não pode acontecer

- Um CPF ver processo em que não é solicitante nem está vinculado.
- A consulta por CPF aceitar o CPF como parâmetro: o CPF vem **só** da sessão do Portal (token conferido no BFF), senão qualquer um listaria processos de qualquer CPF.
- Dados internos da análise na consulta (parecer, motivo interno, analista, documentos). Só o que o cidadão já vê na lista dele.
- O vínculo por CPF virar `requester_user_id` (a decisão de 15/10 continua).

## Desenho

### Vínculos de CPF por processo

- Tabela `viability_request_cpfs`: `id`, `viability_request_id` (FK, cascade), `cpf` (string 11, indexada), `papel` (string: `socio` | `solicitante`), timestamps; único em (`viability_request_id`, `cpf`, `papel`).
- Model `ViabilityRequestCpf`; relação `ViabilityRequest::cpfsVinculados()`.
- `ReginSociosExtrator::cpfs(array $rowset): list<string>`: lê `GROUPRUC_PROF.RUC_PROF` (lista ou objeto único) e, de cada item, os valores de chaves que contenham `CPF` ou `CGC` com CPF válido (11 dígitos, dígito verificador). Item com qualquer chave contendo `CRC` preenchida é ignorado (contador). A análise de 15/10/2026 dos 1.341 envelopes reais mostrou que `RUC_PROF` traz só sócios (relação 2, qualificação 22, sem CRC); o filtro de CRC é a proteção caso venha contador. **O nome exato do campo não foi confirmado num envelope real** (o banco de produção não é acessível daqui); a leitura tolerante cobre `RPR_CGC_CPF`, `RPR_CPF` e afins. Validar com um envelope real antes de ligar a entrada (pendência).
- `VinculosCpfProcesso::sincronizar(ViabilityRequest $processo, list<string> $socios): void`: os vínculos `socio` do processo passam a ser exatamente a lista recebida (insere os novos, apaga os que saíram); o vínculo `solicitante` segue `solicitante_cpf` (cria, troca ou apaga). Ex-sócio perde o acesso no reenvio.
- `ReginProcessoProtocolador`: ao criar ou reaproveitar o processo de um recebimento do REGIN, sincroniza os vínculos com o rowset daquele recebimento.
- Comando `regin:vincular-cpfs {--dry-run}`: para os processos do REGIN, lê o recebimento mais recente do mesmo protocolo (`regin_recebimentos.protocolo = viability_requests.external_reference`) e sincroniza; idempotente. Processo sem recebimento só sincroniza o `solicitante_cpf`.

### Quem vê

- `ViabilityRequestPolicy::view`: `owns($user, $request)` **ou** existe vínculo com o CPF do usuário efetivo (`effectiveUser($user)->cpf`).
- `SolicitacaoController::index` (Minhas solicitações): `requester_user_id = usuário efetivo` **ou** processo com vínculo do CPF do usuário efetivo. Ações de editar/cancelar continuam calculadas pelas políticas (`update`/`cancel`) e ficam desligadas para o vinculado.
- Resposta a convite (`PendenciaRespostaController`) já usa `view`, então o vinculado responderia ao convite. **Mas hoje não há convite em processo do REGIN:** `PendenciaService::abrir()` recusa abrir convite em processo sem requerente. Liberar isso é decisão pendente com o usuário (revisão de 23/10/2026).
- O vinculado vê a mesma tela do processo que o solicitante: ela não mostra CPF de outras pessoas (o CPF do solicitante declarado só aparece na gestão).
- Em Minhas solicitações, os botões de editar e cancelar passam a depender também das políticas `update` e `cancel` (hoje dependem só da situação); para o vinculado ficam desligados.
- O Detalhar por número (entrada) já usa a política `view`: passa a abrir os processos do REGIN do sócio.

### Consulta por CPF para o Portal

- Rota `GET /api_integracao/portal-servicos/processos`, nome `portal-servicos.processos`, no grupo `LogInboundIntegration` (o cabeçalho de autorização sai mascarado no log), com `throttle:portal-servicos-processos` (60 por minuto por IP).
- Autenticação: `Authorization: Bearer <token da sessão do cidadão no Portal>`. O Viabiliza confere o token com `PortalServicosSessaoClient::buscarSessao` e usa o CPF devolvido.
  - Chave `features.login_portal_servicos` desligada ou sem URL do BFF → 503 `{"message": "Consulta indisponível."}`.
  - Sem token, token recusado ou sem CPF → 401 `{"message": "Sessão do Portal de Serviços inválida."}`.
  - BFF fora → 503 `{"message": "Não foi possível confirmar a sessão agora."}`.
- Processos devolvidos: não rascunho, e (solicitante = conta do Viabiliza com aquele CPF) **ou** vínculo daquele CPF. Ordem: mais recente primeiro. Limite 200 (o Portal mostra os mais recentes; sem paginação nesta versão).
- Corpo (Eloquent API Resource `ProcessoPortalServicosResource`), em `data`:
  - `numero_processo` (`protocol_number`), `numero_viabilidade` (TVL da decisão ou `null`), `bap` (`external_reference` ou `null`);
  - `servico` (nome do tipo de serviço), `empresa` (razão social ou nome pretendido), `cnpj` (se houver);
  - `situacao` (`status->value`), `situacao_descricao` (`status->publicLabel()`), `convite_pendente` (`status === EmPendencia`);
  - `protocolado_em`, `atualizado_em` (ISO 8601);
  - `vinculo` (`solicitante` ou `socio`);
  - `detalhar_url`: `route('portal.entrar.portal-servicos', ['destino' => 'processo', 'numero' => <numero_processo>])` — o Portal acrescenta `&token=`.

## Testes

- Extrator: lista e objeto único; CPF formatado; CPF inválido ignorado; CNPJ ignorado; sem `RUC_PROF`.
- Protocolador do REGIN grava sócios e solicitante; comando preenche processos antigos e é idempotente; `--dry-run` não grava.
- Política: sócio vê, não edita nem cancela; CPF sem vínculo não vê; representado continua funcionando.
- Minhas solicitações inclui o processo do sócio, sem ações de editar/cancelar.
- Detalhar por número abre processo do REGIN para o sócio.
- Consulta: token válido devolve só os processos daquele CPF (solicitante e sócio), sem rascunho e sem processo de outro CPF; formato do item; 401 sem token e com token recusado; 503 com chave desligada e com BFF fora; o CPF em parâmetro é ignorado; o token não aparece no log de integrações.

## Decisões minhas (o usuário pode trocar)

- Tabela própria de vínculos (indexada por CPF) em vez de JSON no processo — a consulta por CPF precisa de índice.
- O solicitante declarado do REGIN (`solicitante_cpf`, empresa a constituir) também ganha vínculo e vê o processo: é quem pediu.
- O vinculado responde a convite (a resposta já usa a permissão de ver).
- Processos vinculados entram em Minhas solicitações.
- Consulta protegida pelo token da sessão do Portal (o CPF nunca vem como parâmetro), atrás da mesma chave da entrada.
- Limite de 200 processos, sem paginação.
- Sem chave separada para o vínculo de sócio: o filtro de CRC cobre o risco de contador.
- Vínculos de sócio sincronizados no protocolar e no comando. O REGIN não regrava protocolo repetido (responde '5'), então não há reenvio que mude o quadro de sócios de um processo existente.

## Pendências

- Confirmar o nome do campo de CPF dentro de `RUC_PROF` com um envelope real (log de integração no pod).
- Rodar `regin:vincular-cpfs` em cada ambiente depois do `migrate`.
