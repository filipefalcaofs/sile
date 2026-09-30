# Ficha de análise e regras — ajustes do relatório de 30/09/2026

**Data:** 2026-09-30
**Status:** aprovado para planejamento
**Origem:** relatório de teste de Lisa Santos, "Regras + ficha de análise", ambiente `http://144.22.212.3:8082`
**HUs:** HU-135 (ficha), HU-136 (malha fina, já atualizada), HU-140 (pré-análise), HU-142 (precedentes), HU-080 (distribuir), HU-039 (Quadro 10)

## Fora deste ajuste

A frase do parecer ("Diante do enquadramento acima…") já é dado administrável. A cliente altera em **Configuração → Textos decisórios** (`/gestao/textos-decisao`), grupo Justificativa fundamentada. Este ajuste não muda o catálogo.

A redação da HU-136 já foi atualizada. Este ajuste implementa a regra no sistema.

## Problema

A ficha e a tela do processo misturam fato operacional, diagnóstico técnico e campo que a cliente retirou. O motor, ao indeferir, cita o grupo nR1 sem o subgrupo, embora no Quadro 10 parte dos nR1 seja permitida na mesma zona.

## Regras fechadas com a cliente

1. **Motivo de Análise** traz só o que derrubou o processo para análise humana, em linguagem operacional. O analista não edita.
2. **Malha fina** existe para o fluxo expresso: significa que aquele processo precisa de análise humana. Processo que já está em análise humana não oferece a ação.
3. O parecer continua usando o texto do catálogo e passa a mostrar, antes da frase, o enquadramento de cada CNAE.

## Ficha de análise

Tela: `/gestao/processos/{id}/ficha`. Arquivo principal: `resources/js/pages/gestao/ficha-analise/show.tsx`.

### Resumo (item 01)

Quando o processo tem restrição de endereço (loteamento com TAC, logradouro vedado, zona especial), o card Resumo exibe essa restrição como fato do sistema, junto da síntese. A restrição não depende da IA: se a IA não mencionar, o fato continua visível.

Texto operacional, o mesmo do motivo. Exemplo: "Solicitação cadastrada no Loteamento Itaigara".

### Motivo de Análise (itens 02, 09 e o aviso azul do 10)

`MotivoAnaliseComposer` hoje concatena a queda técnica (`camada geográfica indisponível`, gatilho, dimensão) com contexto que não é o motivo (tipo de imóvel, quadro, tendência).

A lista passa a conter somente linhas operacionais:

| Situação | Linha |
|---|---|
| Loteamento Itaigara fora da allow-list | Solicitação cadastrada no Loteamento Itaigara |
| Loteamento Vela Branca | Solicitação cadastrada no Loteamento Vela Branca |
| ZEM, ZPAM, ABM trechos 5 e 6, ZUE | O motivo já definido na especificação territorial SEDUR |
| CNAE sem enquadramento na planilha | CNAE {código} — não enquadrado — para enquadramento do analista |
| Área declarada que pesa na queda | Área utilizada declarada: {valor} m² |

Somem da lista: mensagem de camada indisponível, nome interno de gatilho, "dimensão decisiva", tipo de imóvel, permissão do Quadro 10, tendência do motor. Esses fatos continuam nos blocos próprios (camadas, enquadramento, classificação de risco).

O aviso "Atividade sem enquadramento no Quadro 7 — segue para análise técnica", hoje sob a decisão do CNAE, vai para este campo.

### Camadas do imóvel (item 03)

Em `resources/js/components/geo/camadas-imovel.tsx`:

- A linha "Logradouros próximos" sai da ficha. O cálculo em `CamadasIncidentesService` permanece para quem já o consome fora da ficha.
- A zona deixa de aparecer "Não identificado" quando o processo já tem zona (a do polígono ou a do território do motor). A lista usa essa zona.
- Entra a linha Via, com a via já exibida no card do polígono.

### Polígono e confirmações (item 06)

O card "Confirmações do imóvel" (área pública e "Endereço correto?") entra no card do polígono, abaixo de Zona e Via. Deixa de ser um card separado.

### Ações — malha fina (item 07)

Na ficha de processo em análise humana, "Encaminhar para a malha fina" não é renderizado.

`MalhaFinaService::encaminhar` e `encaminharLote` recusam processo que não seja do fluxo expresso já decidido sem analista (deferido ou indeferido pelo expresso). Processo em análise humana recebe recusa explícita, não falha silenciosa.

