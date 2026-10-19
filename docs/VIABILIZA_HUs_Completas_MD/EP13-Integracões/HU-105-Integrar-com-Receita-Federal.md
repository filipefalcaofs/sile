# HU-105 — Integrar com Receita Federal

## Épica
**EP13 — Integrações**

## Situação na implementação
- **Status:** Não evidenciada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Não há, no código ligado à aplicação, consulta à Receita Federal nem validação de DBE.

## História de Usuário
**Como** sistema, **quero** validar CNPJ e DBE na Receita Federal quando esse canal existir, **para** conferir dado federal antes do cadastro. Esse canal não está ligado.

## Atores e permissões
- Não há controller, rota nem binding de Receita Federal ou DBE.
- A consulta de CNPJ do portal (`CnpjLookupController`) usa `SedurCnpjLookup`, que chama a base cadastral da SEDUR (ws-contato). Não é a Receita Federal.

## Fluxo evidenciado
1. `AppServiceProvider` liga `CnpjLookup` a `SedurCnpjLookup`.
2. Essa consulta devolve razão social, nome fantasia, telefone e endereço quando a SEDUR responde. Natureza jurídica, porte e CNAE ficam nulos de propósito: a SEDUR não os envia e o código não os inventa.
3. A classe `BrasilApiCnpjLookup` existe e não é o binding em uso.
4. Com `features.cnpj_lookup` desligado, a rota do portal não faz HTTP e pede preenchimento manual. Isso continua sendo a base da SEDUR, não a Receita.

## Regras de negócio evidenciadas
- Não há chamada evidenciada a serviço da Receita Federal neste repositório ligado ao fluxo.
- Ausência de CNAE na consulta da SEDUR não é preenchida por outra fonte.

## Validações
- Não há validação de DBE.

## Critérios de aceite
- Nenhuma rota em uso consulta a Receita Federal para CNPJ ou DBE.
- A consulta de CNPJ do cadastro de empresa, quando ligada, fala com a SEDUR e não completa porte, natureza ou CNAE.

## Divergências da redação anterior
O texto pedia integrar com a Receita para validar CNPJ/DBE. O que existe é consulta cadastral da SEDUR, com outro contrato e sem DBE.

## Evidências
- `app/Providers/AppServiceProvider.php` (binding `CnpjLookup`)
- `app/Services/Cnpj/SedurCnpjLookup.php`
- `app/Http/Controllers/Portal/CnpjLookupController.php`
- `app/Services/Cnpj/BrasilApiCnpjLookup.php` (não ligada)

## Não evidenciado
- Cliente da Receita Federal, e-CAC, DBE ou comprovante de inscrição federal.
- Uso de `BrasilApiCnpjLookup` no fluxo da aplicação.
