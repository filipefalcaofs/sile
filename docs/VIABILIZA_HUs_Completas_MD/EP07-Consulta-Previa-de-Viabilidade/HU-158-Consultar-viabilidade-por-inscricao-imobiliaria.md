# HU-158 — Consultar viabilidade por inscrição imobiliária

## Épica
**EP07 — Consulta Prévia de Viabilidade**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Consultar a viabilidade usando a inscrição imobiliária para obter o ponto do imóvel, quando o Cadastro Imobiliário devolver coordenada.

## História de Usuário
**Como** cidadão, com ou sem login,  
**quero** informar a inscrição imobiliária e o CNAE,  
**para** obter o veredito locacional do ponto do imóvel quando o cadastro localizar esse ponto.

## Atores e permissões
- Rota pública `POST /portal/viabilidade/inscricao`, no mesmo throttle da consulta (padrão 20 por minuto).
- A página é a mesma de "Consultar viabilidade", aba "Inscrição imobiliária".
- A resolução do ponto depende de `features.cadastro_imobiliario` e da resposta HTTP do BFF da SEDUR (`SedurSefazPropertyRegistryLookup`).

## Fluxo evidenciado
1. O cidadão informa inscrição, CNAE e área opcional, mais respostas da planilha quando a consulta anterior as devolveu.
2. `LocalizacaoInscricaoService` chama o cadastro. Com o interruptor ligado e HTTP de sucesso (`CodigoRetorno` `0`), mapeia coordenada UTM para WGS84.
3. Com latitude e longitude do cadastro, a consulta segue a mesma pipeline do endereço: território, enquadramento LOUOS e risco. Não geocodifica de novo.
4. Sem coordenada no cadastro, e com logradouro e número, tenta geocodificar. Se o ponto cair em Salvador, usa esse geocode na pipeline.
5. Quando não há ponto utilizável, a resposta continua HTTP 200 e degrada para análise por CNAE, sem território e sem coordenada inventada:
   - cadastro desligado, falha de conexão ou resposta sem sucesso: aviso "Resolução por inscrição imobiliária indisponível (base de lotes pendente SEDUR). Resultado sem análise territorial; consulte por endereço para o veredito locacional.";
   - inscrição não encontrada (`CodigoRetorno` diferente de `0`, ou 404): aviso de inscrição não encontrada no Cadastro (SEFAZ/SEDUR), sem análise territorial;
   - inscrição localizada sem coordenada e sem geocode em Salvador: aviso de inscrição sem coordenada do lote.
6. O veredito locacional nesse modo degradado é o do enquadramento sem território (pendente no consolidado do motor). A tela mostra os avisos.
7. Sucesso HTTP, inclusive a degradação, grava histórico só se o usuário estiver autenticado. A auditoria `viabilidade` / `consulta` ocorre nos dois casos.

## Regras de negócio evidenciadas
- Inscrição sem dígitos não chama o cadastro: o serviço de localização devolve sem inscrição e a consulta degrada por falta de ponto.
- O lookup descarta nome de proprietário e histórico de propriedade.
- Não há erro HTTP específico de inscrição nesta rota. Indisponibilidade vira aviso no corpo 200.
- O texto do aviso de indisponibilidade fala em "base de lotes pendente". O gatilho no código é interruptor desligado ou falha/indisponibilidade da API de inscrição, não uma checagem separada de camada de lotes.

## Validações
`ConsultaViabilidadeInscricaoRequest`:
- `inscricao`: obrigatória, texto, até 60 caracteres.
- `cnae`: obrigatório, texto, até 14 caracteres.
- `area`: opcional, numérica, de 0 a 9999999.
- `respostas`: array opcional, com `RespostaPlanilhaValida`.

## Critérios de aceite
### CA-01 — Ponto resolvido
**Dado** `features.cadastro_imobiliario` ligado e o cadastro devolvendo coordenada (ou geocode em Salvador a partir do endereço do imóvel),  
**Quando** o cidadão consulta pela inscrição e pelo CNAE,  
**Então** o resultado inclui território e o veredito do motor para aquele ponto.

### CA-02 — Sem ponto
**Dado** o interruptor desligado, o serviço indisponível, a inscrição inexistente ou o imóvel sem coordenada utilizável,  
**Quando** o cidadão consulta,  
**Então** a resposta é 200, com aviso, sem coordenada inventada e sem análise territorial. O veredito locacional não é apresentado como se o imóvel tivesse sido localizado.

## Divergências da redação anterior
A redação anterior afirmava localizar o imóvel com maior precisão como fluxo único de sucesso. O código só faz a pipeline territorial quando obtém ponto; nos demais casos degrada para a consulta por CNAE e avisa que não houve análise territorial.

## Evidências
- `app/Http/Controllers/Portal/ConsultaViabilidadeController.php` (`inscricao`)
- `app/Services/Viabilidade/ConsultaViabilidadeService.php` (`consultarPorInscricao`)
- `app/Services/Realty/LocalizacaoInscricaoService.php`
- `app/Services/Realty/SedurSefazPropertyRegistryLookup.php`
- `app/Providers/AppServiceProvider.php` (binding do lookup)
- `app/Services/Decisao/DecisionTextCatalog.php` (aviso de inscrição indisponível)
- `app/Http/Requests/Portal/ConsultaViabilidadeInscricaoRequest.php`
- Parâmetro `features.cadastro_imobiliario` em `database/seeders/ParameterSeeder.php`

## Não evidenciado
- Veredito locacional do imóvel quando o cadastro não devolve ponto.
- Tratamento da indisponibilidade como erro HTTP nesta rota.
- Consulta por inscrição sem CNAE.
