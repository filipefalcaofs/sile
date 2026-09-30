# Login de gestão — alinhamento óptico

**Data:** 2026-09-30
**Status:** aprovado para planejamento
**Origem:** handoff de design `design_handoff_login_alinhamento` (README + protótipo `Login Gestao v3.dc.html` + 10 screenshots)
**Telas:** `/gestao/login` (`resources/js/pages/auth/gestao-login.tsx`) e a entrada do console (`GestaoLayout` / `AppShell`)

## Objetivo

Substituir o mapa de zoneamento estático do palco do login por uma cena animada
que representa a metáfora do produto — **Atividade + Localização + LOUOS →
Viabilidade** — como três anéis ópticos que entram em foco e se alinham conforme
a sessão avança. O alinhamento completo abre um disco com a marca, que viaja até
o logo da sidebar enquanto o palco se contrai e vira a sidebar do console e o
painel do formulário vira a área de conteúdo.

As animações reagem a **mudanças de estado** — foco, identificador reconhecido,
senha iniciada, envio, resultado — e nunca à contagem de caracteres digitados.

## Fonte da verdade

O protótipo `prototipo/Login Gestao v3.dc.html` (classe `Component` no
`<script data-dc-script>`) contém todos os alvos, tempos e fórmulas. Onde o
README do handoff for ambíguo, vale o protótipo. Onde o protótipo divergir do
repositório atual, valem os desvios registrados na seção "Desvios deliberados".

## Problema a resolver antes de tudo

O passo 3 do "caminho 1" do handoff não é implementável como está escrito. Ele
supõe que, com `useForm().post`, dá para receber o sucesso e segurar a navegação
por ~2,9 s rodando `authorized → preparing → docking`. O Inertia troca a página
assim que a resposta chega; `onSuccess` roda **depois** da troca, com o
componente de login já desmontado.

O Inertia v3 tem `useHttp`, que faz requisição solta sem tocar na página, mas
ele não envia o header `X-Inertia`. O `redirect($destino)` de
`LoginController::store` seria seguido pelo próprio XHR e devolveria o HTML
inteiro do destino — o `DashboardController` executaria `PainelOperacaoService::montar`
duas vezes por login, uma delas para uma resposta descartada.

### Decisão: contrato assíncrono explícito no controller

`App\Http\Controllers\Gestao\LoginController::store` passa a verificar o header
`X-Viabiliza-Login-Async`. Quando presente, responde `200` com
`{"destino": "<url>"}` em vez de `redirect($destino)`. Tudo o que vem antes —
throttle, autenticação no guard `gestao`, regeneração de sessão, registro de
auditoria (RN-002) e resolução de `GestaoIntendedRedirect` — permanece
idêntico. Muda só o formato da última resposta.

Consequências:

- Um único round trip para descobrir o destino; a visita ao destino acontece uma
  vez só, ao fim da animação.
- `GestaoIntendedRedirect` continua sendo resolvido no servidor, no mesmo ponto.
  O destino devolvido já é o final, incluindo `url.intended`.
- Sem JavaScript, o formulário continua sendo um POST normal que redireciona.
  A degradação atual é preservada.
- Falha de credencial devolve `422` com os erros de validação, que `useHttp`
  expõe em `errors` — vira a fase `denied`.

## Arquitetura

### Módulos novos

`gestao-login.tsx` tem 380 linhas hoje e receberia cena, loop de animação,
máquina de estados e orquestração de navegação. A divisão abaixo mantém cada
arquivo com um propósito só e concentra o que é testável em código puro.

| Arquivo | Responsabilidade | Depende de |
|---|---|---|
| `resources/js/pages/auth/gestao-login-scene-state.ts` | Puro, sem DOM: `classificarIdentificador()`, `estadoLogico()`, `alvosPorEstado()`, `deriva()`, tabelas de tempos, textos de estado e taxas de interpolação | nada |
| `resources/js/pages/auth/gestao-login-scene.tsx` | Markup da cena (anéis, arco de autorização, disco, linha de estado, fórmula, linha de conexão) e o loop `requestAnimationFrame` que escreve estilos por refs | `gestao-login-scene-state.ts` |
| `resources/js/pages/auth/gestao-login-docking.ts` | Orquestra a sequência pós-envio (`validating → authorized → preparing → docking → visit`), cancelamento no desmonte e atalho de movimento reduzido; grava a marca de entrada do console | `@inertiajs/react` |
| `resources/js/pages/auth/gestao-login.tsx` | Palco, formulário e a ligação entre eles | os três acima |

