# HU-157 — Consultar viabilidade por endereço

## Épica
**EP07 — Consulta Prévia de Viabilidade**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Consultar a viabilidade locacional de uma atividade a partir de um endereço em Salvador, com geocodificação e território reais.

## História de Usuário
**Como** cidadão, com ou sem login,  
**quero** informar endereço, CNAE e, se quiser, a área,  
**para** ver o veredito locacional, o risco e o território daquele ponto.

## Atores e permissões
- Rota pública: `GET /portal/viabilidade` e `POST /portal/viabilidade/endereco`. Login não é exigido.
- Logado, a página usa o shell do portal e o middleware de representação. Anônimo usa o shell público.
- Menu "Consultar viabilidade" e card público na landing.
- Os POST têm throttle `consulta-viabilidade`: padrão de 20 por minuto por usuário ou por IP (`viabiliza.seguranca.throttle.consulta_viabilidade.por_minuto`). A página em si não tem esse throttle.
- O controller envia `consultaEnabled: true`. Não há interruptor `features.consulta_viabilidade` no catálogo de parâmetros (a migração o retirou).

## Fluxo evidenciado
1. Na consulta, o cidadão escolhe "Endereço", informa endereço (mínimo 3 caracteres), CNAE e área opcional em m².
2. Se a resposta trouxer perguntas da planilha do CNAE, a tela as exibe e reenvia as respostas.
3. `ConsultaViabilidadeService::consultarPorEndereco` geocodifica o endereço.
4. Fora de Salvador: 422, "O endereço está fora do município de Salvador. A consulta de viabilidade abrange apenas Salvador."
5. Endereço não localizado: 404, "Endereço não localizado. Revise o endereço ou posicione a consulta por CNAE."
6. Geocodificador indisponível: 503, "Serviço de geocodificação indisponível no momento. Tente novamente em instantes."
7. Ponto em Salvador: identifica o território, enquadra pela LOUOS e classifica o risco. O veredito locacional é o resultado do motor LOUOS, não um cálculo à parte. Sem zona identificada, o veredito já vem pendente do motor e a consulta acrescenta o aviso de zona pendente.
8. O JSON inclui entrada, geocode, território, enquadramento, risco, restrições, veredito, fundamentação, avisos, versões e as perguntas do ramo. A tela mostra o mapa quando há geocode e o componente de resultado.
9. Sucesso é auditado em `viabilidade` / `consulta`. Erros de endereço ou de geocodificador não geram histórico pessoal.
10. Usuário autenticado: a consulta bem-sucedida grava snapshot em `viability_queries`. Anônimo não gera histórico pessoal.

## Regras de negócio evidenciadas
- CNAE é obrigatório em toda consulta por endereço. A viabilidade é de uma atividade num local.
- Endereço não localizado e serviço indisponível não viram resultado de viabilidade.
- A área, quando informada, entra no enquadramento. Sem área, o serviço usa 0.
- O CNAE é normalizado para dígitos. Com 7 dígitos, a resposta também traz o formato `0000-0/00`.

## Validações
`ConsultaViabilidadeEnderecoRequest`:
- `endereco`: obrigatório, texto, 3 a 255 caracteres, com espaços das pontas removidos.
- `cnae`: obrigatório, texto, até 14 caracteres.
- `area`: opcional, numérica, de 0 a 9999999.
- `respostas`: array opcional; cada valor passa por `RespostaPlanilhaValida`.

## Critérios de aceite
### CA-01 — Consulta com ponto em Salvador
**Dado** endereço geocodificado em Salvador e CNAE informado,  
**Quando** o cidadão consulta,  
**Então** recebe veredito do motor LOUOS, risco, território e, se autenticado, um item de histórico.

### CA-02 — Endereço inválido para a consulta
**Dado** endereço fora de Salvador, não localizado ou geocodificador indisponível,  
**Quando** o cidadão consulta,  
**Então** a resposta é 422, 404 ou 503, com a mensagem correspondente, sem resultado simulado e sem histórico.

## Divergências da redação anterior
A redação anterior pedia só "simulação por endereço" e um fluxo genérico com GIS e REDESIM. O código exige CNAE junto do endereço, recusa ponto fora de Salvador e propaga o veredito do motor, sem decidir à parte.

## Evidências
- `app/Http/Controllers/Portal/ConsultaViabilidadeController.php` (`endereco`)
- `app/Services/Viabilidade/ConsultaViabilidadeService.php`
- `app/Http/Requests/Portal/ConsultaViabilidadeEnderecoRequest.php`
- `app/Services/Geo/ForaDeSalvadorException.php`
- `routes/portal.php`
- `resources/js/pages/portal/viabilidade/consulta.tsx`
- `resources/js/navigation/portal-nav.ts`

## Não evidenciado
- Consulta por endereço sem CNAE.
- Interruptor de parâmetro que desligue esta página.
- Resultado locacional inventado quando o geocodificador falha.
