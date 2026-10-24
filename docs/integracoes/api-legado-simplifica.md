# API do legado Simplifica: ligação do portal

O portal consulta a API do legado (somente leitura) para listar e renovar as TVLs provisórias do sistema anterior. O desenho está em `docs/superpowers/specs/2026-10-19-api-legado-viabilidade-design.md`.

## Variáveis do portal

| Variável | Uso |
|---|---|
| `LEGADO_API_URL` | Base da API, com ou sem `/v1`. No Portainer de dev, o `legado-web` publica a porta 8083 (`http://<host-do-portainer>:8083`); dentro da rede `sile_net` a API responde em `http://legado-api:8000`. |
| `LEGADO_API_TOKEN` | Token Bearer de um cliente da API com os escopos `provisorias:ler` e `processos:ler`. |

Sem as duas variáveis o portal trata a API como indisponível: as TVLs do Viabiliza continuam na lista e as legadas mostram um aviso.

## Emitir e trocar o token

O token só aparece na criação. Rode no servidor onde está o contêiner:

```bash
docker exec legado-api php artisan clientes:criar viabiliza --escopo=provisorias:ler --escopo=processos:ler
```

O token vale por 90 dias (`legado.cliente_dias` na API). Depois disso as consultas passam a responder 401 e o portal volta a tratar a API como indisponível. Para trocar, crie um cliente novo, atualize `LEGADO_API_TOKEN` e revogue o antigo:

```bash
docker exec legado-api php artisan clientes:revogar <id>
```

## Conferência depois da ligação

1. `GET <LEGADO_API_URL>/v1/provisorias?cnpj=<cnpj>` com o token deve devolver 200.
2. Na tela de renovação do portal, uma empresa com TVL legada deve listá-la marcada como "legado".
3. A renovação de uma TVL sem CNPJ gravado só libera depois da conferência de razão social e endereço.

## Pendências de implantação

- Fechar a publicação da porta 8082 do SQL Server na stack `legado-simplifica`.
- O `legado-web` expõe a porta 8083 no host; restringir se a API for usada só pela rede interna.
