#!/usr/bin/env python3
"""Monta a contagem detalhada IFPUG 4.3.1 do Viabiliza a partir da evidência funcional do código."""

from __future__ import annotations

import json
import sys
from collections import Counter
from pathlib import Path

SKILL_SCRIPTS = Path("/Users/filipefalcao/.cursor/skills/contagem-ponto-funcao/scripts")
sys.path.insert(0, str(SKILL_SCRIPTS))

from ifpug_calc import calcular_pf, complexidade  # noqa: E402

SAIDA = Path("/Users/filipefalcao/Downloads/fabrica/sile/docs/contagem-pf/contagem-viabiliza-codigo-fonte.json")

TRANS = {"EE", "CE", "SE"}
PAPEL = {
    "ALI": "Arquivo Lógico Interno mantido dentro da fronteira do Viabiliza.",
    "AIE": "Arquivo de Interface Externa: a aplicação referencia o grupo e outro sistema o mantém.",
    "EE": "Entrada Externa. Processo elementar que mantém arquivo lógico ou muda o estado do negócio.",
    "CE": "Consulta Externa. Recupera informação sem derivação relevante e sem manter arquivo lógico.",
    "SE": "Saída Externa. Entrega informação derivada, agregada ou documento reconhecido pelo negócio.",
}


def _partes(texto: str) -> list[str]:
    return [parte.strip() for parte in texto.split(";") if parte.strip()]


def f(nome: str, tipo: str, ders: str, refs: str, evidencia: str, extra: str = "") -> dict:
    der_list = _partes(ders)
    ref_list = _partes(refs)
    if tipo in TRANS:
        der_list += ["comando da ação", "mensagens do sistema"]
    td = len(der_list)
    ar = len(ref_list)
    pf = calcular_pf(tipo, td, ar)
    rotulo = "FTR" if tipo in TRANS else "RLR"
    citacao = (
        "CPM Parte 1 §5.5.2–5.5.4 e Parte 2 Cap. 7."
        if tipo in TRANS
        else "CPM Parte 1 §5.4 e Parte 2 Cap. 6."
    )
    observacoes = (
        f"{PAPEL[tipo]} DER ({td}): {'; '.join(der_list)}. "
        f"{rotulo} ({ar}): {'; '.join(ref_list)}. "
        f"{extra} Evidência: {evidencia}. {citacao}"
    )
    return {
        "nome": nome,
        "tipo": tipo,
        "td": td,
        "ar": ar,
        "pf": pf,
        "pf_local": pf,
        "complexidade": {"L": "Baixa", "A": "Média", "H": "Alta"}[complexidade(tipo, td, ar)],
        "observacoes": observacoes,
    }


def grupo(ident: str, nome: str, itens: list[dict]) -> dict:
    return {"id": ident, "nome": nome, "itens": itens}


