# Perfis governados pela tabela, sem nome de papel no código

Etapa 1 do fluxo de primeiro acesso AD. A etapa 2 (solicitação de acesso pelo
servidor, aprovada pelo administrador) tem spec própria e depende desta.

## Objetivo

A tabela `roles` passa a ser a única fonte de verdade dos perfis. O código decide
por **permissão**, nunca pelo nome do perfil. Os perfis `analista` e `gestor` saem,
e a análise de processos fica com Coordenador, Subcoordenador, Chefe de setor e
Analista Terceirizado.

## Contexto verificado no código

- Hoje só cinco pontos decidem por nome de perfil:
  - `ProcessoController::atualizarStatusAnalise` — `hasRole('gestor')` libera o
    `force` (ignora o grafo de transições da `AnalysisStatusStateMachine`).
  - `EscalonarSlaCommand` e `AlertarExercicioTllCommand` — notificam quem tem o
    papel de `config('sile.notificacoes.escalonamento.gestor_role')`.
  - `CreateNewUser` e `GovBrAuthService` — `assignRole('cidadao')` no autocadastro.
  - `Roles::STRUCTURAL` — lista no código dos perfis não excluíveis/renomeáveis
    (`RoleController`, `UpdateRoleRequest`).
  - `RolesAndPermissionsSeeder` — apaga os papéis fora de `Roles::catalog()`.
- Concluir a análise está atrás de `analisar-processos` (`ConcluirAnaliseRequest`,
  grupos de rota em `routes/gestao.php`). Hoje a permissão está em `analista`,
  `gestor` e `administrador`; Coordenador, Subcoordenador, Chefe de setor e
  Analista Terceirizado nascem sem nenhuma permissão (`Roles::LEGACY`).

## Decisões

1. **Permissões novas**, atribuídas na tela de Perfis:
   - `forcar-transicao-analise` — substitui `hasRole('gestor')` no `force`.
   - `receber-escalonamento-sla` — define quem recebe os escalonamentos de SLA
     vencido e de exercício TLL faltante. Os dois comandos listam usuários com
     essa permissão; o `gestor_role` sai de `config/sile.php`.
2. **Perfil do cidadão** vira o parâmetro `portal.perfil_cidadao` (`Settings`), que
   guarda o **nome** do perfil usado no autocadastro (o id muda entre ambientes) e
   só aceita nome existente na tabela `roles`. `CreateNewUser` e `GovBrAuthService`
   leem o parâmetro.
3. **Perfil protegido** vira a coluna booleana `roles.protegido` (`cidadao` e
   `administrador` nascem protegidos). Perfil protegido, ou o perfil apontado pelo
   parâmetro do cidadão, não pode ser excluído nem renomeado. `Roles::STRUCTURAL`,
   `Roles::LEGACY` e `Roles::catalog()` deixam de existir.
4. **O seeder só cria o estado inicial** (`firstOrCreate` + `givePermissionTo`
   aditivo) e não apaga mais nenhum perfil. `analista` e `gestor` saem por uma
   migração única.
5. **Migração dos usuários:** quem tinha `analista` passa a Analista Terceirizado;
   quem tinha `gestor` passa a Coordenador. Depois o administrador ajusta caso a
   caso em Usuários. A migração roda antes de apagar os dois perfis.
6. **`administrador` perde `analisar-processos`**, para que só os quatro perfis
   analisem e concluam. Mantém as demais permissões (reprocessar, emitir TVL,
   cadastros, parâmetros, usuários, perfis).

## Permissões por perfil

Conjunto de análise (os quatro perfis): `acessar-gestao`, `consultar-cnaes`,
`consultar-territorio`, `consultar-louos`, `consultar-solicitacoes`,
`analisar-processos`, `reprocessar-processos`, `encaminhar-malha-fina`, `emitir-tvl`,
`enviar-tvl-analise`, `preencher-ficha-vistoria`.

| Perfil | Conjunto de análise | Extras |
|---|---|---|
| Analista Terceirizado | sim | — |
| Coordenador | sim | `distribuir-processos`, `analisar-malha-fina`, `consultar-relatorios`, `relatorios.produtividade.nominal`, `forcar-transicao-analise`, `receber-escalonamento-sla` |
| Subcoordenador | sim | os mesmos extras do Coordenador |
| Chefe de setor | sim | os mesmos extras do Coordenador |

As permissões que só o `gestor` tinha e que o `administrador` já tem
(`registrar-contingencia`, `atendimento-presencial`, `manter-tipos-servico`,
`manter-tipos-imovel`, `manter-gatilhos-risco`, `manter-requisitos-documentais`)
não são atribuídas às chefias. O administrador pode concedê-las pela tela de
Perfis se a SEDUR quiser.

O perfil `apoio` não muda.

## Fora do escopo

- Solicitação e aprovação de acesso no primeiro login AD (etapa 2).
- Lista de perfis solicitáveis por setor (etapa 2).
- Atribuir permissões aos demais perfis do legado (Diretor, Gerente, Fiscal etc.).

## Riscos

- Em produção, quem hoje é `administrador` e analisa processos deixa de poder fazê-lo
  (decisão 6). A migração não mexe nessas contas; o administrador precisa de um
  segundo perfil só se a SEDUR quiser que ele continue analisando, e o modelo atual
  permite um perfil por conta.
- Escalonamento sem destinatário: se nenhum perfil tiver `receber-escalonamento-sla`,
  o comando não notifica ninguém. O comando registra um aviso no log nesse caso.

## Testes

- Cada um dos quatro perfis conclui análise; `administrador`, `apoio` e `cidadao` não.
- `force` só vale para quem tem `forcar-transicao-analise`.
- Escalonamentos chegam a quem tem `receber-escalonamento-sla`, e a um perfil
  qualquer que receba a permissão, sem alterar código.
- Autocadastro e login gov.br atribuem o perfil indicado pelo parâmetro.
- Perfil protegido não pode ser excluído nem renomeado.
- A migração reatribui os usuários de `analista` e `gestor` e remove os dois perfis.
- Os testes e seeders que criam usuários com `analista`/`gestor` (16 arquivos de
  teste e os seeders de desenvolvimento) passam a usar os perfis novos.
