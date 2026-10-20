# HU-174 — Renovar viabilidade

## Épica
**EP08 — Solicitação de Viabilidade**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Protocolar a renovação de uma viabilidade locacional provisória já deferida no portal, encaminhando o processo à análise semi-expressa.

## História de Usuário
**Como** cidadão com vínculo ativo com a empresa,  
**quero** renovar uma viabilidade provisória deferida neste portal,  
**para** protocolar a prorrogação sem reeditar endereço e atividades.

## Atores e permissões
- Portal autenticado, e-mail verificado, termo LGPD aceito e middleware `portal.solicitacao`.
- Com `viabiliza.features.portal_solicitacao` desligado, a jornada redireciona ao painel (ou responde 503 em JSON) com "O serviço de solicitação e renovação de viabilidade está temporariamente indisponível. Tente novamente mais tarde."
- A empresa da solicitação de origem precisa estar entre as empresas com vínculo ativo do usuário efetivo. Caso contrário, 403.
- Em representação, lista, consulta e protocolo usam o representado.
- Menu "Renovar viabilidade" (`/portal/renovacao`) e card público na landing. A rota em si exige login.

## Fluxo evidenciado
1. `GET /portal/renovacao` lista solicitações deferidas das empresas do usuário efetivo que tenham número de TVL na decisão. Para cada uma, `RenovacaoElegibilidadeService` informa se pode renovar e o motivo quando não pode.
2. Opcionalmente, `POST /portal/renovacao/consultar-cnpj` consulta o CNPJ na SEDUR (`SedurContatoClient`) e devolve razão social, nome fantasia, telefone, endereço e as viabilidades deferidas com TVL desse CNPJ que pertençam às empresas do usuário.
3. `GET /portal/renovacao/{origem}` só abre o assistente se a origem for elegível. Se não for, volta à lista com o motivo em erro.
4. O assistente tem quatro passos: confirmar a viabilidade (número, empresa, CNPJ, endereço, atividades, validade e tipo, somente leitura), contato, declarações vigentes do tipo de serviço `renovacao-tvl` e revisão.
5. `POST /portal/renovacao/{origem}` protocola. Copia área, inscrição, endereço e polígono da origem, anexa os mesmos CNAEs, grava o aceite das declarações obrigatórias, gera número de protocolo, passa a `protocolada` e em seguida a `em_analise`, com categoria `semi_expresso`.
6. Fora da transação, completa o território como no protocolo comum e envia à análise. Não há deferimento automático.
7. Redireciona para o detalhe da solicitação criada, com "Renovação protocolada. O processo foi encaminhado para análise."
8. E-mail e telefone informados, quando preenchidos, atualizam a empresa. O celular vai só para a auditoria `solicitacoes` / `renovacao_protocolada`.

## Regras de negócio evidenciadas
- Sem decisão deferida ou sem número de TVL: "Não há viabilidade vinculada a este CNPJ."
- TVL que não seja provisório: "Viabilidade definitiva não pode ser renovada."
- Renovação da mesma origem já deferida dentro de `renovacao.intervalo_meses` (padrão 3, mínimo 1): "Esta viabilidade foi renovada há menos de {meses} meses."
- Já existe renovação dessa origem em `protocolada`, `em_analise` ou `em_pendencia`: "Para cadastrar novo processo de renovação, aguarde a conclusão da análise ou solicite desistência do processo em análise."
- Tipo de serviço `renovacao-tvl` ausente: "O tipo de serviço Renovação de viabilidade locacional não está cadastrado."
- Declaração obrigatória vigente sem aceite: "Marque todas as declarações de responsabilidade para protocolar a renovação."
- Endereço e atividades da origem não são editáveis neste assistente.
- Setor de triagem (`analise.setor_triagem_id`) é aplicado se o setor existir e estiver ativo. Sem parâmetro ou com setor inativo, o processo segue sem caixa.

## Validações
`ProtocolarRenovacaoRequest`:
- `email`: opcional, e-mail, até 255.
- `phone`: obrigatório, texto, até 20. Mensagem: "Informe o telefone."
- `mobile`: opcional, texto, até 20.
- `declaration_ids`: presente, array de inteiros.
Consulta de CNPJ na renovação: 14 dígitos; fora disso, 422 "Informe um CNPJ numérico de 14 dígitos." CNPJ ausente na SEDUR: 404. SEDUR indisponível: 503.

## Critérios de aceite
### CA-01 — Protocolar provisória elegível
**Dado** TVL provisório deferido, com número, de empresa com vínculo ativo, fora do intervalo de bloqueio e sem renovação em aberto, e as declarações obrigatórias marcadas,  
**Quando** o cidadão protocola,  
**Então** nasce uma solicitação de renovação protocolada, em análise semi-expressa, ligada à origem, e o cidadão cai no detalhe do processo.

### CA-02 — Bloqueios de elegibilidade
**Dado** TVL definitivo, renovação recente dentro do intervalo, ou renovação ainda em análise,  
**Quando** o cidadão tenta abrir ou protocolar,  
**Então** o sistema recusa com o motivo correspondente e não cria outra solicitação.

### CA-03 — Empresa de outro usuário
**Dado** uma solicitação cuja empresa não tem vínculo ativo com o usuário efetivo,  
**Quando** ele abre ou protocola a renovação,  
**Então** a resposta é 403.

## Divergências da redação anterior
Não havia HU anterior desta renovação na pasta do EP08. O fluxo está no `RenovacaoController` e nos serviços de elegibilidade e protocolo, como jornada do portal de solicitação.

## Evidências
- `app/Http/Controllers/Portal/RenovacaoController.php`
- `app/Services/Renovacao/RenovacaoElegibilidadeService.php`
- `app/Services/Renovacao/RenovacaoProtocoloService.php`
- `app/Http/Requests/Portal/ProtocolarRenovacaoRequest.php`
- `app/Http/Middleware/EnsurePortalSolicitacaoEnabled.php`
- `routes/portal.php` (`portal.renovacao.*`)
- `resources/js/pages/portal/renovacao/index.tsx`
- `resources/js/pages/portal/renovacao/wizard.tsx`
- `resources/js/navigation/portal-nav.ts`
- Parâmetro `renovacao.intervalo_meses` em `database/seeders/ParameterSeeder.php`

## Não evidenciado
- Renovação de TVL cuja solicitação não esteja deferida neste sistema com número de TVL.
- Deferimento automático da renovação.
- Edição de endereço, área ou CNAEs no assistente.
- Filtro por origem SIGS na montagem da lista. A tela informa que a viabilidade do SIGS continua no SIGS. A lista reúne solicitações deferidas, com número de TVL, das empresas do usuário efetivo.
