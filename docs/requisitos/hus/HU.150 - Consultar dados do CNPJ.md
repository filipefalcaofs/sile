# HU.150 – CONSULTAR DADOS DO CNPJ
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

No cadastro de empresa, o cidadão busca o CNPJ na base cadastral da SEDUR para pré-preencher o formulário. Quando o serviço responde, a tela copia só o que vier preenchido: razão social, nome fantasia, telefone e o endereço no campo de logradouro. Natureza jurídica, porte, CNAE, número, complemento, bairro, cidade, UF, CEP e e-mail não vêm nessa resposta.

A consulta não cria a empresa. A gravação continua manual. Se a busca automática estiver desligada, o botão não consulta a SEDUR e a orientação é preencher os dados à mão. Não há consulta à Receita Federal. Consultas demais no mesmo minuto são recusadas. Em representação ativa, a busca usa quem está logado; a empresa gravada em seguida fica para o representado.

---

## Sumário

- [Épico: EP03 — Cadastro Empresarial](#épico)
- [História de Usuário: Consultar dados do CNPJ](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP03 — Cadastro Empresarial

**Como** a SEDUR **quero** pré-preencher o cadastro com a base cadastral do CNPJ**, de modo que** o cidadão revise os dados antes de gravar a empresa

## História de Usuário: Consultar dados do CNPJ

**Como** cidadão autenticado **quero** buscar o CNPJ na base da SEDUR ao cadastrar uma empresa**, de modo que** razão social, nome fantasia, telefone e endereço sejam preenchidos quando o serviço responder

## Pré-requisitos

- Cidadão autenticado no portal, com e-mail confirmado e termo aceito
- Busca automática de CNPJ ligada; desligada, o botão não chama a SEDUR
- CNPJ obrigatório e válido

## Dependências

**Passadas**

- HU-001 e HU-145 — sessão do portal com termo aceito

**Futuras**

- HU-151 — a empresa só é gravada no cadastro manual, depois da revisão

---

## Interface de Usuário

### Tela 1 — Buscar CNPJ

**Caminho no menu:** Serviços > Minhas empresas > Cadastrar empresa

*[Print da tela](screenshots/portal-empresas.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Pré-preenchimento com a SEDUR**
**Dado que** a busca automática está ligada e a SEDUR devolve a pessoa jurídica
**Quando** o cidadão busca o CNPJ no cadastro
**Então** o formulário recebe razão social, nome fantasia, telefone e endereço quando esses campos vierem preenchidos, e a consulta fica registrada como sucesso

**Cenário 2: Consulta desligada**
**Dado que** a busca automática de CNPJ está desligada
**Quando** houver tentativa de consulta
**Então** o sistema não chama a SEDUR, informa que a consulta automática está desativada e orienta o preenchimento manual

**Cenário 3: CNPJ ausente ou serviço fora**
**Dado que** a SEDUR não encontra o CNPJ ou não responde
**Quando** o cidadão busca o CNPJ
**Então** o sistema informa que o CNPJ não foi encontrado na base da SEDUR, ou que o serviço está indisponível, e o cadastro manual continua disponível

---

## Aprovação do Requisito

A Sudoeste Informática esclarece que o desenvolvimento das funcionalidades descritas neste documento foi realizado de acordo com os requisitos e especificações fornecidos pelo cliente. Nossa equipe seguiu rigorosamente as instruções recebidas, garantindo que o sistema esteja alinhado com o que foi solicitado.

Para evitar possíveis retrabalhos ou ajustes futuros, sugerimos que o cliente revise atentamente os requisitos apresentados, verificando se eles atendem plenamente às suas necessidades e expectativas, bem como se estão em conformidade com eventuais normas aplicáveis ao seu negócio.

Reafirmamos nosso compromisso de entregar um software de qualidade que agregue valor às operações do cliente, e a validação cuidadosa dos requisitos é um passo importante para garantir o sucesso do projeto.

| Data | Setor | Nome | Assinatura |
|------|-------|------|------------|
|      |       |      |            |
|      |       |      |            |
|      |       |      |            |
