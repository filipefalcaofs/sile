# Aviso de localização não confirmada na ficha

## Contexto

Com a conferência do ponto da inscrição (v0.125.0 a v0.126.1), 22 processos em aberto ficaram como "sem fonte que confirme": o ponto do Cadastro Imobiliário está longe da rua do endereço e nenhuma outra fonte (endereçamento SEDUR, SEMAP, geocodificação que localize o imóvel) confirmou o local. O comando `processos:corrigir-ponto-inscricao` só lista esses processos. A ficha continua mostrando o lote da outra avenida (BAP2601886456, processo 1441: lote na Barros Reis, endereço na Heitor Dias) como se fosse o do imóvel, sem nenhum aviso.

## Decisões do usuário (24/10/2026)

- Registrar na ficha, abaixo do mapa, que o endereço não foi confirmado nem pelo polígono do endereçamento nem pelo ponto.
- O lote que veio do ponto não confirmado continua no mapa, **marcado** (não escondido): contorno vermelho tracejado e rótulo "não confirmado".
- Sem "corrigir localização" pela analista por enquanto: ela usa o ajuste manual de zona e via que já existe.

## Comportamento

1. Coluna nova `viability_requests.localizacao_conferencia` (JSON, opcional) guarda o resultado da conferência: `confirmacao`, `distancia_m`, `logradouro`, `conferido_em`.
2. Gravação:
   - no protocolo, quando a conferência roda (`TerritorioProcessoService`, junto da auditoria `localizacao-ponto-conferido`);
   - pelo comando com `--aplicar`: marca os "sem fonte que confirme" e, nos corrigidos, grava `substituido`. Processo confirmado só é regravado se tinha marca de não confirmado (para não tocar nos demais).
3. O endpoint `mapa-decisao` (usado pela consulta do processo e pela ficha de análise) devolve `localizacao` quando a conferência é `nao_confirmado`: distância, logradouro e se o lote gravado veio do ponto (`lote_do_ponto`).
4. A ficha mostra, abaixo do mapa, o aviso **"Localização não confirmada"**: o endereço da inscrição não foi confirmado nem pelo polígono do endereçamento nem pelo ponto; o ponto do Cadastro Imobiliário está a X m da rua e nenhuma outra fonte confirmou o local; o lote e a zona podem não ser os do imóvel. O aviso também aparece quando o mapa cai no modo simples.
5. Lote do ponto não confirmado: vermelho tracejado, rótulo "Lote no ponto do Cadastro Imobiliário · não confirmado". A zona diz "zona do ponto não confirmado" e a explicação avisa que pode não ser a do imóvel.
6. Conferência que depois confirma ou substitui o ponto apaga o aviso.

## Fora do escopo

Aviso para o requerente; correção de localização pela analista; planilha para a SEFAZ.

## Plano (execução direta com TDD)

1. Migration + cast + `ViabilityRequest::localizacaoNaoConfirmada()`; gravação no protocolo (teste em `LocalizacaoPelaInscricaoTest`).
2. Comando grava/limpa a marca (testes em `CorrigirPontoInscricaoCommandTest`).
3. `MapaDecisaoService` devolve `localizacao` (teste em `MapaDecisaoTest`).
4. Front: `mapa-decisao-dados.ts` (aviso, rótulo e explicação, com vitest), estilo do lote em `mapa-decisao.tsx`, aviso em `mapa-decisao-section.tsx`.
5. Pint, testes das áreas, build do front, commit e publicação no SEMIT só com aval.
