# Evidência de divergência de endereço — Protocolo REDESIM/Regin BAP2601862831

## 1. Resumo

O endereço do estabelecimento recebido pelo Regin no protocolo **BAP2601862831** não corresponde ao endereço da inscrição imobiliária **5046912** no Cadastro Imobiliário da SEFAZ/Salvador.

| Campo | Recebido via Regin | Cadastro Imobiliário (SEFAZ) |
|---|---|---|
| Tipo e logradouro | AVENIDA SÃO CRISTÓVÃO | Rua São Geraldo (cód. 4902) |
| Número | 28 | 000028 |
| Bairro | SÃO CRISTÓVÃO | SAO CRISTOVAO |
| CEP | 41510333 | 41500350 |
| Complemento | não informado | Galpão |
| Área | 130 (`RES_AREA`) | 130 (construída) / 157 (terreno) |
| Inscrição imobiliária | 5046912 (`RES_NUM_REG_PROP`) | 5046912 |

Número, bairro e área coincidem. Logradouro e CEP divergem.

## 2. Fonte A — Dado recebido via Regin

Tabela `regin_recebimentos` (corpo gravado como recebido). Consulta feita no ambiente de produção em 14/10/2026.

- Protocolo: `BAP2601862831`
- Recebido em: `2026-10-14 18:37:45`
- CNPJ de origem (`cnpj_origem`): `05457349000170`
- CNPJ da empresa: `12844187587`
- Usuário de origem no corpo (`RES_TUS_COD_USR`): `JUCESC`

Bloco `RUC_ESTAB` do corpo (campos de endereço):

```json
{
  "RES_RGE_PRA_PROTOCOLO": "BAP2601862831",
  "RES_AREA": "130",
  "RES_NUME": "28",
  "RES_NUM_REG_PROP": "5046912",
  "RES_TTL_TIP_LOGRADORO": "AVENIDA",
  "RES_DIRECCION": "SÃO CRISTÓVÃO",
  "RES_URBANIZACION": "SÃO CRISTÓVÃO",
  "RES_TES_COD_ESTADO": "BA",
  "RES_ZONA_POSTAL": "41510333",
  "RES_TMU_COD_MUN": "38490",
  "RES_TUS_COD_USR": "JUCESC"
}
```

O Sile gravou esses valores sem alteração em `address_street`, `address_number`, `address_neighborhood` e `address_zip`.

**Arquivo íntegro do recebimento:** `BAP2601862831.json` (protocolo, data de recebimento, CNPJ de origem, envelope e corpo completos, extraídos de `regin_recebimentos`).

- SHA-256: `ed8606158506741e4d71e7c5c4eb451bfc881cb2d52a2b98c4999baf385a0f2f`

## 3. Fonte B — Cadastro Imobiliário da SEFAZ

Consulta à API de inscrição imobiliária pelo número 5046912, em 14/10/2026, com `CodigoRetorno 0` ("Operação efetuada com sucesso") e `DataProcessamento 2026-10-14T20:24:06.990+00:00`.

```json
{
  "CodigoRetorno": "0",
  "MensagemRetorno": "Operação efetuada com sucesso.",
  "AreaConstruida": "130",
  "AreaTerreno": "157",
  "CodigoLogradouroTributario": "4902",
  "LogradouroTributario": "Rua São Geraldo",
  "ListaEndereco": {
    "RetornoEndereco": [
      {
        "TipoEndereco": "Localização",
        "CodigoEspecie": "Rua",
        "Logradouro": "Rua São Geraldo",
        "NumeroPorta": "000028",
        "Bairro": "SAO CRISTOVAO",
        "CEP": "41500350",
        "SubUnidade": "Galpão",
        "Municipio": "SALVADOR",
        "UF": "BA"
      },
      {
        "TipoEndereco": "Correspondência Inscricao Imobiliária",
        "CodigoEspecie": "Rua",
        "Logradouro": "Rua São Geraldo",
        "NumeroPorta": "S/N",
        "Bairro": "SAO CRISTOVAO",
        "CEP": "41500350",
        "SubUnidade": "Galpão",
        "Municipio": "SALVADOR",
        "UF": "BA"
      }
    ]
  }
}
```

**Consulta direta ao endpoint de produção (curl):** `GET https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-bff-portal-servicos/v1/inscricao-imobiliaria/5046912`

- Data da consulta: `2026-10-14T20:36:25Z` (cabeçalho do servidor: `Date: Wed, 14 Oct 2026 20:36:26 GMT`)
- Resultado: `HTTP/1.1 200 OK`, `CodigoRetorno 0`, `DataProcessamento 2026-10-14T20:36:26.361+00:00`
- `x-correlation-id`: `791cba5c-e4b4-4b8e-9340-36536f2409fc`
- Arquivo da resposta bruta (`sefaz-5046912.resposta.txt`), SHA-256: `dc24dd481f99f077a9641f4f93ce2e5a730e53a3c8762f06b5c66825efe0065b`
- A resposta repete `CodigoLogradouroTributario 4902`, área construída 130 e área de terreno 157, iguais às da consulta feita pelo sistema.

## 4. Conclusão

O cadastro municipal registra o imóvel da inscrição 5046912 na Rua São Geraldo, nº 28, CEP 41500-350, tanto no endereço de localização quanto no de correspondência. O Regin enviou Avenida São Cristóvão, nº 28, CEP 41510-333. O número, o bairro e a área são os mesmos, o que indica que a inscrição está correta e o logradouro foi informado de forma divergente na origem.

## 5. Limites desta evidência

- A consulta à SEFAZ reflete o cadastro na data indicada e pode mudar depois.
- O que a evidência prova é a divergência entre as duas fontes. A causa (erro de digitação na origem, cadastro desatualizado) não consta dos dados.
- `cnpjOrigem` e `cnpjDestino` são o mesmo CNPJ (`05457349000170`): ele identifica o destinatário da viabilidade, não o órgão de registro.
- `RES_TUS_COD_USR = JUCESC` é o código do usuário do sistema integrador e não identifica, por si só, o órgão de registro.

## 6. Indícios de origem no registro empresarial da Bahia

- NIRE `29204402217`: o prefixo 29 corresponde à Bahia.
- Protocolo com prefixo `BAP`.
- Órgão de registro informado (`TIE_ORGAO_REGISTRO = 1`, `TIE_CNPJ_REGISTRO = 13574983000111`). Confirmar em consulta pública de CNPJ que o número é o da JUCEB antes de citá-lo.
- O mesmo endereço (Avenida São Cristóvão, nº 28, CEP 41510333) consta nos blocos `RUC_COMP` (empresa) e `RUC_ESTAB` (estabelecimento).