GRUPOS = [
    grupo(
        "F1",
        "Acesso, usuários, perfis e setores",
        [
            f(
                "Dados - Usuário",
                "ALI",
                "nome; e-mail; CPF; telefone; tipo de autenticação; identificador no diretório; situação; data de inativação; nível de confiabilidade gov.br; data do vínculo gov.br; última autenticação gov.br; perfil",
                "Cadastro do usuário; Conta gov.br",
                "telas de usuários, perfil e senha da gestão e do portal",
                "A conta gov.br é subgrupo opcional do mesmo usuário, não arquivo separado. Senha e segredo de segundo fator não são DER de negócio. ",
            ),
            f(
                "Dados - Perfil de acesso",
                "ALI",
                "nome do perfil; permissões",
                "Perfil de acesso",
                "tela Perfis",
                "O perfil existe e é mantido sem depender de um usuário específico. A lista de permissões é um único DER, pois o gestor a trata em conjunto. ",
            ),
            f(
                "Dados - Setor",
                "ALI",
                "código; nome; situação; analista vinculado; início do vínculo; fim do vínculo; origem da mudança; responsável pela mudança",
                "Setor; Histórico de vínculo do analista",
                "tela Setores",
                "O histórico de quem cobre o setor é subgrupo do setor. Não há exclusão: inativar preserva o registro. ",
            ),
            f(
                "Usuários - autenticar na gestão",
                "EE",
                "identificador; senha; resultado do acesso",
                "Usuário",
                "login da gestão",
                "Canal próprio da retaguarda, com lógica de credencial diferente do gov.br. ",
            ),
            f(
                "Usuários - autenticar no portal",
                "EE",
                "retorno do gov.br; CPF; nível de confiabilidade; vínculo da conta; resultado do acesso",
                "Usuário",
                "login gov.br do portal",
                "O primeiro acesso também grava o usuário. Não é cadastro separado: a intenção do usuário é entrar. ",
            ),
            f(
                "Usuários - solicitar recuperação de senha",
                "EE",
                "e-mail; confirmação do envio",
                "Usuário",
                "esqueci minha senha da gestão",
                "A mensagem ao usuário é a saída desta entrada. O envio em si não é outra função. ",
            ),
            f(
                "Usuários - redefinir senha",
                "EE",
                "nova senha; confirmação da senha",
                "Usuário",
                "tela de redefinição por token",
            ),
            f(
                "Usuários - alterar senha autenticado",
                "EE",
                "senha atual; nova senha; confirmação da senha",
                "Usuário",
                "minha conta, gestão e portal",
                "Mesma manutenção de senha de quem já está autenticado, nos dois canais. ",
            ),
            f(
                "Usuários - consultar perfil pessoal",
                "CE",
                "nome; e-mail; CPF; telefone; tipo de autenticação",
                "Usuário",
                "meu perfil na gestão e no portal",
            ),
            f(
                "Usuários - alterar perfil pessoal",
                "EE",
                "nome; e-mail; telefone",
                "Usuário",
                "meu perfil na gestão e no portal",
            ),
            f(
                "Usuários - aceitar termo de consentimento",
                "EE",
                "termo apresentado; versão do termo; confirmação do aceite",
                "Termo legal; Usuário",
                "termo LGPD da gestão e do portal",
                "Os dois canais executam o mesmo aceite do termo vigente. ",
            ),
            f(
                "Usuários - consultar registros",
                "CE",
                "nome; e-mail; CPF; perfil; setor; situação; tipo de autenticação",
                "Usuário; Perfil de acesso; Setor",
                "tela Usuários",
            ),
            f(
                "Usuários - alterar registro",
                "EE",
                "perfil; situação; tipo de autenticação; identificador no diretório",
                "Usuário; Perfil de acesso",
                "tela Usuários",
                "Papel, inativação e tipo de autenticação são a mesma manutenção do usuário. Não há inclusão pela tela: o usuário nasce na autenticação. ",
            ),
            f(
                "Perfis de acesso - consultar",
                "CE",
                "nome do perfil; permissões",
                "Perfil de acesso",
                "tela Perfis",
            ),
            f(
                "Perfis de acesso - incluir",
                "EE",
                "nome do perfil; permissões",
                "Perfil de acesso",
                "tela Perfis",
            ),
            f(
                "Perfis de acesso - alterar",
                "EE",
                "nome do perfil; permissões",
                "Perfil de acesso",
                "tela Perfis",
            ),
            f(
                "Perfis de acesso - excluir",
                "EE",
                "perfil selecionado",
                "Perfil de acesso",
                "tela Perfis",
            ),
            f(
                "Setores - consultar",
                "CE",
                "código; nome; situação; analistas vinculados",
                "Setor; Usuário",
                "tela Setores",
            ),
            f(
                "Setores - incluir",
                "EE",
                "código; nome",
                "Setor",
                "tela Setores",
            ),
            f(
                "Setores - alterar",
                "EE",
                "código; nome; situação; analistas vinculados",
                "Setor; Usuário",
                "tela Setores",
                "Ativar, inativar e definir analistas integram a alteração. Não há exclusão. ",
            ),
        ],
    ),
    grupo(
        "F2",
        "Empresas, procurações e atendimento",
        [
            f(
                "Dados - Empresa",
                "ALI",
                "CNPJ; razão social; nome fantasia; natureza jurídica; porte; endereço; e-mail; telefone; origem do cadastro; protocolo REDESIM; data da sincronização; CNAE; indicador de CNAE principal; papel do vínculo; início do vínculo; fim do vínculo",
                "Estabelecimento; CNAEs do estabelecimento; Vínculos de usuário",
                "telas de empresas do portal",
                "CNAEs e vínculos são subgrupos. O cadastro copiado da Receita passa a ser mantido aqui. ",
            ),
            f(
                "Dados - Procuração",
                "ALI",
                "outorgante; procurador; início da vigência; fim da vigência; data da revogação; responsável pela revogação",
                "Procuração",
                "tela Procurações",
            ),
            f(
                "Dados - Atendimento presencial",
                "ALI",
                "atendente; cidadão; início; expiração; encerramento",
                "Atendimento presencial",
                "tela Atendimento presencial",
                "A sessão de balcão tem vida própria. A solicitação aberta durante o atendimento pertence ao arquivo da solicitação. ",
            ),
            f(
                "Dados externos - Cadastro nacional de pessoa jurídica",
                "AIE",
                "CNPJ; razão social; nome fantasia; natureza jurídica; porte; endereço; e-mail; telefone; CNAE principal; CNAE secundário",
                "Estabelecimento; CNAEs do estabelecimento",
                "consulta de CNPJ no cadastro de empresa, na renovação e na contingência",
                "Mantido na Receita Federal. A consulta que só preenche formulário não é transação própria; o arquivo é referenciado pela inclusão da empresa e pela contingência. ",
            ),
            f(
                "Dados externos - Cadastro de pessoa da SEDUR",
                "AIE",
                "CPF; nome; indicação de cadastro existente; telefone",
                "Pessoa",
                "consulta de CPF no atendimento e na contingência",
                "Mantido em base da SEDUR fora do Viabiliza. A consulta auxilia a abertura do atendimento e o registro em contingência. ",
            ),
            f(
                "Empresas - consultar",
                "CE",
                "CNPJ; razão social; nome fantasia; situação do vínculo; CNAE principal; endereço",
                "Empresa; CNAE",
                "lista e detalhe de empresas do portal",
            ),
            f(
                "Empresas - incluir",
                "EE",
                "CNPJ; razão social; nome fantasia; natureza jurídica; porte; endereço; e-mail; telefone; CNAE principal; CNAEs secundários",
                "Empresa; CNAE; Cadastro nacional de pessoa jurídica",
                "cadastrar empresa no portal",
                "A busca do CNPJ apenas recupera dados do arquivo externo para esta inclusão. ",
            ),
            f(
                "Empresas - alterar",
                "EE",
                "razão social; nome fantasia; endereço; e-mail; telefone; CNAE principal; CNAEs secundários",
                "Empresa; CNAE",
                "detalhe da empresa no portal",
            ),
            f(
                "Empresas - encerrar vínculo",
                "EE",
                "empresa; confirmação do desvínculo",
                "Empresa",
                "desvincular usuário da empresa",
                "Não exclui o estabelecimento. Encerra o vínculo do usuário. ",
            ),
            f(
                "Procurações - consultar",
                "CE",
                "outorgante; procurador; início da vigência; fim da vigência; situação",
                "Procuração; Usuário",
                "tela Procurações",
            ),
            f(
                "Procurações - incluir",
                "EE",
                "procurador; início da vigência; fim da vigência",
                "Procuração; Usuário",
                "tela Procurações",
            ),
            f(
                "Procurações - revogar",
                "EE",
                "procuração; confirmação da revogação",
                "Procuração",
                "tela Procurações",
            ),
            f(
                "Atendimento presencial - abrir",
                "EE",
                "CPF do cidadão; nome retornado; validade do atendimento",
                "Atendimento presencial; Usuário; Cadastro de pessoa da SEDUR",
                "tela Atendimento presencial",
                "A consulta do CPF compõe esta abertura. A solicitação feita em seguida é a inclusão de solicitação. ",
            ),
            f(
                "Atendimento presencial - encerrar",
                "EE",
                "atendimento em curso",
                "Atendimento presencial",
                "tela Atendimento presencial",
            ),
        ],
    ),
    grupo(
        "F3",
        "Solicitação e consulta de viabilidade",
        [
            f(
                "Dados - Solicitação de viabilidade",
                "ALI",
                "número de protocolo; situação; origem do canal; tipo de serviço; empresa; requerente; área utilizada; inscrição imobiliária; logradouro; número do imóvel; complemento; bairro; CEP; ponto de referência; localização geográfica; geometria do imóvel; zona; código do logradouro; escritório virtual; área pública; acesso independente; motivo da contingência; referência externa; data de protocolação; motivo do cancelamento; setor responsável; analista atribuído; categoria da análise; etapa da análise; prazo da análise; indicador de malha fina; tipo de imóvel; CNAE da atividade; indicador de atividade principal; requisito do documento; nome do arquivo; resultado da decisão; fluxo da decisão; fundamentação; número da TVL; validade da TVL; data da decisão; responsável pela decisão; código de verificação do documento; situação anterior; situação nova; motivo da tramitação; descrição da pendência; prazo da pendência; resposta da pendência; situação da pendência; canal da comunicação; assunto da comunicação; revisão da ficha; parecer técnico; tipo de vistoria; situação da vistoria; parecer da vistoria; motivo do encaminhamento; resultado da malha fina",
                "Solicitação; Atividades; Documentos; Decisão; Tramitações; Pendências; Comunicações; Ficha de análise; Vistoria; Malha fina",
                "wizard do portal, consulta de processos, ficha de análise, ficha de vistoria e malha fina",
                "Ficha, vistoria, decisão, pendência, documento e comunicação não sobrevivem sem a solicitação: são registros lógicos do mesmo arquivo. As medidas detalhadas da vistoria permanecem no RLR Vistoria e sustentam a faixa alta. ",
            ),
            f(
                "Dados - Histórico de consulta de viabilidade",
                "ALI",
                "tipo da consulta; dado informado; resultado; versão das regras; data; usuário",
                "Consulta de viabilidade",
                "histórico de consultas do portal",
                "A consulta prévia, sem protocolo, deixa este histórico. A solicitação protocolada é outro arquivo. ",
            ),
            f(
                "Dados externos - Inscrição imobiliária municipal",
                "AIE",
                "inscrição; logradouro; número; bairro; CEP; situação do imóvel",
                "Inscrição imobiliária",
                "consulta de inscrição na viabilidade e na contingência",
                "Mantida no cadastro imobiliário municipal, fora do Viabiliza. ",
            ),
            f(
                "Solicitações - incluir",
                "EE",
                "tipo de serviço; empresa; CNAE; área; endereço do imóvel; inscrição imobiliária; tipo de imóvel; intenção de escritório virtual",
                "Solicitação de viabilidade; Empresa; Tipo de serviço; CNAE",
                "nova solicitação no portal e no atendimento presencial",
                "Atividades, imóvel e documentos são passos da mesma manutenção do rascunho, até deixá-lo consistente. O atendimento de balcão reutiliza esta inclusão. ",
            ),
            f(
                "Solicitações - alterar",
                "EE",
                "atividades; área; endereço do imóvel; inscrição imobiliária; geometria; tipo de imóvel; documentos anexados; intenção de escritório virtual",
                "Solicitação de viabilidade; Requisito documental; CNAE",
                "wizard de edição da solicitação",
                "Incluir, trocar e retirar documento do rascunho integram a alteração. O download do anexo é recuperação do mesmo dado. ",
            ),
            f(
                "Solicitações - consultar",
                "CE",
                "protocolo; situação; origem; empresa; requerente; setor; analista; bairro; zona; CNAE; resultado; prazo; tipo de serviço; período; categoria",
                "Solicitação de viabilidade; Empresa; Usuário; Setor",
                "minhas solicitações, protocolo público, consulta de processos e detalhe do processo",
                "Fila, caixa, vistorias, malha fina e resultados do expresso são recortes desta consulta, não funções novas. O comprovante reapresenta os mesmos dados do protocolo. ",
            ),
            f(
                "Solicitações - cancelar",
                "EE",
                "solicitação; motivo do cancelamento",
                "Solicitação de viabilidade",
                "cancelar solicitação no portal",
            ),
            f(
                "Solicitações - simular viabilidade",
                "SE",
                "atividades; imóvel; zona; resultado por atividade; fundamentação; fluxo previsto; versão das regras",
                "Solicitação de viabilidade; Enquadramento de atividades; CNAE; Permissão de uso por zona; Condição de instalação por classe de via; Regra territorial",
                "simular no wizard, antes de protocolar",
                "Processo elementar independente: o requerente aciona a simulação e recebe o resultado completo da viabilidade do rascunho. O snapshot gravado é consequência dessa saída. ",
            ),
            f(
                "Solicitações - protocolar",
                "EE",
                "declarações aceitas; número de protocolo; resultado consolidado; data de protocolação",
                "Solicitação de viabilidade; Enquadramento de atividades; CNAE; Permissão de uso por zona; Regra territorial",
                "protocolar solicitação no portal",
                "Deixa a solicitação protocolada e, quando a regra permite, registra a decisão automática no mesmo arquivo. ",
            ),
            f(
                "Solicitações - renovar viabilidade",
                "EE",
                "solicitação de origem; tipo de serviço; declarações de responsabilidade aceitas; confirmação",
                "Solicitação de viabilidade; Tipo de serviço",
                "renovação no portal",
                "Cria a nova solicitação a partir de uma viabilidade vigente. As declarações exibidas não têm cadastro próprio: o aceite entra nesta entrada. ",
            ),
            f(
                "Solicitações - responder pendência",
                "EE",
                "pendência; resposta; documento de resposta",
                "Solicitação de viabilidade",
                "pendências da solicitação no portal",
            ),
            f(
                "Solicitações - registrar em contingência",
                "EE",
                "CPF ou CNPJ do beneficiário; empresa; CNAE; imóvel; inscrição; motivo da contingência; protocolo gerado",
                "Solicitação de viabilidade; Empresa; CNAE; Inscrição imobiliária municipal; Cadastro de pessoa da SEDUR",
                "nova solicitação de contingência na gestão",
                "Uma única ação do operador protocola o pedido, com motivo de contingência. As consultas de CPF, CNPJ e inscrição apenas alimentam esta entrada. ",
            ),
            f(
                "Viabilidade - consultar localização da atividade",
                "SE",
                "tipo da consulta; endereço; CNAE; inscrição imobiliária; zona identificada; resultado; fundamentação; versão das regras",
                "Histórico de consulta de viabilidade; Enquadramento de atividades; CNAE; Feição territorial do GeoServer; Inscrição imobiliária municipal; Permissão de uso por zona",
                "consulta de viabilidade do portal, por endereço, CNAE ou inscrição",
                "Os três modos são a mesma consulta: a intenção é saber a viabilidade antes de abrir solicitação. O histórico é mantido como consequência. ",
            ),
            f(
                "Viabilidade - consultar histórico",
                "CE",
                "data; tipo da consulta; dado informado; resultado",
                "Histórico de consulta de viabilidade",
                "histórico de consultas do portal",
            ),
            f(
                "Solicitações - consultar comunicações",
                "CE",
                "data; canal; assunto; situação do envio; resumo",
                "Solicitação de viabilidade",
                "histórico de comunicações no portal e na gestão",
                "A comunicação é registro lógico da solicitação. ",
            ),
            f(
                "Notificações - consultar",
                "CE",
                "data; assunto; situação de leitura",
                "Solicitação de viabilidade",
                "central de notificações da gestão e do portal",
            ),
            f(
                "Notificações - marcar como lida",
                "EE",
                "notificação; situação de leitura",
                "Solicitação de viabilidade",
                "central de notificações",
                "Marcar uma ou todas é a mesma manutenção da situação de leitura. ",
            ),
        ],
    ),
    grupo(
        "F4",
        "Análise técnica, vistoria e malha fina",
        [
            f(
                "Análise - assumir processo",
                "EE",
                "processo; analista",
                "Solicitação de viabilidade; Usuário",
                "caixa do setor, ação assumir",
            ),
            f(
                "Análise - atribuir processo",
                "EE",
                "processos; analista; setor",
                "Solicitação de viabilidade; Usuário; Setor",
                "distribuir, redistribuir e distribuição em lote",
                "Lote e sugestão de distribuição preparam a mesma atribuição. A sugestão não é saída à parte. ",
            ),
            f(
                "Análise - alterar ficha",
                "EE",
                "parecer; condicionantes; vagas; enquadramento por atividade; justificativa de divergência",
                "Solicitação de viabilidade",
                "ficha de análise, gravação do rascunho",
                "O salvamento automático é a mesma alteração. Texto sugerido pela assistência apenas preenche o parecer antes de gravar e não é função separada. ",
            ),
            f(
                "Análise - finalizar ficha",
                "EE",
                "revisão; situação da ficha; divergências materializadas",
                "Solicitação de viabilidade",
                "finalizar ficha de análise",
                "Torna a revisão imutável. Comparar revisões é leitura da mesma ficha. ",
            ),
            f(
                "Análise - abrir nova revisão",
                "EE",
                "processo; nova revisão",
                "Solicitação de viabilidade",
                "nova revisão da ficha",
            ),
            f(
                "Análise - registrar decisão",
                "EE",
                "resultado; fundamentação; parecer",
                "Solicitação de viabilidade; Texto decisório",
                "deferir, indeferir, encerrar, desativar, cassar ou revogar a partir da ficha ou da conclusão",
                "Os pontos de entrada registram o mesmo desfecho oficial do processo. ",
            ),
            f(
                "Análise - abrir pendência",
                "EE",
                "descrição; prazo",
                "Solicitação de viabilidade",
                "abrir pendência no processo",
            ),
            f(
                "Análise - cancelar pendência",
                "EE",
                "pendência; motivo do cancelamento",
                "Solicitação de viabilidade",
                "cancelar convite ou pendência",
            ),
            f(
                "Análise - encaminhar à malha fina",
                "EE",
                "processos; motivo",
                "Solicitação de viabilidade",
                "encaminhar um processo ou um lote à malha fina",
                "O lote repete a mesma entrada. ",
            ),
            f(
                "Análise - concluir malha fina",
                "EE",
                "resultado da revisão; observação",
                "Solicitação de viabilidade",
                "concluir na caixa de malha fina",
            ),
            f(
                "Análise - encaminhar à vistoria",
                "EE",
                "processo; motivo; setor de vistoria",
                "Solicitação de viabilidade; Setor",
                "encaminhar processo à vistoria",
            ),
            f(
                "Vistoria - alterar ficha",
                "EE",
                "tipo da vistoria; data; endereço conferido; áreas; pavimento; entorno; instalações; equipamentos; segurança; observações; geometria conferida; anexos",
                "Solicitação de viabilidade",
                "ficha de vistoria",
                "Incluir e retirar anexo, e validar o polígono, integram o preenchimento da mesma ficha. ",
            ),
            f(
                "Vistoria - concluir",
                "EE",
                "parecer; data da vistoria; situação da ficha",
                "Solicitação de viabilidade",
                "concluir ficha de vistoria",
            ),
            f(
                "Análise - consultar precedentes",
                "SE",
                "processo de referência; processo semelhante; grau de semelhança; decisão anterior; CNAE",
                "Solicitação de viabilidade",
                "painel de precedentes da ficha",
                "Processo elementar independente: o analista pede os casos semelhantes e recebe a lista ordenada. ",
            ),
            f(
                "Documentos - emitir TVL",
                "SE",
                "número da TVL; protocolo; requerente; endereço; atividades; resultado; fundamentação; validade; código de verificação",
                "Solicitação de viabilidade; Texto decisório",
                "emitir TVL do processo deferido",
                "Processo elementar independente: o analista aciona a emissão e o negócio entrega o termo de viabilidade. A obtenção posterior do arquivo já emitido integra esta saída. ",
            ),
            f(
                "Documentos - emitir indeferimento",
                "SE",
                "protocolo; requerente; endereço; atividades; resultado; fundamentação; código de verificação",
                "Solicitação de viabilidade; Texto decisório",
                "emitir documento do processo indeferido",
                "Processo elementar independente: a emissão produz o documento fundamentado de indeferimento. ",
            ),
            f(
                "Documentos - verificar autenticidade",
                "CE",
                "código de verificação; tipo do documento; protocolo; situação; data de emissão",
                "Solicitação de viabilidade",
                "página pública de verificação do documento",
            ),
            f(
                "Escritório virtual - desvincular inscrição",
                "EE",
                "inscrição da sede; processo; confirmação",
                "Solicitação de viabilidade",
                "desvincular inscrição na gestão",
            ),
            f(
                "Análise - enviar TVL para análise",
                "EE",
                "processo localizado; confirmação do envio",
                "Solicitação de viabilidade",
                "enviar processo de TVL para a fila de análise",
                "A pesquisa do processo apenas seleciona o alvo desta entrada. ",
            ),
        ],
    ),
    grupo(
        "F5",
        "Catálogos que decidem a viabilidade",
        [
            f(
                "Dados - CNAE",
                "ALI",
                "código; descrição; seção; divisão; grupo; classe; situação; risco municipal; exige responsável técnico; exige responsável técnico se alto; exige fator multiplicador; exige detalhamento do fator",
                "CNAE",
                "tela CNAEs",
                "O risco municipal vigente é atributo mantido na mesma ficha do CNAE. Hierarquia IBGE conta um DER por nível porque código e descrição daquele nível andam juntos. ",
            ),
            f(
                "Dados - Gatilho de queda do expresso",
                "ALI",
                "código; título; motivo; categoria; situação",
                "Gatilho",
                "tela Gatilhos de risco",
                "Não é dado de código: o motivo e a situação mudam o roteamento do expresso. Não há inclusão nem exclusão; o conjunto é fechado. ",
            ),
            f(
                "Dados - Tipo de serviço",
                "ALI",
                "código; nome; fluxo associado; situação",
                "Tipo de serviço",
                "tela Tipos de serviço",
                "O fluxo associado altera o encaminhamento da solicitação, por isso o tipo não é só código e descrição. ",
            ),
            f(
                "Dados - Tipo de imóvel",
                "ALI",
                "código; nome; interfere no expresso; situação",
                "Tipo de imóvel",
                "tela Tipos de imóvel",
                "O indicador que derruba o expresso é dado de negócio. Apelidos usados só no casamento técnico com o integrador são dado de código e ficam de fora. ",
            ),
            f(
                "Dados - Requisito documental",
                "ALI",
                "código; nome; descrição; obrigatoriedade; situação; instrução de validação; CNAE vinculado",
                "Requisito; Vínculo com CNAE",
                "tela Requisitos documentais",
            ),
            f(
                "Dados - Zona urbanística",
                "ALI",
                "código; nome; macrozona; situação",
                "Zona",
                "tela Zonas",
                "A zona é a referência territorial usada na permissão de uso e na consulta. Não se confunde com a feição mantida no GeoServer. ",
            ),
            f(
                "CNAEs - consultar",
                "CE",
                "código; descrição; seção; situação; risco municipal",
                "CNAE",
                "lista de CNAEs",
                "A busca auxiliar dos formulários não é outra consulta. ",
            ),
            f(
                "CNAEs - incluir",
                "EE",
                "código; descrição; seção; divisão; grupo; classe; risco municipal; exigências de responsável técnico e fator",
                "CNAE",
                "criar CNAE",
            ),
            f(
                "CNAEs - alterar",
                "EE",
                "descrição; situação; risco municipal; exigências de responsável técnico e fator",
                "CNAE",
                "editar CNAE",
                "Ativar e inativar integram a alteração. ",
            ),
            f(
                "CNAEs - excluir",
                "EE",
                "CNAE selecionado",
                "CNAE",
                "excluir CNAE",
            ),
            f(
                "Gatilhos de queda - consultar",
                "CE",
                "código; título; motivo; categoria; situação",
                "Gatilho de queda do expresso",
                "tela Gatilhos de risco",
            ),
            f(
                "Gatilhos de queda - alterar",
                "EE",
                "título; motivo; situação",
                "Gatilho de queda do expresso",
                "tela Gatilhos de risco",
                "Ligar e desligar o gatilho integram a alteração. ",
            ),
            f(
                "Tipos de serviço - consultar",
                "CE",
                "código; nome; fluxo associado; situação",
                "Tipo de serviço",
                "tela Tipos de serviço",
            ),
            f(
                "Tipos de serviço - incluir",
                "EE",
                "código; nome; fluxo associado",
                "Tipo de serviço",
                "tela Tipos de serviço",
            ),
            f(
                "Tipos de serviço - alterar",
                "EE",
                "nome; fluxo associado; situação",
                "Tipo de serviço",
                "tela Tipos de serviço",
            ),
            f(
                "Tipos de imóvel - consultar",
                "CE",
                "código; nome; interfere no expresso; situação",
                "Tipo de imóvel",
                "tela Tipos de imóvel",
            ),
            f(
                "Tipos de imóvel - incluir",
                "EE",
                "código; nome; interfere no expresso",
                "Tipo de imóvel",
                "tela Tipos de imóvel",
            ),
            f(
                "Tipos de imóvel - alterar",
                "EE",
                "nome; interfere no expresso; situação",
                "Tipo de imóvel",
                "tela Tipos de imóvel",
            ),
            f(
                "Requisitos documentais - consultar",
                "CE",
                "código; nome; obrigatoriedade; situação; CNAEs vinculados",
                "Requisito documental; CNAE",
                "tela Requisitos documentais",
            ),
            f(
                "Requisitos documentais - incluir",
                "EE",
                "código; nome; descrição; obrigatoriedade; instrução de validação",
                "Requisito documental",
                "tela Requisitos documentais",
            ),
            f(
                "Requisitos documentais - alterar",
                "EE",
                "nome; descrição; obrigatoriedade; situação; instrução; CNAEs vinculados",
                "Requisito documental; CNAE",
                "tela Requisitos documentais",
            ),
            f(
                "Zonas - consultar",
                "CE",
                "código; nome; macrozona; situação",
                "Zona urbanística",
                "tela Zonas",
            ),
            f(
                "Zonas - incluir",
                "EE",
                "código; nome; macrozona",
                "Zona urbanística",
                "tela Zonas",
            ),
            f(
                "Zonas - alterar",
                "EE",
                "nome; macrozona; situação",
                "Zona urbanística",
                "tela Zonas",
            ),
        ],
    ),
    grupo(
        "F6",
        "Motor de regras da LOUOS",
        [
            f(
                "Dados - Enquadramento de atividades",
                "ALI",
                "versão; vigência; situação da versão; CNAE; denominação; risco; regra de tratamento; faixa de área; enquadramento; código TLL; especificação TLL; vagas; pergunta; resposta do ramo; fluxo do ramo; condicionante; nota; código CNLU; código LOUOS; base legal",
                "Versão; Linha de enquadramento; Ramo da regra; Pergunta; Condicionante; Item CNLU",
                "aba de enquadramento do motor e importação da planilha",
                "Perguntas, ramos, condicionantes, notas e itens CNLU são subgrupos da versão de enquadramento. ",
            ),
            f(
                "Dados - Permissão de uso por zona",
                "ALI",
                "versão; vigência; situação da versão; zona; grupo de uso; subgrupo; permissão; condicionante; base legal; observação",
                "Versão; Linha de permissão",
                "Quadro 10 no motor de regras",
            ),
            f(
                "Dados - Condição de instalação por classe de via",
                "ALI",
                "versão; vigência; situação da versão; classe de via; grupo de uso; condições; base legal; observação",
                "Versão; Linha de condição",
                "Quadro 11A no motor de regras",
                "A classe de via é atributo desta matriz. O cadastro auxiliar de vias, só com código, nome e situação, é dado de código e não entra. ",
            ),
            f(
                "Dados - Regra territorial",
                "ALI",
                "versão; vigência; código; título; tipo; efeito; logradouro; número; bairro; camada; filtro da camada; zona alvo; motivo de análise; base legal; situação; CNAE vinculado",
                "Versão; Regra; Endereços e CNAEs da regra",
                "aba de regras territoriais",
            ),
            f(
                "Dados - Atividade de escritório virtual",
                "ALI",
                "versão; vigência; situação da versão; CNAE; anexo; autor da publicação",
                "Versão; CNAE do anexo",
                "tela Anexos de escritório virtual",
                "Anexos A e B do decreto são subgrupos da mesma lista versionada. ",
            ),
            f(
                "Enquadramento - consultar",
                "CE",
                "versão; vigência; situação; CNAE; denominação; regra; faixa de área; enquadramento",
                "Enquadramento de atividades",
                "aba de enquadramento do motor",
            ),
            f(
                "Enquadramento - importar planilha",
                "EE",
                "arquivo da planilha; versão; totais importados",
                "Enquadramento de atividades",
                "importar planilha de enquadramento",
                "A planilha é o modo de incluir a versão. Não há edição linha a linha desta base. ",
            ),
            f(
                "Enquadramento - publicar versão",
                "EE",
                "versão; autor; publicação",
                "Enquadramento de atividades",
                "publicar versão de enquadramento",
                "A publicação por quatro olhos torna a versão vigente. ",
            ),
            f(
                "Permissões por zona - consultar",
                "CE",
                "versão; zona; grupo de uso; subgrupo; permissão; base legal",
                "Permissão de uso por zona; Zona urbanística",
                "Quadro 10",
            ),
            f(
                "Permissões por zona - incluir linha",
                "EE",
                "zona; grupo de uso; subgrupo; permissão; condicionante; base legal; observação",
                "Permissão de uso por zona; Zona urbanística",
                "rascunho do Quadro 10",
                "A carga por arquivo repete esta inclusão. ",
            ),
            f(
                "Permissões por zona - alterar linha",
                "EE",
                "zona; grupo de uso; subgrupo; permissão; condicionante; base legal; observação",
                "Permissão de uso por zona",
                "rascunho do Quadro 10",
            ),
            f(
                "Permissões por zona - excluir linha",
                "EE",
                "linha do rascunho",
                "Permissão de uso por zona",
                "rascunho do Quadro 10",
            ),
            f(
                "Permissões por zona - publicar versão",
                "EE",
                "versão; autor; publicação",
                "Permissão de uso por zona",
                "publicar Quadro 10",
            ),
            f(
                "Permissões por zona - ativar versão",
                "EE",
                "versão; situação",
                "Permissão de uso por zona",
                "ativar versão do Quadro 10",
            ),
            f(
                "Permissões por zona - descartar rascunho",
                "EE",
                "rascunho",
                "Permissão de uso por zona",
                "descartar rascunho do Quadro 10",
            ),
            f(
                "Condições de via - consultar",
                "CE",
                "versão; classe de via; grupo de uso; condições; base legal",
                "Condição de instalação por classe de via",
                "Quadro 11A",
            ),
            f(
                "Condições de via - incluir linha",
                "EE",
                "classe de via; grupo de uso; condições; base legal; observação",
                "Condição de instalação por classe de via",
                "rascunho do Quadro 11A",
                "A carga por arquivo repete esta inclusão. ",
            ),
            f(
                "Condições de via - alterar linha",
                "EE",
                "classe de via; grupo de uso; condições; base legal; observação",
                "Condição de instalação por classe de via",
                "rascunho do Quadro 11A",
            ),
            f(
                "Condições de via - excluir linha",
                "EE",
                "linha do rascunho",
                "Condição de instalação por classe de via",
                "rascunho do Quadro 11A",
            ),
            f(
                "Condições de via - publicar versão",
                "EE",
                "versão; autor; publicação",
                "Condição de instalação por classe de via",
                "publicar Quadro 11A",
            ),
            f(
                "Condições de via - ativar versão",
                "EE",
                "versão; situação",
                "Condição de instalação por classe de via",
                "ativar versão do Quadro 11A",
            ),
            f(
                "Condições de via - descartar rascunho",
                "EE",
                "rascunho",
                "Condição de instalação por classe de via",
                "descartar rascunho do Quadro 11A",
            ),
            f(
                "Regras territoriais - consultar",
                "CE",
                "código; título; tipo; efeito; logradouro; CNAE; zona alvo; situação",
                "Regra territorial",
                "aba de regras territoriais",
            ),
            f(
                "Regras territoriais - gravar rascunho",
                "EE",
                "código; título; tipo; efeito; endereços; CNAEs; camada; zona alvo; motivo; base legal",
                "Regra territorial",
                "rascunho de regra territorial",
            ),
            f(
                "Regras territoriais - publicar versão",
                "EE",
                "versão; publicação",
                "Regra territorial",
                "publicar regras territoriais",
            ),
            f(
                "Regras territoriais - ativar versão",
                "EE",
                "versão; situação",
                "Regra territorial",
                "ativar regras territoriais",
            ),
            f(
                "Regras territoriais - descartar rascunho",
                "EE",
                "rascunho",
                "Regra territorial",
                "descartar rascunho territorial",
            ),
            f(
                "Regras - simular aplicação",
                "SE",
                "CNAE; zona; área; endereço; resultado; fundamentação; versão das regras",
                "Enquadramento de atividades; Permissão de uso por zona; Regra territorial; CNAE",
                "simulação de regras do motor",
                "Processo elementar independente: o gestor informa um caso e recebe o resultado da regra, sem protocolar solicitação. Publicar a partir da simulação é a publicação da versão já contada. ",
            ),
            f(
                "Escritório virtual - consultar anexos",
                "CE",
                "versão; situação; quantidade do anexo A; quantidade do anexo B",
                "Atividade de escritório virtual",
                "tela Anexos de escritório virtual",
            ),
            f(
                "Escritório virtual - carregar rascunho dos anexos",
                "EE",
                "arquivo do anexo A; arquivo do anexo B; versão",
                "Atividade de escritório virtual",
                "carga do rascunho dos anexos",
            ),
            f(
                "Escritório virtual - publicar anexos",
                "EE",
                "versão; autor; publicação",
                "Atividade de escritório virtual",
                "publicar anexos de escritório virtual",
            ),
        ],
    ),
    grupo(
        "F7",
        "Território",
        [
            f(
                "Dados - Camada geográfica",
                "ALI",
                "tipo; versão; situação; vigência; origem; geometria da feição; atributos da feição",
                "Camada; Feição",
                "tela Camadas geográficas",
            ),
            f(
                "Dados - Catálogo de camadas do GeoServer",
                "ALI",
                "espaço de trabalho; nome da camada; rótulo; situação; ordem",
                "Camada configurada",
                "tela Camadas do GeoServer",
                "É a lista que o Viabiliza mantém para saber quais camadas consultar. A feição em si está no arquivo externo. ",
            ),
            f(
                "Dados externos - Feição territorial do GeoServer",
                "AIE",
                "identificador da feição; camada; zona; bairro; logradouro; código do logradouro; geometria",
                "Feição territorial",
                "consulta territorial, identificação do imóvel e viabilidade por endereço",
                "Mantida no GeoServer da SEDUR. ",
            ),
            f(
                "Território - consultar localização",
                "SE",
                "endereço; coordenadas; zona; bairro; logradouro; camadas incidentes; resultado da validação",
                "Feição territorial do GeoServer; Zona urbanística; Camada geográfica",
                "consulta territorial: localizar, identificar e validar",
                "Geocodificar, identificar a feição e validar a localização são passos da mesma consulta territorial. ",
            ),
            f(
                "Camadas geográficas - consultar",
                "CE",
                "tipo; versão; situação; vigência; origem; quantidade de feições",
                "Camada geográfica",
                "tela Camadas geográficas",
            ),
            f(
                "Camadas geográficas - incluir",
                "EE",
                "tipo; origem; vigência",
                "Camada geográfica",
                "tela Camadas geográficas",
            ),
            f(
                "Camadas do GeoServer - consultar",
                "CE",
                "espaço de trabalho; nome da camada; rótulo; situação; ordem",
                "Catálogo de camadas do GeoServer",
                "tela Camadas do GeoServer",
            ),
            f(
                "Camadas do GeoServer - incluir",
                "EE",
                "espaço de trabalho; nome da camada; rótulo; ordem",
                "Catálogo de camadas do GeoServer",
                "tela Camadas do GeoServer",
            ),
            f(
                "Camadas do GeoServer - alterar",
                "EE",
                "rótulo; situação; ordem",
                "Catálogo de camadas do GeoServer",
                "tela Camadas do GeoServer",
            ),
        ],
    ),
    grupo(
        "F8",
        "Configuração institucional",
        [
            f(
                "Dados - Parâmetro",
                "ALI",
                "chave; grupo; tipo; valor; descrição; sensível; valor proposto; situação da proposta; revisor; data da revisão",
                "Parâmetro; Proposta de alteração",
                "telas Parâmetros e APIs",
                "URL e credencial de REGIN, inscrição imobiliária e demais APIs são parâmetros deste arquivo, não arquivos novos. ",
            ),
            f(
                "Dados - Feriado",
                "ALI",
                "data; nome; recorrência anual; situação",
                "Feriado",
                "tela Feriados",
                "Entra no cálculo do prazo e não é dado de código. ",
            ),
            f(
                "Dados - Valor da TLL",
                "ALI",
                "código; exercício; valor; taxa de serviço; código na SEFAZ; especificação; situação; versão",
                "Valor; Exercício publicado",
                "tela Valores TLL",
            ),
            f(
                "Dados - Termo legal",
                "ALI",
                "tipo; versão; título; conteúdo; data de publicação; usuário do aceite; data do aceite",
                "Termo; Aceite",
                "tela Termos legais e aceite LGPD",
            ),
            f(
                "Dados - Texto decisório",
                "ALI",
                "chave; descrição; texto do documento",
                "Texto decisório",
                "tela Textos decisórios",
                "A chave é estável; o gestor altera o texto emitido nos documentos. Não há inclusão nem exclusão. ",
            ),
            f(
                "Dados - Texto-padrão",
                "ALI",
                "categoria; conteúdo; situação; versão",
                "Texto-padrão",
                "tela Minutas e textos-padrão",
            ),
            f(
                "Dados - Servidor de e-mail",
                "ALI",
                "nome; servidor; porta; criptografia; usuário; remetente; nome do remetente; situação; indicador de padrão",
                "Servidor de e-mail",
                "tela Servidores de e-mail",
            ),
            f(
                "Dados - Provedor de inteligência artificial",
                "ALI",
                "nome; provedor; finalidade; modelo; situação; indicador de padrão",
                "Provedor",
                "tela Configuração de IA",
                "A credencial não é reexibida e não entra como DER de negócio além da existência da configuração. ",
            ),
            f(
                "Dados - Registro de envio de e-mail",
                "ALI",
                "destinatário; tipo da mensagem; situação; erro; data de envio; data da falha",
                "Envio",
                "tela Monitoramento de e-mails",
                "Mantido pela aplicação quando uma comunicação sai. O usuário só consulta. ",
            ),
            f(
                "Parâmetros - consultar",
                "CE",
                "grupo; chave; descrição; valor; situação da proposta",
                "Parâmetro",
                "telas Parâmetros e APIs",
                "O histórico do valor anterior aparece nesta consulta. REGIN, inscrição e demais APIs são grupos do mesmo parâmetro. ",
            ),
            f(
                "Parâmetros - alterar",
                "EE",
                "parâmetro; valor novo",
                "Parâmetro",
                "Parâmetros e APIs, inclusive REGIN e inscrição imobiliária",
                "Testar a conexão confirma a gravação e não é outra função. ",
            ),
            f(
                "Parâmetros - aprovar alteração",
                "EE",
                "valor proposto; valor anterior; decisão",
                "Parâmetro",
                "aprovar proposta de parâmetro",
            ),
            f(
                "Parâmetros - rejeitar alteração",
                "EE",
                "valor proposto; decisão",
                "Parâmetro",
                "rejeitar proposta de parâmetro",
            ),
            f(
                "Feriados - consultar",
                "CE",
                "data; nome; recorrência; situação",
                "Feriado",
                "tela Feriados",
            ),
            f(
                "Feriados - incluir",
                "EE",
                "data; nome; recorrência anual",
                "Feriado",
                "tela Feriados",
            ),
            f(
                "Feriados - alterar",
                "EE",
                "data; nome; recorrência; situação",
                "Feriado",
                "tela Feriados",
            ),
            f(
                "Valores TLL - consultar",
                "CE",
                "código; exercício; valor; taxa de serviço; especificação; situação",
                "Valor da TLL",
                "tela Valores TLL",
            ),
            f(
                "Valores TLL - incluir",
                "EE",
                "código; exercício; valor; taxa de serviço; códigos SEFAZ; especificação",
                "Valor da TLL",
                "tela Valores TLL",
            ),
            f(
                "Valores TLL - alterar",
                "EE",
                "valor; taxa de serviço; especificação; situação",
                "Valor da TLL",
                "tela Valores TLL",
            ),
            f(
                "Valores TLL - gerar exercício",
                "EE",
                "exercício de origem; exercício novo",
                "Valor da TLL",
                "gerar tabela de exercício",
            ),
            f(
                "Valores TLL - publicar exercício",
                "EE",
                "exercício; publicação",
                "Valor da TLL",
                "publicar exercício da TLL",
            ),
            f(
                "Termos legais - consultar",
                "CE",
                "tipo; versão; título; situação; data de publicação",
                "Termo legal",
                "tela Termos legais",
            ),
            f(
                "Termos legais - incluir",
                "EE",
                "tipo; título; conteúdo",
                "Termo legal",
                "tela Termos legais",
            ),
            f(
                "Termos legais - alterar",
                "EE",
                "título; conteúdo",
                "Termo legal",
                "tela Termos legais",
                "Só a versão ainda não publicada pode ser alterada. ",
            ),
            f(
                "Termos legais - publicar",
                "EE",
                "termo; data de publicação",
                "Termo legal",
                "publicar termo legal",
            ),
            f(
                "Termos legais - excluir",
                "EE",
                "termo não publicado",
                "Termo legal",
                "excluir termo legal",
            ),
            f(
                "Textos decisórios - consultar",
                "CE",
                "chave; descrição; texto",
                "Texto decisório",
                "tela Textos decisórios",
            ),
            f(
                "Textos decisórios - alterar",
                "EE",
                "descrição; texto do documento",
                "Texto decisório",
                "tela Textos decisórios",
            ),
            f(
                "Textos-padrão - consultar",
                "CE",
                "categoria; conteúdo; situação; versão",
                "Texto-padrão",
                "tela Minutas e textos-padrão",
            ),
            f(
                "Textos-padrão - incluir",
                "EE",
                "categoria; conteúdo",
                "Texto-padrão",
                "tela Minutas e textos-padrão",
            ),
            f(
                "Textos-padrão - alterar",
                "EE",
                "categoria; conteúdo; situação",
                "Texto-padrão",
                "tela Minutas e textos-padrão",
                "Nova versão do texto nasce desta alteração. Não há exclusão. ",
            ),
            f(
                "Servidores de e-mail - consultar",
                "CE",
                "nome; servidor; porta; remetente; situação; indicador de padrão",
                "Servidor de e-mail",
                "tela Servidores de e-mail",
            ),
            f(
                "Servidores de e-mail - incluir",
                "EE",
                "nome; servidor; porta; criptografia; usuário; remetente; nome do remetente; indicador de padrão",
                "Servidor de e-mail",
                "tela Servidores de e-mail",
            ),
            f(
                "Servidores de e-mail - alterar",
                "EE",
                "servidor; porta; criptografia; remetente; situação; indicador de padrão",
                "Servidor de e-mail",
                "tela Servidores de e-mail",
            ),
            f(
                "Servidores de e-mail - excluir",
                "EE",
                "servidor selecionado",
                "Servidor de e-mail",
                "tela Servidores de e-mail",
            ),
            f(
                "Provedores de IA - consultar",
                "CE",
                "nome; provedor; finalidade; modelo; situação; indicador de padrão",
                "Provedor de inteligência artificial",
                "tela Configuração de IA",
            ),
            f(
                "Provedores de IA - incluir",
                "EE",
                "nome; provedor; finalidade; modelo; indicador de padrão",
                "Provedor de inteligência artificial",
                "tela Configuração de IA",
            ),
            f(
                "Provedores de IA - alterar",
                "EE",
                "finalidade; modelo; situação; indicador de padrão",
                "Provedor de inteligência artificial",
                "tela Configuração de IA",
            ),
            f(
                "Provedores de IA - excluir",
                "EE",
                "provedor selecionado",
                "Provedor de inteligência artificial",
                "tela Configuração de IA",
            ),
            f(
                "E-mails - consultar envios",
                "CE",
                "destinatário; tipo da mensagem; situação; erro; data de envio",
                "Registro de envio de e-mail",
                "tela Monitoramento de e-mails",
            ),
        ],
    ),
    grupo(
        "F9",
        "Indicadores, relatórios e auditoria",
        [
            f(
                "Dados - Trilha de auditoria",
                "ALI",
                "data e hora; usuário; ação; descrição; resultado; origem; canal; versão de regras; indicação de dado pessoal; objeto afetado; evento de acesso",
                "Evento de negócio; Acesso de usuário",
                "trilha de auditoria e histórico de acessos",
                "O acesso é subgrupo da trilha. Vários processos gravam o evento como consequência; a função reconhecida é consultá-lo. ",
            ),
            f(
                "Dados - Alerta de abuso",
                "ALI",
                "regra; severidade; situação; evidência; período; solicitação relacionada; responsável pela resolução; data da resolução; justificativa",
                "Alerta",
                "tela Alertas de abuso",
            ),
            f(
                "Dados - Anomalia preditiva",
                "ALI",
                "solicitação; pontuação; severidade; situação; fatores; período; responsável; justificativa",
                "Anomalia",
                "tela Auditoria preditiva",
            ),
            f(
                "Painel - consultar operação",
                "SE",
                "período; protocolos; variação contra o período anterior; decisões; estoque; atrasados; taxa do expresso; tempo médio de decisão; projeção",
                "Solicitação de viabilidade; Setor; Usuário",
                "painel da gestão",
                "Reúne indicadores derivados da operação. Cada relatório abaixo responde uma pergunta própria, com outro recorte. ",
            ),
            f(
                "Relatórios - consultar indicadores de viabilidade",
                "SE",
                "período; volume de protocolos; decisões por fluxo; série temporal; taxa",
                "Solicitação de viabilidade",
                "relatório Indicadores de viabilidade",
                "A exportação do mesmo recorte integra esta saída. ",
            ),
            f(
                "Relatórios - consultar SLA e vencimentos",
                "SE",
                "período; processo; prazo; situação do prazo; dias de atraso; setor",
                "Solicitação de viabilidade; Feriado",
                "relatório SLA e vencimentos",
                "O prazo desconta feriado, por isso o calendário é referenciado. ",
            ),
            f(
                "Relatórios - consultar tempo de análise",
                "SE",
                "período; tempo médio; distribuição; quantidade de amostras",
                "Solicitação de viabilidade",
                "relatório Tempo de análise",
            ),
            f(
                "Relatórios - consultar produtividade",
                "SE",
                "período; analista; quantidade decidida; deferidas; indeferidas; atribuídas",
                "Solicitação de viabilidade; Usuário",
                "relatório Produtividade",
            ),
            f(
                "Relatórios - consultar tempo de emissão de TVL",
                "SE",
                "período; tempo até a emissão; quantidade",
                "Solicitação de viabilidade",
                "relatório Tempo de emissão de TVL",
            ),
            f(
                "Relatórios - consultar quedas do expresso",
                "SE",
                "período; motivo; gatilho; quantidade; taxa",
                "Solicitação de viabilidade; Gatilho de queda do expresso",
                "relatório Quedas do expresso",
            ),
            f(
                "Relatórios - consultar pendências",
                "SE",
                "período; situação da pendência; quantidade; prazo; processo",
                "Solicitação de viabilidade",
                "relatório Pendências e exigências",
            ),
            f(
                "Relatórios - consultar painel por bairro",
                "SE",
                "período; bairro; zona; quantidade; taxa de deferimento",
                "Solicitação de viabilidade; Zona urbanística",
                "relatório Painel por bairro",
            ),
            f(
                "Relatórios - consultar saturação locacional",
                "SE",
                "período; bairro; CNAE; pares avaliados; saturados; limiar",
                "Solicitação de viabilidade; CNAE; Parâmetro",
                "relatório Saturação locacional",
            ),
            f(
                "Relatórios - consultar escritório virtual",
                "SE",
                "período; sedes; abrigados; inscrição",
                "Solicitação de viabilidade",
                "relatório Escritório virtual",
            ),
            f(
                "Relatórios - consultar contingência",
                "SE",
                "período; quantidade; operador; motivo",
                "Solicitação de viabilidade; Usuário",
                "relatório Atendimento em contingência",
            ),
            f(
                "Relatórios - consultar falhas de comunicação",
                "SE",
                "período; canal; situação; quantidade; erro",
                "Solicitação de viabilidade",
                "relatório Falhas de comunicação",
            ),
            f(
                "Auditoria - consultar trilha",
                "CE",
                "período; usuário; ação; resultado; origem; objeto; processo",
                "Trilha de auditoria; Solicitação de viabilidade",
                "trilha de auditoria e trilha por processo, inclusive impressão",
                "A impressão e a exportação repetem esta consulta. ",
            ),
            f(
                "Auditoria - consultar acessos",
                "CE",
                "usuário; evento; origem; data e hora",
                "Trilha de auditoria",
                "histórico de acessos na gestão e no portal",
            ),
            f(
                "Auditoria - consultar monitoramento LGPD",
                "SE",
                "consentimentos; retenção; acessos a dado pessoal; período",
                "Termo legal; Trilha de auditoria",
                "painel de monitoramento LGPD",
            ),
            f(
                "Alertas de abuso - consultar",
                "CE",
                "regra; severidade; situação; período; solicitação; justificativa",
                "Alerta de abuso",
                "tela Alertas de abuso",
            ),
            f(
                "Alertas de abuso - confirmar",
                "EE",
                "alerta; justificativa; situação",
                "Alerta de abuso",
                "confirmar alerta",
            ),
            f(
                "Alertas de abuso - descartar",
                "EE",
                "alerta; justificativa; situação",
                "Alerta de abuso",
                "descartar alerta",
            ),
            f(
                "Anomalias preditivas - consultar",
                "CE",
                "solicitação; pontuação; severidade; situação; fatores; período",
                "Anomalia preditiva",
                "tela Auditoria preditiva",
            ),
            f(
                "Anomalias preditivas - confirmar",
                "EE",
                "anomalia; justificativa; situação",
                "Anomalia preditiva",
                "confirmar anomalia",
            ),
            f(
                "Anomalias preditivas - descartar",
                "EE",
                "anomalia; justificativa; situação",
                "Anomalia preditiva",
                "descartar anomalia",
            ),
        ],
    ),
    grupo(
        "F10",
        "Canal do integrador REGIN",
        [
            f(
                "Dados - Simulação de protocolo REGIN",
                "ALI",
                "identificador; dados informados; resultado da classificação; data; operador",
                "Simulação",
                "tela Simulação REGIN",
            ),
            f(
                "REGIN - receber solicitação",
                "EE",
                "protocolo REDESIM; CNPJ; atividades; endereço; inscrição; evento do integrador; protocolo interno",
                "Solicitação de viabilidade; Empresa",
                "serviço de recebimento do integrador",
                "O integrador é usuário externo desta entrada. O payload não é arquivo de outra aplicação mantido para consulta: a solicitação passa a ser do Viabiliza. ",
            ),
            f(
                "REGIN - consultar simulações",
                "CE",
                "identificador; data; operador; resultado",
                "Simulação de protocolo REGIN",
                "tela Simulação REGIN",
            ),
            f(
                "REGIN - simular protocolo",
                "SE",
                "atividades informadas; imóvel; risco resultante; enquadramento; fundamentação",
                "Simulação de protocolo REGIN; CNAE; Enquadramento de atividades",
                "simular protocolo na tela Simulação REGIN",
                "Processo elementar independente: o gestor dispara a simulação e recebe a classificação, sem receber solicitação real. ",
            ),
            f(
                "REGIN - excluir simulação",
                "EE",
                "simulação selecionada",
                "Simulação de protocolo REGIN",
                "apagar simulação REGIN",
            ),
        ],
    ),
]


