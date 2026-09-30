# Plano de correção das corretivas da qualidade

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Corrigir os defeitos confirmados da sprint 1.0.0 e devolver ao teste o que não é falha.

**Architecture:** Mensagem e validação ficam no backend, onde o PHPUnit prova o contrato. Texto, contraste, quebra de linha e máscara ficam no React, reusando o componente compartilhado em vez de corrigir tela por tela.

**Tech Stack:** Laravel 13, Inertia React 19, PHPUnit 12, Tailwind 4.

## Global Constraints

- Teste PHPUnit antes do código de produção que muda regra.
- Texto de interface em português.
- Sem fachada: a Simulação REGIN continua criando processo real.
- Sem parâmetro novo.
- 119631 acompanha a correção de 119632.

---

### Task 1: Devoluções

- [ ] Comentar e mover para teste 119662, 119259, 119664, 119578 e 119262.

### Task 2: 404, REGIN, IA, CEP, convite e endereço

**Files:** `bootstrap/app.php`, `ReginConnectionTester.php`, `StoreAiConfigurationRequest.php`, `UpdateAiConfigurationRequest.php`, `AiConfigurationController.php`, `ComprovanteProtocoloService.php`, `PendenciaSolicitadaNotification.php`, `NotificationResource.php`, `ConsultaViabilidadeController.php`, testes correspondentes.

- [ ] 404 JSON sem nome de model.
- [ ] Timeout REGIN em português, sem cURL e sem URL.
- [ ] IA ativa exige chave. O alternar também recusa.
- [ ] CEP do comprovante em 00000-000.
- [ ] Convite aponta para pendências. Solicitação inexistente fica sem link.
- [ ] 422 fora de Salvador inclui `errors.endereco`.

### Task 3: Contingência, ficha, exportação e PDF

**Files:** `RegistrarContingenciaRequest.php`, formulário de contingência, `processos/show.tsx`, `ficha-analise/show.tsx`, `auditoria/index.tsx`, `PdfExporter.php`, breadcrumbs de indicadores e relatórios.

- [ ] Atributos CPF do beneficiário e CNPJ da empresa. Máscara e limite no formulário.
- [ ] Centroide inválido não vai para o mapa.
- [ ] Nova revisão recarrega a ficha sem preservar o estado antigo.
- [ ] CSV da trilha: 200 baixa, 202 avisa e permanece na tela.
- [ ] PDF com mais de sete colunas em paisagem e `Content-Disposition: attachment`.
- [ ] Trilha Painel > Indicadores ou Relatórios > página.

### Task 4: Portal

**Files:** `consulta.tsx`, `accessibility-bar.tsx`, `theme-context.tsx`, `page-header.tsx`, `home.tsx`, `etapa-documentos.tsx`, `protocolo.tsx`, `renovacao/index.tsx`, `resultado-viabilidade.tsx`, `procuracoes/index.tsx`, `acessos.tsx`, centro de notificações.

- [ ] Mensagem municipal na consulta. Contraste restaura o tema salvo.
- [ ] H1, cabeçalho público, cursor do arquivo, copiar link, quebra de justificativa, rótulo do requisito, máscara e erro da renovação.

### Task 5: Gestão visual e texto

**Files:** `app-sidebar.tsx`, `data-table.tsx`, `pagination.tsx`, `button.tsx`, `label` nas telas citadas, `parametros/index.tsx`, `territorio/index.tsx`, `textos-padrao/index.tsx`, `config-email`, `config-apis`, `caixa-setor`, `emails/index.tsx`, textos de indicadores e auditoria, CEP da ficha.

- [ ] Contraste, asterisco, grupos de parâmetro, feição no singular/plural, categoria estável, wrap em 320 px, modal com rolagem e erro, textos sem voz interna.

### Task 6: Verificação

- [ ] `php artisan test --compact` nos arquivos de teste alterados.
- [ ] `vendor/bin/pint --dirty --format agent`.
- [ ] Comentar a solução e mover os cards corrigidos para teste, com a origem quando ela não tiver outro filho ainda em aberto.