`gestao-login-theme.ts` recebe as classes do palco em flex (ver "Palco em flex").

### Estado

Estado de fase vive no `gestao-login.tsx` e desce por props. O estado lógico `L`
é derivado, nunca armazenado:

```
se fase ≠ 'idle'          → L = fase
senão se identOk && senha → 'ready'
senão se senha            → 'secure'
senão se foco || ident    → 'session'
senão                     → 'idle'
```

Fases: `idle → validating → (denied → idle) | (authorized → preparing → docking)`.
A fase `open` não existe no login: ela acontece já no console, depois da
navegação.

`identOk` é o tipo de conta reconhecido, avaliado 520 ms após a última digitação
(debounce) ou no `blur` do campo:

| Padrão | Rótulo | Selo curto |
|---|---|---|
| `/@salvador\.ba\.gov\.br$/i` | E-mail institucional | E-mail institucional |
| e-mail válido | Conta local | Conta local |
| `/^[a-z0-9._-]{3,}$/i` | Rede corporativa (AD) | Rede corporativa · AD |

### Tempos

A partir do clique em Entrar: `validating` começa em 0 e dura enquanto o POST
estiver em voo, com piso de 1450 ms para o beat não colapsar em rede rápida.
Depois: `authorized` +0 · `preparing` +900 · `docking` +2000 · `router.visit`
+2900. Falha: `denied` por 1100 ms, limpa a senha e volta a `idle` com a
mensagem de erro.

O piso é a única diferença em relação ao protótipo, que simula latência fixa de
1450 ms. Se a resposta demorar mais que o piso, a fase `validating` se estende —
o arco de autorização continua girando, sem barra de progresso falsa.

### Loop de animação

Um único `requestAnimationFrame` escreve direto no DOM via refs, sem re-render
do React por frame. Cada valor persegue seu alvo com
`cur += (alvo − cur) × (1 − e^(−k·dt))`, com `dt` limitado a 50 ms. Taxas: 3,2
padrão; rotação 2,2; disco 3,4; alvo Y da linha 9; blur da foto 2; deriva 1,5;
comprimento da linha 3,6.

Esse amortecimento é o que absorve mudança de estado no meio de outra sem salto
— tirar o foco durante `session`, por exemplo.

Centro da cena: `x = largura do palco × 0,70`, `y = altura da janela × 0,47`,
recalculado no resize e **congelado** a partir de `docking`.

Deriva por anel `i` ∈ {0,1,2}, multiplicada pelo fator `drift` do estado:
`dx = sin(t × 0,00031 × (1 + 0,27i) + 2,1i) × 26`,
`dy = cos(t × 0,00027 × (1 + 0,19i) + 1,3i) × 20`, com `t` em ms.

Paralaxe do mouse sobre o palco (mx, my ∈ [−0,5; 0,5]): anéis
`−mx × (10 + 9i)` e `−my × (8 + 7i)`; foto `−mx × 8` e `−my × 6`. Desligada a
partir de `docking`.

Alvos por estado, deriva, opacidade, blur e arco de autorização seguem a tabela
do README do handoff e o método `targets()` do protótipo, sem alteração.

### Palco em flex

`gestaoLoginShellClasses` usa hoje
`grid-cols-[minmax(0,1fr)_clamp(440px,34vw,580px)]`. Coluna `fr` não interpola,
então a contração do palco para 290px não anima. O shell passa a ser flex com o
palco em `width: calc(100% - clamp(440px,34vw,580px))` e o painel em `flex:1`,
preservando as mesmas larguras resultantes em todos os tamanhos.

A foto sai do `background` do palco para uma camada própria (`div` absoluta com
`inset:-30px`, `background-size:cover`), para poder receber `filter: blur()
brightness()` e `transform: scale()` animados. Os dois gradientes de
`gestaoLoginBrandStageClasses` ficam numa camada acima, e um véu `#0c111d` numa
terceira.

