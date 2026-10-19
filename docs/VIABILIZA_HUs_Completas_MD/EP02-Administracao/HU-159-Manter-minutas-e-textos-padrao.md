# HU-159 — Manter minutas e textos-padrão

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Manter a biblioteca de trechos que o analista insere no parecer, com versão incrementada quando o conteúdo muda.

## História de Usuário
**Como** administrador com `manter-parametros`,
**quero** cadastrar, corrigir e desativar textos-padrão por categoria,
**para** o parecer usar trechos pré-aprovados e a versão registrar qual texto mudou.

## Atores e permissões
- A manutenção exige `manter-parametros`. Menu Configuração, item Minutas e textos-padrão (`/gestao/textos-padrao`).
- O controller registra que a leitura da lista ativa no parecer fica sob `analisar-processos`, em outro fluxo.

## Fluxo evidenciado
1. A lista busca no conteúdo, filtra por categoria e por situação, ordena por categoria ou versão e pagina em 10, 15, 25 ou 50 (padrão 15). Cada item sai pelo `StandardTextResource`.
2. Incluir grava categoria, conteúdo e situação, com versão 1. Texto novo nasce ativo quando a situação não vem.
3. Editar grava os mesmos campos. Se o conteúdo difere do armazenado, a versão soma um. Mudar só categoria ou situação mantém a versão.
4. A ativação inverte `active`. Não há exclusão. Texto inativo sai da biblioteca oferecida ao analista e o histórico da versão permanece.
5. Criar, atualizar e ativar são auditados no log `textos-padrao`.

## Regras de negócio evidenciadas
- A versão sobe somente quando o conteúdo muda.
- Não há exclusão. Inativar preserva o trecho.
- A permissão desta tela é a mesma dos parâmetros. O controller deixa registrada a pendência de a coordenação da SEDUR exigir permissão própria.

## Validações
- `category` obrigatória, até 100 caracteres, com trim.
- `content` obrigatório.
- `active` booleano obrigatório.

## Critérios de aceite
### CA-01 — Primeira versão
**Dado** um usuário com `manter-parametros`,
**Quando** cadastra categoria e conteúdo,
**Então** o texto é gravado com versão 1.

### CA-02 — Conteúdo novo
**Dado** um texto na versão 1,
**Quando** a edição muda o conteúdo,
**Então** a versão passa a 2 e a auditoria marca que o conteúdo mudou.

### CA-03 — Só a categoria
**Dado** um texto na versão 2,
**Quando** a edição muda a categoria e mantém o conteúdo,
**Então** a versão continua 2.

### CA-04 — Desativar
**Dado** um texto ativo,
**Quando** o administrador aciona a ativação,
**Então** o texto fica inativo e não é apagado.

## Divergências da redação anterior
Não havia HU. A capacidade existia no código sem rastreabilidade.

## Evidências
- `routes/gestao.php`: `textos-padrao.index`, `store`, `update`, `ativacao.update`.
- `app/Http/Controllers/Gestao/StandardTextController.php`.
- `app/Http/Requests/Gestao/StandardTextRequest.php`.
- `resources/js/navigation/gestao-nav.ts`: Minutas e textos-padrão em Configuração.

## Não evidenciado
- Exclusão física do texto.
- Permissão exclusiva desta biblioteca, distinta de `manter-parametros`.
- A inserção do trecho no parecer. Esta HU cobre o cadastro.
