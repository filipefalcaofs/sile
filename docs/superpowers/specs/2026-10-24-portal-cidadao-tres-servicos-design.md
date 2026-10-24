# Portal do cidadão do Viabiliza com três serviços: renovar, consultar processo e responder convite

Data: 24/10/2026. Branch: `feat/portal-cidadao-tres-servicos`, a partir de `teste/portal-renovacao-legada` (entrada pelo Portal de Serviços + renovação de TVL legada).

## Intenção

Todo processo de viabilidade começa na JUCEB e chega ao Viabiliza pelo REGIN. O portal do cidadão deixa de ser porta de entrada de pedido novo e fica com três serviços:

1. **Renovar** uma viabilidade provisória, do Viabiliza ou do Simplifica (sistema anterior).
2. **Consultar processo** pelo número, para ver a situação e a tramitação.
3. **Responder convite** do processo de que a pessoa é requerente.

O resto sai do portal: consulta prévia de viabilidade, nova solicitação (assistente, rascunho, protocolo), Minhas solicitações como lista, Minhas empresas (cadastro, CNAEs, vínculo), procurações e representação "em nome de", e o histórico de consultas prévias.

## Metas

- Menu, painel e landing mostram só os três serviços, mais Notificações, Meus dados e Histórico de acessos.
- As rotas dos serviços retirados deixam de responder (404), mas o código fica, atrás de uma chave de configuração, para poder religar sem deploy de código.
- Renovação sem "Minhas empresas": o cidadão informa um CNPJ ou um número de viabilidade, e o Viabiliza procura no próprio banco e no Simplifica.
- Consulta aberta por número, para qualquer cidadão logado, sem dados pessoais.
- Convite: só o requerente responde e só ele recebe o aviso no painel.

## Não metas

- Consultar pela tela do Viabiliza processos que só existem no Simplifica. A consulta procura nos processos do Viabiliza.
- Mudar a análise, a decisão ou a emissão da TVL da renovação. Tudo isso continua como já está.
- Apagar controllers, páginas ou testes dos serviços retirados. Eles ficam desligados, não removidos.
- Mudar a API `GET /api_integracao/portal-servicos/processos`, que a Minha Área usa. Ela continua listando os processos ligados ao CPF (requerente, solicitante declarado e sócio do REGIN).

## Anti-metas (o que seria fracasso mesmo funcionando)

- A consulta aberta mostrar CPF, e-mail, telefone ou nome de pessoa, o texto do convite, anexos ou o parecer interno.
- Um sócio do REGIN, ou qualquer pessoa que achou o processo pelo número, conseguir responder o convite, mesmo pelo endereço direto.
- A renovação de TVL legada criar uma empresa duplicada para um CNPJ que já existe no Viabiliza.

## Decisões do usuário

1. **Quem pode renovar:** qualquer cidadão logado pelo Portal, sem conferir ligação do CPF com a empresa.
2. **Onde procurar na renovação:** primeiro no banco do Viabiliza, depois na API do Simplifica. O resultado junta as duas fontes e mostra a origem.
3. **Depois da renovação de TVL do Simplifica:** a renovação é gravada no Viabiliza e daí em diante tudo passa por ele. A empresa é cadastrada no Viabiliza com os dados da origem, ou reaproveitada se o CNPJ já existir. Quem protocolou vira requerente.
4. **Consulta:** um campo de busca para o número do processo, da viabilidade (TVL) ou do BAP. Qualquer cidadão logado consulta qualquer processo. O resultado mostra número do processo e da viabilidade, a empresa, a situação atual e a tramitação com datas, sem dados pessoais e sem o texto interno.
5. **Convite:** só o requerente responde. No processo do REGIN, o CPF que a JUCEB manda como solicitante conta como requerente. Quando vier só o CNPJ, ninguém responde pelo portal, e o convite segue o caminho de hoje até vencer o prazo. O sócio do REGIN fica só com a consulta.
6. **Aviso no painel:** ao entrar, se o CPF logado é requerente de processo com convite pendente, o primeiro painel já mostra o aviso com o processo, o prazo e o botão Responder.
7. **Telas:** dentro da UI/UX do próprio portal do Viabiliza, que já existe, seguindo o esboço aprovado (painel com aviso + três cartões; consulta com campo único e tramitação; renovação com busca única).

## Como fica cada parte

### Chave dos serviços retirados

