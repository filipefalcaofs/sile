# HU-149 — Consultar painel do cidadão

## Épica
**EP08 — Solicitação de Viabilidade**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 20/10/2026, com o código-fonte como referência.

## Objetivo
Dar ao cidadão uma visão do que está em andamento e do que depende dele, em uma única tela.

## História de Usuário
**Como** cidadão com conta no portal,
**quero** abrir o Meu painel e ver indicadores, o que precisa da minha atenção e minhas solicitações recentes,
**para que** eu saiba o que fazer em seguida sem procurar em cada solicitação.

## Atores e permissões
- Usuário autenticado no portal (`GET /portal/painel`).
- Em representação, o painel mostra os processos do representado e nunca os do procurador.

## Fluxo evidenciado
1. O cidadão abre «Meu painel». O sistema usa o usuário efetivo: o representado, quando há representação, ou o próprio usuário.
2. Os indicadores mostram «Convites para responder» (processos em pendência), «Em análise» e «Total de solicitações», calculados por contagem por situação.
3. O bloco «Precisa da sua atenção» lista os convites abertos (protocolo, descrição e prazo, com o botão Responder) e os rascunhos não protocolados (com o botão Retomar e protocolar). Sem itens, a tela diz «Nada precisa de você agora».
4. O bloco «O que você quer fazer?» traz os atalhos dos serviços do portal, quando os serviços estão habilitados.
5. O bloco «Minhas solicitações recentes» lista as últimas solicitações (5 por padrão, por configuração técnica), com protocolo, empresa e situação, e o link Ver todas. Sem solicitações, a tela diz «Você ainda não tem solicitações».
6. A tela só lê dados: não grava nada.

## Regras de negócio evidenciadas
- O painel é sempre do usuário efetivo; um procurador não vê, no painel do representado, processos que sejam só dele.
- Convite aberto é a pendência da análise ainda sem resposta.
- Rascunho é a solicitação ainda não protocolada.
- Os atalhos de serviços são um menu fixo do portal e não dependem dos dados do usuário.

## Critérios de aceite
### CA-01 — Indicadores do usuário
**Dado** há um cidadão com solicitações em análise e em pendência,
**Quando** abre o Meu painel,
**Então** os indicadores mostram as quantidades corretas por situação e o total.

### CA-02 — O que precisa de atenção
**Dado** há um cidadão com convite aberto e rascunho a protocolar,
**Quando** abre o Meu painel,
**Então** os dois aparecem no bloco de atenção, com os botões Responder e Retomar e protocolar.

### CA-03 — Representação
**Dado** há um procurador que atua em nome de um representado,
**Quando** abre o Meu painel,
**Então** o painel mostra os processos do representado e nenhum do procurador.

## Evidências
- `routes/portal.php`: `portal.dashboard` (`GET /portal/painel`).
- `app/Http/Controllers/Portal/DashboardController.php` e `app/Services/Painel/PainelCidadaoService.php`.
- `resources/js/pages/portal/dashboard.tsx`.
- `tests/Feature/Portal/PainelCidadaoTest.php`.

## Não evidenciado
- Filtros ou ordenação dos blocos do painel.
- Gráficos de evolução dos processos.

