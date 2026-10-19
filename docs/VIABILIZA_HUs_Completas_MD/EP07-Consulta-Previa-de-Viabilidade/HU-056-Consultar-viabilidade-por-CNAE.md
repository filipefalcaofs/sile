# HU-056 — Consultar viabilidade por CNAE

## Épica
**EP07 — Consulta Prévia de Viabilidade**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Consultar risco e enquadramento de uma atividade sem avaliar o local.

## História de Usuário
**Como** cidadão, com ou sem login,  
**quero** informar o CNAE e, se quiser, a área,  
**para** ver risco e enquadramento da atividade sabendo que o local não foi avaliado.

## Atores e permissões
- Rota pública `POST /portal/viabilidade/cnae`, mesmo throttle das outras consultas (padrão 20 por minuto).
- Aba "CNAE" na página pública "Consultar viabilidade".

## Fluxo evidenciado
1. O cidadão informa CNAE e área opcional. Não há endereço nem inscrição.
2. `consultarPorCnae` classifica o risco e enquadra com território nulo. Não chama o geocodificador.
3. A resposta inclui o aviso: "Consulta por CNAE não avalia o local: o veredito locacional depende do endereço/zona. Para a viabilidade locacional, consulte por endereço."
4. O veredito locacional é o do motor sem zona (pendente). Território e restrições vêm nulos. Não há mapa.
5. Perguntas da planilha do CNAE podem ser reenviadas em `respostas`, como nas outras entradas.
6. Auditoria `viabilidade` / `consulta`. Histórico pessoal só com usuário autenticado.

## Regras de negócio evidenciadas
- Esta entrada não produz análise territorial.
- A área alimenta o enquadramento da planilha. Sem área, o serviço usa 0.
- O controller desta rota não recebe tipo de imóvel nem categoria de empresa; esses argumentos existem no serviço e ficam nulos na chamada pública.

## Validações
`ConsultaViabilidadeCnaeRequest`:
- `cnae`: obrigatório, texto, até 14 caracteres.
- `area`: opcional, numérica, de 0 a 9999999.
- `respostas`: array opcional, com `RespostaPlanilhaValida`.

## Critérios de aceite
### CA-01 — Resultado sem local
**Dado** um CNAE informado,  
**Quando** o cidadão consulta pela aba CNAE,  
**Então** recebe risco, enquadramento e o aviso de que a consulta não avalia o local, sem território e sem mapa.

### CA-02 — CNAE ausente
**Dado** a requisição sem CNAE,  
**Quando** é enviada,  
**Então** a validação recusa com "Informe o CNAE da atividade pretendida."

## Divergências da redação anterior
A redação anterior dizia que o cidadão informa o CNAE para entender restrições antes de protocolar. O código não lista restrições urbanísticas nesta entrada: território é nulo e o aviso declara que o local não foi avaliado.

## Evidências
- `app/Http/Controllers/Portal/ConsultaViabilidadeController.php` (`cnae`)
- `app/Services/Viabilidade/ConsultaViabilidadeService.php` (`consultarPorCnae`)
- `app/Http/Requests/Portal/ConsultaViabilidadeCnaeRequest.php`
- `app/Services/Decisao/DecisionTextCatalog.php` (`consulta.aviso.cnae_sem_local`)
- `resources/js/pages/portal/viabilidade/consulta.tsx`

## Não evidenciado
- Restrições de zona, via ou lote na consulta só por CNAE.
- Abertura de solicitação a partir desta consulta.
