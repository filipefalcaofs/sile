# HU-121 — Assistente virtual do analista

## Épica
**EP14 — Inteligência Artificial**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Há indexação e busca por similaridade de precedentes, sem conversa do analista nem tela que use essa busca.

## História de Usuário
**Como** analista, **quero** perguntar ao sistema sobre regras e casos parecidos. A pergunta em conversa não existe. A busca vetorial existe como serviço, sem rota.

## Atores e permissões
- `features.ia_assistente` é o interruptor descrito para os assistentes. Não há checagem desse interruptor num controlador de chat.
- `PrecedentIndexer` grava embedding quando há provedor de embeddings ativo (`AiFeatureGate::embeddingsAvailable`). Sem provedor, não indexa e não simula vetor.
- `PrecedentVectorSearch::maisSimilares` consulta por distância. Não há controller que a exponha.
- Comando de indexação existe para a carga de precedentes. Não abre um assistente.

## Fluxo evidenciado
1. Indexar precedente chama o provedor de embeddings e persiste o vetor, ou não faz nada se não há provedor.
2. A busca devolve precedentes mais próximos de um texto. Isso não redige parecer nem decide.
3. Não há página, rota ou `AgentConversation` no código da aplicação.
4. Minuta de parecer, resumo e justificativa são outras funções, disparadas na ficha, e não um assistente de diálogo.

## Regras de negócio evidenciadas
- Sem provedor de embeddings não há vetor inventado.
- A busca não é decisão e não está ligada à conclusão da análise.

## Validações
- A busca recebe texto e um limite de vizinhos. Não há validação de pergunta de chat porque não há esse pedido HTTP.

## Critérios de aceite
- Não há rota de gestão de conversa com o analista.
- A busca por similaridade, quando chamada, não grava `ViabilityDecision`.
- Sem provedor de embeddings, a indexação não finge vetor.

## Divergências da redação anterior
O texto pedia um assistente que aumentasse a produtividade do analista. O que há é infraestrutura de precedentes, sem o diálogo.

## Evidências
- `app/Services/Ai/Embeddings/PrecedentIndexer.php`
- `app/Services/Ai/Embeddings/PrecedentVectorSearch.php`
- `app/Services/Ai/AiFeatureGate.php` (`embeddingsAvailable`)
- `database/seeders/ParameterSeeder.php` (`features.ia_assistente`)

## Não evidenciado
- Tela ou endpoint em que o analista envie uma pergunta e receba resposta conversacional.
- Uso da busca por similaridade na ficha de análise.
