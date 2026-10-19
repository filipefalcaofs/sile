# HU-132 — Emitir TVL em PDF no backoffice

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Gerar o PDF da viabilidade locacional de uma decisão deferida e entregá-lo na retaguarda por URL temporária assinada, a partir de disco que não é público.

## História de Usuário
**Como** usuário com `emitir-tvl`, **quero** emitir e baixar o PDF do TVL no backoffice, **para** ter o documento da decisão deferida sem publicar o arquivo.

## Atores e permissões
- `POST processos/{processo}/tvl` e `GET processos/tvl/{documento}/download`: `emitir-tvl`. O download ainda exige assinatura (`signed`).
- No seeder, a permissão está no conjunto de análise (Analista Servidor, Analista Terceirizado e chefias) e no administrador.
- O cidadão não tem esta rota.

## Fluxo evidenciado
1. Sem decisão, a emissão responde 422.
2. `TvlPdfService::generate` produz o documento. Erro de domínio do serviço também responde 422. A regra de só emitir decisão deferida está declarada no controller como comportamento do serviço.
3. A resposta JSON traz id, código de verificação, data e a URL temporária. O TTL é o parâmetro `analise.tvl.download.ttl_minutos`, com padrão 5 minutos na config.
4. O download faz streaming do disco gravado no documento, com nome `viabilidade-{codigo}.pdf`, e audita `tvl-download`.

## Regras de negócio evidenciadas
- A URL deixa de valer depois do TTL. Sem assinatura válida, o middleware `signed` recusa.
- O disco do arquivo não é servido por URL pública.
- A emissão não é o deferimento: o número de TVL nasce na decisão (HU-086); este endpoint gera o PDF.

## Validações
- Processo sem `decision`: 422 com a mensagem de que não há decisão para gerar o PDF.
- Exceção de domínio do gerador: 422 com a mensagem do serviço.

## Critérios de aceite
1. Sem `emitir-tvl`, emitir e baixar respondem 403.
2. Decisão ausente não gera arquivo.
3. Emissão bem-sucedida devolve URL assinada com o TTL configurado.
4. Download com assinatura válida entrega o PDF e registra auditoria. Download sem assinatura não entrega o arquivo.

## Divergências da redação anterior
- A redação anterior, quando falava em comunicar o TVL ao requerente junto com REGIN e SEFAZ, não corresponde a este endpoint. Aqui o PDF fica no backoffice, para quem tem `emitir-tvl`.

## Evidências
- `app/Http/Controllers/Gestao/TvlDocumentController.php`
- `routes/gestao.php`
- `database/seeders/RolesAndPermissionsSeeder.php`

## Não evidenciado
- Download do PDF no portal do cidadão.
- Emissão de TVL para decisão indeferida. O documento de indeferimento é outra rota, também sob `emitir-tvl`.
