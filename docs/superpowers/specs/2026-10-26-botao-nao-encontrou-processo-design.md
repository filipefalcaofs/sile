# Botão "Não encontrou seu processo?" na Minha Área e consulta por CNPJ no Viabiliza

Data: 26/10/2026. Pedido da SEDUR depois das demonstrações do Viabiliza e do Portal de Serviços.

## Intenção

Na tela "Meus Processos" da Minha Área, a listagem mostra os processos ligados ao CPF de quem entrou (SAD, Viabiliza e Simplifica). Quem não encontra o seu processo ali precisa de um caminho direto para o Viabiliza, onde consulta pelo CNPJ ou pelo número da viabilidade, ou usa os outros serviços (renovar, responder convite).

## Botão na Minha Área (SAD)

- Texto: "Não encontrou seu processo?". Texto de apoio: "Consulte pelo CNPJ ou número da viabilidade, ou acesse os serviços do VIABILIZA."
- Posição: ação de cabeçalho da página "Meus Processos", no canto direito, ao lado do título (lugar padrão das ações das telas Filament do SAD). Aparência discreta (botão contornado, cor neutra, ícone de lupa), para não competir com a listagem; o texto de apoio vai na dica do botão.
- Com a tabela vazia, o estado vazio troca o "Sem registros" por: título "Nenhum processo encontrado para o seu CPF", o texto de apoio e o mesmo botão.
- Destino: a entrada do Viabiliza pelo Portal de Serviços, que já existe: `{VIABILIZA_URL}/portal/entrar/portal-servicos?destino=processo&token=<token da sessão do Portal>`. Sem número, o Viabiliza abre a consulta de processo vazia, com o menu dos outros serviços. Abre em nova aba, como o Detalhes das linhas do Viabiliza.
- O botão só aparece quando a integração com o Viabiliza está configurada no ambiente (`VIABILIZA_URL`) e a sessão tem o token do Portal do próprio contato (mesma regra das linhas do Viabiliza). Sem isso, não aparece.

## Consulta por CNPJ no Viabiliza

- A consulta de processo passa a aceitar CNPJ (14 dígitos, com ou sem máscara), além do número do processo, da viabilidade e do BAP.
- Com CNPJ, o resultado é uma lista: processos do Viabiliza das empresas com aquele CNPJ (sem rascunho) e processos do Simplifica pela API do legado (`GET /v1/processos?documento=<cnpj>`). Cada item mostra número, origem, empresa, situação e data, e leva à consulta daquele número.
- Sem dados pessoais, como no resto da consulta. Limite de 50 itens por origem.
- Simplifica fora do ar: a lista do Viabiliza aparece e o aviso do sistema anterior também.
- O exemplo do campo passa a citar o CNPJ.

## Não metas

- Mudar a regra de quem responde convite ou de quem renova.
- Criar rota, parâmetro ou endpoint novo na integração entre os sistemas: o botão usa a entrada que já existe.
