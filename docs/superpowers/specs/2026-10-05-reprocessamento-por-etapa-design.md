# Reprocessamento do motor por etapa

Data: 2026-10-05. Estado: aguardando revisão.

## Problema

Quando um serviço de terceiro está fora do ar durante o protocolo, o processo é decidido sem o dado que faltou e vai para a análise técnica. Três lacunas:

1. **Não há reavaliação.** `FluxoExpressoService::decide` só age sobre processo `Protocolada`. `expresso:reavaliar` redespacha apenas processos protocolados sem decisão. Um processo já encaminhado à análise por falha de serviço não volta ao motor.
2. **A falha é invisível.** A causa fica em `activity_log` (por exemplo `geo / localizacao-inscricao`) e em uma frase em `ExpressoQueda.motivo`. A ficha não diz qual etapa falhou, nem qual serviço, nem se a falha é transitória.
3. **Tudo é manual.** Sem comando nem botão, refazer exige mexer no banco.

Caso concreto: no homolog, o pod não conecta em `api.sedur.salvador.ba.gov.br` (exceção de conexão, sem resposta HTTP). Os processos VIA-2026-000002 e 000003 foram para a análise sem ponto, sem zona e sem via.

## Objetivos

- Registrar, por processo, o resultado de cada etapa do motor que depende de dado externo.
- Mostrar na ficha de análise onde o motor parou, qual serviço falhou e se a falha é transitória.
- Reprocessar a partir da etapa que falhou, sem refazer o que já deu certo, por botão, por comando e automaticamente.
- Quando o reprocessamento completar os dados, o motor reavalia o veredito, inclusive de processo que já está em análise.

## Fora de escopo

- Parecer devolvido à JUCEB (`ReginParecerNotifier`) e avisos por WhatsApp e e-mail. São saída e não alimentam o veredito.
- Consultas de CNPJ e CPF.
- Nova máquina de estados. A existente já permite `em_analise → deferida` e `em_analise → indeferida`.
- Corrigir a rede do pod do homolog. É questão de infraestrutura da Prefeitura.

## Decisões assumidas (confirmar na revisão)

1. O veredito antigo permanece como histórico. A reavaliação cria nova entrada, nunca apaga `ExpressoQueda` nem a auditoria.
2. Reprocesso manual: analista e administrador.
3. Vale para todos os processos, não só os do REGIN.

## Desenho

### Etapas

Enum `EtapaMotor`. Cada etapa tem um serviço de terceiro de origem:

| Etapa | Serviço | Alimenta |
|---|---|---|
| `localizacao_inscricao` | Inscrição imobiliária (SEDUR/SEFAZ), Nominatim como apoio | Ponto, CODLOG tributário |
| `poligono_lote` | Endereçamento SEDUR, SEMAP | Polígono do lote |
| `zona` | GeoServer SEDUR (zonas) | Zona, Quadro 10 |
| `via` | GeoServer SEDUR (vias) | Classe viária, Quadro 11-A |
| `bairro` | Camada GeoSalvador | Bairro oficial |
| `regra_territorial` | GeoServer SEDUR (camadas de parcelamento) | RT-02 a RT-04 |

### Dados

Tabela `viability_request_etapas`, uma linha por processo e etapa (atualizada, não acumulada):

| Coluna | Tipo | Observação |
|---|---|---|
| `viability_request_id` | FK | |
| `etapa` | string | valor de `EtapaMotor` |
| `servico` | string | chave do serviço de terceiro |
| `situacao` | string | `ok`, `indisponivel`, `definitiva`, `pulada` |
| `motivo` | text, nulo | texto curto, sem dado pessoal |
| `tentativas` | unsigned int | |
| `ultima_tentativa_em` | timestamp | |
| `proxima_tentativa_em` | timestamp, nulo | |
| `esgotada_em` | timestamp, nulo | |

Índice único em (`viability_request_id`, `etapa`). A trilha completa de cada tentativa continua em `activity_log`, com `log_name` `motor` e evento `etapa`.

`indisponivel` é transitória: o serviço não respondeu ou respondeu erro. `definitiva` cobre "não encontrado" e "sem coordenada". Só `indisponivel` é reprocessada sozinha.

