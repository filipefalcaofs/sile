# HU-016 — Configurar servidores de e-mail

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Manter os servidores SMTP e escolher o padrão ativo que o envio passa a usar.

## História de Usuário
**Como** administrador com `manter-config-email`,
**quero** cadastrar o servidor, testar o envio e marcá-lo como padrão,
**para** o Viabiliza disparar e-mail pela configuração gravada.

## Atores e permissões
- A tela exige `manter-config-email`. Menu Configuração, item Servidores de e-mail (`/gestao/config-email`).
- A senha é criptografada no model. A lista mostra `masked_password`. A auditoria não inclui a senha.

## Fluxo evidenciado
1. A lista ordena o padrão primeiro e depois o nome. Não pagina.
2. Incluir grava nome, driver, host, porta, criptografia, timeout, usuário, senha, remetente e situação. Se `is_default` vem verdadeiro, `setAsDefault` marca esse servidor.
3. Editar grava os mesmos campos. Senha vazia não substitui a atual. Padrão verdadeiro chama `setAsDefault` de novo.
4. Excluir apaga o registro e audita nome, driver e demais propriedades não sensíveis.
5. Testar exige um destinatário e envia por `EmailServerConnectionTester`. Sucesso e falha são auditados. Falha volta no campo `recipient`.
6. O controller declara que o servidor padrão ativo controla o envio via `MailConfigServiceProvider`.

## Regras de negócio evidenciadas
- O único driver aceito é `smtp`.
- Criptografia aceita: `tls`, `ssl` ou `none`.
- Senha em branco na edição mantém a credencial.
- O teste envia mensagem real ao destinatário informado. Não é uma checagem só de formulário.

## Validações
- `name`, `host` e `from_name` obrigatórios, até 255.
- `driver` obrigatório, igual a `smtp`.
- `port` inteiro de 1 a 65535.
- `encryption` em `tls`, `ssl`, `none`.
- `timeout` inteiro de 1 a 300.
- `username` opcional, até 255.
- `password` opcional, até 255.
- `from_address` e-mail obrigatório, até 255.
- `active` e `is_default` booleanos obrigatórios.
- No teste, `recipient` é e-mail obrigatório.

## Critérios de aceite
### CA-01 — Servidor padrão
**Dado** um usuário com `manter-config-email`,
**Quando** cadastra um SMTP com `is_default` verdadeiro,
**Então** esse servidor é marcado como padrão.

### CA-02 — Senha em branco
**Dado** um servidor que já tem senha,
**Quando** a edição envia senha vazia,
**Então** a senha armazenada permanece.

### CA-03 — Teste real
**Dado** um servidor gravado e um e-mail destinatário,
**Quando** o administrador testa a conexão,
**Então** o testador tenta o envio e a auditoria registra sucesso ou falha, sem gravar a senha.

### CA-04 — Excluir
**Dado** um servidor cadastrado,
**Quando** o administrador exclui,
**Então** o registro é removido e a exclusão entra no log `config-email`.

## Divergências da redação anterior
Não havia HU. A capacidade existia no código sem rastreabilidade.

## Evidências
- `routes/gestao.php`: `config-email.index`, `store`, `update`, `destroy`, `test`.
- `app/Http/Controllers/Gestao/EmailServerController.php`.
- `StoreEmailServerRequest` e `UpdateEmailServerRequest`.
- `resources/js/navigation/gestao-nav.ts`: Servidores de e-mail em Configuração.

## Não evidenciado
- Driver diferente de SMTP.
- Reexibição da senha em claro.
- Monitoramento da fila de envios. Isso é a HU-017, com outra permissão.
