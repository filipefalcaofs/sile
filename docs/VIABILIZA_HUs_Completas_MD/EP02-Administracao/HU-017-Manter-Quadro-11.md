# HU-017 — Manter Quadro 11

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Não evidenciada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
A redação anterior pedia a manutenção do Quadro 11 da LOUOS. O código atual não oferece esse quadro.

## História de Usuário
**Como** mantenedor do motor,
**quero** localizar o Quadro 11 no hub de regras,
**para** versionar suas condições.

Não há ator com essa capacidade: o quadro não está no catálogo de domínios nem na tela.

## Atores e permissões
- O hub Motor de regras (`/gestao/louos`) lista e edita `quadro10` e `quadro11a`.
- `RuleDomain` não tem caso de Quadro 11. O Quadro 11A está na HU-018. O Quadro 11B não aparece como domínio nem como rota.

## Fluxo evidenciado
1. O mapa `QUADRO_DOMAINS` de `LouosController`, `LouosDraftService`, `LouosSandboxController` e `PublishLouosVersionRequest` aceita apenas `quadro10` e `quadro11a`.
2. Um parâmetro `quadro` fora desse mapa cai no Quadro 10 na consulta, ou é recusado com «Quadro inválido ou ausente» nas operações de rascunho.
3. A validação de linha e de publicação usa `in:quadro10,quadro11a`.

## Regras de negócio evidenciadas
- Não há tabela, seeder vigente nem CRUD nomeados como Quadro 11 no domínio de regras atual.
- O comentário de `LouosQuadro11ImportService`, ainda usado pelo Quadro 11A, registra que a publicação oficial tratada pelo import é o 11A.

## Validações
- Enviar `quadro=quadro11` não passa em `in:quadro10,quadro11a`.

## Critérios de aceite
### CA-01 — Quadro ausente do catálogo
**Dado** o enum `RuleDomain` e os mapas de quadro do hub,
**Quando** se procura um domínio de Quadro 11,
**Então** só existem `louos_quadro10` e `louos_quadro11a`.

### CA-02 — Parâmetro recusado
**Dado** um mantenedor com `manter-louos`,
**Quando** envia `quadro` igual a `quadro11` para abrir rascunho ou publicar,
**Então** a validação recusa o quadro.

## Divergências da redação anterior
A redação anterior tratava o Quadro 11 como cadastro administrável, com as mesmas regras genéricas de zona, via e resultado proibitivo ou condicionado. Esse cadastro não está no código. O quadro de condições por classe de via que a tela mantém é o 11A (HU-018).

## Evidências
- `app/Enums/RuleDomain.php`.
- `LouosController::QUADRO_DOMAINS`, `LouosDraftService::QUADRO_DOMAINS`, `PublishLouosVersionRequest::QUADRO_DOMAINS`.
- `StoreLouosLinhaRequest`: `quadro` em `quadro10,quadro11a`.

## Não evidenciado
- CRUD, importação, rascunho, publicação ou simulação do Quadro 11.
- Cadastro do Quadro 11B.