- Chave nova `features.portal_servicos_extras` (env `SILE_PORTAL_SERVICOS_EXTRAS`, padrão `false`; ver decisão 2).
- Middleware `portal.extras`: com a chave desligada, responde 404 nas rotas retiradas. Ficam atrás dele: `viabilidade` (consulta prévia, página e endpoints JSON), `viabilidade/historico`, `procuracoes*`, `representacao` (POST), `empresas*`, `cnaes`, `solicitacoes` (lista), `solicitacoes/nova`, `solicitacoes/{s}/editar`, `solicitacoes` (POST), `atividades`, `imovel`, `documentos` (upload e remoção), `simular`, `protocolar` e `solicitacoes/{s}` (DELETE, cancelar).
- Continuam: `painel`, `notificacoes*`, `conta*`, `acessos`, `representacao` (DELETE, para quem já atua em nome de alguém conseguir sair), `renovacao*`, a consulta nova, os convites, e o detalhe do processo do requerente (`solicitacoes/{s}`, `comprovante`, `pendencias`, `comunicacoes`, download de documento), que é para onde a renovação leva depois do protocolo.
- O `phpunit.xml` liga a chave, para os testes dos serviços retirados continuarem valendo. Os testes novos desligam a chave explicitamente.

### Menu, painel e landing

- `resources/js/navigation/portal-nav.ts`: grupo Serviços com Renovar viabilidade, Consultar processo e Responder convite. Ações do painel e da landing: as mesmas três. Saem Consultar viabilidade, Minhas solicitações, Minhas empresas, Solicitar viabilidade, Gerenciar empresas e Procurações.
- O contador de convites (badge) no item Responder convite vem de uma prop compartilhada, com o total de convites abertos dentro do prazo do requerente.
- Painel: o bloco "atenção" passa a listar só convites que o usuário pode responder (requerente ou solicitante declarado) e dentro do prazo, no topo, com o processo, a empresa, o prazo e o botão Responder. Os indicadores e as solicitações recentes continuam.

### Renovar viabilidade

- Tela `portal/renovacao`: um campo para CNPJ ou número da viabilidade, e o resultado em cartões com a origem ("Viabiliza" ou "Sistema anterior"), a situação, a validade e o botão Renovar quando elegível (ou o motivo quando não).
- Busca por CNPJ: TVLs deferidas com número do Viabiliza das empresas com esse CNPJ, mais as provisórias do Simplifica para o CNPJ (`provisoriasPorCnpj`).
- Busca por número: com 7 dígitos ou no formato de processo do Simplifica, procura no Viabiliza pelo número da TVL e no Simplifica pelo número (`tvlPorNumero` ou `tvlsDoProcesso`).
- TVL do Simplifica: o CNPJ da renovação é o gravado na TVL. Sem CNPJ gravado, vale o CNPJ informado na busca, conferido pela regra de titularidade que já existe (cadastro do CNPJ e endereço da inscrição). Sem CNPJ gravado e com busca por número, a TVL aparece com o motivo "Busque pelo CNPJ da empresa para renovar esta viabilidade.".
- Rotas da TVL legada passam a ser por CNPJ e número: `renovacao/legado/{cnpj}/{numero}` (GET assistente, POST protocolo). A conferência de titularidade e a elegibilidade continuam as mesmas, com uma empresa em memória quando o CNPJ ainda não existe no Viabiliza.
- No protocolo da TVL legada, a empresa é a do Viabiliza com aquele CNPJ. Não existindo, é criada com `source = legado`, razão social da TVL (ou da consulta de CNPJ da SEDUR quando a TVL não traz), e endereço do imóvel. Isso acontece dentro da transação do protocolo.
- Renovação de TVL do Viabiliza: deixa de exigir que o usuário atue pela empresa.
- Sai a consulta de CNPJ na SEDUR da tela de renovação (`renovacao/consultar-cnpj`) e o modal "Renovar viabilidade do sistema anterior": a busca única cobre os dois.

### Consultar processo

- Rota nova `GET portal/consulta?numero=` (nome `portal.consulta`), autenticada, com throttle próprio (`consulta-processo`, por usuário, 30 por minuto).
- Número aceito: o do processo (`protocol_number`), o do BAP (`external_reference`) ou o da viabilidade (`decision.tvl_product_number`). Maiúsculas e espaços normalizados. Mais de um processo para o mesmo número: vale o que casa pelo número do processo; depois, o mais recente.
- Resultado: número do processo, número da viabilidade (quando há), tipo de serviço, empresa (razão social e CNPJ; sem endereço, que a consulta pública por link também omite), data do protocolo, situação (rótulo público) e tramitação pública (a mesma da consulta pública por link assinado: `timeline->build(publico: true)`).
- Botão "Responder convite" só quando o usuário pode responder e há convite aberto dentro do prazo. Leva à tela de pendências que já existe.
- Não encontrado ou formato inválido: a mesma mensagem, com os formatos aceitos.
- Cada consulta encontrada é auditada (RN-002) com o usuário e o processo.

### Responder convite

