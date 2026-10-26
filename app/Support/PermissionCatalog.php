<?php

namespace App\Support;

final class PermissionCatalog
{
    /**
     * Rótulos e descrições exibidos em Gestão > Perfis. A chave técnica
     * (name) permanece o valor gravado; a tela não deve mostrar o slug.
     *
     * @return list<array{name: string, label: string, description: string, group: string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'acessar-gestao',
                'label' => 'Acessar a gestão',
                'description' => 'Entra no ambiente interno da SEDUR.',
                'group' => 'Acesso',
            ],
            [
                'name' => 'consultar-acessos-de-qualquer-conta',
                'label' => 'Ver histórico de acessos de qualquer conta',
                'description' => 'Consulta o histórico de login de todos os usuários, não só o próprio.',
                'group' => 'Acesso',
            ],
            [
                'name' => 'consultar-cnaes',
                'label' => 'Consultar CNAEs',
                'description' => 'Vê a lista e os dados dos CNAEs, sem cadastrar nem alterar.',
                'group' => 'CNAEs',
            ],
            [
                'name' => 'manter-cnaes',
                'label' => 'Cadastrar e editar CNAEs',
                'description' => 'Cria, edita, ativa, desativa e exclui CNAEs, inclusive risco municipal e condicionantes.',
                'group' => 'CNAEs',
            ],
            [
                'name' => 'manter-gatilhos-risco',
                'label' => 'Manter gatilhos de risco',
                'description' => 'Edita e liga/desliga os gatilhos semi-expresso que enviam processos à análise técnica.',
                'group' => 'CNAEs',
            ],
            [
                'name' => 'consultar-territorio',
                'label' => 'Consultar território',
                'description' => 'Acessa o mapa e as camadas geográficas da cidade.',
                'group' => 'Território e LOUOS',
            ],
            [
                'name' => 'manter-territorio',
                'label' => 'Manter camadas do GeoServer',
                'description' => 'Cadastra e liga/desliga as camadas WFS de zona consultadas no GeoServer.',
                'group' => 'Território e LOUOS',
            ],
            [
                'name' => 'consultar-louos',
                'label' => 'Consultar quadros LOUOS',
                'description' => 'Vê as regras vigentes da LOUOS, sem publicar versões.',
                'group' => 'Território e LOUOS',
            ],
            [
                'name' => 'manter-louos',
                'label' => 'Publicar versões da LOUOS',
                'description' => 'Publica versões dos quadros e usa a simulação de regras.',
                'group' => 'Território e LOUOS',
            ],
            [
                'name' => 'atendimento-presencial',
                'label' => 'Atendimento presencial',
                'description' => 'Opera o atendimento presencial ao requerente.',
                'group' => 'Atendimento',
            ],
            [
                'name' => 'registrar-contingencia',
                'label' => 'Registrar contingência',
                'description' => 'Abre solicitação de viabilidade quando o canal regular estiver indisponível.',
                'group' => 'Atendimento',
            ],
            [
                'name' => 'consultar-solicitacoes',
                'label' => 'Consultar solicitações',
                'description' => 'Vê solicitações e processos na gestão.',
                'group' => 'Atendimento',
            ],
            [
                'name' => 'manter-tipos-servico',
                'label' => 'Cadastrar tipos de serviço',
                'description' => 'Cria e edita os tipos de serviço de viabilidade.',
                'group' => 'Atendimento',
            ],
            [
                'name' => 'manter-nomenclatura-servicos',
                'label' => 'Parametrizar nomenclatura dos serviços',
                'description' => 'Define o nome exibido de cada serviço enviado pela JUCEB (De x Para).',
                'group' => 'Atendimento',
            ],
            [
                'name' => 'manter-tipos-imovel',
                'label' => 'Cadastrar tipos de imóvel',
                'description' => 'Cria e edita os tipos de imóvel que dirigem a regra de roteamento do expresso.',
                'group' => 'Atendimento',
            ],
            [
                'name' => 'manter-requisitos-documentais',
                'label' => 'Cadastrar requisitos documentais',
                'description' => 'Define quais documentos cada tipo de serviço exige.',
                'group' => 'Atendimento',
            ],
            [
                'name' => 'analisar-processos',
                'label' => 'Analisar processos',
                'description' => 'Trabalha na ficha de análise técnica: parecer, pendência e decisão.',
                'group' => 'Análise técnica',
            ],
            [
                'name' => 'concluir-analise',
                'label' => 'Concluir análise',
                'description' => 'Defere ou indefere o processo e conclui a análise técnica.',
                'group' => 'Análise técnica',
            ],
            [
                'name' => 'encaminhar-para-conclusao',
                'label' => 'Encaminhar para conclusão',
                'description' => 'Envia o processo em análise para a aba Para conclusão, para a chefia ou outro analista concluir. Quem conclui só encaminha se também tiver esta permissão.',
                'group' => 'Análise técnica',
            ],
            [
                'name' => 'forcar-transicao-analise',
                'label' => 'Forçar transição da situação da análise',
                'description' => 'Muda a situação da análise para qualquer estado permitido pela máquina de estados, mesmo fora das opções manuais.',
                'group' => 'Análise técnica',
            ],
            [
                'name' => 'editar-zona-via',
                'label' => 'Editar zona e via do imóvel',
                'description' => 'Escolhe a zona e a via do imóvel na ficha de análise, com justificativa, e recalcula o enquadramento e a decisão do motor.',
                'group' => 'Análise técnica',
            ],
            [
                'name' => 'receber-escalonamento-sla',
                'label' => 'Receber escalonamentos de prazo e alertas de TLL',
                'description' => 'Recebe os avisos de processo com prazo de análise vencido e de exercício sem tabela TLL vigente.',
                'group' => 'Análise técnica',
            ],
            [
                'name' => 'reprocessar-processos',
                'label' => 'Reprocessar etapas do motor',
                'description' => 'Refaz as etapas do motor que falharam por serviço de terceiro indisponível e reavalia o veredito.',
                'group' => 'Análise técnica',
            ],
            [
                'name' => 'distribuir-processos',
                'label' => 'Distribuir processos',
                'description' => 'Encaminha processos para setores e analistas.',
                'group' => 'Análise técnica',
            ],
            [
                'name' => 'distribuir-qualquer-setor',
                'label' => 'Distribuir em qualquer setor',
                'description' => 'Vê a caixa de todos os setores e entrega o processo a qualquer analista; o processo passa para o setor do analista.',
                'group' => 'Análise técnica',
            ],
            [
                'name' => 'emitir-tvl',
                'label' => 'Gerar PDF da viabilidade',
                'description' => 'Gera o PDF interno da viabilidade locacional.',
                'group' => 'Análise técnica',
            ],
            [
                'name' => 'encaminhar-malha-fina',
                'label' => 'Encaminhar para malha fina',
                'description' => 'Envia o processo para malha fina.',
                'group' => 'Análise técnica',
            ],
            [
                'name' => 'analisar-malha-fina',
                'label' => 'Analisar malha fina',
                'description' => 'Acessa e opera a Caixa de Malha Fina (consulta e baixa de encaminhamentos).',
                'group' => 'Análise técnica',
            ],
            [
                'name' => 'enviar-tvl-analise',
                'label' => 'Enviar viabilidade para análise',
                'description' => 'Encaminha uma viabilidade já decidida para análise técnica.',
                'group' => 'Análise técnica',
            ],
            [
                'name' => 'preencher-ficha-vistoria',
                'label' => 'Preencher ficha de vistoria',
                'description' => 'Abre e preenche a ficha de vistoria do processo: localização, polígono, imóvel, anexos e parecer.',
                'group' => 'Análise técnica',
            ],
            [
                'name' => 'manter-setores',
                'label' => 'Cadastrar setores',
                'description' => 'Cria e edita os setores da análise técnica.',
                'group' => 'Análise técnica',
            ],
            [
                'name' => 'manter-usuarios',
                'label' => 'Cadastrar usuários',
                'description' => 'Cria e edita contas da gestão e do portal.',
                'group' => 'Administração',
            ],
            [
                'name' => 'manter-perfis',
                'label' => 'Cadastrar perfis',
                'description' => 'Cria e edita perfis e as permissões de cada um.',
                'group' => 'Administração',
            ],
            [
                'name' => 'manter-parametros',
                'label' => 'Alterar parâmetros',
                'description' => 'Altera prazos, textos e demais parâmetros de negócio.',
                'group' => 'Administração',
            ],
            [
                'name' => 'monitorar-emails',
                'label' => 'Monitorar e-mails',
                'description' => 'Acompanha o envio de e-mails do sistema.',
                'group' => 'Administração',
            ],
            [
                'name' => 'manter-config-email',
                'label' => 'Configurar e-mail',
                'description' => 'Altera a configuração do servidor de e-mail.',
                'group' => 'Administração',
            ],
            [
                'name' => 'manter-config-ia',
                'label' => 'Configurar IA',
                'description' => 'Altera a configuração das funcionalidades de inteligência artificial.',
                'group' => 'Administração',
            ],
            [
                'name' => 'consultar-auditoria',
                'label' => 'Consultar auditoria',
                'description' => 'Acessa a trilha de auditoria das ações do sistema.',
                'group' => 'Auditoria e conformidade',
            ],
            [
                'name' => 'monitorar-lgpd',
                'label' => 'Monitorar LGPD',
                'description' => 'Acessa o painel de conformidade e dados pessoais.',
                'group' => 'Auditoria e conformidade',
            ],
            [
                'name' => 'gerenciar-alertas-abuso',
                'label' => 'Gerenciar alertas de abuso',
                'description' => 'Trata alertas de uso indevido do sistema.',
                'group' => 'Auditoria e conformidade',
            ],
            [
                'name' => 'consultar-relatorios',
                'label' => 'Consultar relatórios',
                'description' => 'Acessa os relatórios e indicadores da gestão.',
                'group' => 'Relatórios',
            ],
            [
                'name' => 'relatorios.produtividade.nominal',
                'label' => 'Ver produtividade com nome do analista',
                'description' => 'Vê os nomes dos analistas nos relatórios de produtividade.',
                'group' => 'Relatórios',
            ],
            [
                'name' => 'gerenciar-procuracoes-proprias',
                'label' => 'Gerenciar procurações próprias',
                'description' => 'No portal, vincula e revoga procuradores da própria empresa.',
                'group' => 'Portal do cidadão',
            ],
        ];
    }

    /**
     * @param  iterable<int, string>  $names
     * @return list<array{name: string, label: string, description: string, group: string}>
     */
    public static function forNames(iterable $names): array
    {
        $requested = collect($names)->map(fn ($name) => (string) $name)->unique()->values();
        $seen = [];
        $catalog = [];

        foreach (self::definitions() as $item) {
            if ($requested->contains($item['name'])) {
                $catalog[] = $item;
                $seen[$item['name']] = true;
            }
        }

        foreach ($requested as $name) {
            if (! isset($seen[$name])) {
                $catalog[] = [
                    'name' => $name,
                    'label' => $name,
                    'description' => 'Permissão sem descrição cadastrada.',
                    'group' => 'Outras',
                ];
            }
        }

        return $catalog;
    }
}
