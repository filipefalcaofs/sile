<?php

namespace App\Services\Decisao;

use App\Models\DecisionText;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Catálogo dos textos decisórios emitidos em documentos oficiais (TVL,
 * parecer, ficha do cidadão): os ~40 textos que viviam em constantes PHP dos
 * motores passam a ser administráveis por chave estável, com efeito sem
 * deploy (cache invalidado na escrita do model, padrão PropertyType).
 *
 * Resolução de get(): banco (via cache) → defaults() de fábrica. O fallback
 * aos defaults vale com o banco INALCANÇÁVEL (QueryException — build/CI) ou
 * com a chave ainda não seedada; chave desconhecida nas duas fontes lança
 * InvalidArgumentException (chave ausente é bug, não estado de runtime).
 *
 * Templates com placeholders `:nome` interpolados por strtr em render(): o
 * placeholder não fornecido permanece literal no texto (visível, honesto) —
 * nunca inventado nem escondido.
 */
final class DecisionTextCatalog
{
    /** @var array<string, true> */
    private array $reportedMissing = [];

    /**
     * Texto vigente da chave: banco (edição administrada) ou fábrica.
     *
     * @throws InvalidArgumentException Chave desconhecida no banco e nos defaults.
     */
    public function get(string $key): string
    {
        $textos = $this->catalogo();

        if (array_key_exists($key, $textos)) {
            return $textos[$key];
        }

        $defaults = self::defaults();

        if (array_key_exists($key, $defaults)) {
            // Banco alcançável mas sem a chave (deploy sem o seed): o documento
            // sai com o texto de fábrica — correto — mas a edição administrada
            // não está valendo. Reportar uma vez por chave por instância.
            if (! isset($this->reportedMissing[$key])) {
                $this->reportedMissing[$key] = true;
                Log::warning("Texto decisório ausente no banco; emitindo o texto de fábrica: {$key}");
            }

            return $defaults[$key];
        }

        throw new InvalidArgumentException("Texto decisório desconhecido: {$key}");
    }

    /**
     * Interpola os placeholders `:nome` do template. O não fornecido permanece
     * literal — visível no documento, nunca substituído por valor inventado.
     *
     * @param  array<string, string>  $placeholders
     */
    public function render(string $key, array $placeholders = []): string
    {
        return strtr($this->get($key), $placeholders);
    }

    /**
     * Mapa chave → template lido do banco com cache invalidado na escrita do
     * model. Banco inalcançável degrada para os defaults de fábrica — e o
     * fallback é reportado, nunca silencioso (muda o texto de documento oficial).
     *
     * @return array<string, string>
     */
    private function catalogo(): array
    {
        try {
            /** @var array<string, string> */
            return Cache::remember(
                DecisionText::CACHE_KEY,
                (int) config('viabiliza.parameters.cache_ttl', 300),
                fn () => DecisionText::query()->pluck('template', 'key')->all(),
            );
        } catch (QueryException|\Exception $e) {
            report($e);

            return self::defaults();
        }
    }

