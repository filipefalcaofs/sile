# HU-076 — Emitir resultado expresso

## Épica
**EP09 — Fluxo Expresso**

## Objetivo
Concluir automaticamente o processo de viabilidade (deferido ou indeferido), comunicar o resultado ao integrador Regin/Junta, enviar dados à SEFAZ quando deferido e registrar o produto (número do TVL) para emissão de relatório PDF no backoffice pelo analista.

## História de Usuário
**Como** sistema,  
**quero** emitir resultado expresso,  
**para** finalizar processo sem intervenção humana e integrar Regin, SEFAZ e retaguarda SEDUR.

## Contexto de Negócio
O SILE deverá apoiar a SEDUR na gestão da viabilidade locacional de atividades econômicas, priorizando automação, precisão, rastreabilidade e redução de análise manual. Esta HU faz parte do fluxo de Portal do Cidadão, Retaguarda SEDUR, Motor de Regras da LOUOS, integrações, auditoria ou indicadores, conforme sua épica.

## Atores Envolvidos
- Cidadão / empresário / contador / procurador, quando aplicável.
- Servidor ou analista da SEDUR, quando aplicável.
- Administrador do sistema, quando aplicável.
- Sistema SILE e serviços integrados.

## Pré-condições
- Usuário autenticado quando a funcionalidade exigir identificação.
- Perfil com permissão compatível.
- Parâmetros e cadastros básicos disponíveis.
- Regras, integrações ou bases oficiais configuradas quando aplicável.

## Fluxo Principal
1. O sistema identifica processo elegível concluído pelo fluxo expresso (HU-074 ou HU-075).
2. O sistema consolida fundamentação legal, regras aplicadas, condicionantes e resultado por CNAE.
3. O sistema atribui número de produto (TVL) ao processo deferido, para rastreio interno e emissão de relatório.
4. O sistema comunica o parecer (deferido ou indeferido) ao integrador Regin/Junta Comercial via HU-104.
5. Quando deferido, o sistema dispara envio dos dados da viabilidade à SEFAZ municipal via API (HU-110).
6. O sistema **não** disponibiliza PDF/TVL ao requerente — o cidadão acompanha o resultado pelo Regin/Junta ou portal Simplifica.
7. O sistema disponibiliza na retaguarda os dados consolidados para emissão opcional de PDF/TVL pelo analista (HU-132), quando necessário.
8. O sistema registra decisão, integrações e metadados em trilha de auditoria.

## Fluxos Alternativos
### FA-01 — Dados incompletos
1. O sistema identifica ausência de dados obrigatórios.
2. O sistema informa os campos pendentes.
3. O usuário complementa as informações.
4. O sistema permite nova tentativa.

### FA-02 — Regra não encontrada
1. O sistema não encontra regra parametrizada suficiente.
2. O processo não deve ser decidido automaticamente.
3. O sistema encaminha para análise técnica, quando aplicável.

### FA-03 — Falha de integração
1. Serviço externo fica indisponível ou retorna erro.
2. O sistema registra a falha.
3. O sistema permite retentativa, processamento posterior ou encaminhamento para análise.

### FA-04 — Usuário sem permissão
1. O sistema identifica ausência de permissão.
2. O acesso é bloqueado.
3. A tentativa é registrada para auditoria.

## Regras de Negócio
- RN-001: O sistema deve validar dados obrigatórios antes de avançar o fluxo.
- RN-002: Toda ação relevante deve ser registrada em auditoria com usuário, data, hora e origem.
- RN-003: O usuário somente poderá executar a ação se possuir permissão compatível com seu perfil.
- RN-004: Decisão automática somente deve ocorrer quando todas as regras necessárias forem encontradas e não houver conflito.
- RN-005: O sistema deve registrar a fundamentação legal, dados de entrada, regras aplicadas e resultado final.
- RN-006: Casos ambíguos, sem dado confiável ou com conflito normativo devem ir para análise técnica.
- RN-007: O resultado deferido deve gerar número de produto (TVL) e dados consolidados do Termo de Viabilidade de Localização, com fundamentação na LOUOS (Lei nº 9.148/2016) e no PDDU (Lei nº 9.069/2016). A emissão do PDF/TVL é função da retaguarda (HU-132) — **não** é entregue automaticamente ao requerente.
- RN-008: O resultado (deferido ou indeferido) deve ser comunicado ao integrador Regin/Junta Comercial (HU-104). Quando deferido, os dados da viabilidade devem ser enviados à SEFAZ municipal via API (HU-110).
- RN-009: Deferimento exige que **todas** as atividades (CNAEs) estejam deferidas; uma indeferida indefere o processo (regra confirmada no SAPS legado).
- RN-010: O PDF/TVL gerado no backoffice pode conter código de verificação e QR code para autenticidade interna ou consulta pública — é relatório administrativo, não canal oficial de entrega ao cidadão (canal oficial: Regin + API SEFAZ).

