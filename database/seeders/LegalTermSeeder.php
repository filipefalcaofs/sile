<?php

namespace Database\Seeders;

use App\Models\LegalTerm;
use Illuminate\Database\Seeder;

class LegalTermSeeder extends Seeder
{
    /**
     * Publica a versão 1 do termo de consentimento LGPD.
     *
     * Texto PROVISÓRIO, administrável como dado (nunca código): a substituição
     * pelo texto oficial da SEDUR deve ocorrer pela publicação de uma nova
     * versão do termo via interface administrativa (HU-014, Fase 2) — o
     * middleware re-exige o aceite automaticamente.
     */
    public function run(): void
    {
        LegalTerm::firstOrCreate(
            ['type' => 'lgpd', 'version' => 1],
            [
                'title' => 'Termo de Consentimento para Tratamento de Dados Pessoais',
                'content' => <<<'TEXTO'
O Sistema de Licenciamento Eletrônico (SILE), mantido pela Secretaria Municipal de Desenvolvimento Urbano (SEDUR) do Município de Salvador/BA, realiza o tratamento de dados pessoais com a finalidade exclusiva de viabilizar a gestão do licenciamento de atividades econômicas, incluindo a análise de viabilidade locacional, a emissão de licenças e alvarás, a comunicação oficial com o requerente e o cumprimento de obrigações legais e regulatórias decorrentes da legislação municipal, estadual e federal aplicável.

Para essas finalidades, são coletados e tratados os seguintes dados: nome completo, CPF, endereço de e-mail, telefone de contato, endereço do empreendimento objeto do licenciamento e registros de acesso ao sistema (data, hora e origem das operações realizadas). Os registros de acesso e de operações compõem trilha de auditoria exigida para a segurança, a rastreabilidade e a integridade dos processos administrativos, e são conservados pelo prazo necessário ao cumprimento dessas finalidades.

Nos termos da Lei nº 13.709/2018 (Lei Geral de Proteção de Dados Pessoais — LGPD), o titular dos dados tem direito a obter a confirmação da existência de tratamento, o acesso aos dados, a correção de dados incompletos, inexatos ou desatualizados, a anonimização, bloqueio ou eliminação de dados desnecessários ou excessivos, a portabilidade, a informação sobre compartilhamentos, a informação sobre a possibilidade de não fornecer consentimento e suas consequências, e a revogação do consentimento, quando este for a base legal do tratamento.

Para exercer seus direitos ou esclarecer dúvidas sobre o tratamento de dados pessoais no SILE, o titular pode contatar o Encarregado pelo Tratamento de Dados Pessoais (DPO) da SEDUR pelo canal oficial de atendimento da Prefeitura Municipal de Salvador, disponível no portal do Município, ou pelo e-mail institucional divulgado pela SEDUR.

Ao prosseguir, você declara que leu e compreendeu este termo e que consente com o tratamento dos seus dados pessoais para as finalidades aqui descritas.
TEXTO,
                'published_at' => now(),
            ],
        );
    }
}
