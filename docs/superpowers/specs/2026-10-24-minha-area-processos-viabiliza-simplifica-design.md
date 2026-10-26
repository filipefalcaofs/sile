# Minha Área do Portal de Serviços com os processos do Viabiliza e do Simplifica, no ambiente de teste

Data: 24/10/2026. Repositório: SAD da SEDUR (`~/Downloads/fabrica/sedur-mais/sad`), branch `viabiliza-minha-area`. Ambiente: stacks de teste no Portainer `144.22.212.3` (endpoint 3).

## Intenção

No Portal de Serviços que subimos no Portainer, o botão "Minha área" hoje é um atalho falso, porque a Minha Área de verdade roda em outro sistema da SEDUR, o SAD, que não estava no ar. O usuário quer:

1. Subir a Minha Área (SAD) junto do portal de teste, para o fluxo real aparecer: entrar pelo Portal e cair em "Meus Processos".
2. Manter a lista "Meus Processos" que já existe (processos do SAD) e acrescentar duas listas novas com os processos ligados ao CPF de quem entrou:
   - **Processos de viabilidade (Viabiliza)**: o bloco já preparado na branch `viabiliza-minha-area`.
   - **Processos do sistema anterior (Simplifica)**: bloco novo, pela API do legado.

## Metas

- `portal-teste.../` → "Minha área" → login de demonstração → `/minha-area` do SAD, com as três listas.
- A lista do Viabiliza usa a API `GET /api_integracao/portal-servicos/processos` do Viabiliza de teste. O Detalhar abre o processo na consulta do Viabiliza.
- A lista do Simplifica usa a API do legado `GET /v1/processos?documento=<cpf>`, com um token de cliente próprio do SAD, com escopo `processos:ler`.
- Falha ou lentidão de uma API não derruba a página: a lista mostra "indisponível" e as outras continuam.

## Não metas

- Mudar o SAD de produção ou enviar algo ao GitLab da SEDUR. Tudo fica na branch local e nas stacks de teste.
- Detalhar processo do Simplifica: o legado não tem tela pública de processo, e o Viabiliza não consulta processo do legado.
- Login real pelo GOV.BR. O teste usa o login de demonstração que já existe (token base64 com cpf, nome, e-mail e telefone).

## Anti-metas

- Mostrar processos de outro CPF.
- Expor no navegador o token do cliente da API do legado.
- A Minha Área de teste depender de Oracle, do Harbor da prefeitura ou do GitLab interno para subir.

## Decisões do usuário

- Incluir a Minha Área no portal de teste do Portainer.
- A lista atual fica. Entram duas novas: Viabiliza e Simplifica, ambas filtradas pelo CPF do cidadão logado.

## Como fica

### Bloco do Simplifica no SAD

- Serviço `SimplificaProcessosService` no mesmo molde do `ViabilizaProcessosService` (closures injetáveis, `disponivel()`, `listar(cpf)`), lendo `config('simplifica.url')`, `config('simplifica.token')` e `config('simplifica.timeout')` (env `SIMPLIFICA_API_URL`, `SIMPLIFICA_API_TOKEN`, `SIMPLIFICA_TIMEOUT`, padrão 5 s).
- Chamada servidor a servidor: `GET {url}/v1/processos?documento={cpf}`, com Bearer do cliente. O CPF é o do `Contato` logado (`cont_cpf`), só com dígitos e só com 11 dígitos. Sem CPF válido, a lista não aparece.
- Resposta `{data:[{numero, criado_em, status:{nome, fim}, numeros_tvl}]}` → linhas com Processo, Data (d/m/Y), Situação (`status.nome`, badge "Finalizado" quando `fim`) e Viabilidades (números das TVLs).
- 401/403 do legado → "indisponível" com `report()`; outras falhas → "indisponível".
- Widget `ProcessosSimplificaWidget` na página Meus Processos, abaixo do bloco do Viabiliza e acima da tabela do SAD. Título "Processos do sistema anterior (Simplifica)".
- Testes unitários do serviço no padrão de `tests/Unit/ViabilizaProcessosServiceTest.php`.

### Bloco do Viabiliza

- Fica como está. A URL do Viabiliza passa a valer também com `VIABILIZA_DEMO=true`, como no portal de teste, para funcionar fora de produção.

### Ambiente de teste

