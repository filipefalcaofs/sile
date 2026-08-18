# HU-110 — Integrar com SEFAZ municipal

> **Status: Confirmada** — o deferimento da viabilidade é comunicado à SEFAZ via API no fluxo atual. A API da SEFAZ Salvador já é conhecida e utilizada em produção pelo projeto SIGVISA (`sls-sms/docs/DAM-SEFAZ-IMPLEMENTACAO.md`): autenticação JWT via SenhaWeb (`POST /users/authenticate`), base de produção `https://api.sefaz.salvador.ba.gov.br/`, homologação `https://apihmle.sefaz.salvador.ba.gov.br/` (instável). Endpoints conhecidos: consulta de estabelecimento por CGA, lista de estabelecimentos por data e consulta de pagamento de DAMs. **Pendente**: confirmar o endpoint específico de envio do deferimento de viabilidade (não consta na documentação do SIGVISA, que só consome consultas).

## Épica
**EP13 — Integrações**

## Objetivo
Enviar à SEFAZ municipal, via API, os dados da viabilidade deferida, permitindo a continuidade do processo de licenciamento (alvará de funcionamento) e a atualização cadastral.

## História de Usuário
**Como** sistema,  
**quero** enviar os dados da viabilidade deferida à SEFAZ via API,  
**para** que o requerente dê continuidade ao licenciamento sem retrabalho.

## Contexto de Negócio
Conforme informado pela SEDUR, após a conclusão do processo de viabilidade a resposta é encaminhada para dois destinos: a Junta Comercial (integrador), com o parecer de deferimento ou indeferimento, e a SEFAZ municipal, que recebe os dados da viabilidade deferida via API. Nos casos em que o requerente já possui CNPJ, ele segue diretamente com a SEFAZ.

## Atores Envolvidos
- Sistema SILE.
- API da SEFAZ municipal.
- Servidor ou analista da SEDUR (acompanhamento de falhas), quando aplicável.

## Pré-condições
- Processo de viabilidade concluído com resultado deferido.
- Credenciais e contrato da API da SEFAZ configurados.

## Fluxo Principal
1. O sistema identifica processo de viabilidade deferido (fluxo expresso ou análise técnica).
2. O sistema monta o payload com os dados da viabilidade conforme o contrato da API.
3. O sistema envia os dados à API da SEFAZ.
4. O sistema valida a resposta e registra o comprovante de entrega.
5. O sistema atualiza o status de integração do processo.
6. O sistema registra a operação em trilha de auditoria.

## Fluxos Alternativos
### FA-01 — Falha de integração
1. A API da SEFAZ fica indisponível ou retorna erro.
2. O sistema registra a falha com o detalhe do erro.
3. O sistema realiza retentativas automáticas com intervalo progressivo.
4. Persistindo a falha, o caso é sinalizado para tratamento manual sem bloquear a decisão já comunicada ao integrador Regin.

### FA-02 — Resposta de rejeição da SEFAZ
1. A API responde com rejeição de dados (validação).
2. O sistema registra o motivo.
3. O caso é encaminhado para tratamento (correção de dados ou contato com a SEFAZ).

### FA-03 — Envio duplicado
1. O sistema identifica que o processo já foi enviado com sucesso.
2. O reenvio é bloqueado ou tratado de forma idempotente.

## Regras de Negócio
- RN-001: Somente processos com resultado **deferido** (fluxo expresso HU-076 ou análise técnica HU-086) devem ser enviados à SEFAZ. Indeferimentos seguem apenas para o integrador Regin (HU-104).
- RN-002: Toda tentativa de envio (sucesso ou falha) deve ser registrada em auditoria com data, hora, payload e resposta.
- RN-003: O envio deve ser idempotente — reenvios não podem duplicar registros na SEFAZ.
- RN-004: Falha na integração não pode reverter nem bloquear a decisão de viabilidade já tomada; deve gerar pendência operacional de reenvio.
- RN-005: Dados sensíveis trafegados devem respeitar a LGPD e o contrato da API (autenticação e transporte seguro).
- RN-006: A autenticação JWT deve ser cacheada (TTL do token menos margem de segurança); em resposta 401, invalidar o cache, renovar o token e repetir a requisição.
- RN-007: Respostas da SEFAZ devem ser normalizadas: campos com nomes variáveis, booleanos "S"/"N", CEP/CNPJ numéricos, datas com prefixo "0001-" tratadas como nulas (comportamentos observados na API real).

## Critérios de Aceite — BDD

### CA-01 — Envio com sucesso
**Dado** que um processo de viabilidade foi deferido,  
**Quando** o sistema enviar os dados à API da SEFAZ,  
**Então** a entrega deve ser confirmada, o status de integração atualizado e o comprovante registrado.

### CA-02 — Auditoria obrigatória
**Dado** que houve tentativa de envio,  
**Quando** a API responder (sucesso, erro ou rejeição),  
**Então** o sistema deve registrar data, hora, origem, payload, resposta e resultado.

### CA-03 — Resiliência a falhas
**Dado** que a API da SEFAZ está indisponível,  
**Quando** o envio falhar,  
**Então** o sistema deve agendar retentativas automáticas e, persistindo a falha, sinalizar para tratamento manual sem alterar a decisão do processo.

### CA-04 — Idempotência
**Dado** que um processo já foi enviado com sucesso,  
**Quando** ocorrer novo disparo de envio,  
**Então** o sistema não deve gerar duplicidade na SEFAZ.

## Campos/Dados Envolvidos
- Identificador do processo de viabilidade.
- Dados da empresa, CNPJ e CNAEs.
- Dados do imóvel e endereço.
- Resultado, condicionantes e fundamentação, conforme contrato da API.
- Comprovante de entrega, status de integração, logs e metadados de auditoria.

## Permissões
- **Sistema:** envio automático.
- **Gestor SEDUR:** consulta do status de integração e tratamento de falhas.
- **Administrador:** configuração de credenciais e parâmetros da integração.

## Exceções e Tratamentos
- API indisponível ou tempo de resposta excedido.
- Rejeição por validação de dados.
- Credenciais inválidas ou expiradas.
- Envio duplicado.

## Auditoria
O sistema deve manter registro completo da execução desta HU, incluindo:
- serviço responsável;
- data e hora;
- payload enviado e resposta recebida;
- número de tentativas;
- resultado;
- erros ou exceções.

## Dependências
- HU-074/HU-076 (deferimento expresso) e HU-086 (deferimento em análise técnica).
- Documentação da API da SEFAZ (contrato, autenticação, homologação) — pendente com a SEDUR.
- Módulo de auditoria.

## Prioridade
Alta

## Observações
Implementação de referência da integração SEFAZ no projeto SIGVISA (`sls-sms`): `app/Services/SefazService.php` (autenticação JWT com cache, retentativas, normalização de respostas), tela de parametrização com credenciais criptografadas e botões de teste de conexão. Padrões de resiliência já validados em produção: timeout 30s, connect timeout 10s, 3 retentativas, renovação automática em 401.

Pendências com a SEDUR: endpoint específico para envio do deferimento de viabilidade (a documentação conhecida cobre apenas consultas), credenciais SenhaWeb para o SILE e acesso à homologação. Avaliar sobreposição com a HU-109 (Portal do Contribuinte) quando o escopo de ambas estiver confirmado.
