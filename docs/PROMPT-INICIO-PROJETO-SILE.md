# Prompt — Iniciar o projeto SILE pelas HUs

Copie e cole o bloco abaixo em uma nova sessão do agente para iniciar o projeto.

---

Inicie o projeto SILE usando o GSD como framework de gestão.

## Contexto

- O SILE é o Sistema de Licenciamento Eletrônico da SEDUR: gestão da viabilidade locacional de atividades econômicas, com automação, motor de regras da LOUOS, classificação de risco, georreferenciamento, fluxo expresso, análise técnica, integrações (REDESIM, Receita Federal, GIS), auditoria e indicadores.
- O repositório já contém o esqueleto da aplicação: Laravel 13 + Inertia v3 + React 19 + Tailwind 4 + PHPUnit. Não há código de domínio ainda (apenas `User` e `Welcome.tsx`).
- A fonte de verdade dos requisitos são as 131 Histórias de Usuário em `docs/SILE_HUs_Completas_MD/`, organizadas em 15 épicas (EP01 a EP15). O índice completo está em `docs/SILE_HUs_Completas_MD/README-CATALOGO-HUs-SILE.md`.
- Cada HU contém: objetivo, fluxos principal e alternativos, regras de negócio (RN), critérios de aceite em BDD (CA), campos, permissões, exceções, auditoria, dependências e prioridade.
- Leia também `docs/ANALISE-HUs-REUNIAO-SEDUR.md`: confronto das HUs com a reunião realizada com a SEDUR e com as fontes oficiais (LOUOS Lei 9.148/2016, Portal Simplifica). Contém pontos pendentes de confirmação e as HUs propostas (HU-071/HU-072 — DAM, HU-110 — SEFAZ, HU-111 — migração do legado).

## O que fazer

1. Leia `docs/SILE_HUs_Completas_MD/README-CATALOGO-HUs-SILE.md` para visão geral das épicas e HUs.
2. Execute `/gsd-new-project` para criar a estrutura `.planning/` (PROJECT.md, ROADMAP.md, STATE.md, REQUIREMENTS.md, config.json):
   - Use as HUs como fonte dos requisitos. Em REQUIREMENTS.md, rastreie cada requisito pelo ID da HU (HU-001 a HU-131). Marque HU-071, HU-072 e HU-111 como pendentes de confirmação de escopo com a SEDUR.
   - Estruture o roadmap por épicas, respeitando as dependências entre elas.
3. Após criar o roadmap, inicie o ciclo da primeira fase: `/gsd-plan-phase 1` e em seguida `/gsd-execute-phase 1`.

## Ordem sugerida para o roadmap (respeitar dependências)

1. **EP01 — Identidade, Acesso e Segurança** (HU-001 a HU-010): fundação de autenticação, perfis e permissões. Tudo depende disso.
2. **EP02 — Administração** (parcial — HU-011 a HU-014): cadastros base (CNAEs, usuários, perfis, parâmetros) necessários para as demais épicas; os mantenedores de quadros/condicionantes/risco (HU-015 a HU-020) entram junto com EP05/EP06.
3. **EP03 — Cadastro Empresarial** (HU-021 a HU-028).
4. **EP04 — Georreferenciamento e Território** (HU-029 a HU-037).
5. **EP05 — Motor de Regras da LOUOS** (HU-038 a HU-046) e **EP06 — Classificação de Risco** (HU-047 a HU-053), incluindo os mantenedores correspondentes do EP02 (Quadros 7/10/11/11A, condicionantes, risco).
6. **EP07 — Consulta Prévia de Viabilidade** (HU-054 a HU-060): consome EP04, EP05 e EP06.
7. **EP08 — Solicitação de Viabilidade** (HU-061 a HU-070; HU-071 e HU-072 se o escopo de pagamento/DAM for confirmado).
8. **EP09 — Fluxo Expresso** (HU-073 a HU-078).
9. **EP10 — Análise Técnica SEDUR** (HU-079 a HU-089) e **EP11 — Pendências e Comunicação** (HU-090 a HU-096).
10. **EP12 — Auditoria e Compliance** (HU-097 a HU-102) — atenção: o registro de auditoria é transversal (RN-002 de todas as HUs) e deve ser implementado como infraestrutura desde o EP01; esta fase cobre consulta, exportação e LGPD.
11. **EP13 — Integrações** (HU-103 a HU-109, HU-110 SEFAZ via API, HU-111 migração do legado) — isole os serviços externos atrás de interfaces (contratos), mas implemente cada adaptador contra a API real em homologação assim que documentação, credenciais e acesso forem disponibilizados pela SEDUR. Se uma integração ainda não tem acesso disponível, a feature que depende dela fica explicitamente bloqueada no ROADMAP.md/STATE.md — não implemente adaptador falso para "destravar".
12. **EP14 — Inteligência Artificial** (HU-112 a HU-121).
13. **EP15 — Relatórios e Indicadores** (HU-122 a HU-131).