- Política `view` do processo: requerente ou CPF vinculado como **solicitante** (`viability_request_cpfs.papel = solicitante`). O sócio do REGIN deixa de ver o detalhe e de responder; ele usa a consulta aberta.
- A tela de pendências e o POST de resposta já usam `view`, então passam a valer a regra nova.
- Tela nova `GET portal/convites` (nome `portal.convites.index`): lista os convites abertos dentro do prazo dos processos que o usuário pode responder, com o botão Responder. Sem convites, a tela diz isso.
- Escopo novo no modelo `respondivelPor(userId, cpf)`, usado pelo painel, pela lista de convites, pelo badge e pela consulta.

### Entrada pelo Portal de Serviços

- Destino `renovacao`: igual.
- Destino `processo` (Detalhar): abre `portal/consulta?numero=` com o número recebido, para qualquer cidadão.
- Destinos `nova` e `autonomo`: abrem o painel com o aviso "Os pedidos de viabilidade começam na JUCEB. Pelo portal você renova viabilidade, consulta processo e responde convite.".

## Restrições

- Laravel 13, Inertia v3 com React 19, Tailwind 4, PHPUnit 12. Componentes e padrões do portal que já existem.
- Testes PostGIS precisam de `DB_TEST_PORT=5444`.
- Sem migration nova: `companies.source` é texto; entra o caso `Legado = 'legado'` ("Sistema anterior") no enum `CompanySource`.

## Deixado para quem implementa

- Nome dos componentes React e divisão dos arquivos.
- Texto exato das mensagens, mantendo o tom das que já existem no portal.
- Se a lista de convites e o aviso do painel usam um componente em comum.

## Decisões minhas (revisão da spec)

O usuário pediu trabalho autônomo; estas ficaram por minha conta.

1. **Interruptores que já existem continuam.** Os três serviços seguem atrás de `features.portal_servicos` (o interruptor geral do gestor em Parâmetros), e a renovação segue também atrás de `portal.solicitacao`. A chave nova só desliga o que saiu.
2. **Onde mora a chave nova:** `features.portal_servicos_extras`, lida por `Settings::enabled('portal_servicos_extras')` como as outras chaves de recurso. Ela tem padrão em `config/viabiliza.php` (env `SILE_PORTAL_SERVICOS_EXTRAS`, padrão `false`) e pode ser sobreposta por um parâmetro com a mesma chave, sem deploy. Não entra no catálogo de parâmetros semeado, para não mexer na contagem conferida pelos testes. O `phpunit.xml` liga a chave pelo env.
3. **Sócio no painel:** indicadores e processos recentes continuam com `visivelPara`, mas cada processo leva a `portal/consulta?numero=<protocolo>`, que funciona para qualquer pessoa. Assim o sócio nunca cai num 403. Aviso, badge e lista de convites usam `respondivelPor`.
4. **Contato no assistente de renovação:** pré-preenchido com o e-mail e o telefone do próprio usuário logado, não com os da empresa. Quem digita um CNPJ não vê contato de terceiros.
5. **Representação "em nome de":** vale o usuário efetivo, como já fazem a política e a renovação. Não se cria representação nova pelo portal, mas quem já está em uma continua coerente.
6. **CNPJ repetido no Viabiliza:** não acontece, porque `companies.cnpj` é único. O protocolo da TVL legada reaproveita a empresa com aquele CNPJ.
7. **Contato da empresa no protocolo:** o protocolo da renovação gravava na empresa o e-mail e o telefone digitados. Com qualquer cidadão podendo renovar, o contato digitado passa a só preencher o que a empresa ainda não tem, e nunca troca o contato dela.

## Revisão de 24/10 (tarde): consulta também no Simplifica

Decisão do usuário: a consulta de processo procura no Viabiliza e também no Simplifica, porque a Minha Área manda as linhas do Simplifica para essa consulta.

- Ordem: primeiro os processos do Viabiliza (como já está). Sem resultado, a busca vai ao Simplifica:
  - número no formato de processo do Simplifica (`0000000000-00000000/0000`): `GET /v1/processos/{numero}`;
  - 7 dígitos: a TVL do Simplifica (`GET /v1/tvls/{n}`) e, dela, o processo.
- Resultado do Simplifica, no mesmo formato da tela: número do processo, viabilidades (números das TVLs), empresa (razão social e CNPJ da TVL), data de criação, situação (nome do status do Simplifica) e tramitação (data, título e status de cada item do histórico). O texto das mensagens do histórico não aparece, porque pode trazer conteúdo dirigido ao requerente. Selo "Sistema anterior". Sem botão de convite.
- Simplifica fora do ar: aviso "Não foi possível consultar o sistema anterior agora", sem esconder um resultado do Viabiliza.
- A consulta do Simplifica também é auditada (`consulta-processo-legado`, com o número).
