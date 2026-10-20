# Sincronização anual de feriados pela FeriadosAPI

## Intenção
O dashboard Tempo de análise desconta os feriados cadastrados em `holidays`, mas a lista municipal de
Salvador é pendência da SEDUR e o cadastro é só manual. A FeriadosAPI (https://feriadosapi.com) publica
feriados nacionais, estaduais e municipais; a carga passa a ser automática, ano a ano.

## Objetivos
- Comando `feriados:sincronizar {ano?*}` que busca os feriados de Salvador (IBGE 2927408) e grava em `holidays`.
- Sem argumento, sincroniza o ano corrente e o seguinte. Agendado no dia 1º de cada mês, às 04:00, o que
  cobre a virada de ano e captura correções da fonte.
- Idempotente: reexecutar não duplica nem sobrescreve.
- A ressalva do dashboard some sozinha quando houver feriado municipal vindo da API.

## Não objetivos
- Não importa feriados facultativos (padrão da API) nem webhooks.
- Não altera o CRUD manual de feriados.
- Não grava o token em arquivo versionado: vem de `FERIADOS_API_TOKEN` no `.env`.

## Decisões
- Endpoint `GET /api/v1/feriados/cidade/{ibge}?ano=AAAA&limit=100&page=N`, header `Authorization: Bearer`.
  Devolve nacionais, estaduais e municipais juntos. Datas chegam em `DD/MM/AAAA`.
- Importa os tipos NACIONAL, ESTADUAL e MUNICIPAL, como linhas específicas (`recurring_annually=false`).
- `date` é única: data já cadastrada (manual ou semeada) nunca é alterada; só datas novas são criadas, com
  `source='api'` e `type` preenchido. Registros `source='api'` têm o nome atualizado se a fonte mudar, mas
  nunca são reativados se a SEDUR os inativou.
- `hasOfficialCalendar` passa a ser verdadeiro com feriado específico manual OU feriado municipal vindo da API.
  Feriado nacional/estadual da API sozinho não caracteriza calendário municipal.
- O cache do provider é esquecido a cada gravação de feriado.
- Sem token configurado, o comando falha com mensagem clara e não grava nada. Falha da API em um ano não
  interrompe o outro; o comando termina com falha se algum ano falhou.
- Chamada real à API só quando o token estiver no `.env`; o formato de erro e o limite excedido não estão
  documentados, então qualquer resposta não 2xx vira falha do ano com o status registrado.

## A cargo do construtor
Nome das classes, textos do comando e ajuste do texto da mensagem do dashboard.
