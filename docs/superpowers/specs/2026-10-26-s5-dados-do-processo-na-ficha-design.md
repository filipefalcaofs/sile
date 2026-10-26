# S5 — Dados do processo na ficha

Origem: Lisa 22/10 19:51 (itens 2 e 4) e 19:56 (item 6).

## Pedidos e mudanças
1. **EMPRESA = razão social; solicitante em Requerente.** O campo Empresa usa `legal_name` (hoje usa o nome fantasia primeiro). Para empresa a constituir do REGIN cujo nome é o do próprio solicitante, Empresa mostra "Empresa a constituir". A ficha ganha o campo Requerente com `solicitante_nome` (senão o usuário requerente). Vale para a ficha, a tela do processo, a Caixa do Setor e a lista de processos.
2. **Zona e Via editáveis na ficha.** A permissão `editar-zona-via` passa a valer também para Analista Servidor e Analista Terceirizado (seeder + migration). O resto (botão, justificativa, registro na tramitação) já existe.
3. **Tipo do imóvel.** `viability_requests.tipo_imovel` só é preenchido pela declaração do REGIN; o tipo que vem da inscrição (`Utilizacao`, API de produção) fica só no bloco do cadastro. Quando o processo não tem tipo e a consulta da inscrição traz, o tipo é gravado no processo. Comando `processos:preencher-tipo-imovel [--aplicar]` preenche os processos em aberto.
