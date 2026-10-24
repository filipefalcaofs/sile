# Viabiliza no Portal de Serviços e na Minha Área (lado SEDUR) — mudanças preparadas localmente

Data: 23/10/2026. Complementa `2026-10-23-entrada-pelo-portal-de-servicos-design.md` e `2026-10-23-socio-regin-e-consulta-por-cpf-design.md`.

## Intenção

O usuário pediu (23/10/2026) para preparar **localmente, sem enviar**, só a parte que é do Viabiliza nos sistemas da SEDUR: os serviços da Carta no `sedur-mais/portais/portal-servicos` e a lista de processos com o Detalhar na Minha Área (`sedur-mais/sistemas/sad`). A responsabilidade pelo Portal é da Sudoeste (Ricardo Luz); o resultado é entregue como pacote de mudanças (patch), para quem tiver acesso de escrita aplicar.

## portal-servicos (Carta de Serviços)

- `config/viabiliza.php` próprio do portal (não mexe na `laravel-libs`, compartilhada): `url` (`VIABILIZA_URL`, sem padrão) e `servicos` = `[345 => 'renovacao', 723 => 'nova', 7374 => 'autonomo']` (Renovação de Viabilidade, Viabilidade de Localização, Profissional Liberal/Autônomo Estabelecido). MEI fica de fora (em revisão na SEDUR).
- `App\Services\ViabilizaService`: `atende(int $servicoId): bool`; `urlDoServico(int $servicoId, ?string $token): ?string` → `<url>/portal/entrar/portal-servicos?destino=<destino>&token=<token>`; `urlDoProcesso(string $numero, ?string $token): ?string` → `destino=processo&numero=<numero>`. Sem `url` configurada, tudo devolve `null`/`false` e o portal segue como hoje.
- `NavigateService::aberturaProcesso`: primeira verificação — se o serviço é do Viabiliza, devolve a URL dele (antes da regra `servicos_rota`, que hoje manda o 7374 para o portal antigo).
- Botão "Abrir processo" (`servico-detalhes/actions/index.blade.php`): `target="_blank" rel="noopener"` quando o serviço é do Viabiliza (nova aba, sem iframe).
- Sem sessão, `NavigateService::navigate` já manda ao SSO com `state` = página do serviço; na volta o cidadão clica de novo. Nada a mudar.

## Minha Área (SAD)

Lista "Meus Processos" passa a incluir os processos do Viabiliza, buscados em `GET <viabiliza>/api_integracao/portal-servicos/processos` com `Authorization: Bearer <token da sessão>`, e cada um ganha o botão Detalhar (nova aba) para `detalhar_url` + `&token=<token>`. Falha da consulta não derruba a página: a seção mostra aviso de indisponibilidade. Detalhes de encaixe dependem do código do SAD (bloqueado: GitLab da SEDUR fora do ar em 23/10/2026 à noite, 502).

## Validação

- O portal-servicos não instala as dependências aqui (pacotes privados no GitLab da SEDUR e extensão Oracle `oci8`), então a suíte dele não roda. A lógica nova fica numa classe sem dependência do framework além de `config()`, com teste unitário PHPUnit puro executado isoladamente; o restante é conferido com `php -l` e leitura.
- Entrega: `git format-patch` de cada repositório em `/Users/filipefalcao/Downloads/fabrica/sedur-mais/patches/`, fora dos repositórios da SEDUR e do Viabiliza.

## Decisões minhas

- Configuração própria no portal em vez de entrada nova em `servicos_sudoeste` (o Viabiliza é da SEDUR, não da Sudoeste; e a lib é compartilhada).
- Sem `VIABILIZA_URL`, comportamento atual mantido (liga por ambiente).
- 723 como "Viabilidade Locacional" (é o serviço "Viabilidade de Localização" da Carta).
