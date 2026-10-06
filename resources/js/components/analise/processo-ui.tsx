import type { ReactNode } from 'react';
import Badge from '@/components/ui/badge';
import { formatarMetros, metrosComUnidade } from '@/lib/metros';

/** Semáforo do SLA da fila (HU-144) — espelha o enum SlaStatus do backend. */
export interface ProcessoSla {
    status: string;
    status_label: string;
    restante: string;
}

export interface ProcessoCategoria {
    value: string;
    label: string;
}

/**
 * Bloco de escritório virtual do produto (T03) — presente só quando o produto é
 * sede ou abrigado. `tvl_sede` é o "End. Virtual — TVL Nº" (para o abrigado, o
 * TVL da sede; RN-EV-05). `validade_sede` não é modelada (desfecho spec-2) e
 * chega como null → renderizada como "—".
 */
export interface ProcessoEscritorioVirtual {
    tipo: 'sede' | 'abrigado';
    tvl_sede: string | null;
    inscricao: string | null;
    validade_sede: string | null;
    status_produto: string | null;
}

/**
 * Shape do ProcessoResource (10-14), consumido pela fila, consulta e detalhe.
 * Os três identificadores do processo (RN-007) são protocol_number, bap e
 * tvl_product_number.
 */
export interface ProcessoPerguntaRecebida {
    cnae: string;
    pergunta: string;
    resposta: string;
}

export interface ProcessoAtividadeNoLocal {
    cnae: string;
    exercida: string;
}

export interface ProcessoContadorRecebido {
    nome: string | null;
    documento: string | null;
    crc: string | null;
}

/** Declaração plana recebida no POST e gravada no processo. */
export interface ProcessoRecepcao {
    protocolo: string | null;
    razao_social: string | null;
    cpf_solicitante: string | null;
    nome_solicitante: string | null;
    email_solicitante: string | null;
    inscricao_imobiliaria: string | null;
    codlog: string | null;
    tipo_logradouro: string | null;
    logradouro: string | null;
    numero: string | null;
    bairro: string | null;
    cep: string | null;
    ponto_referencia: string | null;
    municipio: string | null;
    uf: string | null;
    area_utilizada: number | null;
    area_construida: number | null;
    area_terreno: number | null;
    tipo_complemento: string | null;
    descricao_complemento: string | null;
    categoria_imovel: string | null;
    cnae_principal: string | null;
    cnaes_secundarios: string[];
    perguntas: ProcessoPerguntaRecebida[];
    tipo_imovel: string | null;
    porte_empresa: string | null;
    atividade_em_area_publica: string | null;
    escritorio_virtual: string | null;
    atividade_abrigada_em_escritorio_virtual: string | null;
    presta_servico_de_escritorio_virtual: string | null;
    cnpj_escritorio_virtual: string | null;
    servico: string | null;
    data_abertura: string | null;
    cnpj_destino?: string | null;
    cnpj_empresa?: string | null;
    cnpj_origem?: string | null;
    cod_funcao?: number | null;
    data_geracao?: string | null;
    nire?: string | null;
    RAE_CALIF_ACTV?: string | null;
    RGP_TGE_COD_TIP_TAB?: string | null;
    RGP_VALOR?: string | null;
    acessos_independentes: string | null;
    imovel_em_condominio: string | null;
    area_embarque_desembarque: string | null;
    possui_concessao_de_uso: string | null;
    termo_dispensa_viabilidade_locacional: string | null;
    bap_anterior_indeferido: string | null;
    mei_desenquadrado: string | null;
    evento: string | null;
    objeto_social: string | null;
    natureza_juridica: string | null;
    coordenada_geografica: { latitude: number; longitude: number } | null;
    contador: ProcessoContadorRecebido | null;
    atividades_exercidas_no_local: ProcessoAtividadeNoLocal[];
    poligono_declarado: { type: string } | null;
}

export interface ProcessoItem {
    id: number;
    protocol_number: string | null;
    bap: string | null;
    tvl_product_number: string | null;
    status: string;
    status_label: string;
    fluxo: string | null;
    fluxo_label: string | null;
    origin: string;
    origin_label: string;
    tipo_imovel: string | null;
    contingency_reason: string | null;
    empresa: string | null;
    cnpj: string | null;
    imovel: string;
    endereco_completo: string;
    inscricao: string | null;
    categorias: ProcessoCategoria[];
    categoria: string | null;
    servico: string | null;
    analista: string | null;
    assigned_user_id: number | null;
    setor: string | null;
    sector_id: number | null;
    analysis_stage: string | null;
    analysis_stage_label: string | null;
    analysis_status: string | null;
    analysis_status_label: string | null;
    analysis_due_at: string | null;
    sla: ProcessoSla | null;
    protocoled_at: string | null;
    escritorio_virtual: ProcessoEscritorioVirtual | null;
    recepcao?: ProcessoRecepcao | null;
    sem_decisao_automatica?: boolean;
    motivo_encaminhamento?: string | null;
    motivo_da_analise?: string | null;
}

