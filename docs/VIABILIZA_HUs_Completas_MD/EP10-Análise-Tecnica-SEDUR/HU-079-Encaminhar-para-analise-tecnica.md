# HU-079 — Encaminhar para análise técnica

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Levar à fila da análise humana o processo que o fluxo expresso não decide, com prazo da etapa de distribuição e pré-análise disparada depois do commit.

## História de Usuário
**Como** sistema, **quero** encaminhar o processo não decidido pelo expresso para a análise técnica, **para** que a SEDUR o distribua e analise com o motivo da queda registrado.

## Atores e permissões
- O encaminhamento automático não tem ator humano: `FluxoExpressoService::encaminharAnalise` transiciona o status e grava o setor de triagem.
- O envio manual da tela T06 e o override `forcar-transicao-analise` estão na HU-172. Esta história cobre o caminho do expresso.

## Fluxo evidenciado
1. Fora de `em_analise`, o serviço transiciona o processo para `em_analise`, grava `analysis_status` `para_distribuir`, etapa `distribuicao`, início da etapa e `analysis_due_at` calculado para essa etapa, e define `sector_id` com o setor de triagem.
2. A auditoria do expresso registra o evento `decisao` com resultado `analise` e o motivo.
3. Depois do commit, dispara `EncaminhadoParaAnalise`. O listener `PreAnalisarProcesso` chama a pré-análise (HU-140).
4. Se o processo já está `em_analise` e ninguém o assumiu, a reavaliação atualiza a queda, o motivo do encaminhamento e o rascunho intocado da ficha, sem abrir outra tramitação.

## Regras de negócio evidenciadas
- O expresso só encaminha a partir do próprio fluxo de decisão. O status canônico passa a `em_analise`.
- O prazo da fila nasce nesse encaminhamento, na etapa de distribuição.
- Processo já em análise e ainda sem analista não regride: a queda e a ficha intocada é que são atualizadas.
- A pré-análise é efeito do evento após o commit, não um passo dentro da mesma transação do encaminhamento.

## Validações
- O método só segue o ramo de reavaliação quando `status` já é `em_analise`.
- Setor de triagem ausente na reavaliação: se `sector_id` está nulo, o processo é depositado no setor de triagem atual.

## Critérios de aceite
1. Processo que o expresso não decide fica `em_analise`, com situação operacional `para_distribuir`, prazo de distribuição e setor de triagem.
2. O motivo da queda fica no encaminhamento e na auditoria.
3. O evento `EncaminhadoParaAnalise` só sai depois do commit e aciona a pré-análise.
4. Reavaliar um processo já em análise e não assumido atualiza motivo e rascunho intocado, sem duplicar a tramitação.

## Divergências da redação anterior
- A redação anterior descrevia um fluxo genérico de usuário autenticado. O código encaminha pelo serviço do expresso, sem tela nesta história.
- Não há, neste caminho, validação de permissão de perfil: o ator pode ser nulo.

## Evidências
- `app/Services/Expresso/FluxoExpressoService.php` (`encaminharAnalise`)
- `app/Events/EncaminhadoParaAnalise.php`
- `app/Listeners/PreAnalisarProcesso.php`

## Não evidenciado
- Escolha manual de setor ou de analista neste encaminhamento. A distribuição é a HU-080.
- Envio manual por protocolo e transição forçada do eixo operacional (HU-172).