## Critérios de Aceite — BDD

### CA-01 — Execução com sucesso
**Dado** que o usuário possui permissão e informou os dados obrigatórios,  
**Quando** executar a funcionalidade **Emitir resultado expresso**,  
**Então** o sistema deve processar a solicitação e apresentar ou registrar o resultado esperado.

### CA-02 — Auditoria obrigatória
**Dado** que a funcionalidade foi executada,  
**Quando** houver alteração, decisão, consulta relevante ou integração,  
**Então** o sistema deve registrar usuário, data, hora, origem, ação executada e resultado.

### CA-03 — Bloqueio por inconsistência
**Dado** que existam dados inconsistentes, regra ausente, conflito ou falha crítica,  
**Quando** o sistema não puder garantir precisão,  
**Então** a decisão automática deve ser bloqueada e o caso deve seguir para tratamento adequado.

### CA-04 — Segurança de acesso
**Dado** que o usuário não possui permissão,  
**Quando** tentar acessar ou executar a funcionalidade,  
**Então** o sistema deve impedir a ação e registrar o evento.

## Campos/Dados Envolvidos
- Identificador da solicitação/processo.
- Identificação do usuário responsável.
- Dados da empresa, CNPJ, CNAE e atividade econômica, quando aplicável.
- Dados do imóvel, endereço, inscrição imobiliária, zona, via e área ocupada, quando aplicável.
- Resultado, status, parecer, condicionantes e fundamentação legal, quando aplicável.
- Logs, integrações e metadados de auditoria.

## Permissões
- **Cidadão/Contador/Procurador:** acesso às próprias empresas e solicitações.
- **Analista SEDUR:** consulta e análise dos processos atribuídos ou disponíveis.
- **Gestor SEDUR:** acompanhamento, distribuição, parametrização e relatórios.
- **Administrador:** configuração de regras, usuários, perfis e parâmetros.

## Exceções e Tratamentos
- Dados obrigatórios ausentes.
- Endereço não localizado.
- CNAE inexistente ou desatualizado.
- Área ocupada inválida.
- Regra da LOUOS não parametrizada.
- Conflito entre regras.
- Serviço externo indisponível.
- Documento ilegível ou incompatível, quando aplicável.
- Processo duplicado ou já encerrado.

## Auditoria
O sistema deve manter registro completo da execução desta HU, incluindo:
- usuário ou serviço responsável;
- data e hora;
- dados de entrada;
- regras aplicadas;
- resultado;
- erros ou exceções;
- versão das regras, quando aplicável.

## Dependências
- Cadastro de usuários e perfis.
- Cadastro empresarial e CNAEs.
- Motor de regras da LOUOS.
- Base geográfica/GIS.
- Classificação de risco.
- Integrações externas, quando aplicável.
- Módulo de auditoria.

## Prioridade
Alta

## Observações
Refinada com base na reunião SEDUR (2026-06-11): o documento de viabilidade **não é mais liberado ao requerente** — os dados seguem via API à SEFAZ e o parecer via Regin. O PDF/TVL permanece como **relatório emitível pelo analista no backoffice** (HU-132), útil para arquivo, malha fina e atendimento presencial.

O DAM de viabilidade via Regin é emitido/pago pela SEFAZ — fora do escopo de geração no SILE (ver HU-071 revisada). Pendente confirmar formato final do PDF com Anderson (assinatura: hoje é imagem do diretor, não ICP-Brasil).

Implementação de referência no SIGVISA (`sls-sms`): geração de PDF com QR code (`AlvaraService` + rota `/verificar-alvara/{codigo}`) — avaliar reutilização para HU-132.
