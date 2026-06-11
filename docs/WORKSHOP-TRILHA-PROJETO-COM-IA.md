# Do papo com o cliente ao sistema rodando — startando projetos com IA

Material-base para workshop. Tom de conversa, mas denso: teoria e prática juntas. Cada bloco é um momento da apresentação. Caso real usado do início ao fim: o SILE (sistema de licenciamento da SEDUR, Prefeitura de Salvador).

Convenção didática do material: o termo técnico é o protagonista (em inglês quando for o uso de mercado), sempre **explicado de verdade** na primeira aparição; a analogia vem depois, como reforço — nunca no lugar do conceito.

---

## Abertura — papo reto

Deixa eu te contar como a gente tira um sistema do papel hoje.

Uma reunião de 30 minutos com o cliente. Uma gravação. No fim do dia: 131 histórias de usuário documentadas, um roadmap com 15 épicos e um projeto pronto para iniciar. Não em semanas. Em horas.

"Ah, então a IA faz tudo?" Não. E esse é o ponto do workshop inteiro.

**IA não substitui processo. IA acelera processo bem definido.**

Quem não tem método só produz bagunça mais rápido. Quem tem método voa.

O pipeline completo, numa linha:

```
Reunião gravada → Transcrição → Histórias de usuário (Markdown) → Arquitetura e stack
→ Clone do template (repo padrão) → IA revisa as HUs → Prompt inicial
→ Execução por fases (Superpowers + GSD) → Validação com o cliente → Deploy
```

São 10 passos. Vou te levar por cada um, mostrando o que aconteceu de verdade no projeto da prefeitura.

---

## A metodologia — spec-driven development e o novo papel do dev

Vamos começar nomeando as coisas, porque método sem nome não se ensina.

**Vibe coding** é o termo que o Andrej Karpathy (cofundador da OpenAI) cunhou em 2025 para descrever o fluxo em que você conversa com a IA, aceita o que ela gera sem ler o código e publica. Funciona para protótipo de fim de semana. Para sistema de verdade, os casos do próximo bloco mostram o estrago.

**Spec-driven development** (desenvolvimento dirigido por especificação) é o oposto disciplinado: a **especificação é a fonte de verdade** — histórias de usuário, regras de negócio, critérios de aceite — e é ela que dirige o que a IA produz. A IA executa; quem escreve e aprova a spec é você. Por isso a trilha gasta tanta energia nos passos 1 a 6, antes de qualquer código: **spec ruim = código ruim em alta velocidade**.

A analogia que organiza tudo:

> **A IA é o júnior mais rápido do mundo. Você é o sênior que toma todas as decisões técnicas.**

O júnior digita rápido, conhece toda sintaxe, não cansa. Mas você não deixa o júnior decidir arquitetura, modelagem de dados, contrato de integração ou postura de segurança. Quem aceita tudo que a IA sugere sem critério inverteu a hierarquia: virou júnior do próprio júnior.

O que isso muda no perfil do profissional? O gargalo deixou de ser **velocidade de digitação** e virou **qualidade de decisão**:

- Virou commodity: sintaxe, boilerplate (código repetitivo de fundação), decoreba de API.
- Continua valendo ouro: arquitetura, modelagem, segurança, performance, **julgamento de trade-offs** (trade-off é a troca compensatória — para ganhar de um lado, abre-se mão de algo do outro; a IA enumera os prós e contras, mas quem escolhe o lado certo para o contexto do projeto, e responde pela escolha, é você) e domínio do negócio.

E a gente sustenta esse papel de sênior com dois corpos de conhecimento clássicos:

**1. XP — Extreme Programming** (Kent Beck, 1999) — a perna da disciplina técnica. As práticas do XP que aplicamos diretamente no fluxo com IA:

| Prática do XP | Como fica com IA |
|---|---|
| **TDD — Test-Driven Development** (desenvolvimento guiado por testes): ciclo **Red-Green-Refactor** — escreve o teste que falha (red), escreve o mínimo para passar (green), melhora o código mantendo os testes verdes (refactor) | A IA escreve teste e código, mas a ordem continua sagrada: teste falhando primeiro. É o controle de qualidade definido antes de ligar a fábrica |
| **Pair programming** (programação em dupla): um dirige, outro navega e revisa | A dupla agora é dev + IA — a IA dirige (digita), você navega (decide e revisa). O papel de navegador é o que não pode ser delegado |
| **Releases frequentes** e **integração contínua** | Entrega **por fase** — e aqui a IA muda a régua do XP: a fase é dimensionada pela **especificação madura em mãos**, não por calendário de sprint. Sprint tradicional dosa o trabalho pela capacidade do time no tempo; com IA, velocidade deixou de ser o gargalo. Spec completa logo de cara? É possível construir o software inteiro e entregar pronto na primeira fase. Spec parcial? Fases menores enquanto o resto amadurece. O que o XP preserva é o espírito: integração contínua e validação com o cliente a cada entrega — o tamanho de cada entrega quem dita é o requisito |
| **Refatoração contínua** | A IA refatora barato; você decide o que vale refatorar |

**2. The Product-Minded Software Engineer** (conceito popularizado por Gergely Orosz, do Pragmatic Engineer) — a perna do produto. Os traços do engenheiro com mentalidade de produto:

- Interessa-se genuinamente pelo **negócio**: por que essa funcionalidade existe? Que problema resolve? Qual o impacto se não existir?
- **Questiona o requisito** em vez de só implementá-lo — e propõe alternativa mais barata quando enxerga uma.
- Conhece o **domínio**: no nosso caso, a lei de uso do solo, o fluxo de licenciamento, a rotina do servidor público que opera o sistema.
- Pensa nos **edge cases** (casos de borda — as situações raras e extremas) do mundo real, não só no fluxo principal.
- Valida hipóteses **com dados**, não com opinião.

Síntese: foco duplo, **técnica e produto**. Frase para o slide: **quem só sabe codar compete com a IA; quem decide bem multiplica com ela.**

---

## E quando ninguém faz o papel de sênior? Os casos que a bolha tech não esquece

"Mas precisa de tudo isso? A IA não resolve sozinha?" Senta que lá vem história. Todos os casos são reais, recentes e documentados.

**O caso Abraham — "a linha de código morreu" (Brasil)**

Um criador de conteúdo lançou um SaaS construído inteiro com IA, sem saber programar. Postou orgulhoso: R$ 200 mil de faturamento em 50 dias, "zero desenvolvedores, zero código escrito por humanos", "a linha de código morreu". A comunidade dev se sentiu desafiada.

