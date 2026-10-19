# HU-160 — Configurar APIs de integração

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Gravar, por aba, as URLs, credenciais e interruptores das integrações que já estão no catálogo de parâmetros.

## História de Usuário
**Como** administrador com `manter-parametros`,
**quero** ajustar cada integração sem ver a senha já gravada,
**para** homologação e produção mudarem sem deploy.

## Atores e permissões
- A tela exige `manter-parametros`. Menu Configuração, item APIs (`/gestao/config-apis`).
- O teste de conexão do REGIN e o da inscrição imobiliária usam rotas próprias, também com `manter-parametros`.

## Fluxo evidenciado
1. A página abre na aba `regin` quando a aba pedida não existe no catálogo. As abas são REGIN, Inscrição imobiliária, CPF e CNPJ, Autenticação SEDUR, GOV.BR, Geocodificação, GeoServer e Cadastro municipal (SEMAP).
2. Cada campo vem do parâmetro ligado à chave: rótulo, tipo (`url`, `texto`, `senha`, `interruptor` ou `escolha`), ajuda e valor. Senha nunca volta preenchida; a tela só sabe se já há valor (`tem_valor`). Interruptor volta como booleano.
3. Gravar percorre os campos da aba. Senha vazia não substitui a credencial. Valor igual ao atual não gera auditoria. Valor diferente atualiza o parâmetro e audita anterior e novo; se a chave é sensível, os dois entram como `[criptografado]`.
4. Quando a aba declara espelho de URL ativa, o interruptor escolhe a URL de produção ou a de homologação e essa URL é copiada para a chave de destino, se estiver preenchida.
5. O catálogo marca teste só no REGIN (`/gestao/config-regin/testar`) e na inscrição imobiliária (`/gestao/config-inscricao-imobiliaria/testar`). As outras abas têm `teste` nulo.
6. Esta gravação chama `Parameter::update` direto. Não passa pela proposta de quatro olhos do `ParameterController`.

## Regras de negócio evidenciadas
- Aba desconhecida na gravação responde 404. Na abertura, cai em REGIN.
- Credencial em branco significa manter a atual.
- O teste, onde existe, é ação separada da gravação.
- Não há inclusão de integração nova fora de `ApiIntegracaoCatalog`.

## Validações
- As regras de cada campo são as `validation_rules` do parâmetro, com exceção da senha: string nula, até 255, ou até 2048 quando o id é `token`. Senha de id `senha` também exige mínimo de 8 caracteres.
- Interruptor é normalizado para `1` ou `0`. URL e senha só com espaços viram nulo.
- Aba ausente do catálogo acrescenta o erro «Integração desconhecida.»

## Critérios de aceite
### CA-01 — Senha preservada
**Dado** uma integração que já tem senha gravada,
**Quando** o administrador salva a aba com o campo de senha vazio,
**Então** a credencial armazenada não muda.

### CA-02 — URL gravada e auditada
**Dado** uma URL diferente da atual numa chave não sensível,
**Quando** o administrador salva,
**Então** o parâmetro muda e a auditoria registra valor anterior e novo.

### CA-03 — Aba inexistente
**Dado** um identificador de aba fora do catálogo,
**Quando** o administrador envia a atualização,
**Então** a validação recusa a aba e o controller responde 404 se a definição não existir.

### CA-04 — Teste só onde o catálogo marca
**Dado** a apresentação das abas,
**Quando** a tela monta REGIN ou inscrição imobiliária,
**Então** essas abas trazem URL de teste. CPF e CNPJ, autenticação SEDUR, GOV.BR, geocodificação, GeoServer e SEMAP vêm com teste nulo.

## Divergências da redação anterior
Não havia HU. A capacidade existia no código sem rastreabilidade.

## Evidências
- `routes/gestao.php`: `config-apis.edit`, `config-apis.update`, `config-regin.testar`, `config-inscricao-imobiliaria.testar`.
- `app/Http/Controllers/Gestao/ApiIntegracaoController.php`.
- `app/Http/Requests/Gestao/UpdateApiIntegracaoRequest.php`.
- `app/Support/ApiIntegracaoCatalog.php`.
- `resources/js/navigation/gestao-nav.ts`: APIs em Configuração.

## Não evidenciado
- Teste de conexão nas abas em que o catálogo deixa `teste` nulo.
- Quatro olhos nesta gravação. Parâmetro de governança `decision` alterado aqui não abre proposta.
- Cadastro de uma integração que não esteja no catálogo.
