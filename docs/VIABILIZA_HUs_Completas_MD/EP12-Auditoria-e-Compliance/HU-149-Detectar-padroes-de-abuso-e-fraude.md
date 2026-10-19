# HU-149 — Detectar padrões de abuso e fraude

## Épica
**EP12 — Auditoria e Compliance**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Gerar alertas determinísticos de padrão suspeito e permitir que um gestor confirme ou descarte, sem punir o processo.

## História de Usuário
**Como** gestor, **quero** revisar alertas de abuso, **para** encaminhar atenção à malha fina onde o padrão se repetiu, sem indeferir automaticamente.

## Atores e permissões
- O ciclo roda pelo comando `abuso:detectar` (`AbuseDetectionService`). Com `features.deteccao_abuso` desligado, o ciclo não grava nada.
- A tela `/gestao/abuso` exige `gerenciar-alertas-abuso`.

## Fluxo evidenciado
1. Na janela `abuso.janela_dias` (padrão 30), os detectores percorrem solicitações reais.
2. Regras presentes: `volume_cnpj` (acima de `abuso.volume_cnpj.limite`, padrão 5), `volume_contador` (acima de `abuso.volume_contador.limite`, padrão 20, pelo usuário que criou), `poligono_repetido` (mesmo anel em endereços distintos), `inscricao_atividades_incompativeis` (mesma inscrição com CNAEs primários distintos; o mínimo no código é 2, proxy até haver matriz oficial) e `condicionante_evasao` (requerente só com respostas que não acionam análise; mínimo 3). Resposta de condicionante ausente não conta como evasão.
3. Alerta aberto com o mesmo `rule_key` e fingerprint não é duplicado.
4. Severidade igual ou acima de `abuso.severidade_malha_fina` (padrão alta), e com processo vinculado, encaminha à malha fina por `MalhaFinaService::encaminharSistema`. O status do processo não muda.
5. Na tela, o gestor filtra por regra, severidade, status e período, vê efetividade (confirmados ÷ gerados; nula se não houver alerta) e confirma ou descarta com justificativa obrigatória. Só o alerta muda.

## Regras de negócio evidenciadas
- Confirmar ou descartar não indefere, não cassa e não altera a malha fina já criada.
- A efetividade ignora o filtro da lista: conta todos os alertas.
- As chaves de filtro saem dos alertas gravados, não de uma lista fixa na tela.

## Validações
- Justificativa obrigatória, texto, no máximo 2000 caracteres (`ResolverAbuseAlertRequest`).
- Data de filtro só no formato `YYYY-MM-DD`; outro valor é ignorado.
- Paginação 10, 20, 50 ou 100.

## Critérios de aceite
- Com o recurso desligado, o comando devolve executado falso e não cria alerta.
- Padrão acima do limite cria um alerta aberto; repetir o ciclo não duplica o aberto.
- Confirmar grava status, responsável, data e justificativa, e o status do processo permanece o mesmo.
- Sem alertas, a taxa de efetividade é nula.

## Divergências da redação anterior
O texto falava em fraude genérica e decisão automática. O código alerta e, no limiar, só encaminha à malha fina. A matriz oficial de CNAEs incompatíveis não está no detector: o proxy é a simultaneidade de atividades.

## Evidências
- `app/Services/Abuso/AbuseDetectionService.php`
- `app/Services/Abuso/Detectors/`
- `app/Http/Controllers/Gestao/AbusoController.php`
- `app/Console/Commands/AbusoDetectarCommand.php`
- `routes/gestao.php` (grupo `abuso`)

## Não evidenciado
- Bloqueio automático de protocolo por suspeita.
- Modelo estatístico ou provedor de IA neste ciclo (a varredura preditiva é outra HU).
