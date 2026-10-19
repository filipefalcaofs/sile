# HU-136 — Encaminhar para malha fina

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Encaminhar processo já decidido pelo fluxo expresso à malha fina, sem mudar o status canônico, e concluir o encaminhamento aberto com um resultado de análise.

## História de Usuário
**Como** usuário com `encaminhar-malha-fina`, **quero** enviar um ou vários processos à malha fina com motivo, **para** que o revisor com `analisar-malha-fina` os veja na caixa e, se também puder concluir a análise, registre o resultado.

## Atores e permissões
- Encaminhar (um ou lote): `encaminhar-malha-fina`. No seeder, está no conjunto de análise e no administrador.
- Ver a caixa: `analisar-malha-fina` (chefias e administrador no seeder). Quem só encaminha não opera a caixa.
- Concluir na caixa: `analisar-malha-fina` e `concluir-analise`. Administrador, no seeder, vê a caixa e não conclui.

## Fluxo evidenciado
1. O encaminhamento humano exige decisão com `flow` `expresso` e `analysis_status` ainda nulo. Fora isso, o item falha com a mensagem de que a malha fina só se aplica a processo decidido pelo expresso.
2. Motivo vazio ou só com espaços recusa o lote inteiro antes de gravar.
3. Cada item liga `in_fine_mesh`, cria `fine_mesh_referrals` e audita, sem transicionar o status. Falha de um processo não cancela os outros. O mesmo motivo vale para o lote. Setor é opcional.
4. Há caminho de sistema (`encaminharSistema`) com autor nulo, usado quando um detector encaminha. Também não muda o status. Esse caminho não passa pela checagem de fluxo expresso do encaminhamento humano.
5. A caixa lista `in_fine_mesh` (abertas) e baixas já registradas (concluídas), com os filtros das outras caixas, e audita a consulta.
6. Concluir só segue se existe encaminhamento com `resolved_at` nulo. Aplica `ConclusaoAnaliseService` e então baixa os abertos com a observação e o resultado. Sem aberto, volta aviso e não muda o status.

## Regras de negócio evidenciadas
- Malha fina humana é ortogonal ao status: alcança processo já deferido ou indeferido pelo expresso, desde que ainda não esteja no eixo de análise humana.
- Repetir o encaminhamento humano cria nova linha de referral.
- A conclusão da caixa reutiliza os mesmos resultados da conclusão de análise (deferido, indeferido, desativado, cassado, revogado), com as regras da HU-089, inclusive `concluir-analise`.

## Validações
- `request_ids` existentes e `motivo` obrigatório, texto, no máximo 2000 caracteres.
- Conclusão: resultado obrigatório; observação conforme o form request da malha fina.

## Critérios de aceite
1. Sem `encaminhar-malha-fina`, o encaminhamento responde 403.
2. Processo do expresso, fora da análise humana, com motivo, entra na caixa sem mudar o status canônico.
3. Processo já com `analysis_status` ou sem decisão expressa não é encaminhado pelo caminho humano, e o lote segue nos demais.
4. Motivo em branco não grava nenhum item.
5. Concluir sem `concluir-analise` responde 403. Concluir sem encaminhamento aberto não altera o processo.

## Divergências da redação anterior
- A redação anterior não restringia a malha fina ao processo do expresso nem separava encaminhar de analisar e de concluir. O código separa as três permissões, e concluir reusa a permissão de concluir a análise.

## Evidências
- `app/Http/Controllers/Gestao/MalhaFinaController.php`
- `app/Http/Controllers/Gestao/MalhaFinaCaixaController.php`
- `app/Services/Analise/MalhaFinaService.php`
- `routes/gestao.php`
- `database/seeders/RolesAndPermissionsSeeder.php`

## Não evidenciado
- Mudança de status canônico no ato de encaminhar.
- Conclusão da malha fina por quem só tem `encaminhar-malha-fina` ou só `analisar-malha-fina`.
