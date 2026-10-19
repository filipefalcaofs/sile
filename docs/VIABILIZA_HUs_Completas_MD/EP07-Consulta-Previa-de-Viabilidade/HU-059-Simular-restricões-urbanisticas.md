# HU-059 — Simular restrições urbanísticas

## Épica
**EP07 — Consulta Prévia de Viabilidade**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Mostrar, quando a consulta tem ponto, as restrições e as dimensões territoriais identificadas nesse ponto.

## História de Usuário
**Como** cidadão que consulta por endereço ou por inscrição com ponto resolvido,  
**quero** ver bairro, via, zona, lote e restrições daquele ponto,  
**para** saber o que a identificação territorial devolveu.

## Atores e permissões
- Não há rota separada. `restricoes` e `territorio` vêm no JSON da consulta quando `TerritoryService::identify` rodou.
- Acesso público, com o throttle da consulta.

## Fluxo evidenciado
1. Consulta por endereço com ponto em Salvador, ou por inscrição com ponto, chama `TerritoryService::identify`.
2. O território inclui bairro, via, zona, lote e restrições (`status`, itens com nome e propriedades, motivo, versão da camada).
3. A tela só renderiza o card "Território" se `territorio` não for nulo. Lista bairro, via mais próxima, zona urbanística e lote. Em restrições:
   - `identificado` com itens: lista os nomes;
   - `indisponivel`: selo e o motivo, ou "Base pendente de disponibilização pela SEDUR.";
   - demais casos: "Nenhuma restrição neste ponto".
4. Se a zona não vier com status `identificado`, a consulta acrescenta o aviso "Veredito locacional pendente: zona urbanística pendente da base oficial (SEDUR)." O veredito em si já vem pendente do motor.
5. Consulta só por CNAE, e inscrição degradada sem ponto, não identificam território. `restricoes` sai nulo e o card não aparece.

## Regras de negócio evidenciadas
- Restrição urbanística nesta consulta depende de ponto. Sem ponto, não há lista de restrições.
- Camada de restrição indisponível é exibida como indisponível, sem lista inventada.
- A descrição do card na tela diz que zona e lote ficam indisponíveis enquanto a base estiver pendente. O serviço exibe o status que a identificação devolve (`identificado` ou outro). O aviso de zona pendente só entra quando o status da zona não é `identificado`.

## Validações
- As da consulta por endereço ou por inscrição. Não há campo próprio de restrição.

## Critérios de aceite
### CA-01 — Restrições com território
**Dado** uma consulta com ponto e território identificado,  
**Quando** o resultado é exibido,  
**Então** o card de território mostra as dimensões e as restrições conforme o status devolvido (itens, indisponível ou nenhuma).

### CA-02 — Sem ponto
**Dado** consulta só por CNAE, ou inscrição sem ponto,  
**Quando** o resultado volta,  
**Então** não há card de território nem lista de restrições urbanísticas.

## Divergências da redação anterior
A redação anterior tratava as restrições como simulação sempre disponível para orientar o cidadão. O código só as inclui quando há território; a consulta por CNAE não as traz.

## Evidências
- `app/Services/Viabilidade/ConsultaViabilidadeService.php` (`consultarPorPonto`)
- `app/Services/Viabilidade/ConsultaViabilidadeResult.php`
- `app/Services/Geo/TerritoryService.php`
- `app/Services/Geo/TerritoryResult.php`
- `resources/js/components/viabilidade/resultado-viabilidade.tsx`
- `app/Services/Decisao/DecisionTextCatalog.php` (`consulta.aviso.zona_pendente`)

## Não evidenciado
- Simulação de restrições sem ponto.
- Rota exclusiva de restrições urbanísticas.
- Lista de restrições quando a camada responde indisponível.
