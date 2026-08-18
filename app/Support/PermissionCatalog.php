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
                'name' => 'consultar-territorio',
                'label' => 'Consultar território',
                'description' => 'Acessa o mapa e as camadas geográficas da cidade.',
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
                'name' => 'distribuir-processos',
                'label' => 'Distribuir processos',
                'description' => 'Encaminha processos para setores e analistas.',
                'group' => 'Análise técnica',
            ],
            [
                'name' => 'emitir-tvl',
                'label' => 'Emitir TVL',
                'description' => 'Emite o Termo de Viabilidade Locacional.',
                'group' => 'Análise técnica',
            ],
            [
                'name' => 'encaminhar-malha-fina',
                'label' => 'Encaminhar para malha fina',
                'description' => 'Envia o processo para malha fina.',
                'group' => 'Análise técnica',
            ],
            [
                'name' => 'enviar-tvl-analise',
                'label' => 'Enviar TVL para análise',
                'description' => 'Encaminha um TVL já emitido para análise.',
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
