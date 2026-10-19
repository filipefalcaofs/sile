# HU-170 — Registrar vistoria

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Encaminhar o processo à vistoria, preencher a ficha do vistoriador e concluí-la com parecer, devolvendo o processo ao analista de origem quando o encaminhamento registrou a volta.

## História de Usuário
**Como** analista, **quero** encaminhar o processo a um setor de vistoria. **Como** vistoriador com `preencher-ficha-vistoria`, **quero** preencher e concluir a ficha, **para** registrar o que foi visto em campo sem decidir a viabilidade.

## Atores e permissões
- Encaminhar e visualizar ficha existente: `analisar-processos`. Visualizar não cria nem edita. Sem ficha, a tela informa ausência.
- Fila `/gestao/vistorias`, abrir, salvar rascunho, concluir, polígono e anexos: `preencher-ficha-vistoria`.
- Download do anexo: `analisar-processos` ou `preencher-ficha-vistoria`.
- Distribuir processo que está em vistoria: o destinatário precisa ter `preencher-ficha-vistoria` (HU-080).
- No seeder, Analista Servidor, Analista Terceirizado, chefias e administrador têm `preencher-ficha-vistoria`. Os perfis "Analista Vistoriador" e "Vistoriador" nascem sem permissão até o administrador configurar.

## Fluxo evidenciado
1. O analista encaminha com setor de vistoria e motivo. O processo vai à caixa daquele setor, desatribuído, com eixo `vistoriar`. A origem fica registrada para o retorno. Erro de domínio volta como validação.
2. A consulta de vistorias lista processos em `vistoriar` ou `vistoriado`, ou que já tenham ficha, com situação derivada da ficha: a designar, em campo ou concluída. A consulta é auditada.
3. Abrir a ficha cria na primeira vez ou retoma. Se a ficha está atribuída a outro vistoriador, responde 403.
4. O rascunho é parcial. Ficha concluída recusa alteração. Só o vistoriador que abriu altera a ficha e os anexos.
5. Concluir exige parecer (obrigatório, mínimo 3, máximo 10000 caracteres). A ficha fica imutável. Se o processo está `vistoriar`, o eixo vai para `vistoriado`. Com referral de origem, volta ao setor e ao analista (`vistoriado` → `em_analise`). Sem referral, permanece `vistoriado` para retorno manual.
6. Anexos usam disco parametrizado e download autenticado, não URL pública. O polígono pode ser redesenhado e validado no rascunho.

## Regras de negócio evidenciadas
- Vistoria não defere nem indefere. A decisão continua na análise.
- Ficha concluída não é reaberta por novo rascunho.
- Abrir a ficha de análise não cria vistoria; só informa se já existe.

## Validações
- Encaminhamento: setor existente e motivo exigidos pelo form request.
- Parecer da conclusão: obrigatório, ao menos 3 caracteres.
- Anexo e polígono: regras dos form requests de vistoria. Ficha de outro autor: 403.

## Critérios de aceite
1. Sem `preencher-ficha-vistoria`, a fila e a edição respondem 403.
2. Encaminhamento por quem analisa coloca o processo em `vistoriar`, sem responsável, no setor informado.
3. Conclusão sem parecer não sela a ficha.
4. Conclusão com parecer sela a ficha e, quando há origem registrada, devolve o processo ao analista.
5. Terceiro vistoriador não edita ficha aberta por outro.

## Divergências da redação anterior
- Não havia redação anterior desta história. A vistoria não estava descrita como decisão da viabilidade; o código também não a trata como decisão.

## Evidências
- `app/Http/Controllers/Gestao/InspectionController.php`
- `app/Http/Controllers/Gestao/VistoriaConsultaController.php`
- `app/Services/Vistoria/InspectionService.php`
- `app/Services/Vistoria/EncaminharVistoriaService.php`
- `app/Http/Requests/Gestao/InspectionConcludeRequest.php`
- `routes/gestao.php`
- `database/seeders/RolesAndPermissionsSeeder.php`

## Não evidenciado
- Deferimento ou indeferimento automático ao concluir a vistoria.
- Perfis "Vistoriador" e "Analista Vistoriador" com a permissão já concedida pelo seeder. Eles nascem sem permissão.
