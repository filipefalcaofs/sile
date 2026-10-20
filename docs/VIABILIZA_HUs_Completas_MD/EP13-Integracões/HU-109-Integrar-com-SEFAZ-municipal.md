# HU-109 — Integrar com SEFAZ municipal

## Épica
**EP13 — Integrações**

## Situação na implementação
- **Status:** Bloqueada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
O envio da viabilidade deferida à SEFAZ municipal está previsto no listener e barrado pelo binding: não há transmissão.

## História de Usuário
**Como** sistema, **quero** comunicar o deferimento à SEFAZ quando houver contrato homologado. Enquanto o binding for o indisponível, o evento fica registrado como bloqueado e a decisão não é desfeita.

## Atores e permissões
- `EnviarViabilidadeSefaz` escuta `ResultadoEmitido` e só tenta envio no deferimento. Indeferimento é ignorado e auditado, sem chamar o gateway.
- O binding de `SefazViabilidadeGateway` é `UnavailableSefazViabilidadeGateway`.

## Fluxo evidenciado
1. No deferimento, o listener chama `sendViabilidade`.
2. O gateway indisponível lança `SefazUnavailableException` e não monta sucesso.
3. O listener captura a exceção, audita a pendência com resultado `bloqueado` e não relança: a decisão permanece.
4. `sendEventoEscritorioVirtual` também lança a mesma exceção. Não há notificação simulada de escritório virtual à SEFAZ.
5. A consulta de inscrição imobiliária pelo BFF da SEDUR é outra integração. Não conta como envio da viabilidade.

## Regras de negócio evidenciadas
- Sem canal oficial não há payload de sucesso.
- Indisponibilidade da SEFAZ não invalida o deferimento já gravado.
- Indeferimento não é enviado por este listener.

## Validações
- Não há URL de envio de viabilidade em uso: o método não chega a HTTP.

## Critérios de aceite
- Deferimento gera auditoria de integração SEFAZ com resultado bloqueado, não sucesso.
- Nenhuma linha de notificação é marcada como enviada por este gateway.
- Indeferimento não chama o gateway.

## Divergências da redação anterior
O texto descrevia o envio da viabilidade deferida para continuidade do alvará. O código registra a pendência e não envia.

## Evidências
- `app/Services/Sefaz/UnavailableSefazViabilidadeGateway.php`
- `app/Listeners/EnviarViabilidadeSefaz.php`
- `app/Providers/AppServiceProvider.php` (binding de `SefazViabilidadeGateway`)

## Não evidenciado
- Cliente HTTP de envio de viabilidade ou de evento de escritório virtual à SEFAZ.
- Homologação com retorno real da SEFAZ.