def validar(grupos: list[dict]) -> None:
    nomes = [item["nome"] for grupo in grupos for item in grupo["itens"]]
    duplicados = [nome for nome, vezes in Counter(nomes).items() if vezes > 1]
    if duplicados:
        raise SystemExit(f"Nomes duplicados: {duplicados}")

    arquivos = set()
    for grupo in grupos:
        for item in grupo["itens"]:
            if item["tipo"] in {"ALI", "AIE"}:
                arquivos.add(item["nome"].split(" - ", 1)[1])

    suspeitos = (
        "gerar pdf",
        "assinar",
        "enfileirar",
        "processar",
        "reprocessar",
        "registrar log",
        "registrar consulta",
        "notificar",
        "enviar e-mail",
        "aplicar template",
        "pré-validar",
        "preencher formulário",
        "calcular hash",
    )
    for grupo in grupos:
        for item in grupo["itens"]:
            texto = f"{item['nome']} {item['observacoes']}".lower()
            for termo in suspeitos:
                if termo in texto:
                    raise SystemExit(f"Termo suspeito em {item['nome']}: {termo}")
            if item["tipo"] in TRANS:
                refs = item["observacoes"].split("FTR (", 1)[1]
                refs = refs.split("): ", 1)[1].split(". ")[0]
                for ref in _partes(refs):
                    if ref not in arquivos:
                        raise SystemExit(f"FTR sem arquivo em {item['nome']}: {ref}")


