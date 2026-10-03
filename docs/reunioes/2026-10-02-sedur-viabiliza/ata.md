# Ata — Reunião SEDUR / Viabiliza (2026-10-02)

**Participantes:** Elba (SEDUR), Prof. André Coelho (SEDUR) e Filipe Falcão (Sudoeste).
**Objetivo:** Elba conferir, no GeoServer e na LOUOS, o que o Viabiliza já consulta, e André tratar do dado da JUCEB, do legado da ACTO e do acesso às bases.
**Fonte:** [`transcricao.md`](./transcricao.md). Áudio mono, sem separação de falantes. Quem responde por cada ponto foi atribuído pelo papel na conversa.

---

## 1. Contexto

- Presencial na SEDUR, das 14:13 às 15:27, em três gravações.
- Elba responde pelo cadastro técnico: planta, logradouro, zoneamento e a base do GeoServer. Viabilidade não é a frente dela; ela entra porque a regra locacional sai dessa base.
- André chamou Filipe para a conversa. O cargo dele na SEDUR não foi identificado. Filipe lidera só o Viabiliza. Outras frentes da SEDUR estão com outros tech leaders da Sudoeste. O CTO citado é Cleilson Gomes.
- Na segunda-feira, 9h, há reunião na SEMIT com a Sudoeste e a JUCEB. A JUCEB, no REDESIM, produz dado que o Viabiliza precisa repassar à SEFAZ para o cálculo do valor a pagar. André soube disso por Lisa, com a negociação já andando. A forma de entrega (tabela que muda ao longo do tempo, ou API) não foi fechada. Há duas APIs; a intenção é unificar.
- O recebimento do que a ProSolution manda já segue o guia técnico dela. O teste com um processo real da base deles não voltou. André se dispõe a validar o material com Thiago, diretor nacional da ProSolution, se Filipe organizar o que já está pronto, para tentar encerrar essa pauta na manhã de segunda.
- Os servidores da SEDUR estão com a Sudoeste. Marcelo (infra) e Andrade gerenciam. O Oracle geoespacial está no servidor Doron. O legado saiu da ACTO e Marcelo está organizando a passagem; nem tudo chegou. Filipe não tem admin do banco, só um usuário da própria instância. Gonzalez saiu do grupo.
- O GeoServer consultado pelo Viabiliza não é público. O acesso é por chave. Elba não tinha sido informada disso. A manutenção do dado é da área dela (GK / GKat).

## 2. Decisões

1. **A viabilidade cruza zona e via, e só os quadros 10 e 11A da LOUOS.** Os outros quadros não entram nessa consulta. Permitido nos dois, libera. Vedado na zona ou na via, não libera.
2. **Classificação viária vem do GeoServer e não inclui via local.** Via local está na view de logradouro. Filipe ficou de conferir se a API que ele consome já traz via local. Se não trouxer, a local sai dessa view de logradouro, não da classificação viária.
3. **Itaigara e Vela Branca são regra de loteamento, não de zona.** A consulta é o parcelamento do tipo loteamento (códigos mostrados pela Elba: 195 e 193). Remembramento, desdobro e os outros tipos ficam de fora. A regra vigente é a LOUOS de 2016.
4. **Lei e enquadramento mudam por versão, não por código.** Filipe já versionou a lei e a planilha de enquadramentos das técnicas. Nova versão publicada passa a valer no motor. Lisa e Adriana são quem passam a regra e testam. Regra que elas não passarem não existe no sistema.
5. **O motor é determinístico. A IA só escreve a justificativa** a partir do resultado e da lei. O técnico confere. Não redige o parecer.
6. **Ponto localiza. Polígono zoneia.** A lei manda valer a maior porção do terreno quando o lote corta mais de uma zona. Ponto serve para loja e escritório. Não serve para galpão ou lote grande. O raio (10 m; 90 m na Avenida Paralela) resolve logradouro fronteiriço, não a porcentagem da zona. Porcentagem é interseção do polígono.
7. **A ProSolution não executa o fluxo expresso e não entrega front-end.** O sistema dela continua com o front e o back dela. Ela manda o dado cru para a API do Viabiliza. O Viabiliza devolve baixo, médio e alto. O prazo citado para a parte dela ficar pronta é terça-feira.
8. **Escritura não é documento obrigatório da viabilidade.** Sem matrícula obrigatória e sem o polígono do cartório, o requerente não tem limite seguro para desenhar o próprio lote. Onde o endereçamento já tem polígono (SEFAZ devolve código de logradouro e número; Elba estima 58 mil), ninguém desenha. O restante continua sem geometria confiável. Desenho livre do cidadão foi rejeitado: zoneia errado.
9. **Esta entrega é viabilidade nova e renovação de viabilidade provisória.** Terraplenagem, demolição e o resto do licenciamento edilício ficam para uma segunda etapa. Demolição acima de cerca de 3.500 m³ era SGDU; abaixo era Simplifica. Os dois estão fora do ar.
10. **O Simplifica encerrou.** O contrato acabou há cerca de uma semana. Vida pregressa e a geolocalização que gerou alvará dependem de resgatar a base. O dado e o fonte pagos pelo município não podem ficar retidos com quem desenvolveu. André já cobra essa entrega.

