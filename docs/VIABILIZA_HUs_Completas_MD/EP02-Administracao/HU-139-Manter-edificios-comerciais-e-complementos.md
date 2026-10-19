# HU-139 — Manter edifícios comerciais e complementos

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
A redação anterior pedia um cadastro de edifício comercial, vinculado à inscrição imobiliária, com vários complementos pesquisáveis. Esse cadastro não existe. O complemento do endereço é texto livre na solicitação.

## História de Usuário
**Como** requerente ou operador de contingência,
**quero** informar o complemento do imóvel em texto livre,
**para** registrar sala, loja ou referência sem escolher um edifício cadastrado.

Não há ator de administração mantendo edifícios: não existe controller, rota nem item de menu para esse cadastro.

## Atores e permissões
- No portal, a etapa do imóvel grava `address_complement` pela solicitação do requerente.
- Na contingência (`registrar-contingencia`), o operador grava o mesmo campo.
- A consulta da inscrição, no retorno da SEFAZ, pode devolver edifício ou conjunto em `conjunto_edificio` e compor o complemento exibido. Isso é leitura da integração, não um CRUD.
- O menu de gestão não tem item de edifício comercial nem de complemento.

## Fluxo evidenciado
1. O formulário do imóvel tem o campo Complemento (`address_complement`), texto, com placeholder de sala ou edifício comercial. A validação do portal e da contingência é string opcional de até 255 caracteres.
2. O valor fica na solicitação (`viability_requests.address_complement`) e segue na renovação, no comprovante, no documento de decisão e no protocolo REGIN.
3. `SedurSefazPropertyRegistryLookup` lê `Edificio` ou `Conjunto` da localização e preenche `conjunto_edificio`. O complemento devolvido à consulta junta edifício e complemento quando os dois vêm preenchidos.
4. A ficha de análise exibe o rótulo «Conjunto / Edifício» para `conjunto_edificio` vindo do cadastro imobiliário consultado.
5. Não há model, migration de cadastro nem rota `gestao` de edifício, sala ou loja pesquisável.

## Regras de negócio evidenciadas
- O complemento da solicitação é texto livre e opcional.
- Não há lista de complementos por inscrição para o requerente buscar.
- `conjunto_edificio` é campo lido da consulta imobiliária, não um registro mantido pela SEDUR nesta aplicação.
- Processo já protocolado guarda o complemento que foi informado. Não há versionamento de um cadastro de edifício.

## Validações
- `UpdateSolicitacaoImovelRequest` e `RegistrarContingenciaRequest`: `address_complement` nulo ou string, máximo 255.

## Critérios de aceite
### CA-01 — Texto livre na solicitação
**Dado** o formulário do imóvel no portal ou na contingência,
**Quando** o usuário informa «Sala 2 do edifício comercial»,
**Então** a solicitação grava esse texto em `address_complement`.

### CA-02 — Complemento omitido
**Dado** o mesmo formulário,
**Quando** o complemento vai vazio,
**Então** a validação aceita, porque o campo é opcional.

### CA-03 — Sem cadastro de edifício
**Dado** `routes/gestao.php` e os controllers em `app/Http/Controllers/Gestao`,
**Quando** se procura manutenção de edifício comercial ou de complemento pesquisável,
**Então** não há rota nem controller. O menu de gestão também não tem o item.

### CA-04 — Edifício vindo da SEFAZ
**Dado** uma consulta de inscrição cujo retorno traz edifício ou conjunto,
**Quando** o lookup monta os campos do imóvel,
**Então** `conjunto_edificio` recebe esse texto da integração, sem gravar um cadastro de edifícios.

## Divergências da redação anterior
A redação anterior, marcada como proposta de 11/06/2026, pedia edifício vinculado à inscrição, N complementos (sala, loja, andar) pesquisáveis e alterações versionadas. O código confirma o complemento em texto livre e não implementa esse cadastro. A busca «buscar complemento» do legado não existe nesta aplicação.

## Evidências
- `resources/js/components/solicitacao/etapa-imovel.tsx`: campo Complemento.
- `app/Http/Requests/Portal/UpdateSolicitacaoImovelRequest.php` e `app/Http/Requests/Gestao/RegistrarContingenciaRequest.php`.
- `viability_requests.address_complement` na migration `create_viability_requests_table`.
- `app/Services/Realty/SedurSefazPropertyRegistryLookup.php`: `conjunto_edificio` e `juntarEdificioComplemento`.
- `resources/js/pages/gestao/ficha-analise/show.tsx`: rótulo Conjunto / Edifício.
- `resources/js/navigation/gestao-nav.ts`: sem item de edifício.

## Não evidenciado
- CRUD de edifício comercial.
- Lista pesquisável de salas, lojas ou andares por inscrição.
- Obrigatoriedade do complemento quando a inscrição tem edifício cadastrado.
- Versionamento de complemento para processos já existentes.
