# Corretivas da qualidade — sprint 1.0.0

Data: 2026-09-29. Projeto SEDUR - Viabiliza, sprint 20447.

## Decisão

Cada card em Novo foi confrontado com o código atual. Três não são falha e voltam para teste com justificativa. Os demais são defeito confirmado e entram na correção.

## Não é falha

| Card | Origem | Motivo |
|---|---|---|
| 119662 | 119259 | A Simulação REGIN homologa o motor real. O aviso da tela declara que o processo é criado de verdade. Um resultado sem processo seria dado simulado. A consulta pública é a simulação que não grava processo. |
| 119664 | 119262 | A tela de Perfis lista o catálogo oficial inteiro, de propósito sem busca nem paginação. O conjunto cabe na página. |
| 119578 | 119262 | O login da gestão já usa o exemplo `seu usuarioad`. O identificador antigo não está no código. |

119631 é o mesmo defeito de 119632 (teste de conexão da API REGIN). A correção vale para os dois.

## Correções

### Consulta e contraste

- 119444: o alto contraste não grava o tema como claro. Ao desligar, o modo noturno salvo volta.
- 119496: o 422 de endereço fora de Salvador chega na tela com a mensagem municipal, no campo endereço.

### Mensagem, máscara e documento

- 119661: 404 JSON de processo inexistente responde "Recurso não encontrado.", sem nome de classe.
- 119574 e 119586: CPF e CNPJ da contingência têm máscara, limite e mensagem em português.
- 119577 e 119585: CEP do comprovante e da ficha no formato 00000-000.
- 119573: configuração de IA ativa exige chave de API.
- 119632: falha de conexão do REGIN em português, sem cURL, sem URL interna e sem link de biblioteca.
- 119571: o convite abre as pendências da solicitação. Se a solicitação não existe, o botão não aponta para 404.

### Fluxo

- 119572: nova revisão da ficha passa a ser a revisão corrente.
- 119591: mapa do processo não recebe coordenada NaN.
- 119665: exportar CSV da trilha não abre página em branco. Resposta 202 permanece na tela com aviso.
- 119605: copiar link tem fallback quando a área de transferência do navegador não existe, e informa o resultado.
- 119624: renovação aplica máscara de CNPJ, mostra o 404 da SEDUR e não mantém o TVL da consulta anterior.
- 119623: o vínculo do documento deixa de ser apresentado como opcional quando há requisito obrigatório pendente.

### Texto

- 119633, 119634, 119622, 119667 e a capitalização do 119584: linguagem de gestor, sem termo interno, sem "feição(ões)", grupos de parâmetro acentuados. O travessão que significa ausência de valor na tabela permanece.

### Layout

- 119575: título principal das páginas do portal é H1.
- 119580, 119576, 119581, 119582, 119583, 119604, 119615: cabeçalho, cartões, URL, paginação, filtros, cursor do arquivo e quebra de justificativa sem espaço.
- 119588: contraste do modo noturno no rótulo do menu, cabeçalho de tabela, paginação e botão outline.
- 119666: label dos campos obrigatórios dos cadastros citados exibe o asterisco.
- 119663: o filtro de categoria mantém as demais categorias.
- 119584: modal de tramitação limita a altura, rola por dentro e mostra o erro de limite.
- 119592: PDF largo em paisagem, download como anexo, trilha Painel > Indicadores ou Relatórios > nome da página.

## Fora deste ciclo

Não se redesenha a Simulação REGIN para dry-run. Não se pagina o catálogo de perfis.