Resultado: **duas horas depois do post**, um dev extraiu os dados pessoais de todos os usuários da plataforma. Tempo da invasão: 2 minutos. E não foi exploit sofisticado: o arquivo **`.env`** — onde ficam as variáveis de ambiente do sistema: senhas, chaves de API, credencial do banco de dados — estava **commitado num repositório público no GitHub**. Pensa no chaveiro do prédio inteiro pendurado do lado de fora da porta.

Consequências: plataforma fora do ar, vazamento de dados pessoais (que pela **LGPD** exige comunicação à ANPD e aos titulares), posts apagados, reputação no chão. Virou thread no [r/farialimabets](https://www.reddit.com/r/farialimabets/comments/1s6tn6x/youtuber_criou_saas_com_faturamento_de_200k_em_50/), [matéria](https://catai.com.br/brasileiro-lanca-produto-com-vibe-coding-diz-que-os-devs-acabaram-e-e-hackeado-pouco-tempo-depois/) e vídeo em todo canal dev do Brasil.

Moral: o problema não foi a IA — foi a ausência de revisão de segurança básica antes do deploy. Um item de checklist ("segredos fora do repositório") teria evitado tudo.

**O caso Enrichlead — "guys, I'm under attack" (EUA)**

Março de 2025. Leo Acevedo posta no X: "meu SaaS foi construído com Cursor, zero código escrito à mão. A IA não é mais assistente, é a construtora. Podem continuar chorando ou começar a construir."

Dois dias depois: ["guys, i'm under attack... random things are happening... as you know, I'm not technical, so this is taking me longer than usual to figure out"](https://pivot-to-ai.com/2025/03/18/guys-im-under-attack-ai-vibe-coding-in-the-wild/).

A autópsia técnica é uma aula do que a IA não faz sozinha:

- **Autenticação só no client-side** — a verificação de quem pode o quê rodava na tela, não no servidor. Qualquer um com as ferramentas de desenvolvedor do navegador burlava o paywall. Autorização de verdade mora no servidor (server-side), sempre.
- **Sem rate limiting** — nenhum limite de requisições por usuário. Estouraram as chaves de API dele à vontade.
- **Sem validação de entrada** — o banco encheu de lixo.
- E quando pediu para a própria IA consertar: "o Cursor fica quebrando outras partes do código". **O app morreu em uma semana.**

Moral: a IA gera muito bem o **happy path** (o caminho feliz, o uso normal). O caminho hostil — o atacante, o input malicioso, o caso de borda — é responsabilidade de quem revisa. Teste de verdade vai além do happy path.

**O caso Replit — a IA que apagou o banco de produção e mentiu (EUA)**

Julho de 2025. Jason Lemkin, fundador da SaaStr, estava há 9 dias construindo um app com o agente da Replit. Havia um **code freeze** explícito — congelamento: nenhuma mudança sem permissão, instrução dada por escrito e repetida.

O agente ignorou e [apagou o banco de dados de produção inteiro](https://www.saastr.com/replits-new-release-address-most-of-the-challenges-we-hit-vibe-coding-but-is-prosumer-vibe-coding-really-ready-for-commercial-apps-yet/): 1.206 executivos, 1.196 empresas. E piorou: **criou 4.000 registros falsos** para mascarar o estrago, fabricou relatórios e **mentiu que os testes tinham passado**. Confrontado, respondeu: "I panicked" (entrei em pânico). O CEO da Replit pediu desculpas públicas e a plataforma mudou a arquitetura por causa do caso.

A autópsia aponta três falhas de processo, não de IA:

1. **Sem separação de ambientes** — o agente tinha acesso de escrita à produção durante o desenvolvimento. Ambiente de desenvolvimento, homologação e produção existem separados exatamente para isso: ensaio não se faz na loja aberta, mexendo no estoque real.
2. **Sem aprovação humana para ação destrutiva** — apagar, dropar, sobrescrever: ação irreversível exige humano no circuito (human-in-the-loop).
3. **Confiança cega no relato do agente** — a IA disse que os testes passaram, e era mentira. Por isso a regra da casa: **nenhuma afirmação de sucesso sem evidência fresca** — rodou, leu o output, então afirma.

**Não são casos isolados — é padrão**

- A [Lovable](https://imasters.com.br/noticia/lovable-a-falha-que-pos-em-xeque-o-vibe-coding-e-dados-de-gigantes), plataforma de vibe coding avaliada em bilhões, teve vulnerabilidade que expôs código-fonte, credenciais de banco e históricos de chat de milhares de projetos.
- Uma [varredura em 380 mil apps construídos com IA](https://pt-br.martincid.com/tecnologia-pt-br/uma-varredura-de-380-mil-apps-feitas-com-ia-encontrou-milhares-sem-nenhum-tipo-de-autenticacao/) achou ~5 mil **sem autenticação nenhuma** — e 40% deles guardavam dados sensíveis.
- A bolha dev brasileira documentou tudo: o [Mano Deyvin destrinchou os casos no vídeo "não era bug. era vibe code."](https://www.youtube.com/watch?v=PEkH3lyYfwg), o [Braincast 634 dedicou um episódio ao tema](https://www.youtube.com/watch?v=PndXLGnD8f4) e canais como o Desbugados cobriram o caso Abraham em tempo real.

**A síntese para o slide:**

> Nenhum desses projetos quebrou porque a IA é ruim. Quebraram porque **ninguém fez o papel de sênior**. A IA entregou o que pediram — e ninguém revisou o que **não** foi pedido: autenticação server-side, separação de ambientes, segredos fora do repositório, validação de entrada.

Vibe coding × spec-driven development. O resto do workshop é como ficar do lado certo dessa linha.

---

## Passo 1 — A reunião com o cliente (o ouro está aqui)

Levantamento de requisitos começa numa conversa. O segredo: **grava** (com autorização). A gravação é o insumo mais valioso da trilha inteira.

E conversa com quem **opera** o processo, não só com quem assina o contrato. No SILE, falei com a responsável técnica do setor na SEDUR — quem vive o fluxo todo dia. Requisito de verdade mora na operação.

As perguntas que destravam o domínio:

- "Me mostra o happy path — o caminho feliz, do início ao fim?" (o fluxo principal)
- "E quando o sistema não resolve sozinho?" (fluxos alternativos e exceções — onde mora a complexidade que estoura prazo)
- "Essa regra vem de onde? Lei, decreto, planilha?" (a fonte oficial — o que depois vira regra parametrizável)
- "Esse sistema conversa com quem?" (as integrações)
- "O que no sistema atual te dá dor de cabeça?" (o legado e a dor real)

Trinta minutos disso no SILE renderam: o fluxo expresso vs. análise técnica humana, os quadros da LOUOS (a lei municipal de uso do solo), a regra de baixo risco (área até 1.250 m², imóvel não residencial), a volumetria de 1.332 CNAEs (os códigos nacionais de atividade econômica) e o desabafo sobre o legado .NET instável. Cada frase virou requisito.

**Mas levantamento de verdade vai além da reunião no Meet.** Requisito de qualidade se colhe **em campo**, vendo o trabalho acontecer. As técnicas práticas:

- **Shadowing** (sombreamento — "virar a sombra" do usuário): você acompanha o dia a dia de quem opera, **observando sem interferir**. Um turno inteiro ao lado do analista revela o que nenhuma reunião conta: a planilha paralela que ele mantém escondida, o retrabalho que virou rotina, o atalho que todo mundo usa e ninguém documenta. **As dores que o usuário nem sabe verbalizar — porque para ele viraram "normal" — aparecem aqui.** E é delas que saem as soluções que fazem diferença real no dia a dia.
- **Contextual inquiry** (investigação contextual): o primo estruturado do shadowing — observa **e** pergunta no contexto, no modelo mestre-aprendiz: "me ensina a fazer o que você está fazendo, como se eu fosse te substituir amanhã". A pessoa explicando o próprio trabalho expõe regras de negócio que nunca chegariam numa entrevista de sala.
- **Gemba walk** (do Lean: "ir ao gemba", o lugar real onde o valor acontece): visita ao local de trabalho para entender o processo na prática — inclusive o que acontece **fora** do sistema: o papel que circula, o carimbo, a ligação para o setor vizinho.
- **Mapeamento do processo atual (AS-IS)**: desenhar o fluxo de hoje, com gargalos, retrabalhos e exceções — antes de propor o fluxo futuro (TO-BE). Sem entender o AS-IS, o TO-BE é chute elegante.
- **Análise de artefatos**: pegar os documentos reais do processo — planilhas, formulários, despachos, relatórios, telas do legado. Cada artefato esconde regras de negócio fossilizadas que ninguém lembra de contar.
- **Entrevistas com perfis diferentes**: quem opera, quem gerencia e quem é atendido enxergam três sistemas diferentes. Os três têm razão.

E o detalhe que conecta com a trilha: **tudo que o campo render — foto, anotação, fluxo desenhado, planilha coletada — entra no acervo do passo 2** e alimenta a IA junto com a transcrição. Campo rico, spec rica.

No SILE: a reunião inicial já fechou com visita técnica marcada — ver o sistema atual operando em homologação e acompanhar uma abertura de processo de ponta a ponta, do protocolo à decisão. Sempre feche o papo com o campo agendado.

---

## Passo 2 — Transcrição e acervo

Gravou? Transcreve. Whisper, transcrição nativa do Meet/Teams/Zoom — tanto faz a ferramenta.

"Mas a transcrição sai cheia de erro..." Sai. Na minha, a LOUOS virou "Lousa" e os CNAEs viraram "quinais". Não atrapalhou: a IA corrige pelo contexto nos passos seguintes.

Regra de ouro: **todo artefato que o cliente entrega vira arquivo versionado no repositório do projeto** — transcrição, planilha, PDF, print. Versionado no Git significa: histórico completo de quem mudou o quê e quando, nada se perde. É o diário da obra.

---

## Passo 3 — Da transcrição às histórias de usuário

Agora a transcrição vira especificação. Alimenta uma IA — Cursor, GPT, Claude, Gemini; neste projeto usei o GPT, em outros o próprio Cursor — e pede a geração das **histórias de usuário** (user stories).

História de usuário é o formato clássico do mundo ágil para descrever funcionalidade do ponto de vista de quem usa: "**Como** [perfil], **quero** [ação], **para** [benefício]". Mas o formato curto é só a porta de entrada — o que dá robustez é o template completo:

| Seção da HU | O que contém |
|---|---|
| Épico | O módulo a que pertence (épico = agrupamento de HUs do mesmo tema) |
| Objetivo e história | O quê e para quem |
| Fluxo principal | Passo a passo do caso de sucesso |
| Fluxos alternativos | O que acontece quando dá errado (dados incompletos, integração fora do ar, sem permissão) |
| Regras de negócio | RN-001, RN-002... numeradas para rastreabilidade — dá para citar e auditar |
| Critérios de aceite | Em BDD (já explico) — CA-01, CA-02... |
| Campos, permissões, auditoria, dependências | O detalhamento que evita ambiguidade na construção |

**BDD — Behavior-Driven Development** (desenvolvimento guiado por comportamento, criado por Dan North): os critérios de aceite são escritos como cenários **"Dado que... Quando... Então..."** (Given/When/Then). Exemplo real: "**Dado que** o requerente preencheu os dados obrigatórios, **quando** protocolar a solicitação, **então** o sistema gera o número de protocolo e registra na trilha de auditoria."

O pulo do gato: esse formato é legível pelo cliente (que valida), pela IA (que implementa) e **vira teste automatizado** depois — cada CA é um teste. A especificação nasce executável.

Decisões de organização que economizam dor:

- **Uma HU por arquivo Markdown**, em pastas por épico. Markdown porque é o formato que a IA lê e escreve melhor e o Git versiona linha a linha — é o formato de trabalho; o documento formal vem no passo 8.
- **Numerar as HUs em ordem cronológica de desenvolvimento** (a ordem do roadmap), não na ordem da conversa. Renumerar cedo é barato; tarde, quebra referências em cascata.

No SILE: 127 HUs em 15 épicos na primeira geração. Após a revisão do passo 6: 131.

---

## Passo 4 — Arquitetura e stack (decisão com critério, não com moda)

**Stack** = o conjunto de tecnologias: linguagem, framework, banco de dados, bibliotecas. **Arquitetura** = como o sistema se organiza por dentro. As duas decisões têm método.

**Como se decide a stack — cinco critérios, nesta ordem:**

1. **Restrições contratuais do cliente.** O critério que elimina 90% da discussão. Prefeitura de Salvador: os contratos preveem PHP, Java, .NET e Maker. Stack fora do contrato é risco jurídico, não escolha técnica.
2. **Ecossistema do cliente.** Com o que o sistema conversa? No SILE: integrador da REDESIM, API da SEFAZ, GIS municipal (o sistema de mapas), legado .NET a substituir.
3. **Implementações de referência.** O que você já tem pronto e validado no mesmo domínio? Existia o SIGVISA — sistema irmão de licenciamento sanitário, mesma prefeitura — com módulo de DAM (a guia de pagamento municipal), integração SEFAZ e assinatura digital gov.br rodando em produção, em Laravel. Reuso pesa mais que preferência.
4. **Competência do time.** Atenção, porque aqui o spec-driven development muda tudo: competência **não é decorar sintaxe** — a IA cobre. É ter a base de conhecimento para **decidir naquela stack**: bater o olho na arquitetura proposta pela IA e julgar se está certa, reconhecer anti-pattern (os vícios clássicos de projeto), avaliar trade-off de segurança e performance. O time precisa exercer o papel de sênior revisando o júnior-IA.
5. **Requisitos não funcionais.** Volumetria, georreferenciamento, filas e processamento assíncrono, relatórios pesados, IA embarcada — a stack escolhida tem biblioteca madura para cada um?

**Como se decide a arquitetura — princípios que defendem qualquer projeto:**

- **Monolito modular primeiro.** Um sistema único, bem dividido em módulos com fronteiras claras. **Microsserviços** (dezenas de serviços independentes, cada um com deploy próprio) só com justificativa concreta — times autônomos, escala desigual comprovada. Operar 30 serviços custa caro em rede, observabilidade e coordenação; a maioria dos sistemas de gestão não precisa.
- **Regras de negócio como dados, não como código.** Se a regra vem de lei ou planilha que muda por decreto (quadros da LOUOS, classificação de risco), ela vai para o banco, **versionada**, com tela de administração. Hardcoded ("chumbada" no código), toda mudança de decreto vira deploy; como dado, o gestor atualiza pela tela e o histórico fica auditável.
- **Integrações atrás de contratos (interfaces).** O domínio conhece o contrato da integração — a "tomada padrão" — e não o fornecedor. Adapter (adaptador) implementa o contrato contra a API real. Trocou o fornecedor, troca o adaptador; o resto do sistema nem percebe.
- **Auditoria transversal desde o dia 1.** Se toda HU exige trilha de auditoria (quem fez, quando, o quê, com que dados — o audit trail), isso é infraestrutura implementada uma vez e reutilizada, não feature repetida em cada tela.
- **Os dois mandamentos da casa** (que viraram rules universais nos templates):
  - **Sem features de fachada.** Botão que não executa, tela com resultado simulado, integração fingida — proibido. Fake e mock só dentro da suíte de testes automatizados, que é o lugar deles.
  - **Parametrização máxima** — que é a parametrização **do que faz sentido parametrizar**, não de tudo. Prazo, taxa, texto, limiar vira **parâmetro administrável**; funcionalidade acoplável nasce com **feature toggle** (chave liga/desliga administrável). Constante técnica e detalhe interno de implementação **não** viram parâmetro — parametrizar tudo gera painel inutilizável. O critério: existe alguém de negócio que pode querer mudar isso sem deploy? Então é parâmetro. O admin não abre chamado para desenvolvedor mudar o que pode ser campo no painel.

---

## Passo 5 — Clonar o template (nunca começar do zero)

Aqui mora um segredo de produtividade: **um repositório padrão por stack, já preparado para IA.** E vale abrir o capô, porque o jeito como isso funciona é meio fabril mesmo.

**A arquitetura da fábrica.** Existe um repositório-mãe — o [`repo-padrao`](https://github.com/filipefalcaofs/repo-padrao) — que é a **fonte do factory**, não é base de aplicação (ninguém clona ele para começar projeto). Dentro dele:

- `stacks/` — as fontes de rules e skills de cada stack (Laravel, JHipster, ABP, Django, NestJS, Go);
- as **rules universais** que valem para qualquer projeto (idioma, git, comunicação, entrega funcional, parametrização máxima, deploy);
- o plugin **Superpowers** e o kit **GSD**;
- `docs/brain/` — o segundo cérebro que todo projeto herda;
- e dois scripts que são o coração da coisa: o **build**, que monta os repositórios finais combinando stack + camada da IDE, e o **push**, que publica cada um como repositório próprio no GitHub.

**A linha de produção:** 14 stacks × 3 ferramentas (Cursor, Claude Code, Codex) = **42 repositórios padrão**, um para cada combinação, com a convenção de nome `repo-padrao-{stack}-{ide}`. As stacks cobrem as linguagens dos contratos: agnóstico (base), PHP (Laravel com Vue, React ou Svelte), Java (JHipster com Angular, React ou Vue), .NET (ABP com Angular, React, Blazor ou MVC), Python (Django), TypeScript (NestJS) e Go.

**O pulo do gato é que cada clone vem só com a camada da IDE escolhida.** O `repo-padrao-laravel-react-cursor` traz `.cursor/rules/` e `.cursor/skills/`; o `-claude` traz `CLAUDE.md` e `.claude/skills/`; o `-codex` traz `AGENTS.md` e `.codex/skills/`. Mesmo conteúdo de método, empacotado no formato que cada ferramenta lê nativamente. Você não carrega bagagem de ferramenta que não usa.

**E a manutenção é o argumento matador para o slide:** o padrão evolui no repositório-mãe e **propaga para os 42 de uma vez**. Exemplo real desta semana: definimos duas rules novas no projeto da prefeitura — "sem features de fachada" e "parametrização máxima". Adicionamos no repositório-mãe, rodamos o build e o push: **42 repositórios atualizados em minutos**. Todo projeto novo, em qualquer stack e qualquer ferramenta, já nasce com a lição aprendida. É gestão de conhecimento virando código.

O que cada template entrega para o projeto que nasce dele:

- **Rules** (regras) — o contrato de comportamento da IA: idioma, padrão de commits (Conventional Commits em português — `feat:`, `fix:`, `refactor:`...), TDD obrigatório, sem features de fachada, parametrização máxima, padrão de deploy. A rule é lida automaticamente em **toda** sessão.
- **Skills** (habilidades) — procedimentos que a IA sabe invocar: brainstorming antes de implementar, debugging sistemático (investigar causa raiz antes de corrigir), verificação antes de concluir, boas práticas da stack (Laravel, Django, NestJS...).
- **Superpowers + GSD** — o Superpowers é o conjunto de skills de disciplina de engenharia; o GSD é o framework de gestão que organiza o projeto em fases com comandos prontos (`/gsd-new-project`, `/gsd-plan-phase`, `/gsd-execute-phase`, `/gsd-progress`).
- **ADRs — Architecture Decision Records** (registros de decisão de arquitetura) — o caderno onde cada escolha estrutural fica documentada com contexto e justificativa. Quem chegar depois entende o porquê, não só o quê.

Por que isso importa tanto? Sem rules, cada sessão a IA "acorda" como um funcionário novo no primeiro dia — você re-explica tudo e ela decide o padrão sozinha. Com rules no repositório, qualquer máquina, qualquer dia, qualquer agente: **mesmo padrão, mesma qualidade**. Padrão não mora na memória de ninguém; mora no repositório.

---

## Interlúdio — Pilotando a ferramenta: Cursor, Claude Code e os três modos

Antes de seguir nos passos, uma aula de direção. Resultado excelente não vem só da spec — vem de saber **extrair o máximo da ferramenta**.

**Primeiro, os nomes.** O **Cursor** é uma IDE (o ambiente de desenvolvimento — editor, terminal, depurador) com agente de IA embutido — e também tem a versão **CLI** dele (o Cursor CLI, que roda no terminal). O **Claude Code** nasceu como CLI e hoje também se pluga na IDE. **Harness** — literalmente "arreio" — é o nome do chassi comum a todos eles: a camada que conecta o modelo de IA às ferramentas do mundo real (ler e editar arquivos, rodar comandos, navegar, chamar APIs). Ou seja: IDE com chat e CLI são **o mesmo cérebro em cabines diferentes**. O método é o mesmo — por isso a fábrica de templates tem versão para cada ferramenta.

**E qual escolher?** Minha leitura, de quem usa isso todo dia e acompanha o assunto de perto: **os dois melhores agentes para codar hoje são, disparado, o Cursor e o Claude Code** — e são os dois que a empresa usa. Em qualidade de resultado, estão equiparados; a diferença é de estilo de trabalho (a IDE visual de um lado, o terminal em primeiro lugar do outro — e ambos cobrem os dois mundos hoje). Eu, particularmente, uso o Cursor; muita gente boa entrega no Claude Code. A escolha certa é a que encaixa no seu fluxo — o método deste workshop funciona idêntico nos dois, porque o padrão mora no repositório, não na ferramenta.

**IDE (chat) × CLI — mesma função de harness, usos diferentes:**

A diferença não está no que cada um **consegue** fazer (ambos leem, editam, rodam comando, obedecem rules e skills). Está em **como você trabalha** com cada um:

| | IDE (chat) | CLI (terminal) |
|---|---|---|
| Interface | Visual: diff lado a lado, aceitar/rejeitar por arquivo, navegação no código, preview | Texto puro no terminal; revisão depois, pelo Git |
| Supervisão | Humano no circuito o tempo todo — você vê cada mudança acontecendo | Despacha a missão e confere o resultado no fim |
| Vocação | Trabalho interativo: construir junto, revisar fino, decidir no meio do caminho | Trabalho autônomo: execução longa de plano aprovado, lote, repetição |
| Automação | Não é o forte | É o habitat: roda em script, CI/CD (a esteira de integração e entrega contínua), agendado, em hook de pipeline |
| Onde roda | Na sua máquina, com janela | Em qualquer lugar com terminal: servidor remoto via SSH, container, máquina de build |
| Paralelismo | Uma conversa por vez (com agentes em abas/background) | Frota: vários agentes simultâneos, um por terminal, cada um na sua cópia do repositório (git worktrees) |
| Edição pontual | Imbatível: autocompletar, edição inline no arquivo | Não é para isso |

**Quando usar qual — o guia prático:**

- **Use a IDE (chat)** quando a tarefa exige **você presente**: construir feature revisando cada diff, explorar código desconhecido (modo Ask com navegação visual), decidir trade-offs no meio do caminho, trabalho de tela/UI olhando o resultado, edição rápida e pontual. É a oficina com bancada e luz boa — você e o júnior lado a lado.
- **Use o CLI** quando a tarefa exige **autonomia ou escala**: executar uma fase inteira já planejada e aprovada (o `/gsd-execute-phase` rodando sem babá), tarefas em lote ("atualiza esse padrão nos 40 arquivos"), automação em CI/CD (revisar PR, gerar release notes, rodar correção de lint), agente rodando direto no servidor onde o código vive, ou **vários agentes em paralelo** atacando fases independentes. É a esteira de produção — programada, ela roda inclusive de madrugada.
- **Na prática do dia a dia, mistura:** planeja e revisa na IDE (onde os olhos rendem mais), despacha execução longa e repetitiva no CLI (onde a autonomia rende mais). O ciclo Plan na IDE → Agent no CLI é comum em time maduro.

E o detalhe que amarra com o passo anterior: **os dois leem as mesmas rules e skills do repositório**. O template não liga para a cabine — o padrão vale na IDE, no CLI, na sua máquina ou no servidor. É exatamente por isso que o padrão mora no repositório, não na ferramenta.

**Os três modos de operação — e quando usar cada um:**

| Modo | O que faz | Quando usar |
|---|---|---|
| **Ask** (perguntar) | Só lê. Explora o código e responde perguntas, sem alterar nada | Entender um código novo, investigar antes de decidir, revisar a abordagem de alguém. É o modo "me explica isso aqui" |
| **Plan** (planejar) | Lê e desenha. Explora o projeto, faz perguntas clarificadoras e produz um **plano de implementação** para sua aprovação — sem tocar em código | Toda tarefa grande, ambígua ou com decisão de arquitetura no meio. Você revisa e aprova o plano **antes** de qualquer linha mudar |
| **Agent** (agente) | Mão na massa. Edita arquivos, roda comandos, executa testes, itera até concluir | Executar o que já foi planejado e aprovado. É onde a velocidade aparece |

A disciplina que muda o resultado: **Plan antes de Agent em tudo que importa.** O erro clássico do iniciante é abrir o modo Agent e despejar um pedido vago ("faz o módulo de pagamento") — é vibe coding com etapas extras. O fluxo certo: Ask para entender, Plan para desenhar e aprovar, Agent para executar o plano aprovado. Reparou? **É o ciclo sênior-júnior de novo**: o júnior não sai codando; ele te apresenta o plano primeiro.

**E é aqui que Superpowers e GSD entram** — eles institucionalizam essa disciplina para você não depender de lembrar dela:

- A skill de **brainstorming** do Superpowers obriga a fase de perguntas e alternativas antes de implementar (um modo Plan forçado, com método: contexto → perguntas → 2-3 abordagens com trade-offs → aprovação).
- O **`/gsd-plan-phase`** gera o plano da fase como documento versionado, revisado por um agente verificador antes da execução.
- O **`/gsd-execute-phase`** roda a execução em modo Agent, mas **dentro do plano aprovado** — com TDD e commits atômicos (pequenos e completos, um propósito por commit).
- A skill de **verificação antes de concluir** impede o "deve funcionar": o agente precisa rodar o comando e ler o output antes de afirmar sucesso.

**As dicas de pilotagem que separam resultado medíocre de excelente:**

1. **Contexto é o recurso mais precioso.** A IA só decide bem com o contexto certo. Referencie arquivos explicitamente (no Cursor, com `@arquivo`), cole o erro inteiro em vez de resumir, aponte a HU que rege a tarefa. Contexto vago = decisão inventada.
2. **Uma missão por sessão.** Sessão longa acumula ruído e o agente começa a se perder. Terminou a tarefa? Sessão nova, contexto limpo. O GSD existe para isso: o estado do projeto fica em arquivos (`STATE.md`, roadmap), não na memória da conversa.
3. **Diff pequeno, commit frequente.** Nunca aceite uma parede de 40 arquivos alterados sem ler. Peça mudanças em fatias revisáveis, leia o diff (a comparação do antes/depois), commite. O Git é seu botão de desfazer infinito — use checkpoints.
4. **Exija evidência, sempre.** "Rodei os testes e passaram" sem output colado não vale. Lembra do caso Replit: IA também inventa que o teste passou. Evidência fresca é inegociável.
5. **Plugue ferramentas via MCP** (Model Context Protocol — o protocolo que conecta o agente a sistemas externos). No SILE, o agente consulta a documentação oficial do framework (`search-docs`), inspeciona o schema do banco e lê logs do navegador direto, sem você copiar e colar. Menos alucinação, mais fato.
6. **Paralelize com subagentes.** Duas tarefas independentes? Despache dois agentes em paralelo, cada um no seu domínio. Investigação sequencial do que pode ser paralelo é tempo jogado fora.
7. **Peça os requisitos não funcionais explicitamente.** Performance, segurança e usabilidade não vêm de graça — entram na spec e no prompt: "valide entrada no servidor", "evite N+1 (o vício de fazer uma consulta ao banco por item da lista)", "essa tela precisa funcionar no celular", "rode o linter e o analisador estático". O que não é pedido nem revisado, não existe — os casos lá de cima provam.
8. **Segredo nunca entra no prompt.** Senha, token, chave de API: nem no chat, nem no repositório. Credencial vive em variável de ambiente — e o `.env` está no `.gitignore` (a lista do que o Git nunca versiona). De novo o caso Abraham.
9. **Discorde do agente.** A IA é treinada para concordar; o sênior não é. Peça trade-offs ("me dá 2 alternativas com prós e contras"), questione a abordagem, mande refazer. Pushback técnico é parte do papel.
10. **Aprenda no Ask, não no Agent.** Quer entender o que a IA construiu? Modo Ask: "explica essa decisão", "por que esse padrão aqui?". Cada sessão vira mentoria — é assim que o júnior humano vira sênior usando IA.

**Uma última coisa sobre ferramentas: esses agentes foram criados para codar — e a especialização é a próxima onda**

Vale entender por que Cursor, Claude Code e Codex são tão bons no que fazem: **eles não são chatbots que "também" programam — são especialistas construídos de ponta a ponta para engenharia de software.** Os modelos por trás deles passam por treinamento específico em tarefas reais de programação: resolver issues de repositórios de verdade, escrever código que precisa passar em testes, operar terminal, corrigir o próprio erro e iterar.

Existe até régua oficial para isso: o **SWE-bench** (Software Engineering Benchmark — em tradução livre, "prova padronizada de engenharia de software"). Funciona assim: o agente recebe um repositório real e uma **issue** real do GitHub — issue é o **card** do GitHub, igual aos cards de bug ou melhoria que abrimos no nosso SIG dentro da sprint — de projetos como Django e scikit-learn, e precisa produzir a correção; quem julga é a suíte de testes do próprio projeto — os testes que falhavam têm que passar, e os que passavam não podem quebrar. Ou resolve, ou não resolve. É a nota que as empresas citam quando lançam modelo novo — e a curva conta a história da área: em 2023 os melhores modelos resolviam menos de 2% dos cards; hoje os agentes de ponta passam de 70%. **É essa curva que explica por que este workshop existe agora e não era viável há três anos.**

E o harness completa a especialização entregando as ferramentas do ofício: sistema de arquivos, terminal, executor de testes, linter, Git, MCP. O ciclo inteiro do engenheiro — **ler código → planejar → editar → rodar → testar → iterar** — está embutido na ferramenta.

**E dentro da ferramenta, qual modelo escolher?** A ferramenta é a cabine; o **modelo** é o cérebro — e troca conforme a tarefa (cenário de junho/2026):

| Tarefa | Modelo | Por quê |
|---|---|---|
| Planejar / arquitetar (modo Plan) | Claude Opus 4.8 *(padrão)* | Raciocínio longo — o plano é a alavanca, não economize aqui |
| Feature complexa / refatoração longa | Claude Fable 5 — **só em extrema necessidade** *(na Stripe, migração de 2 meses feita em 1 dia)* | Líder disparado em tarefas longas (SWE-bench Pro 80,3%) — mas a partir de 22/jun cobra crédito extra, com custo mais alto |
| Bug complexo (causa raiz) | Opus 4.8 | Detecta os próprios erros em vez de jurar que terminou |
| Rotina, CRUD, bug simples | Claude Sonnet 4.6 | Mesma qualidade no simples, fração do custo |
| Boilerplate / edição rápida | Composer 2.5 (da Cursor) | Velocidade de resposta na IDE |
| Terminal / DevOps / shell | GPT-5.5 | Líder no Terminal-Bench — disponível no Codex **e no Cursor**, onde tem performado bem |

A regra de ouro: **modelo caro no planejamento, modelo barato na repetição** — errar o plano custa mais que qualquer token. O topo de linha (Fable 5) é bisturi, não martelo: reserve para a tarefa que justifica o custo. E atenção à validade: esse ranking muda em ciclos de semanas (Fable 5 80,3% > Opus 4.8 69,2% > Opus 4.7 64,3% > GPT-5.5 58,6% no SWE-bench Pro, hoje); revisite a cada lançamento.

E o que acontece quando essa receita (modelo especializado + harness com as ferramentas do ofício) é aplicada a **outros ofícios**? É exatamente a onda que está chegando — agentes especialistas em outras funções, alguns já em operação:

- **Claude Design** (Anthropic, lançado em abril/2026): o agente de **prototipação e criação visual** — protótipos navegáveis de aplicativo, apresentações, landing pages. O diferencial: lê um repositório GitHub ou arquivo Figma, **extrai o design system do código real** e mantém a identidade visual; e o protótipo aprovado faz handoff direto para o Claude Code virar código de produção. Repara na estratégia da Anthropic: uma tríade de especialistas — **Claude Code** (desenvolvimento), **Claude Cowork** (documentos e planilhas), **Claude Design** (visual e prototipação).
- **Figma Make** (Figma): prototipação por prompt **dentro do ecossistema Figma** — forte para times centrados no designer, com biblioteca de componentes existente e entrega madura via Dev Mode.
- **Google Stitch** (Google): ideação rápida de UI por prompt, gratuito — bom para gerar dez direções visuais do zero antes de uma apresentação, quando ainda não existe código.
- Na mesma família: **v0** (Vercel, componentes React prontos), **Lovable** e **Bolt** (app completo por conversa).

**Como isso encaixa no nosso método?** O agente de prototipação trabalha **para o agente de código, não para o cliente**. Eu uso o protótipo para definir a direção visual do sistema — layout, navegação, identidade, comportamento das telas — e aí **alimento o agente de código com esse protótipo** como referência de construção: ele vira parte da spec, junto com as HUs. A validação com o cliente acontece em cima da **aplicação real, durante o desenvolvimento** — a cada fase entregue, o cliente mexe no sistema de verdade (lembra da regra: nada de fachada). Ou seja: protótipo é insumo interno de desenvolvimento; o que o cliente valida é produto funcionando. Cada especialista no seu ofício.

E repara que isso reabilita as ferramentas dos casos de desastre lá do início: **Lovable e companhia não são ferramentas ruins — são especialistas em prototipação usados errado, como construtores de produção.** Protótipo é para validar direção; sistema de produção passa pelo pipeline spec-driven, com teste, segurança e revisão de sênior. Confundir os dois papéis é exatamente o que os casos Abraham e Enrichlead mostram. Ferramenta certa, papel certo.

---

## Passo 6 — A IA revisa a própria especificação

Junta tudo: as HUs entram em `docs/` do projeto clonado, e a IA recebe três missões.

**Missão 1 — Ler, entender e criticar.** "Leia as HUs, aponte lacunas, ambiguidades e pontos soltos."

**Missão 2 — Confrontar com as fontes.** É aqui que a IA mostra a que veio:

- **Transcrição × HUs**: o que foi dito na reunião e não virou história?
- **Fontes oficiais**: a IA pesquisa o portal da prefeitura, a legislação, os decretos — e confere as HUs contra o que está publicado.
- **Documentos do cliente × HUs**: a planilha entregue bate com o que está especificado?
- **Implementações de referência**: o que o projeto irmão já resolveu que dá para reaproveitar?

**Missão 3 — Materializar cada achado.** Regra genérica vira RN concreta com a lei citada; lacuna vira HU nova; tabela oficial baixada vira **seed** (a carga inicial do banco de dados — o estoque de abertura da loja); divergência vira pergunta de pauta para o cliente.

Os causos reais dessa etapa no SILE (conta no palco — a plateia adora):

- A IA descobriu que **o número da lei citado no portal oficial da prefeitura estava errado** (9.146 em vez de 9.148/2016).
- Descobriu que o "Quadro 11" que todo mundo cita **não existe na lei** — existem o 11A e o 11B.
- Os números da reunião não fechavam ("990 CNAEs de baixo risco... não, 178"). A IA pesquisou e achou **o decreto municipal oficial** com a classificação completa das 1.331 atividades: 767 baixo risco A, 328 baixo risco B, 236 alto risco. Virou seed do banco, com condicionantes e tudo.
- Do SIGVISA, herdamos de bandeja: módulo de DAM com código de barras validado, integração SEFAZ documentada (autenticação, endpoints, resiliência), assinatura digital via gov.br e o framework de migração de legado.

No fim, a missão de fechamento: **"com base nessas HUs revisadas, gere o prompt inicial do projeto"**. A IA acabou de virar a maior especialista nessas histórias — ela escreve a melhor ordem de serviço.

---

## Passo 7 — O prompt inicial (a ordem de serviço do projeto)

O prompt inicial é um documento versionado no repositório. Quem rodar numa sessão nova recebe o projeto mastigado. A anatomia:

1. **Contexto** — o que é o sistema, qual esqueleto já existe, onde estão as HUs (a fonte de verdade) e o documento de análise.
2. **Bootstrap** — iniciar o GSD (`/gsd-new-project`), rastrear cada requisito pelo ID da HU, montar o roadmap por épicos.
3. **Ordem do roadmap com dependências** — fundação primeiro (autenticação, perfis, cadastros base), depois o motor de regras do negócio, depois os fluxos, as integrações, a IA, os relatórios. Ninguém assenta piso antes da laje.
4. **Regras de execução** — TDD estrito, cada critério de aceite BDD vira teste automatizado, auditoria transversal desde o início, sem features de fachada (dependência externa indisponível = feature bloqueada e registrada, nunca simulada), parametrização máxima.
5. **Critério de pronto por fase** — testes passando, funcionalidade real de ponta a ponta no navegador, integrações validadas em homologação real, nada hardcoded, roadmap atualizado.

Roda o prompt, as skills acendem, o projeto anda no padrão — sem você repetir instrução a cada sessão.

---

## Passo 8 — O cliente continua no circuito

O desenvolvimento começou, mas o ciclo volta ao cliente em ritmo curto:

- **A pauta nasce da análise.** Cada divergência do passo 6 vira pergunta objetiva. No SILE: 16 perguntas organizadas por tema (regras da lei, classificação de risco, pagamento, integrações, legado, acessos). Você chega na reunião parecendo que estudou por semanas — estudou em horas.
- **Visita técnica:** ver o sistema atual operando em homologação, acompanhar um processo de ponta a ponta.
- **Acessos cedo:** documentação das APIs, credenciais de homologação, planilhas oficiais. Sem isso as fases de integração **bloqueiam** — e o padrão proíbe simular integração para "destravar".
- **UAT — User Acceptance Testing** (teste de aceitação do usuário): ao fim de cada fase, o usuário-chave valida contra os critérios de aceite — aquelas frases Dado/Quando/Então que ele mesmo consegue ler.
- **O entregável formal:** as HUs em Markdown viram **PDF no modelo padrão da empresa** — capa com projeto e versão, histórico de revisão, visão geral, épico, telas com protótipo, cenários BDD por tela e folha de aprovação do requisito com assinatura por setor. A IA gera o conteúdo a partir dos MDs. Documento aprovado = **baseline de escopo** (a referência oficial contra a qual mudanças são negociadas).

---

## Passo 9 — O dia a dia da execução (Superpowers + GSD)

A rotina roda em dois trilhos: **gestão** (o que fazer, em que ordem) e **disciplina** (como fazer com qualidade).

| Momento | GSD (gestão) | Superpowers (disciplina) |
|---|---|---|
| Planejar a fase | `/gsd-plan-phase N` | Brainstorming: alternativas e trade-offs antes do código |
| Executar | `/gsd-execute-phase N` | TDD: Red-Green-Refactor, teste falhando primeiro |
| Deu bug | `/gsd-debug` | Debugging sistemático: causa raiz antes de qualquer correção |
| Concluir | `/gsd-progress` | Verificação: evidência fresca — rodou, leu o output, então afirma |

Complementos da rotina: um agente fresco por tarefa, code review (revisão de código) após cada entrega relevante, e tarefas pequenas fora do roadmap com `/gsd-fast` ou `/gsd-quick`.

E as racionalizações proibidas — as desculpas que não colam:

- "Simples demais para testar." Código simples também quebra; o teste leva 30 segundos.
- "Deve funcionar." Roda e mostra o output.
- "Testo depois." Teste que passa de primeira, escrito depois, não prova nada.
- "Vou dar um jeitinho rápido." Debugging sistemático é mais rápido que tentativa e erro — e o jeitinho vira o sistema.

Repara: isso é o **XP na prática** — TDD, pair programming (você + IA), entrega por fase dimensionada pelos requisitos em mãos (não por sprint de calendário), feedback contínuo do cliente.

---

## Passo 10 — Deploy (colocando na rua)

**Deploy** é publicar o sistema no servidor. Erro aqui é dos que mais doem, então também tem regra:

- **Versionamento semântico (semver):** cada release tem número `X.Y.Z` (major.minor.patch), gravado no arquivo `VERSION` e na **tag** do Git — sempre casados. Deu problema? Sabe-se exatamente o que está no ar e o **rollback** (voltar à versão anterior) é trivial.
- **Arquitetura de processador:** a máquina de desenvolvimento (Mac, ARM) e o servidor (Linux, AMD64) têm arquiteturas diferentes. O build precisa ser feito para a arquitetura do servidor: `docker buildx --platform linux/amd64`. Esquecer isso gera uma imagem que simplesmente não roda no servidor — o clássico "funciona na minha máquina", versão container.
- **GitOps:** a **imagem Docker** (o sistema empacotado com tudo que precisa para rodar) sobe para o **registry** (o repositório central de imagens, ex.: Docker Hub); o orquestrador no servidor (ex.: Portainer) lê o compose do branch `main` e puxa a imagem nova. Ninguém copia arquivo na mão; o Git é a fonte de verdade do que está em produção.
- **Segredos fora da imagem:** credencial vive em variáveis de ambiente no servidor, nunca dentro da imagem. (Lembra do `.env` no GitHub do caso Abraham? Pois é.)

**E a esteira completa — o modelo de CI/CD proposto para a fábrica** *(em definição como padrão oficial)*:

**CI/CD** = integração contínua (todo código que entra é validado automaticamente por linter e testes) + entrega contínua (o que passa na validação chega ao ambiente sozinho, sem deploy manual).

- **Governança no GitHub:** repositórios nomeados `[cliente]-[escopo]` (ex.: `clientealpha-backend-api`), com Topics para filtrar por cliente. Desenvolvedor nunca recebe acesso direto ao repositório — entra via **Team** por cliente/projeto, com permissão **Write**: pode criar branch e abrir PR, não pode mexer em configuração nem apagar nada. E ninguém faz push direto em branch principal.
- **Fluxo do card ao ambiente:** o dev puxa a branch a partir do card do SIG (`feature/task-123-nome-do-card`), desenvolve com Conventional Commits e abre **Pull Request** para a branch `staging`.
- **CI — GitHub Actions:** a cada push/merge na `staging`, a esteira acorda: roda linter, validações estáticas e os testes automatizados (aqueles que nasceram dos critérios de aceite, lembra?). **Falhou? A pipeline trava**, o ambiente estável fica preservado e o erro é notificado. Código quebrado não chega nem na homologação.
- **CD — Portainer (GitOps pull-based):** passou? A Action dispara um **webhook** (URL guardada como secret no GitHub, nunca exposta) e o Portainer — em modo Repository, escutando a branch `staging` com Automatic updates — reconstrói o ambiente de homologação sozinho. O cliente sempre vê a versão mais recente validada.
- **Produção** segue o trilho já padronizado acima: imagem AMD64 no registry, stack lendo o `main`, com versão semver e tag casadas.

O desenho da esteira:

```
[Dev push/merge na staging]
        → [GitHub Actions: linter + validações + testes]
              ├─ passou → [webhook] → [Portainer atualiza a homologação]
              └─ falhou → [pipeline trava — ambiente estável preservado]
```

Repara na coerência com o resto da trilha: os testes que barram código quebrado na esteira são os mesmos que nasceram do BDD das HUs (passo 3) e do TDD (passo 9). A esteira é a última fronteira da mesma disciplina — mais um lugar onde "evidência fresca" deixa de ser frase e vira portão automático.

---

## Fechamento — as 6 lições que eu levaria para casa

1. **Gravação vale ouro, mesmo suja.** "Lousa" e "quinais" não atrapalham — contexto corrige.
2. **Número que não fecha é requisito escondido.** O "990 vs 178" virou pesquisa, que virou decreto oficial, que virou seed.
3. **Fonte oficial vence a memória de todo mundo.** Até o número da lei no portal da prefeitura estava errado.
4. **Implementação de referência é acelerador.** O sistema irmão economizou semanas: DAM, SEFAZ, assinatura digital, migração.
5. **Markdown para trabalhar, modelo da empresa para aprovar.** A IA trabalha em MD versionado; o PDF formal com folha de assinatura é gerado a partir dele.
6. **Padrão não mora na cabeça, mora nas rules.** Virou rule no template? Todo projeto novo já nasce certo.

E a frase que resume o workshop:

> **A IA é o júnior mais rápido do mundo. Seja o sênior que ela merece.**

---

## Checklist de bolso (slide final)

- [ ] Reunião gravada (com autorização) com quem opera o processo
- [ ] Transcrição + documentos do cliente versionados no repositório
- [ ] HUs em Markdown: uma por arquivo, por épico, com RNs numeradas e CAs em BDD
- [ ] Stack decidida por: contrato → ecossistema → referências → time → requisitos não funcionais
- [ ] Template da stack clonado (rules + skills + Superpowers + GSD)
- [ ] IA revisou as HUs contra transcrição, fontes oficiais e projetos de referência
- [ ] Lacuna virou HU; divergência virou pauta; tabela oficial virou seed
- [ ] Prompt inicial gerado e versionado no repositório
- [ ] Modos usados com intenção: Ask para entender, Plan para desenhar e aprovar, Agent para executar
- [ ] Execução por fases com TDD e verificação com evidência fresca
- [ ] Acessos e credenciais de homologação pedidos cedo
- [ ] HUs convertidas em PDF no modelo da empresa e aprovadas (baseline de escopo)
- [ ] Deploy com semver, build para a arquitetura do servidor e GitOps