A consulta de processos mantém o lote só para processos elegíveis. Os demais não entram na seleção.

### Precedentes (item 08)

O painel de precedentes, além do histórico e da estatística do CNAE na zona, mostra a quantidade de processos do mesmo imóvel com status Em análise.

### Enquadramento por atividade (itens 10 e 13)

- Some o campo Justificativa de cada CNAE, inclusive a sugestão de minuta por CNAE nesse bloco.
- Some a descrição do card ("Pré-preenchido pelo sistema… Justificativa e parecer são do analista.").
- Quando o motor não enquadrou a atividade, o select "Enquadramento (LOUOS + TLL)" lista todas as linhas da planilha matriz vigentes para aquele CNAE (`TratamentoEnquadramento` da versão vigente). Lista vazia com CNAE presente na planilha é defeito. CNAE ausente da planilha mantém o select vazio e o motivo "não enquadrado" no campo Motivo de Análise.

### Tramitação na ficha (item 11)

O card Tramitação (tabela data, setor, usuário, status e o botão de extrato) sai da ficha. A tramitação continua na tela do processo.

### Classificação de risco (item 12)

O card hoje chamado "Justificativa" passa a se chamar **Classificação de Risco e Fundamentação Legal**. O conteúdo (tabela LOUOS, TLL, valor, risco, fundamentação) permanece. O card fica imediatamente antes de Condicionantes.

### Parecer (item 14, só a exibição)

Cada CNAE no parecer mostra o enquadramento (grupo, subgrupo, código LOUOS e o quadro que decidiu) e, em seguida, a frase do catálogo. A frase não é hardcoded de novo.

## Tela do processo

Tela: `/gestao/processos/{id}`. Arquivo: `resources/js/pages/gestao/processos/show.tsx`.

### Motivo do encaminhamento (item 05, amarelo)

O campo hoje rotulado "Motivo da contingência", quando o processo está em análise, exibe a razão do encaminhamento no mesmo texto operacional do Motivo de Análise. Exemplo: "Loteamento Vela Branca / Fluxo semi-expresso". O valor interno `simulacao_protocolo` não é o que o analista lê.

### Encaminhar para (item 05, vermelho)

Ao escolher o status Encaminhado para, o formulário pede setor e usuário antes de gravar. Sem os dois, a transição não ocorre. O processo permanece na caixa do setor, com o usuário indicado, como já está na spec de status de análise (2026-07-14).

### Quem distribuiu (item 04)

Na aba Tramitação, a entrada de distribuição/atribuição mostra:

```text
Distribuição: {nome de quem atribuiu}
Atribuído: {nome do analista}
{data e hora}
```

A transição já tem `actor_user_id`. A projeção da timeline em `ProcessoController::show` passa a incluir o ator e, na atribuição, o analista designado.

## Motor — subgrupo no Quadro 10 (item 15)

Processo 3: o desfecho disse que o CNAE 1521-1/00 classificou-se no grupo nR1 e que esse grupo é proibido na ZPR 1 pelo Quadro 10. No quadro, nR1-01 a nR1-13 não têm o mesmo S/N na ZPR 1.

Regra:

- O indeferimento expresso cita o subgrupo (`nR1-04`, por exemplo), não só o grupo.
- Grupo com subgrupos mistos na zona (uns permitidos, outros não) não autoriza indeferir pelo grupo.
- Sem subgrupo resolvido, o processo vai para análise. O motivo diz que o subgrupo do CNAE não foi identificado e por isso o Quadro 10 não foi aplicado. Não há indeferimento automático.

O template `louos.template.nao_permitido` continua no catálogo. A interpolação passa a preencher o subgrupo quando ele existir.

## Testes

Cada regra acima nasce num teste que falha antes da alteração. Feature tests PHPUnit nos serviços (`MotivoAnaliseComposer`, `MalhaFinaService`, `LouosEnquadramentoService`) e smoke da ficha/processo para o que é só apresentação. `vendor/bin/pint --dirty --format agent` nos PHP alterados.

## Fora de escopo

- Reescrever os textos do catálogo.
- Apagar o cálculo de logradouros próximos do serviço geográfico.
- Nova tela de configuração.
- Cadastro de corretiva no SIG.
