<?php

namespace Database\Seeders;

use App\Models\DecisionText;
use App\Services\Decisao\DecisionTextCatalog;
use Illuminate\Database\Seeder;

/**
 * Catálogo inicial dos textos decisórios (TVL, parecer, ficha do cidadão) —
 * os textos que viviam em constantes PHP dos motores, agora administráveis
 * por chave estável. Fonte única: os templates vêm de
 * DecisionTextCatalog::defaults() (verbatim do código); aqui ficam só as
 * descrições/orientações de edição, incluindo o contrato de placeholders.
 *
 * Idempotente: firstOrCreate por chave — re-seed não duplica nem reescreve o
 * template editado pela gestão.
 */
class DecisionTextSeeder extends Seeder
{
    /** @var array<string, string> */
    private const DESCRICOES = [
        'base_legal.louos' => 'Fundamento legal do enquadramento LOUOS, citado em motivos e fundamentações.',
        'base_legal.risco_municipal' => 'Fundamento legal da classificação de risco municipal, citado na fundamentação.',
        'louos.motivo.sem_enquadramento_consolidado' => 'Motivo do consolidado pendente quando o CNAE não tem enquadramento na planilha vigente.',
        'louos.motivo.proibido' => 'Motivo do veredito quando o Quadro 10 proíbe o grupo na zona. Também embutido no template de não permitido (:condicao).',
        'louos.motivo.permitido' => 'Motivo do veredito permitido sem condicionantes. Também embutido no template de permitido (:condicao).',
        'louos.motivo.permitido_com_condicoes' => 'Motivo do veredito permitido com condicionantes. Também embutido no template de permitido (:condicao).',
        'louos.motivo.zona_pendente' => 'Motivo da dimensão Quadro 10 quando a permissão por zona está pendente da base oficial.',
        'louos.motivo.sem_enquadramento' => 'Motivo da dimensão Quadro 10 quando não há enquadramento de uso a verificar.',
        'louos.motivo.quadro10_sem_versao' => 'Motivo da dimensão Quadro 10 quando não há versão vigente da regra.',
        'louos.motivo.zona_sem_regra' => 'Motivo da dimensão Quadro 10 quando a combinação zona × grupo não tem regra vigente.',
        'louos.motivo.via_pendente' => 'Motivo da dimensão de via quando a classificação viária LOUOS está pendente da base oficial.',
        'louos.motivo.via_sem_atributo' => 'Motivo da dimensão de via quando a via não tem o atributo de classificação viária LOUOS.',
        'louos.motivo.via_sem_codlog' => 'Motivo quando a via não tem CODLOG para consultar a classificação viária.',
        'louos.motivo.via_classe_desligada' => 'Motivo quando o interruptor da classificação viária está desligado.',
        'louos.motivo.via_classe_indisponivel' => 'Motivo quando o GeoServer não respondeu a classificação viária.',
        'louos.motivo.via_classe_desconhecida' => 'Motivo quando a classificação viária não é uma das 7 classes do Quadro 11A.',
        'louos.motivo.via_sem_versao' => 'Motivo da dimensão de via quando o quadro de condições pela via não tem versão vigente.',
        'louos.motivo.via_sem_regra' => 'Motivo da dimensão de via quando a combinação classe viária × grupo não tem regra vigente.',
        'louos.motivo.via_vedada' => 'Motivo do consolidado quando o Quadro 11A veda o uso na classe da via (Não).',
        'louos.motivo.via_cnlu' => 'Motivo do consolidado quando o Quadro 11A encaminha à CNLU (R).',
        'louos.template.enquadramento' => 'Template do motivo do enquadramento de uso. Placeholders: :cnae, :area, :grupo, :subgrupo, :codigo_louos.',
        'louos.template.quadro10' => 'Template do motivo da permissão do Quadro 10. Placeholders: :grupo, :permissao, :zona.',
        'louos.template.permitido' => 'Template do motivo do veredito permitido. Placeholders: :cnae, :area, :grupo, :zona, :condicao.',
        'louos.template.nao_permitido' => 'Template do motivo do veredito não permitido. Placeholders: :cnae, :area, :grupo, :zona, :condicao.',
        'louos.template.quadro_via' => 'Template do motivo das condições pela via (Quadro 11-A). Placeholders: :grupo, :classe_via.',
        'consulta.aviso.zona_pendente' => 'Aviso da consulta pública quando a zona urbanística está pendente da base oficial.',
        'consulta.aviso.cnae_sem_local' => 'Aviso da consulta por CNAE, que não avalia o local.',
        'consulta.aviso.inscricao_indisponivel' => 'Aviso da consulta por inscrição imobiliária quando a base de lotes está indisponível.',
        'analise.pre_analise.intro' => 'Introdução do parecer-rascunho da pré-análise. Placeholder: :resultado.',
        'justificativa.conclusao.permitido' => 'Conclusão da justificativa quando o veredito é permitido. Placeholder: :zona.',
        'justificativa.conclusao.permitido_com_condicoes' => 'Conclusão da justificativa quando o veredito é permitido com condições. Placeholder: :zona.',
        'justificativa.conclusao.nao_permitido' => 'Conclusão da justificativa quando o veredito é não permitido. Placeholder: :zona.',
        'justificativa.conclusao.nao_permitido_via' => 'Conclusão da justificativa quando o veredito é não permitido por vedação da via (Quadro 11-A). Placeholder: :classe_via.',
        'justificativa.conclusao.padrao' => 'Conclusão da justificativa quando não há elementos para deferir ou indeferir.',
        'justificativa.indeferimento.sem_bap' => 'Justificativa do indeferimento automático por prazo de atuação na Junta (BAP) vencido. Redação oficial pendente SEDUR.',
        'justificativa.indeferimento.convite_expirado' => 'Justificativa do indeferimento automático por convite expirado sem resposta do requerente. Redação oficial pendente SEDUR.',
        'explicacao.titulo.entrada' => 'Título do passo de entrada na explicação da decisão.',
        'explicacao.titulo.risco' => 'Título do passo de classificação de risco na explicação da decisão.',
        'explicacao.titulo.enquadramento' => 'Título do passo de enquadramento de uso na explicação da decisão.',
        'explicacao.titulo.quadro10' => 'Título do passo do Quadro 10 na explicação da decisão.',
        'explicacao.titulo.quadro11a' => 'Título do passo do Quadro 11-A na explicação da decisão.',
        'explicacao.titulo.consolidacao' => 'Título do passo de consolidação na explicação da decisão.',
        'explicacao.titulo.desfecho' => 'Título do passo de desfecho na explicação da decisão.',
        'explicacao.motivo.nao_registrado' => 'Motivo genérico de passo não registrado na explicação da decisão legada.',
        'explicacao.motivo.risco_nao_registrado' => 'Motivo do passo de risco não registrado na decisão legada.',
        'explicacao.motivo.enquadramento_nao_registrado' => 'Motivo do passo de enquadramento não registrado na decisão legada.',
        'explicacao.motivo.quadro10_nao_registrado' => 'Motivo do passo do Quadro 10 não registrado na decisão legada.',
        'explicacao.motivo.quadro11a_nao_registrado' => 'Motivo do passo do Quadro 11-A não registrado na decisão legada.',
        'explicacao.motivo.permitido_so_enquadramento' => 'Motivo da consolidação legada permitida que cita só o enquadramento.',
        'explicacao.motivo.permitido_com_quadro10' => 'Motivo da consolidação legada permitida que cita o Quadro 10.',
        'territorial.indeferimento.logradouro_cnae' => 'Mensagem de indeferimento automático por logradouro × CNAE (RT-01). Placeholders: :alvo (ex.: logradouro 212 – Avenida Lafayette Coutinho), :fundamento (base legal da regra).',
        'territorial.motivo.camada_indisponivel' => 'Motivo do encaminhamento à análise quando a camada geográfica de uma regra territorial está indisponível. Placeholder: :regra.',
        'territorial.motivo.cadastro_indisponivel' => 'Motivo do encaminhamento à análise quando o Cadastro Imobiliário está indisponível para conferir o logradouro tributário de uma regra de logradouro × CNAE. Placeholder: :regra.',
        'zona.motivo.empate_maior_porcao' => 'Motivo do encaminhamento à análise quando o polígono do endereçamento tem porções iguais em duas zonas (art. 18, § 2º, da LOUOS). Placeholders: :zona_a, :zona_b.',
        'localizacao.motivo.divergencia_zona' => 'Motivo do encaminhamento à análise quando o polígono desenhado e a inscrição imobiliária caem em zonas diferentes (HU-037 RN-005). Placeholders: :zona_poligono, :zona_inscricao.',
        'localizacao.motivo.divergencia_zona_enderecamento' => 'Motivo do encaminhamento à análise quando o polígono desenhado e o polígono do endereçamento SEDUR caem em zonas diferentes pela maior porção. Placeholders: :zona_desenho, :zona_enderecamento.',
        'louos.motivo.quadro10_observacao_fora_da_lista' => 'Motivo do Quadro 10 quando o uso sujeito à observação (a)/(b) está fora das localizações permitidas. Placeholder: :observacao.',
        'louos.motivo.quadro10_observacao_sem_verificacao' => 'Motivo do Quadro 10 quando não há CODLOG/bairro para verificar a allow-list da observação (a)/(b). Placeholder: :observacao.',
        'louos.motivo.cnlu_enquadramento' => 'Motivo do consolidado quando o enquadramento é sinalizado como CNLU e a zona não proíbe o uso: segue para a análise técnica.',
        'tratamento.motivo.sem_versao' => 'Motivo do ramo da planilha quando não há versão vigente da planilha de tratamento.',
        'tratamento.motivo.cnae_sem_regra' => 'Motivo do ramo da planilha quando o CNAE não tem regra de tratamento vigente.',
        'tratamento.motivo.pergunta_sem_resposta' => 'Motivo do ramo da planilha quando uma pergunta ligada ao CNAE não foi respondida. Placeholders: :numero, :texto (enunciado da pergunta).',
        'tratamento.motivo.subdivisao_ausente' => 'Motivo do ramo da planilha quando a pergunta 1 pede a escolha de uma subdivisão do CNAE mas a planilha carregada não traz as opções (carga incompleta, não falta do requerente). Placeholder: :cnae.',
        'tratamento.motivo.sem_linha' => 'Motivo do ramo da planilha quando nenhuma linha de enquadramento do CNAE serve ao ramo escolhido.',
        'tratamento.motivo.ramo_bloqueado' => 'Motivo padrão quando a regra da planilha não permite o prosseguimento do ramo (sem mensagem própria).',
        'tratamento.motivo.cnlu' => 'Motivo do ramo da planilha quando o enquadramento é de análise da CNLU (coluna Risco ou aba Análise CNLU).',
        'tratamento.motivo.area_ausente' => 'Motivo do ramo da planilha quando a área utilizada falta e o ramo depende de faixa de área.',
        'tratamento.motivo.tipo_ausente' => 'Motivo do ramo da planilha quando o tipo de imóvel falta e o ramo depende do tipo.',
        'tratamento.motivo.tipo_desconhecido' => 'Motivo do ramo da planilha quando o tipo de imóvel enviado não é reconhecido e o ramo depende do tipo.',
        'tratamento.motivo.categoria_ausente' => 'Motivo do ramo da planilha quando a categoria da empresa (órgão público ou não) falta e a regra depende dela.',
        'tratamento.bloqueio.so_orgao_publico' => 'Mensagem do indeferimento da regra 38: atividade permitida só para órgãos da Administração Pública.',
        'tratamento.bloqueio.ambulante_alimentacao' => 'Mensagem do indeferimento da regra 9 para serviço ambulante de alimentação (opção 1 da pergunta 1): a licença é da SEMOP.',
        'tratamento.bloqueio.food_truck' => 'Mensagem do indeferimento da regra 9 para food truck com atividade no local (opção 2 da pergunta 1 e SIM na pergunta 11): a SEDUR licencia só o escritório.',
        'tratamento.bloqueio.residencial_plural' => 'Mensagem do indeferimento da regra 38 para imóvel residencial (Decreto Municipal nº 29.987/2018), no plural.',
        'tratamento.bloqueio.residencial' => 'Mensagem do indeferimento das regras 39 e 55 para imóvel residencial (Decreto Municipal nº 29.987/2018).',
        'tratamento.bloqueio.categoria_incompativel' => 'Mensagem do indeferimento da regra 39 quando a resposta não é compatível com a categoria da empresa. Placeholder: :cnae.',
        'tratamento.pendencia.sem_fluxo_curado' => 'Texto da pendência exibida na aba Enquadramento para regra cujo texto não define o fluxo de forma inequívoca.',
        'expresso.motivo.desativado' => 'Motivo do encaminhamento à análise quando o fluxo expresso está desativado.',
        'expresso.motivo.alto_risco' => 'Motivo do encaminhamento à análise de atividade de alto risco.',
        'expresso.motivo.fora_do_expresso' => 'Motivo do encaminhamento à análise de atividade fora do fluxo expresso.',
        'expresso.motivo.veredito_pendente' => 'Motivo do encaminhamento à análise quando o veredito locacional está pendente. Placeholder: :motivo (motivo do motor, como pergunta sem resposta).',
        'expresso.motivo.zona_pendente' => 'Motivo usado em :motivo quando o motor não informa a causa da pendência (zona urbanística pendente).',
        'expresso.motivo.exclusao_sede_sem_inscricao' => 'Motivo do encaminhamento à análise de exclusão do CNAE gatilho sem inscrição imobiliária informada.',
        'expresso.motivo.exclusao_sede_nao_titular' => 'Motivo do encaminhamento à análise de exclusão do CNAE gatilho pedida por solicitação que não é a titular do vínculo de sede.',
    ];

    public function run(): void
    {
        foreach (DecisionTextCatalog::defaults() as $key => $template) {
            DecisionText::firstOrCreate(
                ['key' => $key],
                [
                    'template' => $template,
                    'description' => self::DESCRICOES[$key] ?? "Texto decisório {$key}.",
                ],
            );
        }
    }
}
