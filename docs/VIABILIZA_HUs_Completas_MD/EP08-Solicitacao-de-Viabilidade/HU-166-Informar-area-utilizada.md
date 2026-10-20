# HU-166 — Informar área utilizada

## Épica
**EP08 — Solicitação de Viabilidade**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Registrar a área utilizada do imóvel no rascunho e alertar quando ela excede a área do polígono além da tolerância configurada, sem impedir o salvamento.

## História de Usuário
**Como** requerente dono do rascunho,  
**quero** informar a área utilizada em metros quadrados,  
**para** que o processo carregue a área que a análise e o protocolo exigem.

## Atores e permissões
- Mesmo ator e mesma rota da HU-165: dono efetivo, status rascunho, `PUT portal/solicitacoes/{solicitacao}/imovel`.
- A área não tem endpoint próprio. É campo obrigatório do pedido do imóvel.

## Fluxo evidenciado
1. O pedido traz `used_area_m2` junto com o polígono.
2. O sistema mede a área do polígono (`PropertyGeometryWriter::polygonAreaSquareMeters`).
3. Se a área declarada supera a do polígono em percentual maior que `solicitacao.area_poligono.tolerancia_percentual` (padrão 10), grava auditoria `imovel-area-inconsistente` e devolve o flash `areaAlert` com área declarada, área do polígono, tolerância e divergência.
4. Área declarada menor ou dentro da tolerância não gera alerta.
5. O valor é persistido em `used_area_m2` e a simulação anterior é invalidada.
6. No protocolo, área ausente ou menor ou igual a zero bloqueia a protocolização, com a pendência "área utilizada".

## Regras de negócio evidenciadas
- A área é obrigatória neste passo, numérica e maior que zero.
- A comparação é com o próprio polígono desenhado, não com um lote oficial.
- Usar menos área que o polígono é aceito sem alerta.
- O alerta orienta e fica auditado. Não impede gravar o imóvel nem, por si só, protocolar.
- Sem polígono medível (área nula ou não positiva), não há alerta.

## Validações
- `used_area_m2`: obrigatório, numérico, maior que zero.
- Mensagens: área obrigatória, deve ser número, deve ser maior que zero.

## Critérios de aceite
- Dado um rascunho e uma área maior que zero, quando o dono salva o imóvel, então `used_area_m2` fica gravado.
- Dado área declarada acima da área do polígono além da tolerância, quando salva, então o registro permanece e a resposta traz o alerta, com trilha `imovel-area-inconsistente`.
- Dado área zero, negativa ou ausente, quando envia o passo, então a validação recusa.
- Dado rascunho sem área positiva, quando tenta protocolar, então `ProtocolarSolicitacaoService` bloqueia por dados mínimos, antes de gerar número.

## Divergências da redação anterior
A área não é uma etapa isolada com bloqueio por faixa da LOUOS. Ela entra no mesmo salvamento do imóvel. A divergência com o polígono alerta; não recusa o formulário.

## Evidências
- `App\Http\Controllers\Portal\SolicitacaoImovelController::buildAreaAlert`
- `App\Http\Requests\Portal\UpdateSolicitacaoImovelRequest` (`used_area_m2`)
- `App\Services\Solicitacao\ProtocolarSolicitacaoService::guardMinimumData`
- Parâmetro `solicitacao.area_poligono.tolerancia_percentual`

## Não evidenciado
- Teto ou faixa de área por CNAE ou zona neste passo.
- Bloqueio do salvamento ou do protocolo somente pela divergência área × polígono.
- Endpoint exclusivo de área, separado do imóvel.
