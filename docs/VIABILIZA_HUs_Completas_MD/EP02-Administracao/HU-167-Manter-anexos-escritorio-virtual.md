# HU-167 — Manter anexos de escritório virtual

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Publicar, com quatro olhos, os Anexos A e B do Decreto nº 35.062/2021 que definem CNAEs de sede e de abrigado.

## História de Usuário
**Como** mantenedor com `manter-cnaes`,
**quero** importar os dois CSVs para um rascunho, ver o diff e publicar com outro usuário,
**para** a lista vigente de escritório virtual mudar só na publicação.

## Atores e permissões
- A tela exige `manter-cnaes`. Menu Regras, item Anexos escritório virtual (`/gestao/escritorio-virtual/anexos`).
- A permissão é a mesma do cadastro de CNAE. Não há permissão própria de anexo.

## Fluxo evidenciado
1. A página mostra a versão vigente (versão, data, quem publicou, fonte e totais dos anexos A e B), o rascunho aberto (versão, autor, diff e totais) e o histórico do domínio `atividades_escritorio_virtual`.
2. A versão sugerida no formulário é `ev-anexos-` mais a data do dia.
3. Importar recebe versão, fonte e os dois arquivos. O serviço grava rascunho. A mensagem informa quantos CNAEs entraram em cada anexo e que a vigente não mudou.
4. Falha de formato volta a mensagem do import, sem o caminho do arquivo temporário. Versão já publicada volta `DomainException`.
5. Publicar localiza o rascunho daquela versão e chama o serviço. O autor não publica o próprio rascunho (`FourEyesViolationException`). Sem rascunho, a mensagem informa que não há rascunho aberto com essa versão.
6. O motor lê a lista vigente. O rascunho não entra em `permitidoNoAnexo` antes da publicação, conforme o comentário do controller.

## Regras de negócio evidenciadas
- Os dois anexos sobem juntos. Não há publicação de um só.
- A versão vigente só muda no publish.
- O domínio é sensível: publicador diferente do autor.
- O cabeçalho esperado dos CSVs, declarado no Form Request, é `cnae_code,cnae_description`.

## Validações
- `versao` obrigatória, até 60, padrão `^[a-z0-9\-]+$`. Mensagem de exemplo: `ev-anexos-2026-09-18`.
- `fonte` obrigatória, até 255.
- `anexo_a` e `anexo_b` obrigatórios, arquivo CSV ou texto, até 5 MB cada.

## Critérios de aceite
### CA-01 — Rascunho sem trocar a vigente
**Dado** uma versão vigente e um mantenedor com `manter-cnaes`,
**Quando** importa os dois CSVs numa versão nova,
**Então** nasce rascunho com a contagem de cada anexo e a vigente permanece.

### CA-02 — Quatro olhos
**Dado** o autor do rascunho,
**Quando** ele tenta publicar,
**Então** a publicação é recusada. Outro usuário com `manter-cnaes` publica e a mensagem confirma a atualização dos anexos vigentes.

### CA-03 — Versão mal formada
**Dado** uma versão com letra maiúscula ou espaço,
**Quando** o import é enviado,
**Então** a validação recusa `versao`.

### CA-04 — Sem rascunho
**Dado** uma versão que não está em rascunho,
**Quando** alguém tenta publicar esse nome,
**Então** a publicação informa que não há rascunho aberto com essa versão.

## Divergências da redação anterior
Não havia HU. A capacidade existia no código sem rastreabilidade.

## Evidências
- `routes/gestao.php`: `escritorio-virtual.anexos.index`, `store`, `publicar`.
- `app/Http/Controllers/Gestao/EscritorioVirtualAnexosController.php`.
- `app/Http/Requests/Gestao/PublicarAnexosEscritorioVirtualRequest.php`.
- `RuleDomain::AtividadesEscritorioVirtual`.
- `resources/js/navigation/gestao-nav.ts`: Anexos escritório virtual em Regras.

## Não evidenciado
- Edição de um CNAE do anexo linha a linha. A manutenção é o par de CSVs.
- Publicação pelo autor do rascunho.
- Permissão distinta de `manter-cnaes`.
