# Ficha de análise — layout legado + Cadastro Imobiliário (IPTU) — design

**Data:** 2026-07-22  
**Status:** Aprovado em brainstorming — aguardando revisão do arquivo antes do plano  
**Origem:** Prints SAPS/Salvador Simplifica (Fichas de Análise) + Certidão de Dados Cadastrais IPTU 2023 (SEFAZ)  
**Relacionado:** HU-135 (ficha), HU-106 (Cadastro Imobiliário), HU-062 (imóvel), `PropertyRegistryLookup` (contrato Fase 7 / binding real Fase 13), design análise técnica `2026-06-14-analise-tecnica-design.md`

---

## 1. Problema

A ficha de análise do SILE (`gestao/ficha-analise/show`) diverge do layout operacional do SAPS:

- Localização virou card lateral com mapa + endereço em uma linha.
- Não há o bloco “Endereço Inscrição Imobiliária” (CodLog, Logradouro, Nº Métrico, Bairro, CEP, Ponto de Referência).
- Não há a faixa “Dados do TVL” no formato do legado.
- O espaço em branco à direita da Localização no SAPS permanece morto no SILE — e deve receber os dados da **certidão IPTU / Cadastro Imobiliário**.

Decisão de produto: **aproximar do legado (abordagem A)** e preencher o branco com o bloco Cadastro Imobiliário (IPTU), sem inventar dado quando a integração estiver indisponível.

## 2. Princípios

1. **Paridade estrutural** com o print do legado (Localização | Polígono → Dados do TVL).
2. **Melhoria controlada** no design system SILE (cards, tipografia, contraste AA, empty/degraded states).
3. **Sem fachada:** Cadastro indisponível ⇒ aviso explícito + campos “—”; nunca certidão simulada.
4. **Só leitura no bloco IPTU** — o analista não edita dados do Cadastro/SEFAZ nesta tela.
5. **Duplicação consciente:** endereço/bairro/CEP/tipo podem aparecer em Localização/TVL (informado na solicitação) e no bloco IPTU (fonte cadastro), com rótulos que deixam a origem clara.

## 3. Layout

### 3.1 Topo (após cabeçalho / seletor de revisão)

| Esquerda — Localização | Direita — Polígono |
|---|---|
| Título: Localização / Endereço Inscrição Imobiliária | Mapa do polígono do processo |
| Campos da solicitação: CodLog*, Logradouro, Nº Métrico, Bairro, CEP, Ponto de Referência | Zona, Via (oficiais quando existirem; senão “—” + aviso pendência SEDUR) |
| **Espaço em branco do legado → bloco Cadastro Imobiliário (IPTU)** | Confirma polígono diferente do requerente? (estado já existente na ficha/análise, sem inventar regra nova) |

\* CodLog: exibir se houver na solicitação/integração; senão “—”.

### 3.2 Faixa completa abaixo

**Dados do TVL** (paridade SAPS):

- Razão Social  
- Sede de escritório virtual?  
- Porte da Empresa  
- Tipo de Imóvel  
- Categoria da Empresa  
- Torre/Bloco/Ala  
- Complemento  

Fonte: `ViabilityRequest` + `Company` (+ flags EV já existentes na ficha). Campos ausentes no modelo atual → “—” (não criar fachada de edição completa neste ciclo, salvo o que a ficha já edita — ex.: flag sede EV).

### 3.3 Demais seções

Enquadramento por CNAE, condicionantes, vagas, parecer, precedentes, ações: **permanecem**; apenas o topo é reorganizado para o padrão legado. Não é redesign completo da ficha.

## 4. Bloco Cadastro Imobiliário (IPTU)

### 4.1 Campos marcados (destaque visual)

| Campo | Observação |
|---|---|
| Inscrição Imobiliária | Chave = `property_registration` do processo |
| Endereço | Do cadastro (pode diferir do informado) |
| Nº Métrico | Do cadastro |
| Loteamento | |
| Quadra | |
| Lote | |
| Conjunto / Edifício | |
| Bloco | |
| Sub-Unidade | Tipo (ex.: GL - Galpão) |
| Nº Sub-Unidade | |
| Bairro | Do cadastro |
| CEP | Do cadastro |
| Área Construída (m²) | |
| Tipo Imóvel | Do cadastro (ex.: Residencial Horizontal) |
| Data Lançamento | |
| Situação Cadastral | |

### 4.2 Demais campos da certidão (mesmo bloco)

| Campo |
|---|
| Contribuinte |
| CPF/CNPJ |
| Nº de Porta |
| Área Terreno (m²) |
| Valor Venal IPTU |
| Logradouro Tributário |
| Situação Fiscal (IPTU) |
| Data de Emissão da certidão (quando houver no payload) |

### 4.3 Comportamento

- **Só leitura.**  
- Destaque nos campos marcados (ex.: fundo `warning`/âmbar suave ou label “destacado”, acessível — não só cor).  
- Se inscrição vazia: mensagem “Inscrição imobiliária não informada no processo”; campos “—”.  
- Se lookup indisponível / não encontrado: aviso honesto + campos “—”; auditoria da tentativa.

