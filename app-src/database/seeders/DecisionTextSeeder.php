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
