# Fichas na tramitação: ficha recebida como base, encaminhamento só com ficha finalizada e pareceres no histórico — design

Data: 26/10/2026. Continua a HU.183 (`2026-10-23-ficha-por-analista-design.md`) com as regras que a cliente confirmou em 26/10.

## Regras confirmadas pela cliente

1. Cada usuário tem a própria ficha finalizada, sem modificar a dos anteriores.
2. Quem recebe o processo (João) vê a ficha de quem o encaminhou (Adriana) e usa esses dados como base da própria ficha, podendo alterar.
3. O histórico das fichas é mantido. Toda ficha finalizada fica na tramitação como link clicável para o conteúdo exato daquela ficha.
4. O encaminhamento à chefia só é permitido depois de a ficha ser finalizada, com mensagem ao usuário.
5. Depois do encaminhamento, a ficha encaminhada fica registrada na tramitação.
6. Os pareceres de vistoria, de encaminhamento e de conclusão ficam visíveis no histórico.

## O que já existe e fica

- Ficha por analista, com revisões append-only e revisão finalizada imutável (HU.183, commit `43ee7ca5c`).
- Eventos `ficha-salvar` e `ficha-finalizar` na tramitação, com link `?revisao=N&somente_leitura=1`, que abre a revisão gravada.
- A decisão guarda a ficha usada (`viability_decisions.analysis_record_id`).

## Mudanças

1. **Base da ficha de quem recebe.** `AnalysisRecordService::novaRevisao` sem ficha própria do usuário:
   - devolve a cópia de conclusão sem dono, para quem pode concluir;
   - senão, copia a última ficha **finalizada** do processo para o usuário (`based_on_record_id`);
   - sem ficha finalizada, segue como antes: base disponível ou base do motor.

   `current` mostra, a quem não tem ficha própria, a última ficha finalizada do processo (só leitura) antes da base sem dono. Isso substitui a regra provisória da HU.183, em que o segundo analista partia da base do motor. A chave `viabiliza.analise.ficha.conclusao_parte_da_ficha_do_analista` sai: a cliente confirmou que se parte da ficha anterior.
2. **Encaminhar à chefia.** `ProcessoController::atualizarStatusAnalise` para `para_conclusao` exige que a última ficha do próprio usuário esteja finalizada; senão devolve o erro "Finalize a sua ficha de análise antes de encaminhar à chefia.". Quem usa `forcar-transicao-analise` não é barrado (correção administrativa). Depois da transição, chama `guardarParaConclusao`, que deixa a cópia sem dono para quem conclui e grava o evento `ficha-encaminhada` (sujeito = ficha encaminhada, com a revisão).
   Tela: o botão "Encaminhar para Chefia" continua visível; com a ficha não finalizada, a janela avisa e o botão de confirmar fica desabilitado.
3. **Tramitação** (`TramitacaoProcessoService`):
   - "Ficha de análise finalizada": "Revisão N, de {autor}. Parecer: {parecer}" e link com rótulo próprio para a revisão.
   - "Encaminhado à chefia" (evento `ficha-encaminhada`): parecer de encaminhamento = parecer da ficha encaminhada, quem foi indicado e link para a ficha. A linha "Análise: Para conclusão" do mesmo momento sai (uma ação, uma linha).
   - "Análise: Análise concluída": acrescenta o parecer de conclusão, que é o texto da decisão (`viability_decisions.reason`) quando o motivo não o contém, e o link para a ficha usada na decisão.
   - "Ficha de vistoria concluída": parecer da vistoria (`inspections.parecer`) e link para a ficha de vistoria.
   - "Encaminhado à vistoria": motivo do encaminhamento.
   - Itens ganham `link_label`: o link aparece separado do texto, com rótulo próprio. Sem rótulo, o texto inteiro continua sendo o link, como hoje.

## Interpretação a confirmar

- **Parecer de encaminhamento.** A janela "Encaminhar para Chefia" não tem campo de parecer. Interpretei como o parecer da ficha finalizada que foi encaminhada. Mostro também o motivo do encaminhamento à vistoria. Se a cliente quiser um texto próprio digitado no encaminhamento, falta um campo novo na janela.
- **Forçar transição.** O administrador com `forcar-transicao-analise` continua podendo mover para "Para conclusão" sem ficha finalizada.

## Testes

`tests/Feature/Analise/FichasTramitacaoTest.php`: o fluxo Adriana → João (base, independência, preservação), os links das duas fichas finalizadas na tramitação, o encaminhamento recusado sem ficha finalizada (com mensagem) e aceito depois, o registro do encaminhamento e os três pareceres no histórico.
