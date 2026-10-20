# HU.026 – CONSULTAR CAMADAS GEOGRÁFICAS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O administrador vê a versão vigente de cada tipo de camada local e importa um arquivo de feições geográficas, abrindo nova versão vigente sem apagar a anterior. Os cinco tipos são bairro, zona urbanística, via, lote e restrição territorial. Cada versão mostra situação (vigente, substituída ou pendente de fonte), origem, vigência e quantidade de feições. Zona e lote aparecem como fonte bloqueada. A consulta territorial mostra o status das camadas abertas, sem importar arquivo.

Fora da base geográfica oficial, a importação é recusada e nada é criado. Arquivo inválido é recusado. Arquivo válido torna a nova versão vigente e fecha a anterior, sem apagá-la. A mensagem informa versão, feições lidas, inseridas e, se houver, as inválidas não inseridas, até cinco motivos. O arquivo aceita até o tamanho definido, vinte megabytes se outro limite não estiver definido. Não há exclusão de versão já importada, edição de uma feição na tela nem publicação da camada no serviço de mapas a partir deste formulário.

---

## Sumário

- [Épico: EP04 — Georreferenciamento e Território](#épico)
- [História de Usuário: Consultar camadas geográficas](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP04 — Georreferenciamento e Território

**Como** a SEDUR **quero** versionar as camadas geográficas locais**, de modo que** a base territorial mude sem perder o histórico

## História de Usuário: Consultar camadas geográficas

**Como** administrador **quero** ver a versão vigente de cada tipo de camada e importar um arquivo de feições**, de modo que** eu troque a base territorial sem publicação de versão do sistema

## Pré-requisitos

- Administrador que mantém o território
- Tipo obrigatório, entre os cinco da lista
- Versão obrigatória, com até 100 caracteres; origem opcional, com até 255
- Arquivo obrigatório, até o limite definido
- Base geográfica oficial disponível

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- HU-039 — a consulta territorial lê as camadas vigentes importadas aqui

---

## Interface de Usuário

### Tela 1 — Camadas geográficas

**Caminho no menu:** Território > Camadas geográficas

*[Print da tela](screenshots/gestao-camadas.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Lista dos cinco tipos**
**Dado que** o administrador abre as camadas
**Quando** a listagem carrega
**Então** os cinco tipos aparecem com a vigente, quando houver, e o histórico de versões

**Cenário 2: Fora da base geográfica oficial**
**Dado que** a base em uso não é a geográfica oficial
**Quando** um arquivo de feições é enviado
**Então** a importação é recusada e nenhum registro novo é criado

**Cenário 3: Arquivo válido**
**Dado que** a base geográfica oficial está em uso e o arquivo é válido
**Quando** a importação conclui
**Então** a nova versão fica vigente, a anterior deixa de ser a vigente, e a tela informa lidas, inseridas e inválidas

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
