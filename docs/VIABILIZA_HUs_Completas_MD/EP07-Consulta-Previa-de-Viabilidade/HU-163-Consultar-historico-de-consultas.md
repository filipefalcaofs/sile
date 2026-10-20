# HU-163 — Consultar histórico de consultas

## Épica
**EP07 — Consulta Prévia de Viabilidade**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Listar as consultas prévias bem-sucedidas do próprio usuário e reabrir o snapshot gravado.

## História de Usuário
**Como** cidadão autenticado,  
**quero** ver minhas consultas anteriores,  
**para** reabrir o resultado que foi gravado na época, com a entrada e o veredito.

## Atores e permissões
- `GET /portal/viabilidade/historico` exige sessão do portal, e-mail verificado e termo LGPD aceito.
- A lista é só do usuário da sessão (`ViabilityQuery::forUser`). Não usa o representado da representação.
- Não está no menu lateral. O link "Ver minhas consultas anteriores" aparece na página de consulta quando há usuário logado.
- Anônimo não grava nem consulta este histórico. A consulta anônima continua só na auditoria feita pelo serviço.

## Fluxo evidenciado
1. Consulta pública com sucesso e usuário autenticado grava `entry_type`, `input`, `result` (snapshot completo), `rules_versions`, `resultado` do veredito locacional, IP e data.
2. Erros (endereço não localizado, fora de Salvador, geocodificador indisponível) não gravam histórico.
3. A listagem ordena da mais recente (`created_at`, depois `id`) e pagina com `ui.companies.per_page` (padrão 15).
4. Cada item traz tipo de entrada (endereço, CNAE ou inscrição), rótulo do veredito, entrada, data e o resultado completo.
5. A tela abre o mesmo componente de resultado da consulta, em modal, a partir do snapshot.
6. Abrir a lista audita `viabilidade` / `consulta-historico` com a quantidade de itens.

## Regras de negócio evidenciadas
- O snapshot é o JSON da consulta na data em que foi feita, inclusive versões de regra. A tela não recalcula o motor ao reabrir.
- Veredito nulo não ganha rótulo.
- O histórico pessoal não inclui consultas feitas antes do login nem as de outro usuário.

## Validações
- Não há filtro de busca nesta tela. A paginação usa o parâmetro de empresas, não um parâmetro próprio de histórico.

## Critérios de aceite
### CA-01 — Gravar só sucesso autenticado
**Dado** usuário logado e uma consulta que retornou resultado,  
**Quando** a consulta termina,  
**Então** surge um item do próprio usuário com entrada, resultado e versões. Consulta anônima ou com erro HTTP de localização não cria item.

### CA-02 — Listar e reabrir
**Dado** itens do usuário,  
**Quando** ele abre o histórico,  
**Então** vê os seus, do mais recente ao mais antigo, e pode reabrir o snapshot sem nova execução do motor.

### CA-03 — Escopo
**Dado** consultas de outra pessoa,  
**Quando** o usuário abre o histórico,  
**Então** elas não aparecem.

## Divergências da redação anterior
A redação anterior dizia exibir consultas prévias para reaproveitar informações, sem exigir login nem distinguir erro de sucesso. O código só persiste consulta bem-sucedida de usuário autenticado e não coloca a lista no menu.

## Evidências
- `app/Http/Controllers/Portal/HistoricoConsultaController.php`
- `app/Http/Controllers/Portal/ConsultaViabilidadeController.php` (`registrarHistorico`)
- `routes/portal.php` (`portal.viabilidade.historico`)
- `resources/js/pages/portal/viabilidade/historico.tsx`
- `resources/js/pages/portal/viabilidade/consulta.tsx`

## Não evidenciado
- Histórico do anônimo.
- Histórico do representado no lugar do usuário da sessão.
- Item de menu próprio para o histórico.
- Recálculo da viabilidade ao reabrir um item.