/** Encaminhamento por risco do CNAE — não é deferido/indeferido. */
export function rotuloFluxoRisco(fluxo?: string | null): string {
    if (fluxo === 'expresso') {
        return 'Elegível ao expresso (risco)';
    }

    if (fluxo === 'semi_expresso') {
        return 'Semi-expresso';
    }

    if (fluxo === 'analise') {
        return 'Análise técnica';
    }

    return fluxo ?? '—';
}

/** Cor do badge por estado do semáforo: verde=no prazo, amarelo=alerta, vermelho=vencido. */
const SEMAFORO_COLOR: Record<string, 'success' | 'warning' | 'error'> = {
    verde: 'success',
    amarelo: 'warning',
    vermelho: 'error',
};

/**
 * Rótulo da coluna de prazo quando o processo não tem SLA materializado.
 * Processo decidido no fluxo expresso nunca entra na fila de análise (sem
 * analysis_due_at), então exibe "Fluxo expresso" em vez do genérico
 * "Sem prazo" — que fica reservado a quem ainda não entrou na fila.
 */
export function rotuloSemSla(fluxo: string | null | undefined): string {
    return fluxo === 'expresso' ? 'Fluxo expresso' : 'Sem prazo';
}

/**
 * Badge do semáforo de SLA (HU-144). Exibe o rótulo do estado e o tempo
 * restante calculado on-the-fly no backend. Sem prazo materializado, mostra um
 * traço (honesto — o processo ainda não entrou na fila com prazo).
 */
export function SemaforoBadge({ sla }: { sla: ProcessoSla | null }) {
    if (!sla) {
        return <span className="text-gray-400 dark:text-gray-500">—</span>;
    }

    return (
        <Badge color={SEMAFORO_COLOR[sla.status] ?? 'light'} size="sm">
            {sla.status_label}
            {sla.restante ? ` · ${sla.restante}` : ''}
        </Badge>
    );
}