- Imagem do SAD montada no mesmo esquema do portal de teste: `php:8.4-apache` com `pdo_pgsql`, vendor montado localmente com a mesma cópia da biblioteca `sedur-mais-shared` que o portal de teste usa (modo `pg_local`), `PULSE_ENABLED=false`, sessão em arquivo.
- Serviço `psd-sad` na stack `portal-servicos-demo`, usando o mesmo Postgres (`psd-pgsql`, com o esquema `pr_*` já carregado pelo portal) e as redes `psd_net`, `ssl_default` e `legado-simplifica_default`.
- Endereço `minha-area-teste.144.22.212.3.nip.io` → `psd-sad:80`, publicado no mesmo `http.conf` do proxy em que estão os outros dois endereços de teste.
- No portal de teste, `SEDUR_ENDPOINT_MINHA_AREA_LOGIN_GOVBR` aponta para `{SAD}/login/govbr` e `SEDUR_ENDPOINT_MINHA_AREA` para `{SAD}/minha-area`. Sai o atalho falso `/demo/minha-area-login`.
- O botão Voltar da Minha Área leva à Carta do portal de teste.
- Cliente novo na API do legado, `sad-minha-area-teste`, com escopo `processos:ler`. O token fica só no ambiente da stack.

## Riscos a conferir primeiro

- O SAD grava o `Contato` com sequência no estilo Oracle (`pr_contato_seq.nextval from dual`). Se a biblioteca em modo `pg_local` não cobrir isso, o login de demonstração quebra. Conferir antes de montar a imagem. Se quebrar, a correção vai só no ambiente de teste, com uma sequência ou um ajuste no modo `pg_local`, sem mudar o código do SAD que vai à SEDUR.
- A API do legado lista pelo CPF do requerente do processo (`PRO_USU_ID`) ou pelo CNPJ da TVL, e não pelo outorgante. É o comportamento aceito para o teste.

## Deixado para quem implementa

- Nome dos arquivos de infraestrutura e dos scripts.
- Ordem dos blocos, se a página ficar melhor com o Simplifica abaixo da tabela do SAD.

## Decisões minhas (revisão da spec)

1. **Sequência do Contato:** sem risco. Em `pg_local` a biblioteca usa `nextval('pr_contato_seq')`, e a sequência vem da carga do esquema do portal de teste.
2. **Dados reais num endereço público:** a API do legado lê a base real do Simplifica, e o login de demonstração aceita um token montado à mão. Por isso o endereço `minha-area-teste...` fica protegido por usuário e senha no proxy (Basic Auth). A credencial fica no `segredos.json` da stack, fora do repositório e fora do chat.
3. **Dependências do SAD:** a imagem só precisa da Minha Área. Primeiro tenta-se `composer install` com acesso de leitura ao GitLab da SEDUR, trocando a `sedur-mais-shared` pela cópia do portal de teste (repositório `path`). Sem acesso ao GitLab, a imagem é montada com o vendor do portal de teste. O painel do servidor do SAD pode ficar quebrado nessa imagem, e isso é aceito.

## Revisão de 24/10 (tarde): uma listagem só

Decisão do usuário: a Minha Área funciona igual à original; a diferença é que a mesma listagem "Meus Processos" traz também os processos do Viabiliza e do Simplifica, cada um com botão que abre a consulta de processo do Viabiliza já filtrada pelo número.

- Os blocos separados saem. A tabela da página passa a ter fonte própria (`records()` do Filament 4), que junta:
  - processos do SAD (a mesma consulta `minhaArea()` de hoje), com o botão Detalhes de sempre;
  - processos do Viabiliza (API do Viabiliza), com botão que abre `…/portal/entrar/portal-servicos?destino=processo&numero=<número>&token=<sessão>`;
  - processos do Simplifica (API do legado, pelo CPF), com o mesmo botão, pelo número do processo do Simplifica.
- Colunas: Origem (SAD, Viabiliza, Simplifica), Protocolo, Estágio, Data, Serviço.
- Abas iguais às de hoje. SAD: como hoje. Viabiliza: convite pendente → Em convite; deferida, indeferida, cancelada, arquivada → Finalizados; o resto → Em andamento. Simplifica: status com fim → Finalizados; o resto → Em andamento. Os contadores das abas somam as três fontes.
- Busca no texto de protocolo, estágio, data e serviço das três fontes; ordenação por data, mais recente primeiro; paginação de 10 em 10, como a padrão.
- Decisão minha: a ação em massa de excluir (só fora de produção, para documentos do SAD) sai, porque a tabela mista não tem como excluir linhas de fora.
