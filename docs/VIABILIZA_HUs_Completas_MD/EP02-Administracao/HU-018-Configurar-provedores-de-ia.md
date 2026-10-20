# HU-018 — Configurar provedores de IA

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Manter provedores de IA, com chave criptografada, teste de conexão real e uma configuração padrão.

## História de Usuário
**Como** administrador com `manter-config-ia`,
**quero** cadastrar o provedor, testar a URL e ativá-lo,
**para** os recursos de IA usarem a configuração padrão ativa.

## Atores e permissões
- A tela exige `manter-config-ia`. Menu Configuração, item Configuração de IA (`/gestao/config-ia`).
- O teste usa o throttle `ai-connection-test`.
- A chave não volta na lista: o campo exibido é `masked_api_key`. A auditoria não inclui a chave.

## Fluxo evidenciado
1. A lista ordena a padrão primeiro. Incluir e editar gravam nome, provedor, capacidade, URL base, chave, modelo, temperatura, máximo de tokens, timeout, situação e marca de padrão. `set` de padrão segue o mesmo desenho do servidor de e-mail: `is_default` verdadeiro chama a marcação de padrão.
2. Na edição, chave vazia não substitui a atual.
3. Ativar uma configuração sem chave é recusado com «Informe a chave de API para ativar a configuração.»
4. Testar chama `AiProviderClient::testConnection`. Falha volta em `ai_connection`. Sucesso e falha são auditados.
5. Excluir remove o registro.
6. A configuração gravada é lida por `AiConfigResolver` e por `RunAiAgentJob`. O teste e a URL passam por `AiBaseUrlGuard` (HTTPS e allowlist).

## Regras de negócio evidenciadas
- Provedores aceitos: `openai`, `anthropic`, `gemini`, `azure`, `compativel`.
- Capacidades aceitas: `text`, `vision`, `embeddings`.
- Configuração ativa no cadastro exige chave.
- A URL base recusada pelo guarda não é gravada.
- Chave em branco na edição mantém a credencial.

## Validações
- `name` e `model` obrigatórios, até 255.
- `provider` e `capability` nos conjuntos acima.
- `base_url` obrigatória, até 255, validada por `AiBaseUrlGuard`.
- `api_key` até 500; no cadastro, obrigatória se `active` é verdadeiro.
- `temperature` numérica de 0 a 2.
- `max_tokens` inteiro de 1 a 1.000.000.
- `timeout_ms` inteiro de 1.000 a 120.000.
- `active` e `is_default` booleanos obrigatórios.

## Critérios de aceite
### CA-01 — Ativa sem chave
**Dado** um cadastro com `active` verdadeiro e chave vazia,
**Quando** a validação roda,
**Então** a mensagem pede a chave de API para ativar a configuração.

### CA-02 — URL recusada
**Dado** uma URL que `AiBaseUrlGuard` rejeita,
**Quando** o administrador salva,
**Então** a configuração não é gravada.

### CA-03 — Teste auditado
**Dado** uma configuração gravada,
**Quando** o administrador testa a conexão,
**Então** o cliente HTTP do provedor é chamado e o log `config-ia` registra sucesso ou falha, sem a chave.

### CA-04 — Chave preservada
**Dado** uma configuração que já tem chave,
**Quando** a edição envia a chave vazia,
**Então** a chave armazenada permanece.

## Divergências da redação anterior
Não havia HU. A capacidade existia no código sem rastreabilidade.

## Evidências
- `routes/gestao.php`: `config-ia.index`, `store`, `update`, `destroy`, `test`, `ativacao.update`.
- `app/Http/Controllers/Gestao/AiConfigurationController.php`.
- `StoreAiConfigurationRequest` e `UpdateAiConfigurationRequest`.
- `app/Services/Ai/AiConfigResolver.php` e `app/Jobs/Ai/RunAiAgentJob.php`.
- `resources/js/navigation/gestao-nav.ts`: Configuração de IA em Configuração.

## Não evidenciado
- Provedor ou capacidade fora das listas do Form Request.
- Reexibição da chave em claro.
