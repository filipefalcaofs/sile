# HU-132 — Emitir TVL em PDF no backoffice

> **Status: Confirmada (2026-06-11)** — o TVL **não é entregue ao requerente** (dados vão via API SEFAZ + parecer Regin). O PDF permanece como **relatório administrativo** emitível pelo analista na retaguarda Simplifica.

## Épica
**EP10 — Análise Técnica SEDUR**

## Objetivo
Permitir que analistas e gestores emitam o Termo de Viabilidade de Localização (TVL) em PDF a partir de processo deferido, para arquivo, atendimento presencial, malha fina e auditoria interna.

## História de Usuário
**Como** analista SEDUR,  
**quero** emitir o TVL em PDF pelo backoffice,  
**para** documentar formalmente a decisão deferida sem depender da entrega ao cidadão.

## Contexto de Negócio
Conforme reunião SEDUR (2026-06-11), o canal oficial ao requerente é o Regin/Junta (parecer) e a SEFAZ (dados via API). O PDF/TVL no SAPS legado servia ao cidadão; no SILE passa a ser relatório interno opcional, gerado sob demanda após deferimento (expresso ou humano).

## Atores Envolvidos
- Analista SEDUR.
- Gestor SEDUR.
- Sistema Simplifica (SILE).

## Pré-condições
- Processo com resultado **deferido** (HU-076 ou HU-086).
- Número de produto (TVL) atribuído ao processo.
- Dados consolidados: CNAEs deferidos, condicionantes, enquadramento LOUOS/TLL, fundamentação legal.

## Fluxo Principal
1. Analista acessa o detalhe do processo deferido na retaguarda.
2. Analista aciona "Emitir TVL" / "Imprimir TVL".
3. O sistema monta o documento com dados do processo, condicionantes selecionadas na ficha (HU-135) e fundamentação legal.
4. O sistema gera PDF (e opcionalmente código de verificação/QR para consulta pública de autenticidade).
5. O sistema registra emissão (usuário, data/hora, versão dos dados) em auditoria.
6. O PDF fica disponível para download/reimpressão no histórico do processo — **sem** envio automático ao requerente.

## Fluxos Alternativos
### FA-01 — Processo não deferido
1. Analista tenta emitir TVL em processo indeferido ou em análise.
2. Emissão bloqueada com mensagem explicativa.

### FA-02 — Dados incompletos
1. Faltam condicionantes ou enquadramento obrigatório no PDF.
2. Sistema informa pendências antes de gerar.

## Regras de Negócio
- RN-001: Emissão restrita a processos deferidos e perfis autorizados.
- RN-002: Toda emissão/reimpressão registrada em auditoria.
- RN-003: O PDF **não** substitui integração Regin/SEFAZ — é documento complementar interno.
- RN-004: Conteúdo do PDF deve refletir versão das regras e condicionantes vigentes na data da decisão.
- RN-005: Assinatura parametrizável (HU-014): imagem do diretor (modo legado) ou **assinatura digital gov.br/ICP-Brasil** — a integração `GovBrAssinaturaClient` do SIGVISA é a implementação de referência; oferecer o modo digital desde que credenciais estejam disponíveis. Formato final pendente com Anderson.

## Critérios de Aceite — BDD

### CA-01 — Emissão com sucesso
**Dado** um processo deferido com dados completos,  
**Quando** o analista solicitar emissão do TVL em PDF,  
**Então** o sistema deve gerar PDF com número de produto, atividades deferidas, condicionantes e fundamentação.

### CA-02 — Sem entrega ao requerente
**Dado** que o TVL foi emitido no backoffice,  
**Quando** o processo concluir,  
**Então** nenhum PDF deve ser enviado automaticamente ao cidadão pelo Simplifica.

### CA-03 — Auditoria
**Dado** emissão ou reimpressão,  
**Quando** o PDF for gerado,  
**Então** usuário, data/hora e identificador do processo devem ser registrados.

## Dependências
- HU-076 (resultado expresso), HU-086 (deferimento humano).
- HU-135 (ficha de análise — condicionantes no documento).
- HU-110, HU-104 (integrações já disparadas na conclusão).

## Prioridade
Alta

## Observações
Implementação de referência: SIGVISA `AlvaraService` + rota `/verificar-alvara/{codigo}`. QR code útil para verificação interna/pública do PDF emitido, não como canal principal ao cidadão.
