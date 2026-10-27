# S7 — Centralidade linear pelo acesso do imóvel

Origem: áudio da Lisa, 23/10 13:29.

## Regra informada
Se o imóvel tem acesso pela via que é centralidade linear (ex.: Av. São Rafael), vale a centralidade e não a zona onde o imóvel está.

## Como está
`GeoServerZonaMaiorPorcaoResolver::centralidadeLinear` só reconhece a centralidade quando a linha cruza o lote (spec de 23/10). Medição de 21/10: só 9 lotes do endereçamento são cruzados por ZCLMu; 1.859 lotes com o mesmo CODLOG da ZCLMu ficam a até 60 m do eixo.

## Mudança
Lote lindeiro = o CODLOG do endereço do processo é o CODLOG do trecho de centralidade E o lote fica a até `viabiliza.geo.centralidade_linear.distancia_lindeiro_m` (padrão 60 m) desse trecho. Cruzar o lote continua valendo. Chave `features.centralidade_linear_lindeiro` (padrão ligada) para desligar sem deploy.

## Pendente com a SEDUR
- Distância máxima do lote ao eixo (60 m foi a medida da Av. ACM).
- Trecho sem CODLOG na camada (2 feições) não entra pela regra de acesso.