Você pode ajustar essa ordem se identificar dependências melhores durante o planejamento — justifique no ROADMAP.md.

## Regras de execução (obrigatórias)

- **Superpowers**: brainstorming antes de implementar, TDD estrito (Red-Green-Refactor), debugging sistemático, verificação com evidência fresca antes de afirmar conclusão.
- **Critérios de aceite BDD**: cada CA das HUs vira teste (feature test PHPUnit). Nenhuma HU é considerada concluída sem seus CAs cobertos por testes passando.
- **Auditoria transversal**: implemente desde o início um mecanismo de trilha de auditoria (usuário, data/hora, origem, ação, resultado, versão de regras) reutilizável por todas as HUs.
- **Convenções do repositório**: seguir AGENTS.md e as rules do projeto — Laravel way (`php artisan make:`), Eloquent API Resources, factories nos testes, `vendor/bin/pint --dirty --format agent` após alterar PHP, `search-docs` do Boost antes de mudanças de código.
- **Idioma**: UI, mensagens, commits e documentação em português brasileiro. Código (variáveis, classes, métodos) em inglês.
- **Commits**: conventional commits em português (`feat:`, `fix:`, `test:`, etc.).
- **Sem features de fachada**: NUNCA entregar funcionalidade que finge funcionar mas não funciona — botões que não executam nada, telas que exibem resultado simulado, fluxos que "dão certo" sem processar de verdade, adaptadores falsos fingindo que a integração ocorreu. Toda feature entregue deve executar sua lógica real de ponta a ponta. Se uma dependência externa ainda não está disponível (API da SEFAZ, GIS etc.), a feature que depende dela fica bloqueada e registrada como pendência no ROADMAP.md/STATE.md — não simulada. Fakes/stubs são permitidos exclusivamente dentro da suíte de testes automatizados.
- **Dados de seed**: dados fictícios em seeds são aceitáveis para desenvolvimento. Preferir dados oficiais quando forem públicos e disponíveis — tabela CNAE (IBGE/CONCLA), quadros da LOUOS (Lei 9.148/2016) — e substituir os fictícios pelas planilhas oficiais da SEDUR assim que entregues. O motor de regras processa esses dados de verdade em qualquer caso: o que muda é a carga, nunca a lógica.
- **Parametrização máxima (HU-014)**: o administrador NUNCA deve depender de desenvolvedor para mudar o que pode ser parâmetro. Nenhum valor de negócio hardcoded (prazos, limiares, taxas, textos, e-mails, termos) — tudo em parâmetros administráveis por interface, com validação, histórico auditado e efeito sem deploy. Toda funcionalidade acoplável (integrações, fluxo expresso, IA, canais de notificação, aprovação automática) nasce com feature toggle administrável; desativação degrada de forma controlada (pendência/aviso explícito, nunca falha silenciosa). Credenciais criptografadas, com botão de teste de conexão nas telas de integração. Ao implementar qualquer HU, pergunte-se: "o que aqui o admin pode querer mudar?" — e transforme em parâmetro.
- **Ambiente funcional**: toda integração externa só é considerada concluída após validação contra o ambiente de homologação real do serviço (SEFAZ, REDESIM/integrador, GIS), com evidência da chamada real registrada.

## Critério de pronto por fase

- Todos os CAs das HUs da fase cobertos por testes passando (`php artisan test --compact`).
- Funcionalidades da fase operando de ponta a ponta na aplicação real (navegável no browser), executando a lógica de verdade — nada de resultado simulado nem etapas "em construção" disfarçadas de prontas.
- Integrações da fase validadas contra homologação real, quando houver.
- Revisão de parametrização: nenhum valor de negócio hardcoded introduzido na fase; funcionalidades acopláveis com toggle administrável.
- Pint sem pendências.
- ROADMAP.md e STATE.md atualizados via `/gsd-progress`.

Comece agora pelo passo 1.
