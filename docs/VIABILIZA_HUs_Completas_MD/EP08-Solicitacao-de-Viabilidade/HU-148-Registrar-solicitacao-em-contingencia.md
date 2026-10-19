# HU-148 — Registrar solicitação em contingência

## Épica
**EP08 — Solicitação de Viabilidade**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Permitir que um operador autorizado registre, na retaguarda, uma solicitação completa com origem contingência, protocole pelo mesmo serviço do portal e submeta o processo ao motor do fluxo expresso.

## História de Usuário
**Como** operador com permissão de registrar contingência,  
**quero** lançar o pedido com os dados do formulário, o motivo e a referência externa,  
**para** protocolar o processo quando o canal Regin não é a origem do registro.

## Atores e permissões
- Permissão `registrar-contingencia` nas rotas `gestao/contingencia`.
- O beneficiário é um usuário já cadastrado, localizado pelo CPF. O operador fica em `created_by_user_id`.
- A empresa precisa já existir, localizada pelo CNPJ.
- Não há criação de cidadão nem de empresa nesta tela.

## Fluxo evidenciado
1. `GET gestao/contingencia/create` lista tipos de serviço ativos, requisitos documentais ativos, tipos de imóvel do catálogo e o centro do mapa.
2. `POST` valida CPF, CNPJ, motivo, polígono, área, CNAE principal, complementares e anexos.
3. Se a referência externa já existe em solicitação não cancelada, a operação volta com erro e não cria outro processo.
4. Reincidência por CNPJ gera alerta e não bloqueia.
5. Em uma transação, cria a solicitação com origem `contingencia`, motivo, referência, endereço, área, indicadores e inscrição; aplica o tipo de imóvel; sincroniza CNAEs; grava anexos com SHA-256 no disco de documentos; escreve a geometria; chama `ProtocolarSolicitacaoService::protocol`.
6. Falha de dado mínimo ou de documento obrigatório desfaz a transação, apaga os arquivos gravados e devolve o erro. O número não permanece consumido por um processo pela metade.
7. Depois do commit, `FluxoExpressoService::decide` roda na hora, com o operador como ator.
8. O redirecionamento vai para a ficha do processo, com o número de protocolo. O alerta de CNPJ, se houver, segue na sessão.

## Regras de negócio evidenciadas
- A origem gravada é contingência. O protocolo usa a mesma máquina de estados do portal: rascunho para protocolada.
- A referência externa duplicada bloqueia. A reincidência por empresa não bloqueia.
- `is_virtual_office` também preenche `wants_virtual_office_hq` com o mesmo booleano.
- Inscrição é só dígitos; vazia vira nulo.
- Anexo cuja chave não é um requisito existente é ignorado.
- O ponto de referência é opcional na contingência. No portal, o ponto de referência do imóvel é obrigatório.

## Validações
- CPF obrigatório e válido; cidadão precisa existir.
- CNPJ obrigatório; empresa precisa existir.
- Motivo obrigatório, até 2000 caracteres.
- Referência externa opcional, até 255 caracteres.
- Polígono obrigatório, tipo Polygon, um anel com pelo menos quatro pontos.
- Área obrigatória, numérica, maior que zero.
- Tipo de serviço, se informado, ativo. CNAE principal ativo. Complementares ativos, distintos, até o limite parametrizado, e diferentes da principal.
- Anexos opcionais, MIME e tamanho iguais aos do portal.
- Tipo de imóvel, inscrição e endereço opcionais, com os tamanhos do request.

## Critérios de aceite
- Dado operador autorizado, cidadão e empresa já cadastrados, polígono, área e CNAE principal, quando registra, então a solicitação nasce com origem contingência, é protocolada e o motor do expresso é chamado em seguida.
- Dado a mesma referência externa já usada em processo não cancelado, quando registra de novo, então não cria outro processo e informa o protocolo existente.
- Dado documento obrigatório faltante ou dado mínimo ausente, quando registra, então nada permanece protocolado e os arquivos da tentativa são removidos.
- Dado CPF ou CNPJ sem cadastro, quando envia, então a validação recusa.
- Dado usuário sem `registrar-contingencia`, quando acessa a rota, então o middleware nega.

## Divergências da redação anterior
A vinculação posterior do BAP e a resposta ao integrador não ocorrem neste controller. A referência externa é gravada e, se repetida, bloqueia na hora. Cidadão e empresa não são criados no ato. O expresso é decidido de imediato, não apenas deixado na fila do evento de protocolo.

## Evidências
- `App\Http\Controllers\Gestao\ContingenciaController`
- `App\Http\Requests\Gestao\RegistrarContingenciaRequest`
- `App\Services\Solicitacao\ProtocolarSolicitacaoService`
- `App\Services\Expresso\FluxoExpressoService::decide`
- `App\Services\Solicitacao\DuplicateRequestDetector::detectByExternalReference`
- `routes/gestao.php` (middleware `permission:registrar-contingencia`)

## Não evidenciado
- Cadastro de cidadão ou de empresa inexistentes durante a contingência.
- Transição para `aguardando_bap` ou consulta ao `BapRegistry` neste fluxo.
- Recebimento automático de mensagem Regin. Este canal grava o que o operador digitou.