### Gravação

`EtapaMotorRegistrador` grava a situação nos pontos em que o status já é conhecido:

- `TerritorioProcessoService::localizarPelaInscricao` (já devolve o status).
- `PoligonoEnderecamentoService::aplicar`.
- `TerritorioProcessoService::materializar`, a partir dos estados do `TerritoryResult` (zona, bairro, via).
- `TerritorialRuleService`, quando a regra degrada por camada ou ponto indisponível.

### Reprocessar

`ReprocessarEtapasService::reprocessar(ViabilityRequest)`:

1. Lê as etapas `indisponivel`, na ordem da tabela acima.
2. Para na primeira que continuar indisponível, sem tentar as seguintes.
3. Para as que voltarem, grava o dado (ponto, polígono, zona, via, bairro) e marca `ok`.
4. Chama `FluxoExpressoService::reavaliar`.

`FluxoExpressoService::reavaliar`:

- `Protocolada`: comporta-se como `decide` hoje.
- `EmAnalise` com `analysis_status = ParaDistribuir` (nenhum analista assumiu): refaz a resolução. Se o veredito virar definitivo, transiciona para `deferida` ou `indeferida` pelo caminho de emissão existente. Se continuar pendente, apenas atualiza o motivo e registra a reavaliação.
- Qualquer outro estado, ou análise já assumida: não reavalia e informa o motivo. O trabalho do analista nunca é sobrescrito.

### Agendamento

Comando `processos:reprocessar-etapas`, agendado a cada 15 minutos, com `withoutOverlapping` e `onOneServer`.

- Só elege o processo cujas etapas pendentes são **todas** `indisponivel`. Se houver outro motivo de encaminhamento (risco alto, tipo de imóvel, regra territorial incidente), o retorno do serviço não muda o resultado.
- Intervalo: a cada 15 minutos nas 4 primeiras tentativas, depois de hora em hora, até 24 horas desde a primeira falha. Depois disso, `esgotada_em` é preenchido e o agendamento deixa de tentar.
- Parâmetros em `config/sile.php` (`reprocessamento.*`), ajustáveis sem deploy.
- Aceita `--processo=` e `--limite=` para uso manual em lote.

### Ficha de análise

- Card "Etapas do motor": lista com ícone de situação, serviço, motivo, tentativas e próxima tentativa.
- Botão "Reprocessar" por processo, visível a quem tem a permissão `processos.reprocessar`. Chama `POST` na rota nomeada `gestao.processos.reprocessar`, devolve o resultado por mensagem e atualiza o card.
- Etapa esgotada aparece destacada, com o botão ainda disponível.

### Permissão e auditoria

- Permissão `processos.reprocessar`, concedida a analista e administrador.
- Cada tentativa grava `activity_log` (`motor` / `etapa`) com processo, etapa, serviço, situação e ator (`null` quando automático). Sem dado pessoal nas propriedades.

## Testes

TDD, um teste por comportamento:

- Registro: cada ponto de gravação cria a etapa com a situação correta.
- Reprocesso: etapa indisponível que volta vira `ok` e grava o dado; a seguinte não é tentada quando a anterior continua indisponível.
- Reavaliação: processo em análise sem analista passa a `deferida` quando os dados completam; com analista que já assumiu, não muda.
- Veredito que continua pendente apenas atualiza o motivo.
- Agendamento: elege só processos com todas as etapas indisponíveis; respeita intervalo e esgotamento; é idempotente.
- Ficha: o payload traz as etapas; o botão exige a permissão; sem permissão devolve 403.
- Auditoria: toda tentativa deixa registro.

## Riscos

- Reavaliar processo em análise muda um resultado que o requerente já viu como "em análise". Mitigação: só ocorre sem analista atribuído, e a transição fica na linha do tempo do processo.
- Serviço fora do ar de vez gera carga de tentativas. Mitigação: esgotamento em 24 h e parada na primeira etapa indisponível.
- Gravar etapas em vários serviços aumenta o acoplamento. Mitigação: um único `EtapaMotorRegistrador`, sem lógica de negócio nos serviços chamadores.

## Perguntas abertas

Nenhuma pendente de decisão de produto, além da confirmação das três decisões assumidas.