## 3. Fatos de domínio

- Área pública desafetada deixa de ser pública e pode ser tratada como privada. Só vale se a camada no GeoServer estiver atualizada. APP e área ambientalmente sensível não podem passar. Elba pediu casos difíceis para a Lisa, não só o caminho em que a empresa abre e libera.
- Zona não cruza zona. O polígono é que cai em mais de uma. No mapa, Elba mostrou lote partido (cerca de 50,5% e 49,5%) e área limítrofe, com exemplo em Santa Luzia. ZPR-3 foi citada como a de leitura mais difícil. ZPL-3 apareceu no lote cortado.
- Fazendão do Bahia, entre Salvador e Lauro de Freitas: a maior parte foi licenciada em Salvador. Não há ponto oficial na inscrição. O georreferenciamento da SEFAZ, se não mudou, nasceu do relógio da EMBASA. Serve para a prefeitura localizar e cobrar. Não serve para zonear.
- A norma mandou sair do SAD-69 para o SIRGAS 2000 até 2017. Em Salvador, fuso 24S. O SAPES, que está no ar, roda em SAD-69. Elba converte carga toda semana para consumir em SIRGAS. O GeoServer tem os dois. O Viabiliza está em homologação, não em produção. Filipe não garantiu qual datum a camada consultada usa. Anderson Deiró teria indicado Oracle Spatial.
- A consulta do Viabiliza ao GeoServer é por API (WFS), com latitude e longitude, sem leitura direta da tabela. Camada mostrada na sala: workspace da LOUOS, classificação viária. A view de logradouro é outra, e é nela que está a via local. As views vêm de tabela mantida pela GK, em base ouvida como Prolisigas.
- Filipe juntou num documento só os serviços e o workspace do GeoServer que estavam espalhados em manuais de outros sistemas.
- Desde 2016 Elba pede ferramenta para quem não é arquiteto nem engenheiro desenhar, ou para o sistema desenhar. Na vistoria o polígono pode ser corrigido; o resultado, quando depende do desenho do cidadão, não sai certo.
- André descreveu o cenário das bases como espalhado: licenciamento e fiscalização não conversam, e boa parte dos sistemas que vieram da ACTO está sem dono de acesso. Ele quer um BI para descobrir a qual banco não tem acesso. Ricardo levantou a lista. André encaminhou à procuradora. Há reunião na semana seguinte.
- Lisa entrou por telefone no fim da segunda gravação. A fala que fechou esse trecho não deu para aproveitar.

## 4. Pendências

| # | Pendência | Responsável |
|---|---|---|
| 1 | Organizar o que o Viabiliza já recebe da ProSolution, amarrado ao guia técnico, e enviar ao André | Filipe |
| 2 | André levar esse material ao Thiago (ProSolution) e tentar fechar a validação antes da manhã de segunda | André |
| 3 | Cobrar da ProSolution o teste com um processo real da base deles | Filipe, com Gabriel |
| 4 | Segunda-feira, 9h, SEMIT: formato de entrega do dado da JUCEB/REDESIM para a SEFAZ, e se as duas APIs viram uma | Filipe e André |
| 5 | Confirmar com Marcelo o servidor Doron e obter admin do Oracle, além do usuário da instância atual | Filipe |
| 6 | Conferir se a API de classificação viária devolve via local; se não, usar a view de logradouro | Filipe |
| 7 | Confirmar com Anderson Deiró se a camada usada está em SAD-69 ou em SIRGAS 2000 / 24S | Filipe |
| 8 | Montar casos de APP, área sensível, área pública desafetada, lote cortado por zona e sobreposição, e passar à Lisa | Elba |
| 9 | Onde houver polígono de endereçamento, usar. Onde não houver, não abrir desenho livre. Raio não entra na maior porção | Filipe |
| 10 | Resgatar base e documentos do Simplifica (geolocalização do alvará e vida pregressa) | André, cobrando Anderson Deiró |
| 11 | Entregar por escrito o WhatsApp de Cleilson Gomes (CTO). Quem aciona Anderson Deiró, Gabriel e Alan é Thiago Cedraz, diretor da Sudoeste | Filipe |

Terraplenagem, demolição, licenciamento edilício e a consulta do passado ficam fora desta entrega. Não têm data.

## 5. Encaminhamento

- A pauta de segunda de manhã é o dado da JUCEB, não o polígono.
- O polígono e o datum seguem com Filipe, a partir do que Elba mostrou na sala.
- Nenhuma spec nova sai desta ata.
