# Contrato — Endpoint de consulta de TVL (WS SIGS)

Fonte: arquivo "Endpoint de consulta de TVL.txt" enviado por Dayvson Reis (SEFAZ) em 18/08/2026,
complementado pela demonstração em tela durante a reunião.

## Endpoints existentes (WS SIGS)

| Finalidade | Chamada |
|---|---|
| Dados de TVL consumidos pela SEFAZ | `GET http://ws-sigs.pms.ba.gov.br/viabilidades/v2/buscarPorNumeroTVLRegin/{numeroTVL}` |
| Dados para migração de TVL SEDUR → plataforma de viabilidade | `GET http://ws-sigs.pms.ba.gov.br/migracao-simplifica/{numeroTVL}` |

## Enum de status do TVL

```text
NAOENCONTRADO("00", "TVL não encontrado."),
CONCEDIDO("01", "TVL concedido."),
INVALIDO("02", "TVL Inválido, não deferido."),
UTILIZADO("03", "TVL Utilizado."),
ERROPROCESSAMENTO("99", "Erro no processamento.");
```

## Exemplo de resposta — `buscarPorNumeroTVLRegin/400143`

```json
{
    "resposta": {
        "protocolo": "400143",
        "status": "01",
        "mensagem": "TVL concedido.",
        "cnpj_instituicao": "02027634550"
    },
    "empresa": {
        "nome_requerente": null,
        "inscricao_imobiliaria": "115355",
        "area_utilizada": "511,00",
        "tipo_imovel": null,
        "porte_empresa": "Microempresa",
        "cep": "40330200",
        "uf": "BA",
        "cod_municipio": "38490",
        "municipio": "Salvador",
        "bairro": "IAPI",
        "cod_log": "1253",
        "tipo_logradouro": "Rua",
        "logradouro": "Conde de Porto Alegre",
        "numero": "184",
        "complemento": "  EDIF:EDF CONDE PORTO ALEGRE;LOJA:01",
        "processo": "5921000030-00057338/2024",
        "tipoTVL": "Definitivo",
        "zona": "ZCMe-1/02",
        "via": "VC-I",
        "hashDam": "",
        "observacao": "A análise da Viabilidade de Localização, por si só, não autoriza o funcionamento do estabelecimento. (...) [texto legal completo de condicionantes]",
        "data_vencimento": null,
        "data_viabilidade": null,
        "data_ini_viabilidade": "20240925 153451",
        "data_fim_viabilidade": "20241029 161806",
        "nome_impresso_TVL": "JONIS OLIVEIRA CARMO",
        "tipo_viabilidade": 1,
        "sede_escritorio_virtual": "0",
        "grupo_cnae": [
            {
                "codigo_cnae": "4712100",
                "tipo_cnae": "2",
                "categoria": "nR1-01",
                "descricao": "Comércio varejista de mercadorias em geral, com predominância de produtos alimentícios - minimercado, mercearias e armazéns"
            }
        ],
        "tvlSimplifica": true,
        "taxas": [
            {
                "documento": "5921000030/2021/31895",
                "codigoTLL": "T44998578",
                "valor": 206.10,
                "dataTaxa": "05/10/2021",
                "servico": "Inclusão de Atividade em Viabilidade MEI",
                "codigoServico": "S2253362"
            },
            {
                "documento": "5921000030/2024/57338",
                "codigoTLL": "T45020425",
                "valor": 253.01,
                "dataTaxa": "29/10/2024",
                "servico": "Inclusão de Atividade em Viabilidade MEI",
                "codigoServico": "S2253362"
            }
        ],
        "condicionantes": "A análise da Viabilidade de Localização, por si só, não autoriza o funcionamento do estabelecimento. (...) [mesmo texto legal de observacao]",
        "bap": "BAP2401445619"
    }
}
```

## Notas de formato observadas

- Datas de viabilidade em `"yyyyMMdd HHmmss"` (ex.: `"20240925 153451"`); `dataTaxa` em `"dd/MM/yyyy"`.
- `area_utilizada` com vírgula decimal (`"511,00"`).
- `valor` da taxa numérico com ponto decimal (`206.10`).
- `sede_escritorio_virtual` como string `"0"`/`"1"`.
- `grupo_cnae[].categoria` no padrão `nR1-01`, `nR2-04` etc. (categoria de risco).
- `codigoServico` no padrão `S` + número (ex.: `S2253362`) — código de serviço do SIGS.
- `tvlSimplifica: true` marca TVL gerido pelo Simplifica (numeração inicia em `20...`, casa dos milhões); TVLs SEDUR/SIGS na faixa ~40 mil.
- `observacao` e `condicionantes` trazem o texto legal completo (aparentemente o mesmo conteúdo).
