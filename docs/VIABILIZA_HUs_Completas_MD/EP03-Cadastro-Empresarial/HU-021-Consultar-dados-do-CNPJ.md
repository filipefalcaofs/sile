# HU-021 — Consultar dados do CNPJ

## Épica
**EP03 — Cadastro Empresarial**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Pré-preencher o cadastro de empresa com os dados que a base cadastral da SEDUR devolve para o CNPJ informado.

## História de Usuário
**Como** cidadão autenticado no portal,  
**quero** buscar o CNPJ na base da SEDUR ao cadastrar uma empresa,  
**para** preencher razão social, nome fantasia, telefone e endereço quando o serviço responder.

## Atores e permissões
- Cidadão com sessão do portal (`auth:web`), e-mail verificado e termo LGPD aceito.
- Em representação ativa, a consulta usa a sessão de quem está logado; o cadastro seguinte grava a empresa para o representado.
- A rota `POST /portal/empresas/consultar-cnpj` exige o interruptor `features.cnpj_lookup` ligado. Desligado, a tela desabilita o botão "Buscar CNPJ".
- Limite de chamadas: `cnpj-lookup`, padrão de 30 por minuto por usuário ou, sem usuário, por IP (`viabiliza.seguranca.throttle.cnpj_lookup.por_minuto`).

## Fluxo evidenciado
1. Em `/portal/empresas/cadastrar`, o cidadão informa o CNPJ e aciona "Buscar CNPJ".
2. O `CnpjLookupController` normaliza o CNPJ e consulta `SedurCnpjLookup`, que chama `SedurContatoClient` em `{base}/pessoajuridica/cnpj/{digitos}`.
3. Sucesso (200): o JSON devolve o contrato de `CnpjData`. A tela só copia campos não vazios para o formulário. O provedor SEDUR preenche CNPJ, razão social, nome fantasia, telefone (só dígitos) e o endereço inteiro no campo logradouro. Natureza jurídica, porte, CNAE, número, complemento, bairro, cidade, UF, CEP e e-mail saem nulos.
4. O cidadão revisa e grava pelo cadastro manual (HU-023). A consulta não cria a empresa.

## Regras de negócio evidenciadas
- Com `features.cnpj_lookup` desligado, nenhum HTTP sai. A resposta é 422: "A consulta automática de CNPJ está desativada. Preencha os dados manualmente." A auditoria registra `empresas` / `consulta-cnpj` com resultado `bloqueado`.
- CNPJ inexistente na SEDUR: 404, "CNPJ não encontrado na base da SEDUR.", auditoria com resultado `falha`.
- Indisponibilidade ou falha de conexão: 503, "Serviço de consulta indisponível no momento. Preencha os dados manualmente.", auditoria com resultado `falha`.
- Sucesso é auditado com o host de `integrations.sedur_contato.base_url` (ou o valor de configuração equivalente).
- O binding em uso é `SedurCnpjLookup`. A classe `BrasilApiCnpjLookup` existe no código e não está ligada a este endpoint.

## Validações
- CNPJ obrigatório, normalizado (sem máscara, maiúsculas) e válido pela regra `ValidCnpj` (`LookupCnpjRequest`).

## Critérios de aceite
### CA-01 — Pré-preenchimento com a SEDUR
**Dado** que `features.cnpj_lookup` está ligado e a SEDUR devolve a pessoa jurídica,  
**Quando** o cidadão busca o CNPJ no cadastro,  
**Então** o formulário recebe razão social, nome fantasia, telefone e endereço quando esses campos vierem preenchidos, e a consulta é auditada como sucesso.

### CA-02 — Consulta desligada
**Dado** que `features.cnpj_lookup` está desligado,  
**Quando** houver tentativa de consulta,  
**Então** o sistema responde 422, não chama a SEDUR e orienta o preenchimento manual.

### CA-03 — CNPJ ausente ou serviço fora
**Dado** que a SEDUR não encontra o CNPJ ou não responde,  
**Quando** o cidadão busca o CNPJ,  
**Então** o sistema responde 404 ou 503, com a mensagem correspondente, e o cadastro manual continua disponível.

## Divergências da redação anterior
A redação anterior descrevia uma consulta genérica para "preencher automaticamente dados empresariais", sem fonte, sem interruptor e com fluxo alternativo de integração GIS/REDESIM. O código consulta só a base cadastral da SEDUR (ws-contato) e não traz CNAE, porte nem natureza jurídica.

## Evidências
- `app/Http/Controllers/Portal/CnpjLookupController.php`
- `app/Services/Cnpj/SedurCnpjLookup.php`
- `app/Services/Sedur/SedurContatoClient.php`
- `app/Http/Requests/Portal/LookupCnpjRequest.php`
- `app/Providers/AppServiceProvider.php` (binding `CnpjLookup` → `SedurCnpjLookup`)
- `routes/portal.php` (`portal.empresas.consultar-cnpj`)
- `resources/js/pages/portal/empresas/cadastrar.tsx`
- Parâmetro `features.cnpj_lookup` em `database/seeders/ParameterSeeder.php`

## Não evidenciado
- Consulta à Receita Federal ou à BrasilAPI neste fluxo.
- Gravação automática da empresa ou dos CNAEs a partir da resposta.
- Uso do parâmetro de catálogo `integrations.cnpj_lookup.base_url` pelo provedor SEDUR ligado ao endpoint.
