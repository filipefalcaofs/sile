# HU-019 — Manter tipos de imóvel

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Manter os tipos de imóvel reconhecidos do REGIN e a marca que tira o processo do fluxo expresso.

## História de Usuário
**Como** administrador com `manter-tipos-imovel`,
**quero** cadastrar o tipo, as grafias alternativas e se ele dirige regra,
**para** o motor reconhecer o texto vindo do REGIN e encaminhar à análise quando a marca estiver ligada.

## Atores e permissões
- A tela exige `manter-tipos-imovel`. Menu Regras, item Tipos de imóvel (`/gestao/tipos-imovel`).
- A gravação invalida o cache do catálogo depois do commit.

## Fluxo evidenciado
1. A lista consulta os tipos com busca, situação, ordenação e paginação no mesmo padrão das outras listas do console (10, 15, 25 ou 50).
2. Incluir grava código, rótulo, `drives_rule`, situação e a lista de aliases. Tipo novo nasce ativo quando a situação não vem.
3. Editar altera rótulo, `drives_rule`, situação e aliases. O código é imutável. `drives_rule` e `active` só mudam se vierem no envio; omitir o campo preserva o valor.
4. A ativação inverte `active` e não exclui o registro.
5. Os aliases são sincronizados: os que saem da lista são removidos um a um e os novos são criados. A normalização ocorre no model.

## Regras de negócio evidenciadas
- `drives_rule` verdadeiro faz o tipo derrubar o processo do expresso para análise, conforme o comentário da rota.
- Grafia alternativa não pode colidir com alias já usado (`PropertyTypeAliasAvailable`).
- Código não muda depois do cadastro. Trocar o código seria outro tipo.
- Desativar tira o tipo do catálogo ativo e preserva o histórico.

## Validações
- No cadastro, `code` obrigatório, até 50, padrão `^[a-z0-9_]+$`, único em `property_types.code`.
- `label` obrigatório, até 255.
- `drives_rule` booleano obrigatório no cadastro e opcional na edição.
- `active` booleano obrigatório no cadastro e opcional na edição.
- `aliases` array presente. Cada item é string de até 255, sujeita a `PropertyTypeAliasAvailable`. Na edição, a regra ignora os aliases do próprio tipo.

## Critérios de aceite
### CA-01 — Tipo que dirige regra
**Dado** um usuário com `manter-tipos-imovel`,
**Quando** cadastra código em minúsculas, rótulo e `drives_rule` verdadeiro,
**Então** o tipo é gravado com essa marca e o cache do catálogo é invalidado.

### CA-02 — Código imutável e flag omitida
**Dado** um tipo inativo,
**Quando** a edição envia rótulo e aliases sem o campo `active`,
**Então** o código permanece e o tipo continua inativo.

### CA-03 — Alias repetido
**Dado** uma grafia já vinculada a outro tipo,
**Quando** o administrador tenta gravá-la de novo,
**Então** `PropertyTypeAliasAvailable` recusa o alias.

## Divergências da redação anterior
Não havia HU. A capacidade existia no código sem rastreabilidade.

## Evidências
- `routes/gestao.php`: `tipos-imovel.index`, `store`, `update`, `ativacao.update`.
- `app/Http/Controllers/Gestao/PropertyTypeController.php`.
- `StorePropertyTypeRequest` e `UpdatePropertyTypeRequest`.
- `resources/js/navigation/gestao-nav.ts`: Tipos de imóvel em Regras.

## Não evidenciado
- Exclusão física do tipo.
- Lista fechada dos tipos do REGIN imposta por esta validação. O código é texto no padrão `a-z`, `0-9` e `_`.