O `ZoneMap` é removido, junto com o keyframe `sile-zone-pulse`, que fica sem uso.

### Transição para o console

Disparada por `docking`, via transições CSS:

- Palco: `width → 290px`, `padding → 32px 20px`, `1.05s cubic-bezier(.7,0,.2,1)`.
- Headline, rodapé e header do palco: `opacity 0` + `translateY(-10px)`, `.4s`.
- Véu `#0c111d` sobre o palco: `opacity .92`, `1s ease .25s`.
- Painel: fundo → `#f9fafb` (`.6s ease .3s`), borda esquerda → transparente;
  formulário `opacity 0` + `translateY(-8px)` `.35s`.
- Disco: translada até `(38px, 50px)` na viewport, escala ×0,3 (120px × 0,3 =
  36px, o tamanho do `LogoMark` da sidebar), fundo → transparente, marca de
  branco → `#5992ed` em `.6s`.

`(38px, 50px)` é o centro exato do `LogoMark` do `AppSidebar`: `px-5` dá 20px de
recuo, `pt-8` dá 32px de topo, e o mark tem 36px. Vale sempre, porque
`SidebarProvider` inicializa `isExpanded` como `true` e não persiste o estado —
toda entrada no console começa com a sidebar em 290px.

### Entrada no console

Com o caminho 1 não existe crossfade real entre o disco e o logo da sidebar: a
página é trocada e o login desmonta. O `gestao-login-docking.ts` grava uma marca
em `sessionStorage` imediatamente antes do `router.visit`. O `AppShell` lê e
**consome** essa marca no primeiro render, roda a cascata da fase `open` uma vez
e não repete em navegações seguintes.

Cascata: sidebar `opacity .45s` com grupos em `.12s/.22s/.32s`; header
`translateY(-100%) → 0` em `.7s cubic-bezier(.2,.7,.2,1) .1s`; blocos de
conteúdo em `.2s/.3s/.4s/.5s` com `opacity` + `translateY(10px)`.

O **logo da sidebar entra sem animação**. O disco parou exatamente sobre ele, no
mesmo tamanho e na mesma cor; animar o logo criaria o piscar que o critério de
aceite proíbe.

O eyebrow transitório "06 / 06 · CENTRAL DE VIABILIDADE ABERTA" fica de fora —
era opcional no handoff.

### Microinterações do formulário

- Selo "AMBIENTE INTERNO" no header do palco: quando `identOk`, anexa
  "· {selo curto}" com `max-width 0 → 240px` em `.7s` mais fade, em
  `oklch(80% .09 250)`. A borda do selo vira `oklch(64% .155 250 / .35)` fora de
  `idle`.
- Botão de envio, sem spinner e sem barra de progresso, com rótulo por fase:
  "Entrar no console" · "Validando credencial…" · "Credencial não confere" ·
  "Operador autorizado" (com `CheckCircleIcon`) · "Abrindo a central…" · e
  "Tentar novamente" depois de um erro. A seta aparece só em `idle`.
- Erro: caixa `error-50` / `border error-500/35` / `text error-600` com
  "Credenciais não conferem. A tentativa foi registrada para fins de auditoria.";
  campo de senha com borda `error-500`; o formulário faz shake
  `[10, −8, 6, −4, 2, 0]px` a cada 70 ms.
- Caps Lock, mostrar/ocultar senha, "Manter conectado" (marcado por padrão) e o
  alternador de tema do painel continuam exatamente como no código atual.

## Desvios deliberados do protótipo

