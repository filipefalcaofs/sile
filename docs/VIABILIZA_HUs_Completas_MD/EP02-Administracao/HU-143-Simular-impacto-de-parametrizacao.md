# HU-143 — Simular impacto de parametrização

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Simular um rascunho do Quadro 10 ou do Quadro 11A no motor de enquadramento e, em seguida, publicá-lo com quatro olhos.

## História de Usuário
**Como** mantenedor com `manter-louos`,
**quero** simular o rascunho de um quadro e ver quantos cenários mudariam de resultado,
**para** publicar a versão só depois de olhar o relatório.

## Atores e permissões
- Simular e publicar exigem `manter-louos`. Menu Regras, item Simulação de regras (`/gestao/louos/simulacao`).
- A publicação recusa o autor do rascunho como publicador.

## Fluxo evidenciado
1. A página lista rascunhos de `louos_quadro10` e `louos_quadro11a` (quadro, domínio, rótulo, versão, autor e data) e a amostra padrão lida de `config('viabiliza.louos.sandbox.amostra_padrao')`, 50 se a chave não existir.
2. Simular reexecuta `LouosEnquadramentoService` dentro de uma transação que é sempre revertida. O relatório volta na mesma página: domínio, versão do rascunho, amostra usada, total, quantos mudariam, distribuição e divergências. Atualizar a página cai no GET e o relatório some.
3. A amostra, quando o pedido não envia `amostra`, usa a config. Os cenários são CNAEs ativos, limitados à amostra, com área fixa de 50 m² e uma zona: a zona da versão vigente do Quadro 10 com mais permissões `permitido`. O território desses cenários é sintético. Sem zona vigente ou sem permissões, a amostra fica vazia.
4. Publicar o rascunho passa por `LouosDraftService`, com a mesma trava de zonas do Quadro 10 e a auditoria de publicação do rascunho. Autor e publicador precisam ser pessoas diferentes.
5. A simulação grava um evento de auditoria `simulacao` fora da transação revertida. Essa gravação não publica versão.

## Regras de negócio evidenciadas
- A simulação não deixa escrita do motor persistida.
- Só rascunho existente do Quadro 10 ou do Quadro 11A entra na simulação e na publicação desta tela.
- O tamanho da amostra do pedido é inteiro mínimo 1. O padrão 50 está em configuração da aplicação, não no catálogo de parâmetros da HU-014.
- A área de todos os cenários é 50 m², constante do serviço.
- O relatório informa `amostra_usada`.

## Validações
- `quadro` obrigatório, `quadro10` ou `quadro11a`.
- `versao_rascunho` obrigatória e existente em `rule_versions.version` naquele domínio, com status rascunho. Mensagem: «Não há um rascunho deste Quadro da LOUOS com esse identificador.»
- `amostra` opcional, inteiro, mínimo 1.

## Critérios de aceite
### CA-01 — Simular sem persistir o motor
**Dado** um rascunho do Quadro 10 ou do 11A e um mantenedor com `manter-louos`,
**Quando** simula,
**Então** recebe total, quantos mudariam, distribuição, divergências e a amostra usada, e a transação do motor é revertida.

### CA-02 — Amostra explícita
**Dado** o mesmo rascunho,
**Quando** a simulação envia `amostra` igual a 10,
**Então** o relatório registra `amostra_usada` 10, limitada aos CNAEs ativos encontrados.

### CA-03 — Rascunho inexistente
**Dado** uma versão que não é rascunho daquele quadro,
**Quando** o mantenedor tenta simular,
**Então** a validação recusa `versao_rascunho`.

### CA-04 — Publicar com outro usuário
**Dado** um rascunho simulável,
**Quando** o autor tenta publicar pela simulação,
**Então** a publicação é recusada. Outro usuário com `manter-louos` publica pelo `LouosDraftService`.

## Divergências da redação anterior
A redação anterior pedia simulação contra N processos reais numa janela temporal, amostra parametrizável no catálogo da HU-014, diff da regra na auditoria da publicação e quatro olhos para enquadramento, risco e gatilhos, com a lista de domínios parametrizável. O código simula CNAEs ativos, área fixa de 50 m² e uma zona, em território sintético. A amostra padrão é config, não parâmetro de tela. A tela só cobre rascunho do Quadro 10 e do Quadro 11A. Risco municipal, planilha de tratamento e gatilhos não entram neste sandbox.

## Evidências
- `routes/gestao.php`: `louos.sandbox.index`, `sandbox.simular`, `sandbox.publicar`.
- `app/Http/Controllers/Gestao/LouosSandboxController.php`.
- `app/Http/Requests/Gestao/SimulateLouosRequest.php`.
- `app/Services/Louos/LouosSandboxSimulationService.php`: `montarCenarios`, `areaRepresentativa` (50.0), `territorioComZona`.
- `resources/js/navigation/gestao-nav.ts`: Simulação de regras.

## Não evidenciado
- Amostra de processos protocolados ou janela de datas.
- Simulação de risco municipal, planilha de tratamento, gatilhos ou regras territoriais nesta tela.
- Parâmetro de catálogo para o tamanho da amostra. O padrão está em `config('viabiliza.louos.sandbox.amostra_padrao')`.
- Relatório que sobreviva a um refresh: o POST devolve a página com o relatório; o GET não o guarda.