def resumo(grupos: list[dict]) -> dict:
    totais = Counter()
    pf = Counter()
    for grupo in grupos:
        for item in grupo["itens"]:
            totais[item["tipo"]] += 1
            pf[item["tipo"]] += item["pf"]
    total = sum(pf.values())
    return {
        "funcoes": dict(totais),
        "pf": dict(pf),
        "total": total,
    }


def main() -> None:
    validar(GRUPOS)
    quadros = resumo(GRUPOS)
    dados = {
        "identificacao": {
            "empresa": "Sudoeste Informática",
            "aplicacao": "Viabiliza — Sistema de Licenciamento Eletrônico da SEDUR",
            "projeto": "Contagem IFPUG - Projeto de Desenvolvimento",
            "dv_numero": "DV-SILE-004",
            "responsavel": "Equipe de Análise — Sudoeste Informática",
            "versao": "2.0",
            "revisor": "",
            "data_contagem": "01/10/2026",
            "tipo_contagem": "desenvolvimento",
            "rs_por_pf": 700,
            "proposito": (
                "Medir o tamanho funcional do Viabiliza entregue, em contagem detalhada IFPUG CPM 4.3.1, "
                "pela visão do usuário. Rotas, telas e dados persistidos serviram de evidência; classe, serviço "
                "e tabela física não foram contados como função. O fator de inclusão é 1,00, então o PF local "
                "coincide com o PF não ajustado. O VAF não foi aplicado."
            ),
        },
        "termo": {
            "sistema": "Viabiliza — SISTEMA DE LICENCIAMENTO ELETRÔNICO",
            "nome_curto": "Viabiliza",
            "data": "Salvador-BA, 1 de outubro de 2026",
            "cliente": "Prefeitura Municipal de Salvador",
            "cliente_linha2": "Secretaria Municipal de Desenvolvimento Urbano e Sustentabilidade — SEDUR",
            "cliente_contratante": "Prefeitura Municipal de Salvador",
            "processo_licitatorio": "A definir",
            "contrato": "A definir",
            "processo_administrativo": "A definir",
            "planilha_anexa": "contagem-viabiliza-codigo-fonte.xlsx",
            "elaborado_por": "Equipe de Análise — Sudoeste Informática",
            "elaborado_cargo": "Analista de Pontos de Função",
            "observacoes": (
                "Contagem detalhada do projeto de desenvolvimento, CPM IFPUG 4.3.1. "
                "O PF local usa fator de inclusão 1,00 e por isso é igual ao PF não ajustado. "
                "Não foi aplicado fator de ajuste (VAF). A memória de cálculo, com DER, RLR e FTR, está na planilha anexa."
            ),
        },
        "grupos": GRUPOS,
        "proposta": {
            "titulo": "PROPOSTA COMERCIAL",
            "sistema": "Viabiliza — Sistema de Licenciamento Eletrônico",
            "subtitulo": "Prefeitura Municipal de Salvador — SEDUR",
            "data": "01/10/2026",
            "objeto": "Dimensionamento funcional do Viabiliza entregue, conforme a contagem detalhada IFPUG 4.3.1.",
            "prazo": "Conforme contrato e ordem de serviço.",
        },
        "escopo_macro": [
            {
                "titulo": "1. Acesso, usuários, perfis e setores",
                "referencia": "F1",
                "itens": [
                    "Autenticação da gestão e do portal",
                    "Usuários, perfis de acesso e setores",
                ],
            },
            {
                "titulo": "2. Empresas, procurações e atendimento",
                "referencia": "F2",
                "itens": [
                    "Cadastro de empresas e procurações",
                    "Atendimento presencial",
                ],
            },
            {
                "titulo": "3. Solicitação e consulta de viabilidade",
                "referencia": "F3",
                "itens": [
                    "Consulta prévia, solicitação, simulação, protocolo, renovação e contingência",
                ],
            },
            {
                "titulo": "4. Análise técnica, vistoria e malha fina",
                "referencia": "F4",
                "itens": [
                    "Distribuição, ficha, decisão, vistoria, malha fina e documentos oficiais",
                ],
            },
            {
                "titulo": "5. Catálogos e motor de regras",
                "referencia": "F5 e F6",
                "itens": [
                    "CNAE, gatilhos, zonas, quadros da LOUOS, regras territoriais e escritório virtual",
                ],
            },
            {
                "titulo": "6. Território, configuração, relatórios e REGIN",
                "referencia": "F7 a F10",
                "itens": [
                    "Consulta territorial, parâmetros institucionais, indicadores e recebimento do integrador",
                ],
            },
        ],
        "resumo_pf": {
            "ee": quadros["pf"].get("EE", 0),
            "se": quadros["pf"].get("SE", 0),
            "ce": quadros["pf"].get("CE", 0),
            "ali": quadros["pf"].get("ALI", 0),
            "aie": quadros["pf"].get("AIE", 0),
            "total_nao_ajustado": quadros["total"],
            "vaf": 1.0,
            "total_ajustado": quadros["total"],
        },
        "metodologia_contagem": (
            "Contagem detalhada de projeto de desenvolvimento, IFPUG CPM 4.3.1. "
            "Fronteira: o Viabiliza. Usuários: requerente, procurador, atendente, analista, vistoriador, gestor e integrador REGIN. "
            "Fora da fronteira: Receita Federal, cadastro de pessoa da SEDUR, inscrição imobiliária municipal e GeoServer. "
            "Dados de código excluídos: cadastro de vias (código, nome e situação) e apelidos técnicos de tipo de imóvel (Parte 3 Cap. 1). "
            "Classificação sanitária e condicionantes da VISA não têm processo elementar de manutenção na aplicação medida; o risco municipal é atributo do CNAE. "
            "Fila, caixa, exportação do mesmo recorte, teste de conexão, sugestão de texto e comparação de revisão não foram contados à parte. "
            "PF local = PF não ajustado, fator de inclusão 1,00. VAF não aplicado."
        ),
    }
    SAIDA.write_text(json.dumps(dados, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps({"funcoes": quadros["funcoes"], "pf": quadros["pf"], "total": quadros["total"]}, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
