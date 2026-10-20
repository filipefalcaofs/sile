# Agendamento das edificações complementares no OKD

Data: 20/10/2026. Aprovado em conversa.

## Por quê

A primeira carga da Overture em produção mostrou três falhas no agendamento
(`routes/console.php`):

1. O agendador roda no contêiner de schedule (`docker/okd/DockerFile_schedule`),
   que não tem o CLI `overturemaps` nem o pip. A extração falharia sempre.
2. A Overture só roda no dia 25 e a Microsoft uma vez por trimestre. Cada
   publicação no SEMIT troca os pods; uma carga morta no meio só voltaria no mês
   (ou trimestre) seguinte.
3. Carga morta fica em `em_andamento` para sempre (linhas 1 e 2 de produção).

## O que muda

- **CLI na imagem do agendador.** `DockerFile_schedule` cria um venv em
  `/opt/app-root/overture` com `overturemaps==1.0.2` (versão usada na carga de
  20/10) e o põe no `PATH`. A imagem web não muda: só o agendador precisa dele.
- **Verificação diária com vencimento.** Overture às 03:00 e Microsoft às 03:30,
  todo dia, mas só quando a fonte está vencida: nenhuma carga `concluida` ou
  `ignorada` iniciada dentro do intervalo (Overture 25 dias, Microsoft 85 dias,
  em `viabiliza.edificacoes.agendamento`). `ignorada` conta porque release
  igual não deve ser baixada toda noite; `falhou` e `em_andamento` não contam.
  O interruptor `edificacoes_importacao_agendada` continua valendo.
- **Órfãs.** Ao começar uma carga, as da mesma fonte em `em_andamento` iniciadas
  há mais de 6 horas (`viabiliza.edificacoes.orfa_horas`) passam a `falhou`, com
  `finished_at` e o motivo "interrompida".

## Fora do escopo

- Montagem do volume de `storage/app` no pod de schedule (configuração do OKD).
  O arquivo continua em `storage/app/overture`: persistente se o volume estiver
  montado, no disco do contêiner se não.
- CLI na imagem web.

## Testes

- `AgendamentoEdificacoesTest`: horários diários; interruptor; vencimento por
  fonte (sem carga, concluída recente, ignorada recente, concluída antiga,
  falhou recente).
- Importador: órfã antiga vira `falhou`; recente e de outra fonte ficam.

## Plano

1. Testes acima, falhando.
2. `BuildingFootprintImport::vencida()` e `encerrarOrfas()`, config, chamada no
   importador, agendamento diário.
3. `DockerFile_schedule` com o venv; conferir que o venv instala sob Python 3.12.
4. Suíte `tests/Feature/Geo` e `tests/Unit/Geo`, Pint, commit na branch.
5. Publicar no SEMIT só depois que a carga da Overture em andamento terminar.