/** Formata a data-hora ISO para o padrão pt-BR (dd/mm/aaaa hh:mm). */
export function formatarDataHora(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const data = new Date(iso);

    if (Number.isNaN(data.getTime())) {
        return iso;
    }

    return data.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/** Lista de badges das categorias derivadas do processo (RN-005). */
export function CategoriaBadges({ categorias }: { categorias: ProcessoCategoria[] }) {
    if (categorias.length === 0) {
        return <span className="text-gray-400 dark:text-gray-500">—</span>;
    }

    return (
        <div className="flex flex-wrap gap-1">
            {categorias.map((categoria) => (
                <Badge key={categoria.value} color="light" size="sm">
                    {categoria.label}
                </Badge>
            ))}
        </div>
    );
}

function textoOuTraco(valor: string | number | null | undefined): string {
    if (valor === null || valor === undefined || valor === '') {
        return '—';
    }

    return String(valor);
}

function ItemDeclaracao({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                {label}
            </dt>
            <dd className="mt-1 text-theme-sm text-gray-800 dark:text-white/90">{children}</dd>
        </div>
    );
}

/** Declaração recebida no POST, a mesma lista do detalhe do processo e da ficha. */
export function RecepcaoRecebida({ recepcao }: { recepcao: ProcessoRecepcao }) {
    const secundarios = (recepcao.cnaes_secundarios ?? []).join(', ');
    const cidade = [recepcao.municipio, recepcao.uf].filter(Boolean).join('/');

    return (
        <>
            <ItemDeclaracao label="Solicitante">{textoOuTraco(recepcao.nome_solicitante)}</ItemDeclaracao>
            <ItemDeclaracao label="CPF do solicitante">{textoOuTraco(recepcao.cpf_solicitante)}</ItemDeclaracao>
            <ItemDeclaracao label="E-mail do solicitante">{textoOuTraco(recepcao.email_solicitante)}</ItemDeclaracao>
            <ItemDeclaracao label="Porte da empresa">{textoOuTraco(recepcao.porte_empresa)}</ItemDeclaracao>
            <ItemDeclaracao label="CODLOG">{textoOuTraco(recepcao.codlog)}</ItemDeclaracao>
            <ItemDeclaracao label="Município">{cidade || '—'}</ItemDeclaracao>
            <ItemDeclaracao label="Ponto de referência">{textoOuTraco(recepcao.ponto_referencia)}</ItemDeclaracao>
            <ItemDeclaracao label="Área utilizada">{metrosComUnidade(recepcao.area_utilizada) ?? '—'}</ItemDeclaracao>
            <ItemDeclaracao label="Área construída / terreno">
                {recepcao.area_construida == null && recepcao.area_terreno == null
                    ? '—'
                    : `${formatarMetros(recepcao.area_construida) ?? '—'} / ${formatarMetros(recepcao.area_terreno) ?? '—'} m²`}
            </ItemDeclaracao>
            <ItemDeclaracao label="Acessos independentes">{textoOuTraco(recepcao.acessos_independentes)}</ItemDeclaracao>
            <ItemDeclaracao label="Imóvel em condomínio">{textoOuTraco(recepcao.imovel_em_condominio)}</ItemDeclaracao>
            <ItemDeclaracao label="Embarque/desembarque">{textoOuTraco(recepcao.area_embarque_desembarque)}</ItemDeclaracao>
            <ItemDeclaracao label="Concessão de uso">{textoOuTraco(recepcao.possui_concessao_de_uso)}</ItemDeclaracao>
            <ItemDeclaracao label="Evento">{textoOuTraco(recepcao.evento)}</ItemDeclaracao>
            <ItemDeclaracao label="Natureza jurídica">{textoOuTraco(recepcao.natureza_juridica)}</ItemDeclaracao>
            <ItemDeclaracao label="Termo de dispensa de viabilidade">
                {textoOuTraco(recepcao.termo_dispensa_viabilidade_locacional)}
            </ItemDeclaracao>
            <ItemDeclaracao label="BAP anterior indeferido">{textoOuTraco(recepcao.bap_anterior_indeferido)}</ItemDeclaracao>
            <ItemDeclaracao label="MEI desenquadrado">{textoOuTraco(recepcao.mei_desenquadrado)}</ItemDeclaracao>
            <ItemDeclaracao label="Contador">
                {recepcao.contador
                    ? [recepcao.contador.nome, recepcao.contador.crc].filter(Boolean).join(' · ') || '—'
                    : '—'}
            </ItemDeclaracao>
            <ItemDeclaracao label="Polígono declarado">{recepcao.poligono_declarado ? 'Sim' : '—'}</ItemDeclaracao>
            <ItemDeclaracao label="Categoria do imóvel">{textoOuTraco(recepcao.categoria_imovel)}</ItemDeclaracao>
            <ItemDeclaracao label="CNAE principal">{textoOuTraco(recepcao.cnae_principal)}</ItemDeclaracao>
            <ItemDeclaracao label="CNAEs secundários">{secundarios || '—'}</ItemDeclaracao>
            <ItemDeclaracao label="Área pública">{textoOuTraco(recepcao.atividade_em_area_publica)}</ItemDeclaracao>
            <ItemDeclaracao label="Escritório virtual">{textoOuTraco(recepcao.escritorio_virtual)}</ItemDeclaracao>
            <ItemDeclaracao label="Abrigada em escritório virtual">
                {textoOuTraco(recepcao.atividade_abrigada_em_escritorio_virtual)}
            </ItemDeclaracao>
            <ItemDeclaracao label="Presta serviço de escritório virtual (sede)">
                {textoOuTraco(recepcao.presta_servico_de_escritorio_virtual)}
            </ItemDeclaracao>
            <ItemDeclaracao label="CNPJ do escritório virtual">{textoOuTraco(recepcao.cnpj_escritorio_virtual)}</ItemDeclaracao>
            <ItemDeclaracao label="Serviço">{textoOuTraco(recepcao.servico)}</ItemDeclaracao>
            <ItemDeclaracao label="Data de abertura">{textoOuTraco(recepcao.data_abertura)}</ItemDeclaracao>
            <ItemDeclaracao label="CNPJ de destino">{textoOuTraco(recepcao.cnpj_destino)}</ItemDeclaracao>
            <ItemDeclaracao label="CNPJ da empresa">{textoOuTraco(recepcao.cnpj_empresa)}</ItemDeclaracao>
            <ItemDeclaracao label="CNPJ de origem">{textoOuTraco(recepcao.cnpj_origem)}</ItemDeclaracao>
            <ItemDeclaracao label="Código da função">{textoOuTraco(recepcao.cod_funcao)}</ItemDeclaracao>
            <ItemDeclaracao label="Data de geração">{textoOuTraco(recepcao.data_geracao)}</ItemDeclaracao>
            <ItemDeclaracao label="NIRE">{textoOuTraco(recepcao.nire)}</ItemDeclaracao>
            <ItemDeclaracao label="RAE_CALIF_ACTV">{textoOuTraco(recepcao.RAE_CALIF_ACTV)}</ItemDeclaracao>
            <ItemDeclaracao label="RGP_TGE_COD_TIP_TAB">{textoOuTraco(recepcao.RGP_TGE_COD_TIP_TAB)}</ItemDeclaracao>
            <ItemDeclaracao label="RGP_VALOR">{textoOuTraco(recepcao.RGP_VALOR)}</ItemDeclaracao>
            {(recepcao.atividades_exercidas_no_local ?? []).map((item) => (
                <ItemDeclaracao key={`local-${item.cnae}`} label={`CNAE ${item.cnae} exercido no local`}>
                    {item.exercida}
                </ItemDeclaracao>
            ))}
            {(recepcao.perguntas ?? []).map((item) => (
                <ItemDeclaracao key={`${item.cnae}-${item.pergunta}`} label={`Pergunta do CNAE ${item.cnae}`}>
                    {item.pergunta}: {item.resposta}
                </ItemDeclaracao>
            ))}
        </>
    );
}