    /**
     * Textos de fábrica — o conteúdo EXATO das constantes que viviam no código
     * (verbatim, incluindo pontuação e espaços), extraído na Fase 4. Fonte
     * única: o DecisionTextSeeder lê daqui, sem duplicar strings. A paridade
     * byte-idêntica com as constantes é golden test permanente
     * (DecisionTextSeederTest).
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            // Bases legais citadas nas fundamentações.
            'base_legal.louos' => 'Lei nº 9.148/2016 (LOUOS)',
            // O Decreto 41.758/2026 só substitui os Anexos I a III do 32.636/2020 (art. 1º).
            'base_legal.risco_municipal' => 'Decreto Municipal nº 32.636/2020, com os Anexos na redação do Decreto Municipal nº 41.758/2026',

            // LOUOS — motivos das dimensões e do consolidado
            // (LouosEnquadramentoService, constantes das linhas 38-62).
            'louos.motivo.sem_enquadramento_consolidado' => 'Atividade sem enquadramento na planilha vigente — segue para análise técnica',
            'louos.motivo.cnlu_enquadramento' => 'Enquadramento sinalizado para análise da CNLU — segue para análise técnica',
            'louos.motivo.proibido' => 'Atividade proibida na zona pelo Quadro 10',
            'louos.motivo.permitido' => 'Atividade permitida na zona, sem condicionantes incidentes',
            'louos.motivo.permitido_com_condicoes' => 'Atividade permitida na zona mediante observância das condicionantes',
            'louos.motivo.zona_pendente' => 'Permissão por zona pendente da base oficial (SEDUR)',
            'louos.motivo.sem_enquadramento' => 'Sem enquadramento de uso não há permissão a verificar',
            'louos.motivo.quadro10_sem_versao' => 'Quadro 10 sem versão vigente',
            'louos.motivo.zona_sem_regra' => 'Combinação zona × grupo de uso sem regra no Quadro 10 vigente',
            'louos.motivo.via_pendente' => 'Condições pela via dependem da classificação viária LOUOS (pendente SEDUR)',
            'louos.motivo.via_sem_atributo' => 'Via identificada, porém sem o atributo de classificação viária LOUOS (pendente SEDUR)',
            'louos.motivo.via_sem_codlog' => 'Via identificada sem o código do logradouro (CODLOG) para consultar a classificação viária da LOUOS',
            'louos.motivo.via_classe_desligada' => 'Consulta da classificação viária no GeoServer SEDUR desligada',
            'louos.motivo.via_classe_indisponivel' => 'Classificação viária indisponível no GeoServer SEDUR',
            'louos.motivo.via_classe_desconhecida' => 'Classificação viária fora das classes do Quadro 11A da Lei nº 9.148/2016',
            'louos.motivo.via_sem_versao' => 'Quadro de condições pela via sem versão vigente',
            'louos.motivo.via_sem_regra' => 'Classe viária × grupo de uso sem regra no quadro de via vigente',
            'louos.motivo.via_vedada' => 'Uso vedado na classe da via pelo Quadro 11A da LOUOS',
            'louos.motivo.via_cnlu' => 'Quadro 11A encaminha à CNLU (R)',

            // LOUOS — templates dos motivos compostos (sprintf → :nome; a
            // formatação de CNAE/área/faixa permanece em código). O template
            // pai carrega o ponto final da :condicao — o motor aplica
            // rtrim(..., '.') ao embutir o motivo, exatamente como hoje.
            'louos.template.enquadramento' => 'O CNAE :cnae com área :area m² enquadra-se no grupo :grupo:subgrupo da LOUOS (:codigo_louos). O enquadramento não autoriza o uso na zona — só define o grupo.',
            'louos.template.quadro10' => 'O grupo :grupo é :permissao na zona :zona segundo o Quadro 10 da LOUOS — é este quadro que permite ou proíbe o uso no território.',
            'louos.template.permitido' => 'Permitido: o CNAE :cnae (área :area m²) classificou-se no grupo :grupo pelo enquadramento da planilha vigente e esse grupo é permitido na zona :zona pelo Quadro 10. :condicao.',
            'louos.template.nao_permitido' => 'Não permitido: o CNAE :cnae (área :area m²) classificou-se no grupo :grupo pelo enquadramento da planilha vigente e esse grupo é proibido na zona :zona pelo Quadro 10. :condicao.',
            'louos.template.quadro_via' => 'O grupo :grupo na classe viária :classe_via tem condições de instalação pelo Quadro 11-A da LOUOS. O Quadro 11-A pode vedar o uso (Não), encaminhar à CNLU (R) ou condicionar a instalação pela via.',

            // Consulta pública de viabilidade (ConsultaViabilidadeService).
            'consulta.aviso.zona_pendente' => 'Veredito locacional pendente: zona urbanística pendente da base oficial (SEDUR).',
            'consulta.aviso.cnae_sem_local' => 'Consulta por CNAE não avalia o local: o veredito locacional depende do endereço/zona. Para a viabilidade locacional, consulte por endereço.',
            'consulta.aviso.inscricao_indisponivel' => 'Resolução por inscrição imobiliária indisponível (base de lotes pendente SEDUR). Resultado sem análise territorial; consulte por endereço para o veredito locacional.',
            'consulta.aviso.simulacao' => 'Esta consulta é uma simulação informativa: não cria processo, não concede viabilidade e não vincula a decisão oficial, que é tomada na análise do processo.',
            'consulta.pendencia.area' => 'Informe a área da atividade (m²).',
            'consulta.pendencia.tipo_imovel' => 'Informe o tipo do imóvel.',
            'consulta.pendencia.categoria_empresa' => 'Informe a categoria da empresa.',
            'consulta.pendencia.vagas' => 'Informe as vagas disponíveis no imóvel.',

            // Análise técnica — pré-análise e justificativa fundamentada.
            'analise.pre_analise.intro' => 'Analisa-se o requerimento à luz da Lei nº 9.148/2016 (LOUOS) e das regras de risco aplicáveis. Veredito locacional consolidado: :resultado.',
            'justificativa.conclusao.permitido' => 'Diante do enquadramento acima, manifesta-se pelo deferimento desta atividade, por ser locacionalmente permitida na zona :zona, sem condicionantes urbanísticas incidentes.',
            'justificativa.conclusao.permitido_com_condicoes' => 'Diante do enquadramento acima, manifesta-se pelo deferimento desta atividade na zona :zona, condicionado ao cumprimento das exigências urbanísticas incidentes.',
            'justificativa.conclusao.nao_permitido' => 'Diante do enquadramento acima, manifesta-se pelo indeferimento desta atividade, por ser o uso proibido na zona :zona segundo o Quadro 10 da LOUOS.',
            'justificativa.conclusao.nao_permitido_via' => 'Diante do enquadramento acima, manifesta-se pelo indeferimento desta atividade, por ser o uso vedado na classe viária :classe_via pelo Quadro 11-A da LOUOS.',
            'justificativa.indeferimento.estabilidade_sistemica' => "Solicitação indeferida por estabilidade sistêmica: não foi possível consultar a viabilidade vinculada ao CNPJ na SEFAZ Municipal durante o prazo de tentativas.\n\nCadastre uma nova solicitação para dar andamento à alteração de atividade.",
            'justificativa.indeferimento.inscricao_inativa' => "Solicitação indeferida em razão de pendências identificadas na situação cadastral da inscrição imobiliária junto à SEFAZ Municipal.\n\nRecomenda-se a regularização das pendências junto à SEFAZ Municipal para possibilitar o cadastramento de um novo processo.",
            'justificativa.indeferimento.sem_bap' => 'Indeferido sem atuação: o prazo de atuação na Junta Comercial (BAP) venceu sem que o requerente concluísse o registro, e a solicitação não pôde prosseguir.',
            'justificativa.indeferimento.pergunta_sem_resposta' => 'Indeferido: o requerimento recebido não trouxe a resposta da pergunta :numero (":enunciado") da planilha de tratamento, necessária ao enquadramento da atividade :cnae.',
            'justificativa.indeferimento.convite_expirado' => 'Processo indeferido pelo não atendimento ao convite no prazo estabelecido em consonância com Lei Federal nº 11.598/2007 e Resolução CGSIM nº 61/2020. Recomenda-se a abertura de novo processo contendo toda a documentação solicitada.',
            'justificativa.conclusao.padrao' => 'Não há elementos suficientes para deferir ou indeferir. Encaminha-se a atividade à análise técnica, sem sugerir desfecho locacional.',

            // Explicabilidade da decisão (DecisionExplanationService) — títulos.
            'explicacao.titulo.entrada' => 'Entrada',
            'explicacao.titulo.risco' => 'Classificação de risco',
            'explicacao.titulo.enquadramento' => 'LOUOS — Enquadramento de uso',
            'explicacao.titulo.quadro10' => 'LOUOS — Quadro 10 (permissão na zona)',
            'explicacao.titulo.quadro11a' => 'LOUOS — Quadro 11-A (condições pela via)',
            'explicacao.titulo.consolidacao' => 'Consolidação do veredito locacional',
            'explicacao.titulo.desfecho' => 'Desfecho',

            // Explicabilidade da decisão — motivos dos passos não registrados
            // (decisão legada, sem decision_trace).
            'explicacao.motivo.nao_registrado' => 'não registrado nesta decisão',
            'explicacao.motivo.risco_nao_registrado' => 'O Decreto nº 41.758/2026 classifica o risco do CNAE e define se o processo vai ao fluxo expresso ou à análise técnica. O nível e o encaminhamento desta decisão não foram gravados.',
            'explicacao.motivo.enquadramento_nao_registrado' => 'O enquadramento classifica o uso (CNAE × perguntas × área → grupo). O grupo desta decisão não foi gravado.',
            'explicacao.motivo.quadro10_nao_registrado' => 'O Quadro 10 permite ou proíbe o grupo na zona. A permissão e a zona desta decisão não foram gravadas.',
            'explicacao.motivo.quadro11a_nao_registrado' => 'O Quadro 11-A condiciona a instalação pela via (classe viária × grupo). Não permite nem proíbe o uso. As condições desta decisão não foram gravadas.',
            'explicacao.motivo.permitido_so_enquadramento' => 'O registro cita o enquadramento da LOUOS como fundamento do veredito permitido. O enquadramento só classifica o uso. Quem permite ou proíbe na zona é o Quadro 10. Grupo e zona não foram gravados nesta decisão.',
            'explicacao.motivo.permitido_com_quadro10' => 'O registro cita o Quadro 10 da LOUOS (permissão do grupo na zona). Os detalhes (grupo, faixa e zona) não foram gravados nesta decisão.',

            // Regras territoriais (RT-01 a RT-05 — pacote normativo SEDUR
            // 2026-08-28). O indeferimento por logradouro × CNAE (RT-01) usa
            // placeholders do alvo e do fundamento lidos da própria regra.
            'territorial.indeferimento.logradouro_cnae' => 'A solicitação está localizada no :alvo e contempla atividade(s) econômica(s) abrangida(s) pelas restrições estabelecidas no :fundamento. Dessa forma, considerando o disposto na legislação vigente, o pedido não atende aos requisitos legais para deferimento, razão pela qual a solicitação é indeferida.',
            'territorial.motivo.camada_indisponivel' => 'Não foi possível verificar a restrição territorial ":regra" (camada geográfica indisponível) — segue para análise técnica.',
            'territorial.motivo.localizacao_indisponivel' => 'Não foi possível verificar a restrição territorial ":regra" (localização do imóvel não obtida: Cadastro Imobiliário indisponível ou inscrição sem coordenada) — segue para análise técnica.',
            'territorial.motivo.cadastro_indisponivel' => 'Não foi possível verificar a restrição territorial ":regra" (Cadastro Imobiliário indisponível para conferir o logradouro tributário) — segue para análise técnica.',
            'zona.motivo.empate_maior_porcao' => 'Zona urbanística indefinida (art. 18, § 2º, da Lei nº 9.148/2016): o terreno tem porções iguais nas zonas :zona_a e :zona_b — segue para análise técnica.',
            'zona.motivo.conflito_centralidade_linear' => 'Zona urbanística indefinida (HU.182): as linhas da centralidade linear :zona_a e :zona_b passam por cima do mesmo terreno — segue para análise técnica.',
            'localizacao.motivo.divergencia_zona' => 'Localização divergente (HU-036 RN-005): o polígono informado está na zona :zona_poligono e a inscrição imobiliária, na zona :zona_inscricao — segue para análise técnica.',
            'localizacao.motivo.divergencia_zona_enderecamento' => 'Localização divergente: o polígono desenhado está na zona :zona_desenho e o polígono do endereçamento SEDUR, na zona :zona_enderecamento — segue para análise técnica.',
            // Planilha de tratamento — motivos do ramo não resolvido. A pergunta
            // sem resposta cita o enunciado da planilha (:numero, :texto).
            'tratamento.pendencia.sem_fluxo_curado' => 'Aguardando confirmação da SEDUR: o texto da regra não define o fluxo de forma inequívoca. O motor usa a heurística desta versão.',
            'tratamento.motivo.categoria_ausente' => 'Categoria da empresa ausente para a regra que depende dela',
            'tratamento.bloqueio.so_orgao_publico' => 'Esta atividade só é permitida para órgãos da Administração Pública',
            'tratamento.bloqueio.residencial_plural' => 'A (s) atividade (s) solicitada (s) não é (são) permitida(s) em imóvel residencial conforme o art. 4º do Decreto Municipal nº 29.987/2018, que trata do funcionamento e estabelecimento de microempresas, empresas de pequeno e MEIs nas residências de seus titulares.',
            'tratamento.bloqueio.ambulante_alimentacao' => 'A SEDUR não licencia serviços ambulantes de alimentação, o requerente deverá solicitar licença junto a SEMOP (Secretaria Municipal de Ordem Pública).',
            'tratamento.bloqueio.food_truck' => 'A SEDUR licencia apenas o escritório para a atividade de Food Truck. Deverá solicitar licença específica no órgão competente para o local do funcionamento',
            'tratamento.bloqueio.residencial' => 'A atividade solicitada não é permitida em imóvel residencial conforme o art. 4º do Decreto Municipal nº 29.987/2018, que trata do funcionamento e estabelecimento de microempresas, empresas de pequeno e MEIs nas residências de seus titulares.',
            'tratamento.bloqueio.categoria_incompativel' => 'Resposta selecionada para a CNAE :cnae não é compatível com a categoria da empresa indicada nesta etapa. Revise as informações para continuar.',
            'tratamento.motivo.sem_versao' => 'Planilha de tratamento sem versão vigente',
            'tratamento.motivo.cnae_sem_regra' => 'CNAE sem regra de tratamento vigente',
            'tratamento.motivo.pergunta_sem_resposta' => 'Pergunta :numero sem resposta — :texto',
            'tratamento.motivo.subdivisao_ausente' => 'Subdivisão do CNAE :cnae ausente na planilha de tratamento carregada — rode o complemento da planilha (tratamento:verificar --corrigir)',
            'tratamento.motivo.sem_linha' => 'CNAE sem enquadramento no ramo escolhido',
            'tratamento.motivo.ramo_bloqueado' => 'A regra da planilha não permite o prosseguimento deste ramo',
            'tratamento.motivo.cnlu' => 'A SER DEFINIDO PELA CNLU',
            'tratamento.motivo.area_ausente' => 'Área utilizada ausente para o ramo que depende de faixa',
            'tratamento.motivo.tipo_ausente' => 'Tipo de imóvel ausente para o ramo que depende do tipo',
            'tratamento.motivo.tipo_desconhecido' => 'Tipo de imóvel desconhecido para o ramo que depende do tipo',

            // Fluxo expresso — motivos do encaminhamento à análise técnica,
            // registrados na transição e na queda do processo.
            'expresso.motivo.cnae_invalido' => 'Código CNAE inexistente ou divergente da lista oficial (:cnaes) — encaminhado para análise, em que o analista corrige o código na ficha.',
            'expresso.motivo.abrigada_fora_do_anexo_b' => 'empresa abrigada em escritório virtual com atividade fora do Anexo B (:cnaes) — encaminhado para análise',
            'expresso.motivo.desativado' => 'fluxo expresso desativado',
            'expresso.motivo.alto_risco' => 'atividade de alto risco — análise técnica',
            'expresso.motivo.fora_do_expresso' => 'atividade fora do fluxo expresso (análise técnica)',
            'expresso.motivo.veredito_pendente' => 'veredito locacional pendente — :motivo',
            'expresso.motivo.zona_pendente' => 'zona urbanística pendente SEDUR',
            'expresso.motivo.exclusao_sede_sem_inscricao' => 'exclusão do CNAE gatilho sem inscrição imobiliária informada — encaminhado para verificação',
            'expresso.motivo.exclusao_sede_nao_titular' => 'exclusão do CNAE gatilho da sede pedida por solicitação que não é a titular do vínculo de sede — encaminhado para verificação',
            'louos.motivo.quadro10_observacao_fora_da_lista' => 'Uso sujeito à observação :observacao do Quadro 10 permitido somente nas localizações listadas — endereço fora da allow-list',
            'louos.motivo.quadro10_observacao_sem_verificacao' => 'Uso sujeito à observação :observacao do Quadro 10, mas sem CODLOG/bairro para verificar a allow-list de localizações',
        ];
    }
}