| Item | Protótipo | Implementação | Motivo |
|---|---|---|---|
| Headline do palco | "A cidade, zona a zona" | "O motor decide. / O analista confirma." | O protótipo é anterior ao commit `c367bd14`; os textos atuais do repositório prevalecem |
| Eyebrow do palco | "Gestão de licenciamento eletrônico" | "Decisão de viabilidade locacional" | idem |
| Rodapé do palco | Auditoria / Protocolo / Base legal | Expresso / Análise / LOUOS | idem |
| Rótulo do Anel C | Lei nº 9.184/2016 | Lei nº 9.148/2016 | 9.148/2016 é a LOUOS; 9.184 é erro de digitação do protótipo |
| Biblioteca de animação | rAF manual | rAF manual (mantido) | O loop escreve 12 valores por frame sem re-render; `motion` não cobre esse caso. Desvio consciente da convenção do projeto |
| Movimento reduzido | não tratado | `matchMedia('(prefers-reduced-motion: reduce)')` no loop e na orquestração | `MotionConfig reducedMotion="user"` não alcança código fora da lib |
| Fase `open` | simulada dentro do protótipo | acontece no console real, via marca em `sessionStorage` | Caminho 1: a página é trocada de verdade |
| Sidebar e dashboard do protótipo | maquete com skeleton | `AppShell` e `dashboard.tsx` reais, com dados reais | Sem feature de fachada |

## Movimento reduzido

Com `prefers-reduced-motion: reduce`: sem deriva, sem blur, sem paralaxe e sem
voo da marca. Troca de estado com fade de 150 ms. Após o sucesso do POST,
navegação direta, sem a sequência de 2,9 s. A cena permanece visível e estática,
com os anéis nas posições do estado corrente.

## Responsividade

Abaixo de 1101px o palco não é renderizado e a cena não monta — nem o loop rAF.
Só o formulário aparece, como hoje.

## Testes

**Vitest** (`gestao-login-scene-state.test.ts`), sobre o módulo puro:

- `classificarIdentificador()` nos três padrões, mais os casos que não
  classificam: vazio, `ab` (curto demais), `nome@` (e-mail incompleto).
- `estadoLogico()` cobrindo as combinações de fase, foco, identificador e senha,
  incluindo a precedência da fase sobre tudo.
- `alvosPorEstado()` devolvendo os alvos documentados para cada um dos dez
  estados, com o congelamento a partir de `docking`.
- `deriva()` batendo com a fórmula em instantes conhecidos e zerando quando o
  fator do estado é 0.
- Tabela de tempos: a soma das fases de sucesso dá 2900 ms e `denied` dura
  1100 ms.

**PHPUnit** (feature test do `LoginController`):

- POST com `X-Viabiliza-Login-Async` e credencial válida devolve `200` e o
  destino em JSON.
- O mesmo POST sem o header continua devolvendo `302` para o destino.
- Credencial inválida com o header devolve `422` com o erro de validação.
- `url.intended` sob `/gestao` é respeitada nos dois modos.
- O registro de auditoria (RN-002) é gravado nos dois modos.

## Critérios de aceite

1. Nenhum efeito é disparado por caractere digitado: só por foco, reconhecimento
   do identificador (debounce de 520 ms ou blur), presença de senha, envio e
   resposta.
2. A troca `/gestao/login` → destino não apresenta piscada: o último frame do
   login coincide com o primeiro frame do console.
3. Mudança de estado no meio de uma transição é absorvida sem salto.
4. `prefers-reduced-motion: reduce` é respeitado conforme a seção acima.
5. Abaixo de 1101px só o formulário aparece, sem cena e sem loop rAF.
6. 60 fps em notebook comum: blur só na foto e nos três anéis; o loop escreve
   estilos apenas via refs, sem `setState` por frame.
7. O destino do login continua sendo resolvido pelo servidor, incluindo
   `url.intended`, e a auditoria da tentativa continua sendo registrada.
8. Sem JavaScript, o formulário posta e redireciona como hoje.

## Fora de escopo

- Caminho 2 do handoff (layout persistente do Inertia com palco e sidebar no
  mesmo nó DOM).
- Eyebrow transitório na página do console.
- Animação de saída no logout (`open → leaving → idle` do protótipo).
- Qualquer alteração no login do portal do cidadão.

## Assets

`public/images/salvador-hero.jpg`, `public/images/logo_prefeitura.png` e
`public/images/01JW9M9BYJ76Y0M06Z1XHJDKH8.png` já existem no repositório. Ícones
vêm de `components/app/logo.tsx` (`LogoMark`) e `components/icons`
(`CheckCircleIcon` para a fase `authorized`).