## 5. Dados e contrato

### 5.1 Prop Inertia

`AnalysisRecordController@show` passa:

```text
cadastroImobiliario: {
  status: 'disponivel' | 'indisponivel' | 'nao_encontrado' | 'sem_inscricao',
  mensagem: string|null,   // pt-BR para o aviso
  inscricao: string|null,
  campos: { ...snake_case dos campos das seções 4.1 e 4.2... },
  consultado_em: string|null,  // ISO quando houver consulta
  source: string|null
}
```

### 5.2 Contrato Realty

- Estender `PropertyRegistryResult` (ou DTO irmão tipado `PropertyRegistryCadastro`) para expor os campos cadastrais tipados + `raw` para auditoria.  
- `PropertyRegistryLookup` continua o ponto único; provider real permanece `UnavailablePropertyRegistryLookup` até HU-106 homologada.  
- Fake nos testes prova UI + payload; **zero** adaptador falso em runtime.

### 5.3 Localização / TVL na prop

Complementar `localizacao` (hoje só `poligono` + `endereco` string) com campos estruturados da solicitação para o formulário legado:

- `cod_log`, `logradouro`, `numero_metrico`, `bairro`, `cep`, `ponto_referencia`  
- `zona`, `via` (quando existirem no snapshot territorial; senão null)  
- `poligono` (já existente)

Dados do TVL: objeto `dadosTvl` (razão social, sede EV, porte, tipo imóvel, categoria, torre/bloco, complemento) montado no controller a partir do processo/empresa.

### 5.4 Auditoria (RN-002)

- Evento `ficha-cadastro-consulta` (ou equivalente) ao montar o bloco com tentativa de lookup.  
- Propriedades: `viability_request_id`, `inscricao`, `status` do resultado (sem CPF/valor venal em log se política LGPD exigir — preferir status + inscrição; dados sensíveis só no payload da tela autenticada).

## 6. Degradação (anti-fachada)

| Situação | UI |
|---|---|
| Integração indisponível (estado atual de produção/dev sem binding real) | Banner: “Cadastro Imobiliário indisponível — pendente SEDUR/SEFAZ.” Campos “—” |
| Inscrição inexistente na base | Banner: “Inscrição não encontrada no Cadastro.” Campos “—” |
| Sem inscrição no processo | Banner: “Inscrição não informada.” Sem chamar lookup |
| Sucesso | Campos preenchidos; destaque nos marcados |

Não bloquear abertura da ficha nem autosave/finalizar por falha do Cadastro.

## 7. Escopo

### Inclui

- Reorganização do topo da ficha (Localização | Polígono + Dados do TVL).  
- Bloco Cadastro Imobiliário (IPTU) no espaço em branco.  
- Prop `cadastroImobiliario` + extensão do contrato/DTO.  
- Degradação honesta.  
- Feature tests de payload (ok / unavailable / sem inscrição) + smoke UI da ficha.

### Fora deste ciclo

- Provider real SEFAZ/Cadastro Multifinalitário (HU-106).  
- Edição dos campos IPTU pelo analista.  
- PDF/impressão da certidão IPTU.  
- Redesign das seções CNAE/condicionantes/vagas/parecer.

## 8. Critérios de aceite

**CA-01** DADO processo com polígono e endereço QUANDO abrir a ficha ENTÃO o topo exibe Localização (campos estruturados) à esquerda e Polígono à direita, e Dados do TVL abaixo — reconhecível vs SAPS.

**CA-02** DADO espaço que no legado era branco QUANDO a ficha carregar ENTÃO exibe o bloco Cadastro Imobiliário (IPTU) com a lista das seções 4.1 e 4.2.

**CA-03** DADO lookup unavailable QUANDO abrir a ficha ENTÃO o bloco mostra aviso honesto e campos “—”, sem valor inventado.

**CA-04** DADO processo sem `property_registration` QUANDO abrir a ficha ENTÃO o bloco informa inscrição ausente e não chama o lookup.

**CA-05** DADO fake de lookup com payload completo QUANDO abrir a ficha ENTÃO os campos marcados e os demais da certidão aparecem preenchidos (teste).

**CA-06** Bloco IPTU é somente leitura (sem inputs editáveis nesses campos).

## 9. Testes

| Teste | Cobertura |
|---|---|
| Feature payload ficha + `cadastroImobiliario` | CA-03, CA-04, CA-05 |
| Smoke UI `FichaUiSmokeTest` (estender) | CA-01, CA-02, presença do bloco / aviso |
| Unit/DTO PropertyRegistry (campos tipados / toArray) | Contrato estável |

## 10. Decisões registradas

| # | Decisão |
|---|---|
| D1 | Abordagem A: espelhar legado + painel Cadastro no branco da Localização |
| D2 | Campos = certidão IPTU completa, com destaque nos marcados pelo usuário |
| D3 | Bloco IPTU só leitura; duplicação com Localização/TVL permitida com origem explícita |
| D4 | Integração real continua bloqueada (Unavailable); UI e contrato preparados |
| D5 | Não bloquear análise humana por falha/ausência do Cadastro |
