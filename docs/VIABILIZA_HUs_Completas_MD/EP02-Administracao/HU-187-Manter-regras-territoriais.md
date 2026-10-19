# HU-187 — Manter regras territoriais

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Alterar uma regra territorial já existente no rascunho do domínio, publicar essa versão com outro mantenedor, reativar uma versão publicada ou descartar o rascunho sem mexer na vigente.

## História de Usuário
**Como** mantenedor com `manter-louos`,
**quero** ajustar título, alvos, CNAEs e endereços de uma regra territorial e publicar o rascunho,
**para** a restrição vigente (logradouro, camada ou allow-list) passar a ser a versão publicada.

## Atores e permissões
- As rotas de rascunho, publicação, reativação e descarte ficam em `/gestao/louos` e exigem `manter-louos`.
- A consulta do hub Motor de regras (`/gestao/louos`, aba territoriais) usa o mesmo item de menu e aceita `consultar-louos` ou `consultar-cnaes` só para leitura.
- A publicação chama `RuleVersionService::publish` com quatro olhos ligados. O domínio `territorial` é sensível: o publicador não pode ser o autor do rascunho.

## Fluxo evidenciado
1. `POST louos/territoriais/rascunho` grava a alteração no rascunho do domínio. Se não há rascunho aberto, `TerritorialRuleDraftService::aplicarAlteracao` abre um a partir da versão informada.
2. A regra é localizada pelo código dentro do rascunho. Se ainda não está nele, o serviço copia tipo e efeito da regra de mesmo código em alguma versão territorial. Código inexistente em qualquer versão territorial lança «Regra territorial {código} não encontrada para edição.» e não cria regra nova.
3. Tipo e efeito não entram no formulário e não são alterados. A tela atualiza título, CODLOG, nome do logradouro, camada, filtro, zona alvo, motivo de análise, texto de decisão, base legal e a flag ativo. A lista de CNAEs e a lista de endereços, quando enviadas, substituem as anteriores.
4. A resposta volta à aba territoriais com «Regra gravada no rascunho {versão}. A publicação exige outro mantenedor (quatro olhos).» A auditoria é `regras` / `rascunho-regra-territorial`.
5. `PUT louos/territoriais/versoes/{versao}/publicar` só aceita rascunho do domínio territorial. O autor do rascunho recebe «A publicação por quatro olhos exige um publicador diferente do autor do rascunho.» Outro mantenedor publica e a versão entra em uso.
6. `PUT .../ativar` só aceita versão territorial que não seja rascunho. Rascunho devolve «Rascunho não pode ser ativado — publique-o antes.» A vigente anterior é encerrada, não apagada.
7. `DELETE louos/territoriais/rascunho/{versao}` apaga as regras daquele rascunho e o próprio rascunho. A vigente permanece. A auditoria é `regras` / `descarte-rascunho-territorial`.
8. Versão de outro domínio devolve «A versão não pertence ao domínio das regras territoriais.» Publicar ou descartar o que não é rascunho devolve «A operação exige uma versão em rascunho.»

## Regras de negócio evidenciadas
- RN-001: regra nova não nasce neste formulário. Entra por carga versionada. A edição parte de um código já existente (`TerritorialRuleDraftService::aplicarAlteracao`).
- RN-002: tipo e efeito são estruturais e permanecem os da regra de origem.
- RN-003: a vigente não é editada no lugar. A alteração fica no rascunho até a publicação.
- RN-004: publicar exige publicador diferente do autor, porque `RuleDomain::Territorial` é sensível e o controller não desliga o quatro olhos.
- RN-005: descartar o rascunho não altera a versão vigente.

## Validações
`TerritorialRuleEditRequest`:
- `codigo` e `version` obrigatórios.
- `titulo` obrigatório, até 255.
- `ativo` obrigatório, booleano.
- `cod_log` até 20; `logradouro_nome`, `camada` e `titulo` até 255; `camada_filtro` até 500; `zona_alvo` até 60; `motivo_analise` até 2000; `base_legal` até 500.
- `decision_text_id` inteiro existente em `decision_texts`, ou vazio.
- `cnaes` presente, array. Cada item tem 7 dígitos, sem máscara, e não se repete. Mensagem: «Cada CNAE deve ter os 7 dígitos da subclasse (sem máscara).»
- `addresses` presente, array. Cada endereço pode ter rótulo, CODLOG, nome do logradouro, número de porta e bairro, com os tamanhos do request.

## Critérios de aceite
### CA-01 — Editar regra existente no rascunho
**Dado** um código de regra territorial já versionado e um mantenedor com `manter-louos`,
**Quando** ele envia título, situação, CNAEs e endereços,
**Então** a alteração fica no rascunho, tipo e efeito não mudam, e a vigente continua a anterior até a publicação.

### CA-02 — Código inexistente
**Dado** um código que não existe em nenhuma versão territorial,
**Quando** o mantenedor tenta gravar,
**Então** o serviço recusa e não cria regra.

### CA-03 — Quatro olhos
**Dado** um rascunho territorial cujo autor é o usuário logado,
**Quando** esse mesmo usuário publica,
**Então** a publicação é recusada. Outro mantenedor com `manter-louos` publica e a versão passa a vigorar.

### CA-04 — Descarte
**Dado** um rascunho territorial aberto,
**Quando** o mantenedor descarta,
**Então** o rascunho e as regras dele saem, e a versão vigente permanece.

## Divergências da redação anterior
Não havia HU. A capacidade existia no código sem rastreabilidade. A aplicação das regras no processo continua nas HU-035 e HU-043. Esta história cobre só a manutenção no hub.

## Evidências
- `app/Http/Controllers/Gestao/TerritorialRuleController.php`
- `app/Services/Territorial/TerritorialRuleDraftService.php`
- `app/Http/Requests/Gestao/TerritorialRuleEditRequest.php`
- `app/Services/Rules/RuleVersionService.php` (`publish`, quatro olhos)
- `routes/gestao.php` (`louos.territoriais.rascunho`, `publicar`, `ativar`, `descartar`)
- `app/Enums/RuleDomain.php` (`Territorial`, sensível)

## Não evidenciado
- Inclusão de regra com código novo pela tela.
- Alteração de tipo ou efeito pela tela.
- Exclusão da versão vigente.
- Item de menu próprio: a manutenção fica na aba territoriais do Motor de regras.
