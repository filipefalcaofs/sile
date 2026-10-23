import FieldError from '@/components/form/field-error';
import { Head, Link, router, useHttp, usePage, WhenVisible } from '@inertiajs/react';
import type { GeoJsonObject } from 'geojson';
import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import ComparacaoCnaesCard, { type ComparacaoCnaes } from '@/components/analise/comparacao-cnaes-card';
import ConclusaoAnaliseForm from '@/components/analise/conclusao-analise-form';
import { QuadrosChecklist, type QuadroChecklist } from '@/components/analise/quadros-checklist';
import { RecepcaoRecebida, type ProcessoRecepcao } from '@/components/analise/processo-ui';
import {
    comAreaEmbarqueInformada,
    vereditoEstacionamento,
    type AreaEmbarqueExigida,
    type AreaEmbarqueRequerente,
} from '@/pages/gestao/ficha-analise/area-embarque';
import { desfechoDaAnalise, type DesfechoAnalise } from '@/pages/gestao/ficha-analise/sintese';
import { sugerirCondicionantes } from '@/pages/gestao/ficha-analise/sugestoes-condicionante';
import PageHeader from '@/components/app/page-header';
import CamadasImovelLista, { type CamadasImovel } from '@/components/geo/camadas-imovel';
import { centroDaGeometria, ESTILO_ENDERECAMENTO } from '@/components/geo/geometria';
import { MapaDecisaoSection } from '@/components/geo/mapa-decisao-section';
import { MapaSection } from '@/components/geo/mapa-section';
import Checkbox from '@/components/form/checkbox';
import Input from '@/components/form/input';
import IsoDateInput from '@/components/form/iso-date-input';
import Label from '@/components/form/label';
import OptionSelect from '@/components/form/option-select';
import Select from '@/components/form/select';
import { AlertIcon, ArrowRightIcon, CheckCircleIcon, ChevronDownIcon, MapPinIcon, TrashIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import { Dropdown } from '@/components/ui/dropdown';
import { DropdownItem } from '@/components/ui/dropdown-item';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

type StatusFicha = 'deferida' | 'indeferida';

interface PerCnae {
    cnae: string;
    cnae_formatado?: string | null;
    descricao?: string | null;
    is_primary?: boolean;
    tendencia?: string | null;
    tendencia_label?: string | null;
    status_escolhido?: string | null;
    fluxo?: string | null;
    grupo_uso?: string | null;
    subgrupo_uso?: string | null;
    valor_tll?: number | string | null;
    codigo_louos?: string | null;
    codigo_tll?: string | null;
    gatilhos?: string[] | null;
    motivo_fora_do_expresso?: string | null;
    fundamentacao?: string[] | null;
    condicionantes?: string[] | null;
    justificativa?: string | null;
    risco_municipal?: string | null;
    risco_sanitario?: string | null;
    pergunta_local?: {
        numero?: number | null;
        pergunta: string;
        resposta: string | null;
        pendente: boolean;
    } | null;
    quadros?: QuadroChecklist[] | null;
}

interface Parking {
    vagas_requeridas?: number | null;
    vagas_exigidas?: number | null;
    vistoria?: boolean;
    area_embarque_requerente?: AreaEmbarqueRequerente | null;
    area_embarque_exigida?: AreaEmbarqueExigida | null;
}

interface Ficha {
    id: number;
    viability_request_id: number;
    revision: number;
    status: string;
    status_label: string;
    editavel: boolean;
    engine_available: boolean;
    per_cnae: PerCnae[];
    conditions: string[];
    parking: Parking;
    parecer: string | null;
    is_virtual_office_hq: boolean | null;
    analysis_reasons: string[];
    address_confirmed: boolean | null;
    analyst: string | null;
    finalized_at: string | null;
    updated_at: string | null;
}

interface Processo {
    id: number;
    protocol_number: string | null;
    status: string;
    status_label: string;
    renovacao?: boolean;
    tvl_numero?: string | null;
    bap: string | null;
    servicos: string[];
    empresa: string | null;
}

interface TextoPadrao {
    id: number;
    category: string;
    content: string;
    version: number;
}

interface Localizacao {
    poligono: GeoJsonObject | null;
    poligono_enderecamento?: GeoJsonObject | null;
    endereco: string | null;
    cod_log: string | null;
    logradouro: string | null;
    numero_metrico: string | null;
    bairro: string | null;
    cep: string | null;
    ponto_referencia: string | null;
    zona: string | null;
    via: string | null;
    is_public_area: boolean | null;
    categoria_uso_lote: string | null;
    padrao_construtivo_lote: string | null;
    tipo_imovel: string | null;
    camadas?: CamadasImovel | null;
}

interface DadosTvl {
    razao_social: string | null;
    sede_escritorio_virtual: boolean;
    porte: string | null;
    tipo_imovel: string | null;
    categoria_empresa: string | null;
    torre_bloco_ala: string | null;
    complemento: string | null;
}

type StatusCadastroImobiliario = 'disponivel' | 'indisponivel' | 'nao_encontrado' | 'sem_inscricao';

interface CadastroImobiliario {
    status: StatusCadastroImobiliario;
    mensagem: string | null;
    inscricao: string | null;
    campos: Record<string, string | null>;
    consultado_em: string | null;
    source: string | null;
}

interface PrecedenteImovel {
    viability_request_id: number;
    protocol_number: string | null;
    bap: string | null;
    outcome: string | null;
    decided_at: string | null;
    service_type: string | null;
    analyst: string | null;
}

interface TramitacaoItem {
    data: string;
    setor: string | null;
    usuario: string | null;
    status: string;
    reason: string | null;
}

interface CnaeZona {
    disponivel: boolean;
    cnae?: string;
    zona?: string;
    janela_meses?: number;
    deferidos?: number;
    indeferidos?: number;
    total?: number;
    motivo?: string;
}

interface ProcessoEmAnaliseNoImovel {
    viability_request_id: number;
    protocol_number: string | null;
    bap: string | null;
}

interface PrecedentesResponse {
    em_analise_processos?: ProcessoEmAnaliseNoImovel[];
    imovel: PrecedenteImovel[];
    cnae_zona: CnaeZona;
    em_analise?: number;
}

type DiffValor = { de: unknown; para: unknown };

interface DiffResponse {
    de: number;
    para: number;
    diff: Record<string, unknown>;
}

type SeveridadeIa = 'baixa' | 'media' | 'alta';

interface InconsistenciaIa {
    campo: string;
    declarado: string;
    documento: string;
    severidade: SeveridadeIa;
}

interface SugestaoIaOutput {
    inconsistencias?: InconsistenciaIa[];
    resumo?: string | null;
    pontos_chave?: string[] | null;
    minuta?: string | null;
    fundamentacao?: string | null;
    fonte?: string | null;
    [chave: string]: unknown;
}

interface SugestaoIa {
    id: number;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    confianca: string | null;
    output: SugestaoIaOutput;
    /** CNAE da atividade (sugestão de justificativa) — para aplicar no campo certo. */
    cnae?: string | null;
    created_at: string | null;
}

interface AbrigadoLinha {
    tvl: string | null;
    razao_social: string | null;
    /** Validade do produto não modelada (desfecho spec-2) — sempre null → "—". */
    validade: string | null;
}

interface EscritorioVirtual {
    /** Gatilho de sede disparou (RN-EV-01: CNAE gatilho + requerente "quero ser sede"). */
    gatilho: boolean;
    /** O analista marcou a sede nesta ficha. */
    is_sede: boolean;
    inscricao: string | null;
    abrigados: AbrigadoLinha[];
    /** Código do CNAE gatilho da sede (parametrizável — default 8211-3/00). */
    cnae_gatilho: string;
    /** Texto do parâmetro `analise.escritorio_virtual.flag_analise_sede` (§2º art. 6º do Decreto 35.062/2021). */
    flag_analise_sede: string;
}

interface EnquadramentoOpcao {
    codigo_louos: string;
    denominacao_louos: string | null;
    codigo_tll: string | null;
    especificacao_tll: string | null;
    valor_tll: string | null;
    risco_nivel: string | null;
    risco_label: string | null;
    fluxo: string;
}

interface FichaAnaliseShowProps {
    ficha: Ficha;
    processo: Processo;
    conclusaoOpcoes: { value: string; label: string }[];
    localizacao?: Localizacao;
    dadosTvl: DadosTvl;
    recepcao?: ProcessoRecepcao | null;
    cadastroImobiliario: CadastroImobiliario;
    tramitacao: TramitacaoItem[];
    /** Opções da planilha vigente por CNAE (dígitos) — seleção do enquadramento. */
    enquadramentoOpcoes: Record<string, EnquadramentoOpcao[]>;
    /** Setores ativos — escolha do setor de tramitação no popup de vistoria. */
    setores: { id: number; name: string }[];
    escritorioVirtual: EscritorioVirtual;
    textosPadrao: TextoPadrao[];
    autosaveDebounceMs: number;
    /** Sugestões de IA (HU-107) — prop deferida, carregada sob demanda pelo card. */
    sugestoesIa?: SugestaoIa[];
    /** Disponibilidade honesta do copiloto (resumo + minuta). Sempre no load inicial. */
    iaFicha: IaFicha;
    /** Há ficha de vistoria para consultar. A ausência não cria registro. */
    temFichaVistoria: boolean;
    podeConsultarVistoria: boolean;
    podeEncaminharChefia: boolean;
    /** A ficha foi salva pelo botão ou finalizada: só então "Encaminhar para Chefia" aparece. */
    fichaSalva: boolean;
    /** Quem pode ser indicado ao encaminhar para a chefia (Chefe de setor, Subcoordenador, Coordenador). */
    usuariosIndicaveis: { id: number; name: string }[];
    /** Aberta pelo link da Tramitação: só consulta, sem as ações do processo. */
    somenteLeitura: boolean;
    /** Quem tem a permissão no perfil escolhe a zona e a via do imóvel na ficha. */
    podeEditarZonaVia: boolean;
    /** Alteração de atividade da JUCEB: comparação dos CNAEs com a viabilidade da SEFAZ. */
    comparacaoCnaes: ComparacaoCnaes | null;
    zonasDisponiveis: { codigo: string; nome: string | null }[];
    viasDisponiveis: string[];
}

interface PreviaZonaVia {
    antes: { zona: string | null; via: string | null };
    depois: { zona: string | null; via: string | null };
    motor_disponivel: boolean;
    mudancas: { cnae: string; antes: string | null; depois: string; motivo: string | null; mudou: boolean }[];
}

interface IaFicha {
    resumo_disponivel: boolean;
    resumo_motivo: string | null;
    parecer_disponivel: boolean;
    parecer_motivo: string | null;
    justificativa_disponivel: boolean;
    justificativa_motivo: string | null;
}

const STATUS_OPCOES: { value: StatusFicha; label: string }[] = [
    { value: 'deferida', label: 'Deferida' },
    { value: 'indeferida', label: 'Indeferida' },
];

/** A minuta (HU-110) roda em fila (assíncrona): após solicitar, sondamos a prop
 *  deferida sugestoesIa por uma janela limitada até a sugestão ser processada.
 *  O resumo (HU-109) roda no mesmo request — sem polling. */
const MINUTA_POLL_INTERVAL_MS = 3000;
const MINUTA_POLL_MAX = 8;

function statusLabel(status: string | null | undefined): string {
    return STATUS_OPCOES.find((opcao) => opcao.value === status)?.label ?? (status ?? '—');
}

function statusColor(status: string | null | undefined): 'success' | 'error' | 'warning' | 'light' {
    if (status === 'deferida') {
        return 'success';
    }

    if (status === 'indeferida') {
        return 'error';
    }

    if (status === 'analise') {
        return 'warning';
    }

    return 'light';
}

function formatarDataHora(iso: string | null): string {
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

const moedaFormat = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

/** Formata o valor TLL (string/number) como moeda brasileira (R$). */
function formatarMoeda(valor: number | string): string {
    const numero = typeof valor === 'number' ? valor : Number.parseFloat(String(valor));

    return Number.isNaN(numero) ? String(valor) : moedaFormat.format(numero);
}

export function escapeHtml(valor: string): string {
    return valor
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * Extrato de tramitação real (não é PDF novo nem endpoint novo — spec
 * 2026-07-24 D7): monta uma janela de impressão do navegador a partir dos
 * mesmos dados já renderizados na tabela. O cabeçalho formal identifica o
 * sistema, o processo, a data/hora de emissão e o usuário emissor — o extrato
 * circula como peça operacional documental, não como impressão anônima.
 */
function imprimirExtratoTramitacao(protocolo: string | null, itens: TramitacaoItem[], emitidoPor: string) {
    const janela = window.open('', '_blank', 'width=800,height=600');

    if (!janela) {
        window.alert('Não foi possível abrir a janela de impressão. Verifique se o navegador bloqueou o popup e tente novamente.');

        return;
    }

    const emitidoEm = formatarDataHora(new Date().toISOString());

    const linhas = itens
        .map(
            (item) => `
        <tr>
            <td>${escapeHtml(formatarDataHora(item.data))}</td>
            <td>${escapeHtml(item.setor ?? '—')}</td>
            <td>${escapeHtml(item.usuario ?? '—')}</td>
            <td>${escapeHtml(item.status)}</td>
        </tr>`,
        )
        .join('');

    janela.document.write(`
        <html>
            <head>
                <title>Extrato de tramitação — ${escapeHtml(protocolo ?? '')}</title>
                <style>
                    body { font-family: sans-serif; padding: 24px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; font-size: 13px; }
                    th { background: #f3f4f6; }
                    header { margin-bottom: 16px; }
                    header h3 { margin: 0 0 4px; }
                    header p, footer p { margin: 2px 0; font-size: 12px; color: #555; }
                    footer { margin-top: 16px; border-top: 1px solid #ccc; padding-top: 8px; }
                </style>
            </head>
            <body>
                <header>
                    <h3>Extrato de tramitação — ${escapeHtml(protocolo ?? '')}</h3>
                    <p>Viabiliza — Sistema de Licenciamento Eletrônico · SEDUR</p>
                    <p>Emitido em ${escapeHtml(emitidoEm)} por ${escapeHtml(emitidoPor)}</p>
                </header>
                <table>
                    <thead><tr><th>Data</th><th>Setor</th><th>Usuário</th><th>Status</th></tr></thead>
                    <tbody>${linhas}</tbody>
                </table>
                <footer>
                    <p>Extrato gerado eletronicamente a partir da trilha operacional registrada no processo.</p>
                </footer>
            </body>
        </html>
    `);
    janela.document.close();
    janela.focus();
    janela.print();
}

/** Exibe "—" para valores nulos/vazios — nunca inventa dado (anti-fachada). */
function valorOuTraco(valor: string | null | undefined): string {
    return valor && valor.trim() !== '' ? valor : '—';
}

function cepMascarado(valor: string | null | undefined): string {
    const digitos = (valor ?? '').replace(/\D/g, '');

    if (digitos.length !== 8) {
        return valorOuTraco(valor);
    }

    return `${digitos.slice(0, 5)}-${digitos.slice(5)}`;
}

/**
 * Campos da certidão cadastral exibidos na ficha. Sem destaque âmbar e sem
 * repetir endereço/bairro/CEP — esses ficam no bloco Localização, logo acima
 * (relatório de usabilidade SEDUR 19/09/2026, itens 14, 16 e 20).
 */
const CAMPOS_IPTU = [
    'inscricao',
    'bairro',
    'loteamento',
    'quadra',
    'lote',
    'conjunto_edificio',
    'bloco',
    'sub_unidade',
    'numero_sub_unidade',
    'area_construida_m2',
    'tipo_imovel',
    'data_lancamento',
    'situacao_cadastral',
    'numero_porta',
    'area_terreno_m2',
    'situacao_fiscal',
    'data_emissao_certidao',
] as const;

const LABELS_CADASTRO_IMOBILIARIO: Record<string, string> = {
    inscricao: 'Inscrição Imobiliária',
    endereco: 'Endereço',
    numero_metrico: 'Nº Métrico',
    loteamento: 'Loteamento',
    quadra: 'Quadra',
    lote: 'Lote',
    conjunto_edificio: 'Conjunto / Edifício',
    bloco: 'Bloco',
    sub_unidade: 'Sub-Unidade',
    numero_sub_unidade: 'Nº Sub-Unidade',
    bairro: 'Bairro',
    cep: 'CEP',
    area_construida_m2: 'Área Construída (m²)',
    tipo_imovel: 'Tipo Imóvel',
    data_lancamento: 'Data Lançamento',
    situacao_cadastral: 'Situação Cadastral',
    contribuinte: 'Contribuinte',
    cpf_cnpj: 'CPF/CNPJ',
    numero_porta: 'Nº de Porta',
    area_terreno_m2: 'Área Terreno (m²)',
    valor_venal_iptu: 'Valor Venal',
    logradouro_tributario: 'Logradouro Tributário',
    situacao_fiscal: 'Situação Fiscal',
    data_emissao_certidao: 'Data de Emissão da Certidão',
};

/**
 * Campo da certidão cadastral: só leitura, sem destaque visual (relatório de
 * usabilidade SEDUR 19/09/2026, itens 14 e 16).
 */
function CampoCadastroImobiliario({ chave, valor }: { chave: string; valor: string | null | undefined }) {
    return (
        <div>
            <dt className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                {LABELS_CADASTRO_IMOBILIARIO[chave] ?? chave}
            </dt>
            <dd className="mt-1 text-theme-sm text-gray-800 dark:text-white/90">{valorOuTraco(valor)}</dd>
        </div>
    );
}

/**
 * Bloco Cadastro Imobiliário — preenche o espaço em branco do legado ao
 * lado da Localização (design 2026-07-22). Só leitura; degrada honestamente
 * quando a consulta SEFAZ/SEDUR está indisponível, a inscrição não é
 * encontrada ou o processo não informa inscrição — nunca certidão simulada.
 */
function CadastroImobiliarioPanel({ cadastro }: { cadastro: CadastroImobiliario }) {
    return (
        <div className="mt-6 border-t border-gray-100 pt-6 dark:border-gray-800">
            <h4 className="text-theme-sm font-medium text-gray-800 dark:text-white/90">Cadastro Imobiliário</h4>
            <p className="mt-1 text-theme-xs text-gray-400 dark:text-gray-500">
                Certidão de Dados Cadastrais (SEFAZ) — inscrição {valorOuTraco(cadastro.inscricao)}
                {cadastro.source && ` · fonte: ${cadastro.source}`}
            </p>

            {cadastro.status !== 'disponivel' && (
                <div className="mt-3 flex items-start gap-3 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/15">
                    <AlertIcon className="size-5 shrink-0 fill-current text-warning-500" />
                    <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                        {cadastro.mensagem ?? 'Cadastro Imobiliário indisponível.'}
                    </p>
                </div>
            )}

            <dl className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                {CAMPOS_IPTU.map((chave) => (
                    <CampoCadastroImobiliario key={chave} chave={chave} valor={cadastro.campos[chave]} />
                ))}
            </dl>
        </div>
    );
}

function DescItem({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                {label}
            </dt>
            <dd className="mt-1 text-theme-sm text-gray-800 dark:text-white/90">{children}</dd>
        </div>
    );
}

function rotuloEnquadramento(opcao: EnquadramentoOpcao): string {
    const denominacao = opcao.denominacao_louos ? ` — ${opcao.denominacao_louos}` : '';
    const tll = opcao.codigo_tll ? ` · TLL ${opcao.codigo_tll}` : '';

    return `${opcao.codigo_louos}${denominacao}${tll}`;
}

/** Textarea no padrão visual do design system (sem componente dedicado no kit). */
function Textarea({
    value,
    onChange,
    rows = 4,
    placeholder,
    disabled,
    id,
    ampliavel = false,
}: {
    value: string;
    onChange: (value: string) => void;
    rows?: number;
    placeholder?: string;
    disabled?: boolean;
    id?: string;
    /** Parecer técnico (SEDUR 20/10/2026): cresce até 480px e pode ser redimensionado pelo canto. */
    ampliavel?: boolean;
}) {
    const ref = useRef<HTMLTextAreaElement>(null);
    const teto = ampliavel ? 480 : 160;

    useLayoutEffect(() => {
        const el = ref.current;

        if (!el) {
            return;
        }

        // No campo ampliável, a altura arrastada pelo analista não encolhe ao digitar.
        const alturaAtual = ampliavel ? el.offsetHeight : 0;
        el.style.height = 'auto';
        const altura = Math.max(alturaAtual, Math.min(el.scrollHeight, teto));
        el.style.height = `${altura}px`;
        el.style.overflowY = el.scrollHeight > altura ? 'auto' : 'hidden';
    }, [value, ampliavel, teto]);

    return (
        <textarea
            ref={ref}
            id={id}
            rows={rows}
            value={value}
            disabled={disabled}
            placeholder={placeholder}
            onChange={(event) => onChange(event.target.value)}
            maxLength={2000}
            className={`w-full ${ampliavel ? 'resize-y' : 'max-h-40 resize-none'} rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800`}
        />
    );
}

/** Seletor de status escolhido por CNAE (espelha os radios Deferida/Indeferida/Análise do SAPS). */
function StatusEscolhido({
    cnae,
    valor,
    onChange,
    disabled,
}: {
    cnae: string;
    valor: string | null | undefined;
    onChange: (status: StatusFicha | null) => void;
    disabled: boolean;
}) {
    return (
        <div className="flex flex-wrap gap-2" role="radiogroup" aria-label={`Decisão do CNAE ${cnae}`}>
            {STATUS_OPCOES.map((opcao) => {
                const ativo = valor === opcao.value;

                return (
                    <button
                        key={opcao.value}
                        type="button"
                        role="radio"
                        aria-checked={ativo}
                        disabled={disabled}
                        onClick={() => onChange(ativo ? null : opcao.value)}
                        className={`rounded-lg px-3 py-1.5 text-theme-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-60 ${
                            ativo
                                ? opcao.value === 'deferida'
                                    ? 'bg-success-500 text-white'
                                    : opcao.value === 'indeferida'
                                      ? 'bg-error-500 text-white'
                                      : 'bg-warning-500 text-white'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10'
                        }`}
                    >
                        {opcao.label}
                    </button>
                );
            })}
        </div>
    );
}

/** Linha expansível da tabela de Tramitação — revela o `reason`, quando houver. */
function TramitacaoLinha({ item }: { item: TramitacaoItem }) {
    const [aberto, setAberto] = useState(false);

    return (
        <>
            <tr className="border-b border-gray-100 last:border-0 dark:border-gray-800">
                <td className="py-2 pr-4 text-gray-500 dark:text-gray-400">{formatarDataHora(item.data)}</td>
                <td className="py-2 pr-4">{item.setor ?? '—'}</td>
                <td className="py-2 pr-4">{item.usuario ?? '—'}</td>
                <td className="py-2 pr-4 font-medium text-gray-800 dark:text-white/90">{item.status}</td>
                <td className="py-2 text-right">
                    {item.reason && (
                        <button
                            type="button"
                            onClick={() => setAberto((atual) => !atual)}
                            aria-label={aberto ? 'Ocultar motivo' : 'Ver motivo'}
                            aria-expanded={aberto}
                            className="text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200"
                        >
                            <ChevronDownIcon className={`size-4 transition-transform ${aberto ? 'rotate-180' : ''}`} />
                        </button>
                    )}
                </td>
            </tr>
            {aberto && item.reason && (
                <tr>
                    <td colSpan={5} className="pb-3 text-theme-xs text-gray-500 dark:text-gray-400">
                        {item.reason}
                    </td>
                </tr>
            )}
        </>
    );
}

export default function FichaAnaliseShow({
    ficha,
    processo,
    conclusaoOpcoes,
    localizacao,
    dadosTvl,
    recepcao,
    cadastroImobiliario,
    tramitacao,
    enquadramentoOpcoes,
    setores,
    escritorioVirtual,
    textosPadrao,
    autosaveDebounceMs,
    sugestoesIa,
    iaFicha,
    temFichaVistoria,
    podeConsultarVistoria,
    podeEncaminharChefia,
    fichaSalva: fichaSalvaNoServidor,
    usuariosIndicaveis,
    somenteLeitura,
    podeEditarZonaVia,
    comparacaoCnaes,
    zonasDisponiveis,
    viasDisponiveis,
}: FichaAnaliseShowProps) {
    const { auth } = usePage<SharedProps>().props;
    // Encaminhar à vistoria é ação do analista (handoff ao setor de vistoria).
    const podeEncaminharVistoria = auth.permissions.includes('analisar-processos');
    // Malha fina é caixa à parte para alterar o processo (HU-127) — permissão própria.
    const podeMalhaFina = auth.permissions.includes('encaminhar-malha-fina');
    const classeItemMenu =
        'block w-full rounded-lg px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.05] dark:hover:text-white';

    const editavel = ficha.editavel;

    const [perCnae, setPerCnae] = useState<PerCnae[]>(() => ficha.per_cnae ?? []);
    const [deferirTodos, setDeferirTodos] = useState(false);
    const [salvouNestaTela, setSalvouNestaTela] = useState(false);
    const [maisAcoesAberto, setMaisAcoesAberto] = useState(false);
    const fichaSalva = fichaSalvaNoServidor || salvouNestaTela;
    const statusAntesDeDeferirTodos = useRef<Record<string, PerCnae['status_escolhido']> | null>(null);
    // Regra de decisão: só o indeferimento é marcado por CNAE. Nenhum indeferido
    // → o processo é deferido; ao menos um indeferido → o processo é indeferido.
    const [conditions, setConditions] = useState<string[]>(() => ficha.conditions ?? []);
    const [parecer, setParecer] = useState<string>(() => ficha.parecer ?? '');
    const [tvlKind, setTvlKind] = useState<'provisoria' | 'definitiva'>('provisoria');
    const [tvlValidUntil, setTvlValidUntil] = useState('');
    const [condicionantesAtendidos, setCondicionantesAtendidos] = useState(true);
    const [parking, setParking] = useState<Parking>(() =>
        comAreaEmbarqueInformada(ficha.parking ?? {}, recepcao?.area_embarque_desembarque),
    );
    const [sedeEscritorioVirtual, setSedeEscritorioVirtual] = useState<boolean>(() => ficha.is_virtual_office_hq ?? false);
    const [novaCondicao, setNovaCondicao] = useState('');
    const [sugestaoAtiva, setSugestaoAtiva] = useState(-1);
    const [sugestoesAbertas, setSugestoesAbertas] = useState(false);
    const analysisReasons = ficha.analysis_reasons ?? [];
    const desfecho = desfechoDaAnalise(processo.status);
    const sugestoesCondicao = useMemo(
        () => sugerirCondicionantes(textosPadrao, novaCondicao, conditions),
        [textosPadrao, novaCondicao, conditions],
    );
    const [addressConfirmed, setAddressConfirmed] = useState<boolean | null>(() => ficha.address_confirmed ?? null);
    const [saveState, setSaveState] = useState<'idle' | 'salvando' | 'salvo' | 'erro'>('idle');

    const autosave = useHttp<{
        per_cnae: Array<{ cnae: string; status_escolhido: string | null; justificativa: string | null; condicionantes: string[] }>;
        conditions: string[];
        parking: Parking;
        parecer: string | null;
        is_virtual_office_hq: boolean;
        address_confirmed: boolean | null;
        manual?: boolean;
    }>({
        per_cnae: [],
        conditions: [],
        parking: {},
        parecer: null,
        is_virtual_office_hq: false,
        address_confirmed: null,
    });

    const acao = useHttp<Record<string, never>>({});
    const [pendenciaProcessing, setPendenciaProcessing] = useState(false);
    const [vistoriaProcessing, setVistoriaProcessing] = useState(false);
    const [erroEncaminhar, setErroEncaminhar] = useState<string | null>(null);
    const [malhaFinaProcessing, setMalhaFinaProcessing] = useState(false);
    const [erroMalhaFina, setErroMalhaFina] = useState<string | null>(null);
    const precedentes = useHttp<Record<string, never>, PrecedentesResponse>({});
    const diff = useHttp<{ de: number; para: number }, DiffResponse>({ de: 0, para: 0 });

    // Sugestão de minuta de parecer por IA (HU-110) — apoio, nunca decisão. O job
    // roda em fila; aqui sondamos a sugestão até ela aparecer (UX assíncrona).
    const minuta = useHttp<Record<string, never>, { despachou?: boolean; status?: string }>({});
    const [minutaStatus, setMinutaStatus] = useState<'idle' | 'solicitando' | 'aguardando' | 'pronta' | 'indisponivel'>('idle');
    const [minutaMensagem, setMinutaMensagem] = useState<string | null>(null);
    const minutaPollRef = useRef<number | null>(null);
    const minutaBaselineRef = useRef(0);
    const parecerSugeridos = useMemo(
        () => (sugestoesIa ?? []).filter((sugestao) => sugestao.type === 'parecer').length,
        [sugestoesIa],
    );
    const resumoHttp = useHttp<Record<string, never>, { despachou?: boolean; status?: string }>({});
    const [resumoStatus, setResumoStatus] = useState<
        'idle' | 'solicitando' | 'aguardando' | 'pronta' | 'indisponivel' | 'timeout'
    >('idle');
    const [resumoMensagem, setResumoMensagem] = useState<string | null>(null);
    const resumosSugeridos = useMemo(
        () => (sugestoesIa ?? []).filter((sugestao) => sugestao.type === 'resumo_processo').length,
        [sugestoesIa],
    );

    // Sugestão de justificativa por IA (regra SEDUR 22/09, item 6): apoio por
    // atividade, habilitado SÓ depois da decisão do analista no CNAE. O job roda
    // em fila; sondamos a prop deferida sugestoesIa como na minuta do parecer.
    const justificativaIa = useHttp<{ cnae?: string }, { despachou?: boolean; status?: string }>({});
    const [justificativaStatus, setJustificativaStatus] = useState<
        Record<string, 'solicitando' | 'aguardando' | 'pronta' | 'indisponivel'>
    >({});
    const [justificativaMensagem, setJustificativaMensagem] = useState<Record<string, string | null>>({});
    const justificativaPollRef = useRef<number | null>(null);
    const justificativaBaselineRef = useRef(0);
    const justificativasSugeridas = useMemo(
        () => (sugestoesIa ?? []).filter((sugestao) => sugestao.type === 'justificativa').length,
        [sugestoesIa],
    );

    const [precedentesData, setPrecedentesData] = useState<PrecedentesResponse | null>(null);
    const [precedentesErro, setPrecedentesErro] = useState<string | null>(null);

    const [showDiff, setShowDiff] = useState(false);
    const [diffDe, setDiffDe] = useState(Math.max(1, ficha.revision - 1));
    const [diffPara, setDiffPara] = useState(ficha.revision);
    const [diffData, setDiffData] = useState<DiffResponse | null>(null);
    const [diffErro, setDiffErro] = useState<string | null>(null);

    const [showFinalizar, setShowFinalizar] = useState(false);
    const [showDecidir, setShowDecidir] = useState(false);
    const [erroAcao, setErroAcao] = useState<string | null>(null);
    const [showPendencia, setShowPendencia] = useState(false);
    const [descricaoPendencia, setDescricaoPendencia] = useState('');
    const [erroPendencia, setErroPendencia] = useState<string | null>(null);
    const [showCancelarConvite, setShowCancelarConvite] = useState(false);
    const [showEncaminharChefia, setShowEncaminharChefia] = useState(false);
    const [parecerExpandido, setParecerExpandido] = useState(false);
    const [encaminhandoChefia, setEncaminhandoChefia] = useState(false);
    const [indicadoParaConclusao, setIndicadoParaConclusao] = useState('');
    const zonaViaHttp = useHttp<Record<string, string>, PreviaZonaVia>({});
    const [showZonaVia, setShowZonaVia] = useState(false);
    const [zonaEscolhida, setZonaEscolhida] = useState('');
    const [viaEscolhida, setViaEscolhida] = useState('');
    const [justificativaZonaVia, setJustificativaZonaVia] = useState('');
    const [previaZonaVia, setPreviaZonaVia] = useState<PreviaZonaVia | null>(null);
    const [erroZonaVia, setErroZonaVia] = useState<string | null>(null);

    function abrirZonaVia() {
        setZonaEscolhida(localizacao?.zona ?? '');
        setViaEscolhida(localizacao?.via ?? '');
        setJustificativaZonaVia('');
        setPreviaZonaVia(null);
        setErroZonaVia(null);
        setShowZonaVia(true);
    }

    function fecharZonaVia() {
        setShowZonaVia(false);
        setPreviaZonaVia(null);
    }

    function enviarZonaVia(acao: 'previa' | 'alterar') {
        setErroZonaVia(null);
        zonaViaHttp.transform(() => ({ zona: zonaEscolhida, via: viaEscolhida, justificativa: justificativaZonaVia }));

        const aoFalhar = (erros: Record<string, string | string[]>) => {
            const primeiro = Object.values(erros)[0];
            setErroZonaVia(Array.isArray(primeiro) ? primeiro[0] : (primeiro ?? 'Não foi possível concluir. Tente novamente.'));
        };

        if (acao === 'previa') {
            zonaViaHttp.post(`/gestao/processos/${processo.id}/zona-via/previa`, {
                onSuccess: (resposta) => setPreviaZonaVia(resposta),
                onError: aoFalhar,
                onHttpException: () => {
                    setErroZonaVia('Não foi possível simular a alteração.');

                    return false;
                },
            });

            return;
        }

        zonaViaHttp.patch(`/gestao/processos/${processo.id}/zona-via`, {
            onSuccess: () => {
                fecharZonaVia();
                router.reload();
            },
            onError: aoFalhar,
            onHttpException: () => {
                setErroZonaVia('Não foi possível gravar a alteração.');

                return false;
            },
        });
    }
    const [parecerCancelamento, setParecerCancelamento] = useState('');
    const [cancelarConviteProcessing, setCancelarConviteProcessing] = useState(false);
    const [showVistoria, setShowVistoria] = useState(false);
    const [showSemFichaVistoria, setShowSemFichaVistoria] = useState(false);
    const [motivoVistoria, setMotivoVistoria] = useState('');
    const [setorVistoria, setSetorVistoria] = useState('');
    const [showMalhaFina, setShowMalhaFina] = useState(false);
    const [motivoMalhaFina, setMotivoMalhaFina] = useState('');
    const [setorMalhaFina, setSetorMalhaFina] = useState('');
    const [showConclusao, setShowConclusao] = useState(false);
    const [resultadoConclusao, setResultadoConclusao] = useState('');
    const [parecerConclusao, setParecerConclusao] = useState('');
    const [tipoDeferimento, setTipoDeferimento] = useState('');
    const [validadeMeses, setValidadeMeses] = useState('');
    const [validadeData, setValidadeData] = useState('');
    const [errosTipo, setErrosTipo] = useState<{ tipo: string | null; validade: string | null; validadeData: string | null }>({ tipo: null, validade: null, validadeData: null });
    const [conclusaoProcessing, setConclusaoProcessing] = useState(false);
    const [erroConclusao, setErroConclusao] = useState<string | null>(null);
    const [divergenciaConclusao, setDivergenciaConclusao] = useState<string | null>(null);
    const podeConcluir = auth.permissions.includes('concluir-analise');
    const podeConcluirAnalise = podeConcluir && ['em_analise', 'deferida', 'indeferida', 'desativado', 'cassado', 'revogado'].includes(processo.status);
    const [pickerParaParecer, setPickerParaParecer] = useState(false);
    const [pickerParaCondicao, setPickerParaCondicao] = useState(false);

    const fichaUrl = `/gestao/processos/${processo.id}/ficha`;

    const construirPayload = useCallback(
        () => ({
            per_cnae: perCnae.map((item) => ({
                cnae: item.cnae,
                status_escolhido: item.status_escolhido === 'analise' ? null : (item.status_escolhido ?? null),
                justificativa: item.justificativa ?? null,
                condicionantes: item.condicionantes ?? [],
                codigo_louos: item.codigo_louos ?? null,
                codigo_tll: item.codigo_tll ?? null,
            })),
            conditions,
            parking,
            parecer: parecer.trim() === '' ? null : parecer,
            is_virtual_office_hq: sedeEscritorioVirtual,
            address_confirmed: addressConfirmed,
        }),
        [perCnae, conditions, parking, parecer, sedeEscritorioVirtual, addressConfirmed],
    );

    const salvarRascunho = useCallback((manual = false) => {
        if (!editavel) {
            return;
        }

        setSaveState('salvando');
        autosave.transform(() => (manual ? { ...construirPayload(), manual: true } : construirPayload()));
        autosave.patch(fichaUrl, {
            onSuccess: () => {
                setSaveState('salvo');

                if (manual) {
                    setSalvouNestaTela(true);
                }
            },
            onError: () => setSaveState('erro'),
            onHttpException: () => {
                setSaveState('erro');

                return false;
            },
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [editavel, construirPayload, fichaUrl]);

    const primeiraRenderRef = useRef(true);

    useEffect(() => {
        if (!editavel) {
            return;
        }

        if (primeiraRenderRef.current) {
            primeiraRenderRef.current = false;

            return;
        }

        const timer = window.setTimeout(() => salvarRascunho(), autosaveDebounceMs);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [perCnae, conditions, parecer, parking, sedeEscritorioVirtual, addressConfirmed]);

    useEffect(() => {
        precedentes.get(`/gestao/processos/${processo.id}/precedentes`, {
            onSuccess: (resposta) => setPrecedentesData(resposta),
            onError: () => setPrecedentesErro('Não foi possível carregar os precedentes.'),
            onHttpException: () => {
                setPrecedentesErro('Não foi possível carregar os precedentes.');

                return false;
            },
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [processo.id]);

    function atualizarCnae(indice: number, patch: Partial<PerCnae>) {
        setPerCnae((atual) => atual.map((item, i) => (i === indice ? { ...item, ...patch } : item)));
    }

    /**
     * Marca todas as atividades como deferidas de uma vez. Sem a marcação, cada
     * atividade segue exigindo a escolha individual; ao desmarcar, voltam as
     * escolhas que existiam antes.
     */
    function alternarDeferirTodos(marcar: boolean) {
        if (marcar) {
            statusAntesDeDeferirTodos.current = Object.fromEntries(perCnae.map((item) => [item.cnae, item.status_escolhido ?? null]));
            setPerCnae((atual) => atual.map((item) => ({ ...item, status_escolhido: 'deferida' })));
        } else {
            const anteriores = statusAntesDeDeferirTodos.current;
            setPerCnae((atual) => atual.map((item) => ({ ...item, status_escolhido: anteriores?.[item.cnae] ?? null })));
            statusAntesDeDeferirTodos.current = null;
        }

        setDeferirTodos(marcar);
    }

    // Trocar o resultado de uma atividade na mão desfaz a marcação de "todos".
    useEffect(() => {
        if (deferirTodos && !perCnae.every((item) => item.status_escolhido === 'deferida')) {
            setDeferirTodos(false);
            statusAntesDeDeferirTodos.current = null;
        }
    }, [perCnae, deferirTodos]);

    function adicionarCondicao(texto: string) {
        const limpo = texto.trim();

        if (limpo === '' || conditions.includes(limpo)) {
            return;
        }

        setConditions((atual) => [...atual, limpo]);
    }

    function removerCondicao(indice: number) {
        setConditions((atual) => atual.filter((_, i) => i !== indice));
    }

    function inserirTextoPadrao(conteudo: string) {
        if (pickerParaParecer) {
            setParecer((atual) => (atual.trim() === '' ? conteudo : `${atual}\n\n${conteudo}`));
            setPickerParaParecer(false);

            return;
        }

        if (pickerParaCondicao) {
            adicionarCondicao(conteudo);
            setPickerParaCondicao(false);
        }
    }

    function finalizarFicha() {
        setErroAcao(null);

        if (!editavel) {
            return;
        }

        autosave.transform(() => construirPayload());
        autosave.patch(fichaUrl, {
            onSuccess: () => {
                acao.post(`${fichaUrl}/finalizar`, {
                    onSuccess: () => {
                        setShowFinalizar(false);
                        router.reload();
                    },
                    onError: (erros) =>
                        setErroAcao(
                            (erros as Record<string, string>).ficha ??
                                'Não foi possível finalizar a ficha. Revise os dados e tente novamente.',
                        ),
                    onHttpException: () => false,
                });
            },
            onError: () => setSaveState('erro'),
            onHttpException: () => {
                setSaveState('erro');

                return false;
            },
        });
    }

    function novaRevisao() {
        acao.post(`${fichaUrl}/nova-revisao`, {
            onSuccess: () => router.visit(window.location.href, { preserveState: false, preserveScroll: true }),
            onHttpException: () => false,
        });
    }

    function concluirAnalise(confirmarDivergencia = false) {
        setErroConclusao(null);
        setDivergenciaConclusao(null);
        setErrosTipo({ tipo: null, validade: null, validadeData: null });
        router.post(
            `/gestao/processos/${processo.id}/conclusao-analise`,
            {
                resultado: resultadoConclusao,
                parecer: parecerConclusao,
                confirmar_divergencia: confirmarDivergencia,
                tipo: tipoDeferimento,
                validade_meses: validadeMeses,
                validade_data: validadeMeses === 'personalizado' ? validadeData : null,
            },
            {
                preserveScroll: true,
                onStart: () => setConclusaoProcessing(true),
                onFinish: () => setConclusaoProcessing(false),
                onSuccess: () => {
                    setShowConclusao(false);
                    setResultadoConclusao('');
                    setParecerConclusao('');
                    setTipoDeferimento('');
                    setValidadeMeses('');
                    setValidadeData('');
                },
                onError: (erros) => {
                    const mapa = erros as Record<string, string>;
                    setDivergenciaConclusao(mapa.divergencia ?? null);
                    setErrosTipo({ tipo: mapa.tipo ?? null, validade: mapa.validade_meses ?? null, validadeData: mapa.validade_data ?? null });
                    const erroDeTipo = mapa.tipo !== undefined || mapa.validade_meses !== undefined || mapa.validade_data !== undefined;
                    setErroConclusao(
                        mapa.divergencia || erroDeTipo
                            ? null
                            : (mapa.conclusao ?? mapa.resultado ?? 'Não foi possível concluir a análise.'),
                    );
                },
            },
        );
    }

    function decidirProcesso() {
        setErroAcao(null);
        router.post(
            `/gestao/processos/${processo.id}/decidir`,
            processo.renovacao
                ? {
                      tvl_kind: tvlKind,
                      tvl_valid_until: tvlValidUntil,
                      condicionantes_atendidos: condicionantesAtendidos,
                  }
                : {},
            {
                preserveScroll: true,
                onSuccess: () => setShowDecidir(false),
                onError: (erros) =>
                    setErroAcao(
                        (erros as Record<string, string>).decisao ??
                            'Não foi possível concluir a decisão. Revise a ficha e tente novamente.',
                    ),
            },
        );
    }

    function abrirPendencia() {
        setErroPendencia(null);
        router.post(
            `/gestao/processos/${processo.id}/pendencias`,
            { descricao: descricaoPendencia },
            {
                preserveScroll: true,
                onStart: () => setPendenciaProcessing(true),
                onFinish: () => setPendenciaProcessing(false),
                onSuccess: () => {
                    setShowPendencia(false);
                    setDescricaoPendencia('');
                    setErroPendencia(null);
                },
                onError: (erros) => {
                    const primeiro = Object.values(erros)[0];
                    const texto = typeof primeiro === 'string'
                        ? primeiro
                        : Array.isArray(primeiro) && typeof primeiro[0] === 'string'
                          ? primeiro[0]
                          : '';
                    setErroPendencia(texto || 'A descrição passou do limite de 2.000 caracteres.');
                },
            },
        );
    }

    function encaminharParaChefia() {
        router.post(
            `/gestao/processos/${processo.id}/status-analise`,
            {
                status: 'para_conclusao',
                voltar_para_caixa: true,
                indicado_user_id: indicadoParaConclusao === '' ? null : Number(indicadoParaConclusao),
            },
            {
                onStart: () => setEncaminhandoChefia(true),
                onFinish: () => {
                    setEncaminhandoChefia(false);
                    setShowEncaminharChefia(false);
                },
            },
        );
    }

    function cancelarConvite() {
        router.post(
            `/gestao/processos/${processo.id}/cancelar-convite`,
            { parecer: parecerCancelamento },
            {
                preserveScroll: true,
                onStart: () => setCancelarConviteProcessing(true),
                onFinish: () => setCancelarConviteProcessing(false),
                onSuccess: () => {
                    setShowCancelarConvite(false);
                    setParecerCancelamento('');
                },
            },
        );
    }

    function encaminharVistoria() {
        setErroEncaminhar(null);

        router.post(
            `/gestao/processos/${processo.id}/vistoria/encaminhar`,
            {
                motivo: motivoVistoria,
                setor_vistoria_id: Number(setorVistoria),
            },
            {
                preserveScroll: true,
                onStart: () => setVistoriaProcessing(true),
                onFinish: () => setVistoriaProcessing(false),
                onSuccess: () => {
                    setShowVistoria(false);
                    setMotivoVistoria('');
                    setSetorVistoria('');
                    setErroEncaminhar(null);
                },
                onError: (erros) => {
                    const mensagens = Object.values(erros).filter((mensagem): mensagem is string => typeof mensagem === 'string' && mensagem !== '');

                    setErroEncaminhar(mensagens[0] ?? 'Não foi possível encaminhar o processo à vistoria.');
                },
                onHttpException: () => {
                    setErroEncaminhar('Não foi possível encaminhar o processo à vistoria. Tente novamente.');

                    return false;
                },
                onNetworkError: () => {
                    setErroEncaminhar('Não foi possível encaminhar o processo à vistoria. Verifique a conexão e tente novamente.');
                },
            },
        );
    }

    function encaminharMalhaFina() {
        setErroMalhaFina(null);

        router.post(
            '/gestao/processos/malha-fina',
            {
                request_ids: [processo.id],
                motivo: motivoMalhaFina,
                sector_id: setorMalhaFina === '' ? null : Number(setorMalhaFina),
            },
            {
                preserveScroll: true,
                onStart: () => setMalhaFinaProcessing(true),
                onFinish: () => setMalhaFinaProcessing(false),
                onSuccess: () => {
                    setShowMalhaFina(false);
                    setMotivoMalhaFina('');
                    setSetorMalhaFina('');
                    setErroMalhaFina(null);
                },
                onError: (erros) => {
                    const mensagens = Object.values(erros).filter((mensagem): mensagem is string => typeof mensagem === 'string' && mensagem !== '');

                    setErroMalhaFina(mensagens[0] ?? 'Não foi possível encaminhar o processo à malha fina.');
                },
                onHttpException: () => {
                    setErroMalhaFina('Não foi possível encaminhar o processo à malha fina. Tente novamente.');

                    return false;
                },
                onNetworkError: () => {
                    setErroMalhaFina('Não foi possível encaminhar o processo à malha fina. Verifique a conexão e tente novamente.');
                },
            },
        );
    }

    function compararRevisoes() {
        setDiffErro(null);
        setDiffData(null);
        diff.transform(() => ({ de: diffDe, para: diffPara }));
        diff.get(`${fichaUrl}/diff?de=${diffDe}&para=${diffPara}`, {
            onSuccess: (resposta) => setDiffData(resposta),
            onError: () => setDiffErro('Revisão informada não encontrada.'),
            onHttpException: () => {
                setDiffErro('Revisão informada não encontrada.');

                return false;
            },
        });
    }

    function gerarResumo() {
        setResumoMensagem(null);
        setResumoStatus('solicitando');

        resumoHttp.post(`${fichaUrl}/gerar-resumo`, {
            onSuccess: (resposta) => {
                setResumoMensagem(resposta?.status ?? null);

                if (resposta?.despachou) {
                    setResumoStatus('aguardando');
                    router.reload({
                        only: ['sugestoesIa'],
                        onFinish: () => {
                            setResumoStatus((atual) => (atual === 'aguardando' ? 'idle' : atual));
                        },
                    });
                } else {
                    setResumoStatus('indisponivel');
                }
            },
            onHttpException: () => {
                setResumoStatus('indisponivel');
                setResumoMensagem('Não foi possível gerar o resumo agora. Tente novamente.');

                return false;
            },
        });
    }

    function pararPollMinuta() {
        if (minutaPollRef.current !== null) {
            window.clearInterval(minutaPollRef.current);
            minutaPollRef.current = null;
        }
    }

    function iniciarPollMinuta() {
        pararPollMinuta();
        let tentativas = 0;

        minutaPollRef.current = window.setInterval(() => {
            tentativas += 1;

            if (tentativas > MINUTA_POLL_MAX) {
                pararPollMinuta();

                return;
            }

            router.reload({ only: ['sugestoesIa'] });
        }, MINUTA_POLL_INTERVAL_MS);
    }

    function sugerirMinuta() {
        setMinutaMensagem(null);
        setMinutaStatus('solicitando');
        minutaBaselineRef.current = parecerSugeridos;

        minuta.post(`${fichaUrl}/sugerir-parecer`, {
            onSuccess: (resposta) => {
                setMinutaMensagem(resposta?.status ?? null);

                if (resposta?.despachou) {
                    setMinutaStatus('aguardando');
                    iniciarPollMinuta();
                } else {
                    setMinutaStatus('indisponivel');
                }
            },
            onHttpException: () => {
                setMinutaStatus('indisponivel');
                setMinutaMensagem('Não foi possível solicitar a minuta agora. Tente novamente.');

                return false;
            },
        });
    }

    /**
     * Copia a minuta sugerida para o editor do parecer como RASCUNHO editável
     * (client-side). Nada é decidido nem finalizado: o analista revisa, edita e
     * valida; o autosave persiste apenas o rascunho (RN-008). A IA nunca grava
     * decisão (RN-001) e a revisão finalizada permanece imutável (RN-003).
     */
    function aplicarMinutaAoParecer(minutaTexto: string) {
        setParecer((atual) => (atual.trim() === '' ? minutaTexto : `${atual}\n\n${minutaTexto}`));

        window.requestAnimationFrame(() => {
            document.getElementById('parecer-editor')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }

    function pararPollJustificativa() {
        if (justificativaPollRef.current !== null) {
            window.clearInterval(justificativaPollRef.current);
            justificativaPollRef.current = null;
        }
    }

    function iniciarPollJustificativa() {
        pararPollJustificativa();
        let tentativas = 0;

        justificativaPollRef.current = window.setInterval(() => {
            tentativas += 1;

            if (tentativas > MINUTA_POLL_MAX) {
                pararPollJustificativa();

                return;
            }

            router.reload({ only: ['sugestoesIa'] });
        }, MINUTA_POLL_INTERVAL_MS);
    }

    /**
     * Pede à IA a minuta da justificativa da atividade (regra SEDUR 22/09,
     * item 6): o botão só habilita DEPOIS da decisão do analista no CNAE — o
     * endpoint revalida (422 sem decisão). A minuta chega pela fila e preenche
     * o campo vazio (atuando), sempre editável; nunca decide.
     */
    function sugerirJustificativa(cnae: string) {
        setJustificativaMensagem((atual) => ({ ...atual, [cnae]: null }));
        setJustificativaStatus((atual) => ({ ...atual, [cnae]: 'solicitando' }));
        justificativaBaselineRef.current = justificativasSugeridas;

        justificativaIa.transform(() => ({ cnae }));
        justificativaIa.post(`${fichaUrl}/sugerir-justificativa`, {
            onSuccess: (resposta) => {
                setJustificativaMensagem((atual) => ({ ...atual, [cnae]: resposta?.status ?? null }));

                if (resposta?.despachou) {
                    setJustificativaStatus((atual) => ({ ...atual, [cnae]: 'aguardando' }));
                    iniciarPollJustificativa();
                } else {
                    setJustificativaStatus((atual) => ({ ...atual, [cnae]: 'indisponivel' }));
                }
            },
            onHttpException: (erro) => {
                const dados = erro?.data;
                const mensagem =
                    dados && typeof dados === 'object' && typeof (dados as { message?: unknown }).message === 'string'
                        ? ((dados as { message: string }).message)
                        : 'Não foi possível solicitar a sugestão agora. Tente novamente.';

                setJustificativaStatus((atual) => ({ ...atual, [cnae]: 'indisponivel' }));
                setJustificativaMensagem((atual) => ({ ...atual, [cnae]: mensagem }));

                return false;
            },
        });
    }

    /**
     * Aplica a minuta da IA ao campo da atividade como RASCUNHO editável
     * (client-side) — preenche o campo vazio; se já houver texto do analista,
     * acrescenta ao final. Nada é decidido nem finalizado.
     */
    function aplicarMinutaJustificativa(cnae: string, texto: string) {
        const indice = perCnae.findIndex((item) => item.cnae === cnae);

        if (indice < 0) {
            return;
        }

        const atual = (perCnae[indice].justificativa ?? '').trim();
        atualizarCnae(indice, { justificativa: atual === '' ? texto : `${atual}\n\n${texto}` });
    }

    // A minuta da justificativa chega de forma assíncrona (fila): quando a
    // contagem cresce além do baseline, aplica ao campo vazio do CNAE que
    // aguardava (atuando) e sinaliza pronta.
    useEffect(() => {
        const aguardando = Object.entries(justificativaStatus).find(([, status]) => status === 'aguardando');

        if (!aguardando || justificativasSugeridas <= justificativaBaselineRef.current) {
            return;
        }

        const [cnaeAguardando] = aguardando;
        const sugestao = (sugestoesIa ?? []).find(
            (item) => item.type === 'justificativa' && item.cnae === cnaeAguardando,
        );
        const texto = typeof sugestao?.output?.justificativa === 'string' ? sugestao.output.justificativa.trim() : '';
        const indice = perCnae.findIndex((item) => item.cnae === cnaeAguardando);

        if (texto !== '' && indice >= 0 && (perCnae[indice].justificativa ?? '').trim() === '') {
            atualizarCnae(indice, { justificativa: texto });
        }

        setJustificativaStatus((atual) => ({ ...atual, [cnaeAguardando]: 'pronta' }));
        pararPollJustificativa();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [justificativasSugeridas, justificativaStatus]);

    // A minuta chega de forma assíncrona (fila): quando a contagem de sugestões de
    // parecer cresce além do baseline, encerramos a sondagem e sinalizamos pronta.
    useEffect(() => {
        if (minutaStatus === 'aguardando' && parecerSugeridos > minutaBaselineRef.current) {
            setMinutaStatus('pronta');
            pararPollMinuta();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [parecerSugeridos, minutaStatus]);

    useEffect(() => {
        if (resumoStatus === 'aguardando' && resumosSugeridos > 0) {
            setResumoStatus('pronta');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [resumosSugeridos, resumoStatus]);

    useEffect(
        () => () => {
            pararPollMinuta();
        },
        [],
    );

    const condicionantesSugeridas = useMemo(() => {
        const conjunto = new Set<string>();

        for (const item of perCnae) {
            for (const condicionante of item.condicionantes ?? []) {
                if (condicionante.trim() !== '') {
                    conjunto.add(condicionante.trim());
                }
            }
        }

        return Array.from(conjunto);
    }, [perCnae]);

    const centro = centroDaGeometria(localizacao?.poligono) ?? centroDaGeometria(localizacao?.poligono_enderecamento);
    // Camadas memorizadas: a ficha re-renderiza a cada tecla e o mapa não precisa recalcular.
    const camadasDoMapa = useMemo(
        () => [
            ...(localizacao?.poligono ? [{ id: 'imovel', type: 'imovel', geojson: localizacao.poligono }] : []),
            ...(localizacao?.poligono_enderecamento
                ? [
                      {
                          id: 'enderecamento',
                          type: 'enderecamento',
                          geojson: localizacao.poligono_enderecamento,
                          estilo: ESTILO_ENDERECAMENTO,
                      },
                  ]
                : []),
        ],
        [localizacao?.poligono, localizacao?.poligono_enderecamento],
    );
    const temPoligono = centro !== null && (localizacao?.poligono != null || localizacao?.poligono_enderecamento != null);

    const vagasRequeridas = parking.vagas_requeridas;
    const vagasExigidas = parking.vagas_exigidas;
    const vagasConforme = vereditoEstacionamento({
        vagasRequeridas,
        vagasExigidas,
        embarqueRequerente: parking.area_embarque_requerente,
        embarqueExigida: parking.area_embarque_exigida,
    });

    return (
        <>
            <Head title={`Ficha de análise ${processo.protocol_number ?? ''}`.trim()} />
            <PageHeader
                title={`Ficha de análise ${processo.protocol_number ?? `#${processo.id}`}`}
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Processos', href: '/gestao/processos' },
                ]}
                actions={
                    <Badge color={editavel ? 'warning' : 'success'} size="sm">
                        {ficha.status_label} · revisão {ficha.revision}
                    </Badge>
                }
            />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href={`/gestao/processos/${processo.id}`}
                        className="inline-flex items-center gap-1.5 text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                    >
                        <ArrowRightIcon className="size-4 rotate-180" />
                        Voltar ao processo
                    </Link>

                    <div className="flex items-center gap-2 text-theme-xs text-gray-500 dark:text-gray-400">
                        {saveState === 'salvando' && <span>Salvando rascunho…</span>}
                        {saveState === 'salvo' && (
                            <span className="inline-flex items-center gap-1 text-success-600 dark:text-success-500">
                                <CheckCircleIcon className="size-4 fill-current" /> Rascunho salvo
                            </span>
                        )}
                        {saveState === 'erro' && (
                            <span className="inline-flex items-center gap-1 text-error-500">
                                <AlertIcon className="size-4 fill-current" /> Falha ao salvar
                            </span>
                        )}
                    </div>
                </div>

                {!editavel && (
                    <div className="flex items-start gap-3 rounded-xl border border-success-200 bg-success-50 p-4 dark:border-success-500/30 dark:bg-success-500/15">
                        <CheckCircleIcon className="size-5 shrink-0 fill-current text-success-500" />
                        <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                            Esta revisão está <strong>finalizada</strong> e é imutável (RN-003). Para reeditar, crie uma
                            nova revisão. {ficha.analyst && <>Responsável: {ficha.analyst}. </>}
                            {ficha.finalized_at && <>Finalizada em {formatarDataHora(ficha.finalized_at)}.</>}
                        </p>
                    </div>
                )}

                {!ficha.engine_available && (
                    <div className="flex items-start gap-3 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/15">
                        <AlertIcon className="size-5 shrink-0 fill-current text-warning-500" />
                        <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                            <strong>Modo manual:</strong> o sistema não pôde pré-analisar este processo (sem dado
                            confiável — ex.: zona urbanística pendente SEDUR). Os campos não têm sugestão automática;
                            a análise é integralmente humana, com fundamentação própria.
                        </p>
                    </div>
                )}

                {escritorioVirtual.gatilho && (
                    <div className="flex items-start gap-3 rounded-xl border border-brand-200 bg-brand-50 p-4 dark:border-brand-500/30 dark:bg-brand-500/15">
                        <AlertIcon className="size-5 shrink-0 fill-current text-brand-500" />
                        <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                            <strong>Gatilho — Sede de Escritório Virtual:</strong> o processo contém o CNAE gatilho
                            ({escritorioVirtual.cnae_gatilho}) e o requerente pediu para ser sede (RN-EV-01).{' '}
                            {escritorioVirtual.flag_analise_sede} Confirme o desfecho de sede no card da atividade —
                            ao deferir como sede, a inscrição imobiliária é travada para escritório virtual (RN-EV-03).
                        </p>
                    </div>
                )}

                <ResumoProcessoCard
                    processo={processo}
                    sugestoes={sugestoesIa ?? []}
                    iaFicha={iaFicha}
                    restricoes={[]}
                    status={resumoStatus}
                    mensagem={resumoMensagem}
                    gerando={resumoHttp.processing || resumoStatus === 'aguardando'}
                    onGerar={gerarResumo}
                    editavel={editavel}
                    onAplicarMinuta={aplicarMinutaAoParecer}
                />

                {desfecho && <DesfechoAnaliseCard desfecho={desfecho} />}

                {/* Topo no padrão legado SAPS (design 2026-07-22): Localização + Cadastro
                    Imobiliário à esquerda, Polígono à direita, e a faixa Dados
                    do TVL abaixo, antes das demais seções da ficha. */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader
                            title="Localização / Endereço Inscrição Imobiliária"
                            description="Endereço da solicitação e cadastro imobiliário. O bairro vem da inscrição imobiliária; a zona urbanística e as camadas geográficas aparecem no polígono."
                        />
                        <CardContent>
                            <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <DescItem label="CodLog">{valorOuTraco(localizacao?.cod_log)}</DescItem>
                                <DescItem label="Logradouro">{valorOuTraco(localizacao?.logradouro)}</DescItem>
                                <DescItem label="Nº Métrico">{valorOuTraco(localizacao?.numero_metrico)}</DescItem>
                                <DescItem label="Bairro">{valorOuTraco(localizacao?.bairro)}</DescItem>
                                <DescItem label="CEP">{cepMascarado(localizacao?.cep)}</DescItem>
                                <DescItem label="Ponto de Referência">
                                    {valorOuTraco(localizacao?.ponto_referencia)}
                                </DescItem>
                                <DescItem label="Tipo de imóvel (categoria)">{valorOuTraco(localizacao?.tipo_imovel)}</DescItem>
                                <DescItem label="Categoria de uso do lote">{valorOuTraco(localizacao?.categoria_uso_lote)}</DescItem>
                                <DescItem label="Padrão construtivo do lote">
                                    {valorOuTraco(localizacao?.padrao_construtivo_lote)}
                                </DescItem>
                            </dl>
                            <p className="mt-3 text-theme-xs text-gray-400 dark:text-gray-500">
                                Categoria e padrão vêm do lote no cadastro municipal e não decidem o fluxo.
                            </p>

                            <CadastroImobiliarioPanel cadastro={cadastroImobiliario} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader title="Polígono" description="Polígono cadastrado do imóvel e polígono do endereçamento SEDUR." />
                        <CardContent>
                            {temPoligono && centro ? (
                                <>
                                    <MapaDecisaoSection
                                        url={`/gestao/processos/${processo.id}/mapa-decisao`}
                                        fallback={
                                            <MapaSection
                                                lat={centro.lat}
                                                lng={centro.lng}
                                                zoom={17}
                                                draggable={false}
                                                enquadrar
                                                camadas={camadasDoMapa}
                                            />
                                        }
                                    />
                                    {localizacao?.poligono_enderecamento ? (
                                        <p className="mt-3 text-theme-xs text-gray-500 dark:text-gray-400">
                                            Contorno tracejado: polígono do endereçamento SEDUR.
                                        </p>
                                    ) : null}
                                    <p className="mt-3 text-theme-xs text-gray-500 dark:text-gray-400">
                                        Mapa &copy; OpenStreetMap (ODbL) ou Esri.
                                    </p>
                                </>
                            ) : (
                                <div className="flex items-start gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                    <MapPinIcon className="size-5 shrink-0 text-gray-400" />
                                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                        Polígono do imóvel não cadastrado neste processo.
                                    </p>
                                </div>
                            )}

                            <dl className="mt-4 grid grid-cols-2 gap-4">
                                <DescItem label="Zona">{valorOuTraco(localizacao?.zona)}</DescItem>
                                <DescItem label="Via">{valorOuTraco(localizacao?.via)}</DescItem>
                            </dl>

                            {podeEditarZonaVia && !somenteLeitura && (
                                <div className="mt-3">
                                    <Button size="xs" variant="outline" onClick={abrirZonaVia}>
                                        Editar zona e via
                                    </Button>
                                </div>
                            )}

                            {(!localizacao?.zona || !localizacao?.via) && (
                                <p className="mt-2 text-theme-xs text-gray-400 dark:text-gray-500">
                                    Zona urbanística e via oficiais seguem pendentes SEDUR (Quadro 10).
                                </p>
                            )}

                            <dl className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <DescItem label="Atividade está estabelecida em área pública?">
                                    {localizacao?.is_public_area === null || localizacao?.is_public_area === undefined
                                        ? '—'
                                        : localizacao.is_public_area
                                          ? 'Sim'
                                          : 'Não'}
                                </DescItem>
                                <div>
                                    <Label className="mb-1.5">Endereço correto?</Label>
                                    <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Endereço correto?">
                                        {[
                                            { valor: true, label: 'Sim' },
                                            { valor: false, label: 'Não' },
                                        ].map((opcao) => {
                                            const ativo = addressConfirmed === opcao.valor;

                                            return (
                                                <button
                                                    key={opcao.label}
                                                    type="button"
                                                    role="radio"
                                                    aria-checked={ativo}
                                                    disabled={!editavel}
                                                    onClick={() => setAddressConfirmed(opcao.valor)}
                                                    className={`rounded-lg px-3 py-1.5 text-theme-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-60 ${
                                                        ativo
                                                            ? 'bg-brand-500 text-white'
                                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10'
                                                    }`}
                                                >
                                                    {opcao.label}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            </dl>
                            <p className="mt-3 text-theme-xs text-gray-400 dark:text-gray-500">
                                Área pública é informada pelo requerente na solicitação; "Endereço correto?" é uma confirmação do
                                analista.
                            </p>

                            {localizacao?.camadas && (
                                <div className="mt-6 border-t border-gray-100 pt-6 dark:border-gray-800">
                                    <h4 className="text-theme-sm font-medium text-gray-800 dark:text-white/90">Camadas do imóvel</h4>
                                    <p className="mt-1 text-theme-xs text-gray-400 dark:text-gray-500">
                                        Elemento identificado em cada camada geográfica no ponto do imóvel.
                                    </p>
                                    <div className="mt-2">
                                        <CamadasImovelLista camadas={localizacao.camadas} somentePoligonais />
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader title="Dados da Viabilidade" description="Empresa e imóvel desta solicitação." />
                    <CardContent>
                        <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <DescItem label="Razão Social">{valorOuTraco(dadosTvl.razao_social)}</DescItem>
                            <DescItem label="Sede de Escritório Virtual?">
                                {dadosTvl.sede_escritorio_virtual ? 'Sim' : 'Não'}
                            </DescItem>
                            <DescItem label="Porte da Empresa">{valorOuTraco(dadosTvl.porte)}</DescItem>
                            <DescItem label="Tipo de Imóvel">{valorOuTraco(dadosTvl.tipo_imovel)}</DescItem>
                            <DescItem label="Categoria da Empresa">{valorOuTraco(dadosTvl.categoria_empresa)}</DescItem>
                            <DescItem label="Torre/Bloco/Ala">{valorOuTraco(dadosTvl.torre_bloco_ala)}</DescItem>
                            <DescItem label="Complemento">{valorOuTraco(dadosTvl.complemento)}</DescItem>
                        </dl>
                        <p className="mt-3 text-theme-xs text-gray-400 dark:text-gray-500">
                            O tipo de imóvel é o declarado no recebimento. A marcação de sede de escritório virtual é editada no
                            card da atividade CNAE gatilho, abaixo.
                        </p>
                    </CardContent>
                </Card>

                {recepcao && (
                    <Card>
                        <CardHeader
                            title="Declaração recebida"
                            description="Dados enviados no recebimento do processo. Não substituem a categoria de uso do lote."
                        />
                        <CardContent>
                            <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <RecepcaoRecebida recepcao={recepcao} />
                            </dl>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <div className="space-y-6">
                            <MinutaJustificativaCard
                                sugestoes={sugestoesIa ?? []}
                                editavel={editavel}
                                perCnae={perCnae}
                                onAplicar={aplicarMinutaJustificativa}
                            />
                            <AlertasIaCard sugestoes={sugestoesIa ?? []} />
                        </div>

                        {/* Motivo de Análise — só entra na tela quando o sistema
                            registrou motivo. Vazio, a caixa some (não polui). */}
                        {analysisReasons.length > 0 && (
                            <Card>
                                <CardHeader
                                    title="Motivo de Análise"
                                    description="Registrado automaticamente pelo sistema quando o processo exige análise humana. O analista não edita este campo."
                                />
                                <CardContent>
                                    <ul className="space-y-2">
                                        {analysisReasons.map((motivo, indice) => (
                                            <li
                                                key={`${motivo}-${indice}`}
                                                className="flex items-start justify-between gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-800"
                                            >
                                                <span className="text-theme-sm text-gray-700 dark:text-gray-300">{motivo}</span>
                                                <Badge color="light" size="sm">
                                                    Sistema
                                                </Badge>
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        )}

                        {comparacaoCnaes && (
                            <ComparacaoCnaesCard processoId={processo.id} comparacao={comparacaoCnaes} podeCorrigir={editavel} />
                        )}

                        {/* Enquadramento por CNAE (espelha a ficha SAPS) */}
                        <Card>
                            <CardHeader
                                title="Enquadramento por atividade (CNAE)"
                                actions={
                                    editavel && perCnae.length > 0 ? (
                                        <Checkbox
                                            label="Para Deferir todos os CNAEs"
                                            checked={deferirTodos}
                                            onChange={alternarDeferirTodos}
                                        />
                                    ) : undefined
                                }
                            />
                            <CardContent>
                                {perCnae.length === 0 ? (
                                    <EmptyState
                                        title="Sem CNAEs pré-analisados"
                                        description="Nenhuma atividade foi pré-preenchida pelo sistema para este processo."
                                    />
                                ) : (
                                    <ul className="space-y-5">
                                        {perCnae.map((item, indice) => {
                                            const escolhido = item.status_escolhido ?? null;
                                            const digitosCnae = item.cnae.replace(/\D/g, '');
                                            const opcoesCnae = enquadramentoOpcoes[item.cnae] ?? enquadramentoOpcoes[digitosCnae] ?? [];
                                            const enquadramentoAtual = `${item.codigo_louos ?? ''}|${item.codigo_tll ?? ''}`;
                                            const opcaoSelecionada = opcoesCnae.find(
                                                (opcao) => `${opcao.codigo_louos}|${opcao.codigo_tll ?? ''}` === enquadramentoAtual,
                                            );
                                            const riscoDaAtividade = opcaoSelecionada?.risco_label ?? item.risco_municipal ?? null;
                                            const atualForaDaPlanilha =
                                                item.codigo_louos != null &&
                                                !opcoesCnae.some(
                                                    (opcao) =>
                                                        `${opcao.codigo_louos}|${opcao.codigo_tll ?? ''}` ===
                                                        enquadramentoAtual,
                                                );

                                            return (
                                                <li
                                                    key={`${item.cnae}-${indice}`}
                                                    className="rounded-xl border-2 border-brand-200 p-4 dark:border-brand-500/40"
                                                >
                                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                                        <div>
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <span className="font-medium text-gray-800 dark:text-white/90">
                                                                    {item.cnae_formatado ?? item.cnae}
                                                                </span>
                                                                {item.is_primary && (
                                                                    <Badge color="info" size="sm">
                                                                        Principal
                                                                    </Badge>
                                                                )}
                                                                {item.descricao && (
                                                                    <span className="text-theme-sm text-gray-600 dark:text-gray-300">
                                                                        {item.descricao}
                                                                    </span>
                                                                )}
                                                            </div>
                                                            {(item.subgrupo_uso || item.grupo_uso || item.codigo_louos) && (
                                                                <p className="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
                                                                    Enquadramento:{' '}
                                                                    {[item.subgrupo_uso ?? item.grupo_uso, item.codigo_louos]
                                                                        .filter(Boolean)
                                                                        .join(' · ')}
                                                                </p>
                                                            )}
                                                        </div>
                                                        <div>
                                                            <Label className="mb-1.5">Decisão do analista</Label>
                                                            <StatusEscolhido
                                                                cnae={item.cnae}
                                                                valor={escolhido}
                                                                disabled={!editavel}
                                                                onChange={(status) =>
                                                                    atualizarCnae(indice, { status_escolhido: status })
                                                                }
                                                            />
                                                        </div>
                                                    </div>

                                                    {(item.quadros?.length ?? 0) > 0 && (
                                                        <QuadrosChecklist quadros={item.quadros ?? []} />
                                                    )}

                                                    {item.cnae.replace(/\D/g, '') ===
                                                        escritorioVirtual.cnae_gatilho.replace(/\D/g, '') && (
                                                        <div className="mt-3">
                                                            <Checkbox
                                                                label="Sede de Escritório Virtual"
                                                                checked={sedeEscritorioVirtual}
                                                                onChange={setSedeEscritorioVirtual}
                                                                disabled={!editavel}
                                                            />
                                                        </div>
                                                    )}

                                                    {(item.gatilhos?.length ?? 0) > 0 && (
                                                        <div className="mt-3">
                                                            <p className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                                                                Gatilhos
                                                            </p>
                                                            <div className="mt-1 flex flex-wrap gap-1.5">
                                                                {item.gatilhos?.map((gatilho, i) => (
                                                                    <Badge key={i} color="warning" size="sm">
                                                                        {gatilho}
                                                                    </Badge>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    )}

                                                    <div className="mt-3 grid items-start gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1.7fr)_minmax(0,0.8fr)_minmax(0,0.8fr)]">
                                                        <div className="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                                                            <p className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                                                                Pergunta: {item.pergunta_local?.pergunta ?? 'A atividade será desenvolvida no local?'}
                                                            </p>
                                                            <p
                                                                className={`mt-0.5 text-theme-xs ${
                                                                    item.pergunta_local && !item.pergunta_local.pendente
                                                                        ? 'text-gray-700 dark:text-gray-200'
                                                                        : 'text-gray-400 dark:text-gray-500'
                                                                }`}
                                                            >
                                                                Resposta:{' '}
                                                                {item.pergunta_local && !item.pergunta_local.pendente
                                                                    ? item.pergunta_local.resposta
                                                                    : 'Pendente — requerente ainda não respondeu esta pergunta no formulário de solicitação.'}
                                                            </p>
                                                        </div>
                                                        {editavel ? (
                                                            <div>
                                                                <Label htmlFor={`enquadramento-${indice}`} className="mb-1.5">
                                                                    Enquadramento (LOUOS × TLL)
                                                                </Label>
                                                                <OptionSelect
                                                                    id={`enquadramento-${indice}`}
                                                                    placeholder="Selecione o enquadramento…"
                                                                    value={
                                                                        enquadramentoAtual === '|' ? '' : enquadramentoAtual
                                                                    }
                                                                    onChange={(valor) => {
                                                                        const opcao = opcoesCnae.find(
                                                                            (o) =>
                                                                                `${o.codigo_louos}|${o.codigo_tll ?? ''}` ===
                                                                                valor,
                                                                        );

                                                                        if (!opcao) {
                                                                            return;
                                                                        }

                                                                        atualizarCnae(indice, {
                                                                            codigo_louos: opcao.codigo_louos,
                                                                            codigo_tll: opcao.codigo_tll,
                                                                            valor_tll: opcao.valor_tll,
                                                                        });
                                                                    }}
                                                                    options={[
                                                                        ...opcoesCnae.map((opcao) => ({
                                                                            value: `${opcao.codigo_louos}|${opcao.codigo_tll ?? ''}`,
                                                                            label: rotuloEnquadramento(opcao),
                                                                        })),
                                                                        ...(atualForaDaPlanilha
                                                                            ? [
                                                                                  {
                                                                                      value: enquadramentoAtual,
                                                                                      label: `${item.codigo_louos ?? ''}${
                                                                                          item.codigo_tll
                                                                                              ? ` · TLL ${item.codigo_tll}`
                                                                                              : ''
                                                                                      } (atual — fora da planilha vigente)`,
                                                                                  },
                                                                              ]
                                                                            : []),
                                                                    ]}
                                                                />
                                                            </div>
                                                        ) : (
                                                            <>
                                                                <DescItem label="Código LOUOS">
                                                                    {item.codigo_louos ?? (
                                                                        <span className="text-gray-400 dark:text-gray-500">
                                                                            Pendente — tabela oficial SEDUR não entregue
                                                                        </span>
                                                                    )}
                                                                </DescItem>
                                                                <DescItem label="Código TLL">
                                                                    {item.codigo_tll ?? (
                                                                        <span className="text-gray-400 dark:text-gray-500">
                                                                            Pendente — tabela oficial SEDUR não entregue
                                                                        </span>
                                                                    )}
                                                                </DescItem>
                                                            </>
                                                        )}
                                                        <DescItem label="Valor TLL">
                                                            {item.valor_tll != null && item.valor_tll !== '' ? (
                                                                <span>{formatarMoeda(item.valor_tll)}</span>
                                                            ) : (
                                                                <span className="text-gray-400 dark:text-gray-500">
                                                                    Pendente (tabela de taxas/DAM)
                                                                </span>
                                                            )}
                                                        </DescItem>
                                                        <DescItem label="Risco da atividade">
                                                            {riscoDaAtividade ?? (
                                                                <span className="text-gray-400 dark:text-gray-500">—</span>
                                                            )}
                                                        </DescItem>
                                                    </div>

                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        {/* Condicionantes */}
                        <Card>
                            <CardHeader
                                title="Condicionantes"
                                description="Obrigações que o requerente precisa cumprir para a atividade ficar permitida neste local. O que estiver nesta lista entra no parecer."
                            />
                            <CardContent>
                                <p className="mb-4 text-theme-sm text-gray-600 dark:text-gray-300">
                                    O motor marca sozinho quando o enquadramento traz condição (LOUOS, risco ou
                                    escritório virtual). Confirme as sugeridas ou altere só se discordar. Se não
                                    houver o que exigir, deixe em branco.
                                </p>
                                {condicionantesSugeridas.length > 0 && (
                                    <div className="mb-4 space-y-2">
                                        <p className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                                            Sugeridas pelo motor
                                        </p>
                                        {condicionantesSugeridas.map((sugerida) => (
                                            <Checkbox
                                                key={sugerida}
                                                label={sugerida}
                                                disabled={!editavel}
                                                checked={conditions.includes(sugerida)}
                                                onChange={(marcada) =>
                                                    marcada
                                                        ? adicionarCondicao(sugerida)
                                                        : setConditions((atual) =>
                                                              atual.filter((c) => c !== sugerida),
                                                          )
                                                }
                                            />
                                        ))}
                                    </div>
                                )}

                                {conditions.length > 0 ? (
                                    <ul className="space-y-2">
                                        {conditions.map((condicao, indice) => (
                                            <li
                                                key={`${condicao}-${indice}`}
                                                className="flex items-start justify-between gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-800"
                                            >
                                                <span className="text-theme-sm text-gray-700 dark:text-gray-300">
                                                    {condicao}
                                                </span>
                                                {editavel && (
                                                    <button
                                                        type="button"
                                                        onClick={() => removerCondicao(indice)}
                                                        aria-label={`Remover condicionante ${indice + 1}`}
                                                        className="shrink-0 text-error-500 transition hover:text-error-600"
                                                    >
                                                        <TrashIcon className="size-4.5" />
                                                    </button>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                        Nenhuma condicionante neste processo — o motor não identificou obrigação
                                        urbanística. O texto livre e a biblioteca servem só para acrescentar uma
                                        obrigação que o motor não sugeriu. Ao digitar, aparecem sugestões da
                                        biblioteca.
                                    </p>
                                )}

                                {editavel && (
                                    <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                                        <div className="relative flex-1">
                                            <Label htmlFor="nova-condicao">Adicionar condicionante (texto livre)</Label>
                                            <Input
                                                id="nova-condicao"
                                                type="text"
                                                role="combobox"
                                                autoComplete="off"
                                                aria-autocomplete="list"
                                                aria-controls="sugestoes-condicao"
                                                aria-expanded={sugestoesAbertas && sugestoesCondicao.length > 0}
                                                aria-activedescendant={
                                                    sugestoesAbertas && sugestaoAtiva >= 0
                                                        ? `sugestao-condicao-${sugestoesCondicao[sugestaoAtiva]?.id}`
                                                        : undefined
                                                }
                                                value={novaCondicao}
                                                placeholder="Descreva a condicionante…"
                                                onChange={(event) => {
                                                    setNovaCondicao(event.target.value);
                                                    setSugestaoAtiva(-1);
                                                    setSugestoesAbertas(true);
                                                }}
                                                onBlur={() => setSugestoesAbertas(false)}
                                                onKeyDown={(event) => {
                                                    const abertas = sugestoesAbertas && sugestoesCondicao.length > 0;

                                                    if (event.key === 'ArrowDown' && abertas) {
                                                        event.preventDefault();
                                                        setSugestaoAtiva((atual) => (atual + 1) % sugestoesCondicao.length);
                                                    } else if (event.key === 'ArrowUp' && abertas) {
                                                        event.preventDefault();
                                                        setSugestaoAtiva((atual) => (atual <= 0 ? sugestoesCondicao.length - 1 : atual - 1));
                                                    } else if (event.key === 'Escape') {
                                                        setSugestoesAbertas(false);
                                                    } else if (event.key === 'Enter') {
                                                        event.preventDefault();
                                                        const escolhida = abertas && sugestaoAtiva >= 0 ? sugestoesCondicao[sugestaoAtiva] : null;
                                                        adicionarCondicao(escolhida ? escolhida.content : novaCondicao);
                                                        setNovaCondicao('');
                                                        setSugestaoAtiva(-1);
                                                    }
                                                }}
                                            />
                                            {sugestoesAbertas && sugestoesCondicao.length > 0 && (
                                                <ul
                                                    id="sugestoes-condicao"
                                                    role="listbox"
                                                    aria-label="Sugestões da biblioteca"
                                                    className="absolute inset-x-0 top-full z-10 mt-1 max-h-64 overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900"
                                                >
                                                    {sugestoesCondicao.map((sugestao, indice) => (
                                                        <li
                                                            key={sugestao.id}
                                                            id={`sugestao-condicao-${sugestao.id}`}
                                                            role="option"
                                                            aria-selected={indice === sugestaoAtiva}
                                                            onMouseDown={(event) => {
                                                                event.preventDefault();
                                                                adicionarCondicao(sugestao.content);
                                                                setNovaCondicao('');
                                                                setSugestaoAtiva(-1);
                                                            }}
                                                            className={`cursor-pointer px-3 py-2 text-theme-sm text-gray-700 dark:text-gray-300 ${
                                                                indice === sugestaoAtiva ? 'bg-gray-100 dark:bg-white/5' : 'hover:bg-gray-50 dark:hover:bg-white/[0.03]'
                                                            }`}
                                                        >
                                                            {sugestao.content}
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                        </div>
                                        <div className="flex gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => {
                                                    adicionarCondicao(novaCondicao);
                                                    setNovaCondicao('');
                                                }}
                                            >
                                                Adicionar
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => setPickerParaCondicao(true)}
                                            >
                                                Da biblioteca
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Vagas */}
                        <Card>
                            <CardHeader
                                title="Vagas de estacionamento"
                                description="Compara as vagas e a área de embarque e desembarque informadas pelo requerente com o exigido pela norma."
                            />
                            <CardContent>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label htmlFor="vagas-requeridas">Vagas informadas (requerente)</Label>
                                        <Input
                                            id="vagas-requeridas"
                                            type="number"
                                            min={0}
                                            disabled={!editavel}
                                            value={vagasRequeridas ?? ''}
                                            onChange={(event) =>
                                                setParking((atual) => ({
                                                    ...atual,
                                                    vagas_requeridas:
                                                        event.target.value === '' ? null : Number(event.target.value),
                                                }))
                                            }
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="vagas-exigidas">Vagas exigidas (norma)</Label>
                                        <Input
                                            id="vagas-exigidas"
                                            type="number"
                                            min={0}
                                            disabled={!editavel}
                                            value={vagasExigidas ?? ''}
                                            onChange={(event) =>
                                                setParking((atual) => ({
                                                    ...atual,
                                                    vagas_exigidas:
                                                        event.target.value === '' ? null : Number(event.target.value),
                                                }))
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                                    <Checkbox
                                        label="Exige vistoria de vagas"
                                        disabled={!editavel}
                                        checked={parking.vistoria ?? false}
                                        onChange={(marcada) =>
                                            setParking((atual) => ({ ...atual, vistoria: marcada }))
                                        }
                                    />
                                    {vagasConforme === null ? (
                                        <Badge color="light" size="sm">
                                            Veredito pendente (informe as vagas)
                                        </Badge>
                                    ) : vagasConforme ? (
                                        <Badge color="success" size="sm">
                                            Imóvel conforme
                                        </Badge>
                                    ) : (
                                        <Badge color="error" size="sm">
                                            Imóvel não conforme
                                        </Badge>
                                    )}
                                </div>

                                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label htmlFor="area-embarque-requerente">
                                            Área para embarque e desembarque (requerente)
                                        </Label>
                                        <Select
                                            id="area-embarque-requerente"
                                            disabled={!editavel}
                                            placeholder="Selecione"
                                            value={parking.area_embarque_requerente ?? ''}
                                            onChange={(valor) =>
                                                setParking((atual) => ({
                                                    ...atual,
                                                    area_embarque_requerente:
                                                        valor === 'sim' || valor === 'nao' ? valor : null,
                                                }))
                                            }
                                            options={[
                                                { value: 'sim', label: 'Sim' },
                                                { value: 'nao', label: 'Não' },
                                            ]}
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="area-embarque-exigida">
                                            Área para embarque e desembarque (norma)
                                        </Label>
                                        <Select
                                            id="area-embarque-exigida"
                                            disabled={!editavel}
                                            placeholder="Selecione"
                                            value={parking.area_embarque_exigida ?? ''}
                                            onChange={(valor) =>
                                                setParking((atual) => ({
                                                    ...atual,
                                                    area_embarque_exigida:
                                                        valor === 'sim' || valor === 'nao' || valor === 'na' ? valor : null,
                                                }))
                                            }
                                            options={[
                                                { value: 'sim', label: 'Sim' },
                                                { value: 'nao', label: 'Não' },
                                                { value: 'na', label: 'Não se aplica' },
                                            ]}
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                    </div>

                    {/* Coluna lateral: abrigados, precedentes, ações (mapa/localização
                        já exibidos no topo da ficha — ver grid Localização | Polígono). */}
                    <div className="space-y-6">
                        {escritorioVirtual.is_sede && (
                            <AbrigadosPanel
                                inscricao={escritorioVirtual.inscricao}
                                abrigados={escritorioVirtual.abrigados}
                            />
                        )}

                        <PrecedentesPanel
                            carregando={precedentes.processing}
                            erro={precedentesErro}
                            dados={precedentesData}
                        />

                    </div>
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-3">
                                                <Card>
                            <CardHeader
                                title="Parecer técnico"
                                description="Em branco para o analista redigir — a fundamentação do sistema está na justificativa de cada atividade. Quando a IA está ligada, é possível pedir uma minuta para revisar."
                                actions={
                                    <div className="flex flex-wrap items-center gap-2">
                                        {editavel && (
                                            <>
                                                <Button
                                                    size="xs"
                                                    variant="outline"
                                                    onClick={sugerirMinuta}
                                                    disabled={!iaFicha.parecer_disponivel}
                                                    loading={minuta.processing || minutaStatus === 'aguardando'}
                                                >
                                                    Sugerir minuta (IA)
                                                </Button>
                                                <Button size="xs" variant="ghost" onClick={() => setPickerParaParecer(true)}>
                                                    Inserir texto-padrão
                                                </Button>
                                            </>
                                        )}
                                        <Button size="xs" variant="ghost" onClick={() => setParecerExpandido(true)}>
                                            Expandir
                                        </Button>
                                    </div>
                                }
                            />
                            <CardContent>
                                <Textarea
                                    id="parecer-editor"
                                    ampliavel
                                    rows={6}
                                    disabled={!editavel}
                                    placeholder="Redija o parecer técnico…"
                                    value={parecer}
                                    onChange={setParecer}
                                />

                                {editavel && !iaFicha.parecer_disponivel && (
                                    <p className="mt-2 text-theme-xs text-warning-600 dark:text-warning-500" role="status">
                                        {iaFicha.parecer_motivo ??
                                            'Sugestão de minuta indisponível. Redija o parecer manualmente.'}
                                    </p>
                                )}

                                {editavel && iaFicha.parecer_disponivel && minutaStatus !== 'idle' && (
                                    <p
                                        className={`mt-2 text-theme-xs ${
                                            minutaStatus === 'indisponivel'
                                                ? 'text-warning-600 dark:text-warning-500'
                                                : 'text-gray-500 dark:text-gray-400'
                                        }`}
                                        role="status"
                                        aria-live="polite"
                                    >
                                        {minutaStatus === 'solicitando' && 'Solicitando minuta à IA…'}
                                        {minutaStatus === 'aguardando' &&
                                            'Minuta solicitada — aguardando o processamento da IA. Ela aparecerá no “Resumo do processo”, no topo, como sugestão a revisar.'}
                                        {minutaStatus === 'pronta' &&
                                            'Minuta sugerida disponível no “Resumo do processo”, no topo — revise e use “Aplicar ao parecer”.'}
                                        {minutaStatus === 'indisponivel' &&
                                            (minutaMensagem ?? 'Sugestão de minuta indisponível no momento.')}
                                    </p>
                                )}

                                <p className="mt-2 text-theme-xs text-gray-400 dark:text-gray-500">
                                    A minuta da IA é apenas sugestão (HU-110): você a revisa, edita e valida. A IA não decide o
                                    desfecho nem finaliza a ficha (RN-001/RN-003).
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {!somenteLeitura && (
                    <div
                        role="region"
                        aria-label="Ações da ficha"
                        className="sticky bottom-0 z-30 -mx-4 border-t border-gray-200 bg-white/95 px-4 py-3 shadow-theme-lg backdrop-blur md:-mx-6 md:px-6 dark:border-gray-800 dark:bg-gray-900/95"
                    >
                        <div className="flex flex-wrap items-center justify-end gap-2">
                            <div className="relative">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="dropdown-toggle"
                                    onClick={() => setMaisAcoesAberto((atual) => !atual)}
                                    endIcon={
                                        <ChevronDownIcon
                                            className={`size-4 transition-transform ${maisAcoesAberto ? '' : 'rotate-180'}`}
                                        />
                                    }
                                >
                                    Mais ações
                                </Button>
                                <Dropdown
                                    isOpen={maisAcoesAberto}
                                    onClose={() => setMaisAcoesAberto(false)}
                                    className="bottom-full mt-0! mb-2 w-64 origin-bottom-right p-2"
                                >
                                    {processo.status === 'em_analise' && (
                                        <DropdownItem
                                            baseClassName={classeItemMenu}
                                            onClick={() => setShowPendencia(true)}
                                            onItemClick={() => setMaisAcoesAberto(false)}
                                        >
                                            Colocar em convite
                                        </DropdownItem>
                                    )}
                                    {processo.status === 'em_pendencia' && (
                                        <DropdownItem
                                            baseClassName={classeItemMenu}
                                            onClick={() => setShowCancelarConvite(true)}
                                            onItemClick={() => setMaisAcoesAberto(false)}
                                        >
                                            Cancelar convite
                                        </DropdownItem>
                                    )}
                                    {podeEncaminharVistoria && (
                                        <DropdownItem
                                            baseClassName={classeItemMenu}
                                            onClick={() => setShowVistoria(true)}
                                            onItemClick={() => setMaisAcoesAberto(false)}
                                        >
                                            Encaminhar para a vistoria
                                        </DropdownItem>
                                    )}
                                    {temFichaVistoria ? (
                                        podeConsultarVistoria && (
                                            <DropdownItem
                                                tag="a"
                                                href={`/gestao/processos/${processo.id}/vistoria/visualizar`}
                                                baseClassName={classeItemMenu}
                                                onItemClick={() => setMaisAcoesAberto(false)}
                                            >
                                                Visualizar ficha de vistoria
                                            </DropdownItem>
                                        )
                                    ) : (
                                        <DropdownItem
                                            baseClassName={classeItemMenu}
                                            onClick={() => setShowSemFichaVistoria(true)}
                                            onItemClick={() => setMaisAcoesAberto(false)}
                                        >
                                            Visualizar ficha de vistoria
                                        </DropdownItem>
                                    )}
                                    {ficha.revision > 1 && (
                                        <DropdownItem
                                            baseClassName={classeItemMenu}
                                            onClick={() => setShowDiff(true)}
                                            onItemClick={() => setMaisAcoesAberto(false)}
                                        >
                                            Comparar revisões
                                        </DropdownItem>
                                    )}
                                </Dropdown>
                            </div>

                            {editavel ? (
                                <>
                                    <Button onClick={() => salvarRascunho(true)} variant="destaque" size="sm">
                                        Salvar Ficha
                                    </Button>
                                    <Button onClick={() => setShowFinalizar(true)} size="sm">
                                        Finalizar Ficha de Análise
                                    </Button>
                                </>
                            ) : (
                                <>
                                    {podeConcluir && (
                                        <Button onClick={() => setShowDecidir(true)} size="sm">
                                            Decidir processo (conforme ficha)
                                        </Button>
                                    )}
                                    <Button onClick={novaRevisao} variant="outline" size="sm" loading={acao.processing}>
                                        Criar nova ficha
                                    </Button>
                                </>
                            )}

                            {podeConcluirAnalise && (
                                <Button
                                    onClick={() => {
                                        setErroConclusao(null);
                                        setShowConclusao(true);
                                    }}
                                    size="sm"
                                >
                                    Conclusão de Análise
                                </Button>
                            )}

                            {podeEncaminharChefia && fichaSalva && (
                                <Button onClick={() => setShowEncaminharChefia(true)} variant="claro" size="sm">
                                    Encaminhar para Chefia
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

            <Modal isOpen={showSemFichaVistoria} onClose={() => setShowSemFichaVistoria(false)} className="m-4 max-w-[480px] p-6 lg:p-8">
                    <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Sem ficha de vistoria</h4>
                    <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Este processo não possui ficha de vistoria.
                    </p>
                    <div className="mt-6 flex justify-end">
                        <Button size="sm" onClick={() => setShowSemFichaVistoria(false)}>
                            Entendi
                        </Button>
                    </div>
                </Modal>

            {/* Picker de textos-padrão */}
            {(pickerParaParecer || pickerParaCondicao) && (
                <TextosPadraoPicker
                    textos={textosPadrao}
                    onSelect={inserirTextoPadrao}
                    onClose={() => {
                        setPickerParaParecer(false);
                        setPickerParaCondicao(false);
                    }}
                />
            )}

            <Modal
                isOpen={parecerExpandido}
                onClose={() => setParecerExpandido(false)}
                ariaLabel="Parecer técnico"
                className="m-4 max-h-[90vh] w-full max-w-[1000px] overflow-y-auto p-6 lg:p-8"
            >
                <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Parecer técnico</h4>
                <textarea
                    aria-label="Parecer técnico"
                    rows={24}
                    value={parecer}
                    onChange={(event) => setParecer(event.target.value)}
                    maxLength={2000}
                    readOnly={!editavel}
                    placeholder="Redija o parecer técnico…"
                    className="mt-4 w-full resize-y rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                />
                <div className="mt-4 flex justify-end">
                    <Button size="sm" variant="outline" onClick={() => setParecerExpandido(false)}>
                        Fechar
                    </Button>
                </div>
            </Modal>

            <Modal
                isOpen={showEncaminharChefia}
                onClose={() => setShowEncaminharChefia(false)}
                ariaLabel="Encaminhar para Chefia"
                className="m-4 max-w-[480px] p-6 lg:p-8"
            >
                <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Encaminhar para Chefia?</h4>
                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    O processo vai para a aba Para conclusão da Caixa do Apoio e sai da sua Caixa de entrada. Quem
                    distribui é o Apoio.
                </p>
                <div className="mt-4">
                    <Label htmlFor="indicado-para-conclusao" className="mb-1.5">
                        Indicar quem deve concluir (opcional)
                    </Label>
                    <OptionSelect
                        id="indicado-para-conclusao"
                        placeholder="Sem indicação"
                        value={indicadoParaConclusao}
                        onChange={setIndicadoParaConclusao}
                        options={[
                            { value: '', label: 'Sem indicação' },
                            ...usuariosIndicaveis.map((usuario) => ({ value: String(usuario.id), label: usuario.name })),
                        ]}
                        hint="É só uma sugestão para o Apoio: o processo não vai direto para a pessoa indicada."
                    />
                </div>
                <div className="mt-6 flex justify-end gap-3">
                    <Button size="sm" variant="outline" onClick={() => setShowEncaminharChefia(false)}>
                        Cancelar
                    </Button>
                    <Button size="sm" onClick={encaminharParaChefia} loading={encaminhandoChefia}>
                        Encaminhar
                    </Button>
                </div>
            </Modal>

            <Modal
                isOpen={showZonaVia}
                onClose={fecharZonaVia}
                ariaLabel="Editar zona e via"
                className="m-4 max-w-[560px] p-6 lg:p-8"
            >
                <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Editar zona e via</h4>
                {previaZonaVia === null ? (
                    <>
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Escolha a zona e a classe de via da LOUOS. Antes de gravar, o sistema mostra o que o motor
                            vai recalcular.
                        </p>
                        <div className="mt-4 grid gap-4">
                            <div>
                                <Label htmlFor="zona-manual" className="mb-1.5">
                                    Zona
                                </Label>
                                <OptionSelect
                                    id="zona-manual"
                                    placeholder="Selecione a zona"
                                    value={zonaEscolhida}
                                    onChange={setZonaEscolhida}
                                    options={zonasDisponiveis.map((zona) => ({
                                        value: zona.codigo,
                                        label: zona.nome ? `${zona.codigo} — ${zona.nome}` : zona.codigo,
                                    }))}
                                />
                            </div>
                            <div>
                                <Label htmlFor="via-manual" className="mb-1.5">
                                    Via
                                </Label>
                                <OptionSelect
                                    id="via-manual"
                                    placeholder="Selecione a classe da via"
                                    value={viaEscolhida}
                                    onChange={setViaEscolhida}
                                    options={viasDisponiveis.map((via) => ({ value: via, label: via }))}
                                />
                            </div>
                            <div>
                                <Label htmlFor="justificativa-zona-via" className="mb-1.5" required>
                                    Justificativa
                                </Label>
                                <Textarea
                                    id="justificativa-zona-via"
                                    rows={3}
                                    value={justificativaZonaVia}
                                    onChange={setJustificativaZonaVia}
                                    placeholder="Por que a zona ou a via precisa ser alterada?"
                                />
                            </div>
                        </div>
                        {erroZonaVia && <p className="mt-3 text-sm text-error-600 dark:text-error-500">{erroZonaVia}</p>}
                        <div className="mt-6 flex justify-end gap-3">
                            <Button size="sm" variant="outline" onClick={fecharZonaVia}>
                                Cancelar
                            </Button>
                            <Button
                                size="sm"
                                onClick={() => enviarZonaVia('previa')}
                                loading={zonaViaHttp.processing}
                                disabled={justificativaZonaVia.trim().length < 5}
                            >
                                Ver o que muda
                            </Button>
                        </div>
                    </>
                ) : (
                    <>
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Ao confirmar, o enquadramento e a decisão do motor desta ficha são recalculados. A
                            justificativa, quem alterou e a data e hora ficam registrados na Tramitação e no log.
                        </p>
                        <dl className="mt-4 grid grid-cols-2 gap-4">
                            <DescItem label="Zona">
                                {valorOuTraco(previaZonaVia.antes.zona)} → {valorOuTraco(previaZonaVia.depois.zona)}
                            </DescItem>
                            <DescItem label="Via">
                                {valorOuTraco(previaZonaVia.antes.via)} → {valorOuTraco(previaZonaVia.depois.via)}
                            </DescItem>
                        </dl>
                        <div className="mt-4">
                            <p className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                Decisão do motor por atividade
                            </p>
                            {!previaZonaVia.motor_disponivel ? (
                                <p className="mt-2 text-sm text-warning-600 dark:text-warning-500">
                                    O motor não respondeu agora; não foi possível simular. A alteração ainda pode ser gravada.
                                </p>
                            ) : (
                                <ul className="mt-2 space-y-2">
                                    {previaZonaVia.mudancas.map((mudanca) => (
                                        <li
                                            key={mudanca.cnae}
                                            className="rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-800"
                                        >
                                            <span className="font-medium text-gray-800 dark:text-white/90">{mudanca.cnae}</span>
                                            <span className="ml-2 text-gray-600 dark:text-gray-300">
                                                {mudanca.mudou
                                                    ? `${mudanca.antes ?? '—'} → ${mudanca.depois}`
                                                    : `sem mudança (${mudanca.depois})`}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                        {erroZonaVia && <p className="mt-3 text-sm text-error-600 dark:text-error-500">{erroZonaVia}</p>}
                        <div className="mt-6 flex justify-end gap-3">
                            <Button size="sm" variant="outline" onClick={() => setPreviaZonaVia(null)}>
                                Voltar
                            </Button>
                            <Button size="sm" onClick={() => enviarZonaVia('alterar')} loading={zonaViaHttp.processing}>
                                Confirmar alteração
                            </Button>
                            <Button size="sm" variant="ghost" onClick={fecharZonaVia}>
                                Cancelar
                            </Button>
                        </div>
                    </>
                )}
            </Modal>

            <ConfirmDialog
                isOpen={showFinalizar}
                variant="info"
                title="Finalizar a ficha de análise?"
                description={
                    <>
                        A ficha fica bloqueada para edição e só poderá ser consultada. Para novos registros, abra uma nova
                        ficha: ela vem preenchida com os dados desta. A finalização não conclui o processo.
                        {erroAcao && (
                            <span className="mt-2 block font-medium text-error-600 dark:text-error-500">
                                {erroAcao}
                            </span>
                        )}
                    </>
                }
                confirmLabel="Finalizar ficha"
                processing={acao.processing}
                onConfirm={finalizarFicha}
                onClose={() => {
                    setShowFinalizar(false);
                    setErroAcao(null);
                }}
            />

            <ConfirmDialog
                isOpen={showDecidir}
                variant="warning"
                title="Concluir a decisão do processo?"
                description={
                    <>
                        O resultado (deferir/indeferir) é construído a partir da ficha finalizada e o processo é
                        encerrado. A ação é auditada e dispara as comunicações cabíveis.
                        {processo.renovacao && (
                            <span className="mt-3 flex flex-col gap-3 text-left font-normal text-gray-700 dark:text-gray-300">
                                <span>
                                    Viabilidade {processo.tvl_numero ?? '—'}. A renovação não gera número novo: informe o tipo
                                    liberado e a nova validade.
                                </span>
                                <label className="flex min-h-11 items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={condicionantesAtendidos}
                                        onChange={(event) => setCondicionantesAtendidos(event.target.checked)}
                                        className="size-4 rounded border-gray-300 bg-white scheme-light accent-brand-500 dark:border-gray-400 dark:bg-gray-900 dark:scheme-dark"
                                    />
                                    Condicionantes da viabilidade provisória atendidos
                                </label>
                                <div className="flex flex-col gap-1 text-sm">
                                    <span>Tipo liberado</span>
                                    <Select
                                        value={tvlKind}
                                        onChange={(valor) => setTvlKind(valor as 'provisoria' | 'definitiva')}
                                        options={[
                                            { value: 'provisoria', label: 'Provisória' },
                                            { value: 'definitiva', label: 'Definitiva' },
                                        ]}
                                    />
                                </div>
                                <label className="flex flex-col gap-1 text-sm">
                                    Nova data de validade
                                    <IsoDateInput
                                        value={tvlValidUntil}
                                        onChange={(valor) => setTvlValidUntil(valor)}
                                    />
                                </label>
                            </span>
                        )}
                        {erroAcao && (
                            <span className="mt-2 block font-medium text-error-600 dark:text-error-500">
                                {erroAcao}
                            </span>
                        )}
                    </>
                }
                confirmLabel="Decidir"
                onConfirm={decidirProcesso}
                onClose={() => {
                    setShowDecidir(false);
                    setErroAcao(null);
                }}
            />

            <Modal isOpen={showConclusao} onClose={() => setShowConclusao(false)} className="m-4 max-h-[90vh] max-w-[560px] overflow-y-auto p-6 lg:p-8">
                    <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Conclusão de Análise</h4>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Deferido e indeferido seguem a ficha finalizada. Desativado, cassado e revogado encerram o
                        processo — inclusive um já deferido. Processo finalizado aceita qualquer outro resultado.
                    </p>
                    <div className="mt-4">
                        <ConclusaoAnaliseForm
                            resultadoId="resultado-conclusao"
                            resultado={resultadoConclusao}
                            onResultado={(valor) => {
                                setResultadoConclusao(valor);
                                setDivergenciaConclusao(null);
                                if (valor !== 'deferido') {
                                    setTipoDeferimento('');
                                    setValidadeMeses('');
                                    setValidadeData('');
                                }
                            }}
                            opcoes={conclusaoOpcoes}
                            tipo={tipoDeferimento}
                            onTipo={
                                processo.renovacao
                                    ? undefined
                                    : (valor) => {
                                          setTipoDeferimento(valor);
                                          if (valor === 'definitiva') {
                                              setValidadeMeses('');
                                              setValidadeData('');
                                          }
                                      }
                            }
                            validadeMeses={validadeMeses}
                            onValidadeMeses={setValidadeMeses}
                            validadeData={validadeData}
                            onValidadeData={setValidadeData}
                            erroValidadeData={errosTipo.validadeData}
                            erroTipo={errosTipo.tipo}
                            erroValidade={errosTipo.validade}
                            parecer={parecerConclusao}
                            onParecer={setParecerConclusao}
                            parecerDaFicha={parecer}
                            erro={erroConclusao}
                            divergencia={divergenciaConclusao}
                            onConfirmarDivergencia={() => concluirAnalise(true)}
                            processing={conclusaoProcessing}
                            onConfirm={() => concluirAnalise()}
                            onCancel={() => setShowConclusao(false)}
                        />
                    </div>
                </Modal>

            <Modal isOpen={showPendencia} onClose={() => setShowPendencia(false)} className="m-4 max-h-[90vh] max-w-[560px] overflow-y-auto p-6 lg:p-8">
                    <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Colocar em convite</h4>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        O processo vai para “em convite” e o requerente é notificado para complementar.
                    </p>
                    <div className="mt-4">
                        <Label htmlFor="descricao-pendencia" required>
                            Descrição do convite
                        </Label>
                        <Textarea
                            id="descricao-pendencia"
                            rows={4}
                            placeholder="Descreva o que falta…"
                            value={descricaoPendencia}
                            onChange={setDescricaoPendencia}
                        />
                    </div>
                    {erroPendencia && (
                        <p role="alert" className="mt-3 text-sm text-error-600 dark:text-error-400">
                            {erroPendencia}
                        </p>
                    )}
                    <div className="mt-6 flex items-center justify-end gap-3">
                        <Button variant="outline" size="sm" onClick={() => setShowPendencia(false)}>
                            Cancelar
                        </Button>
                        <Button
                            size="sm"
                            onClick={abrirPendencia}
                            disabled={descricaoPendencia.trim() === ''}
                            loading={pendenciaProcessing}
                        >
                            Colocar em convite
                        </Button>
                    </div>
                </Modal>

            <Modal isOpen={showCancelarConvite} onClose={() => setShowCancelarConvite(false)} className="m-4 max-h-[90vh] max-w-[560px] overflow-y-auto p-6 lg:p-8">
                    <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Cancelar convite</h4>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        O convite é cancelado e a análise reaberta. Registre o parecer com o motivo do cancelamento.
                    </p>
                    <div className="mt-4">
                        <Label htmlFor="parecer-cancelamento" required>
                            Parecer (motivo do cancelamento)
                        </Label>
                        <Textarea
                            id="parecer-cancelamento"
                            rows={4}
                            placeholder="Explique por que o convite está sendo cancelado…"
                            value={parecerCancelamento}
                            onChange={setParecerCancelamento}
                        />
                    </div>
                    <div className="mt-6 flex items-center justify-end gap-3">
                        <Button variant="outline" size="sm" onClick={() => setShowCancelarConvite(false)}>
                            Voltar
                        </Button>
                        <Button
                            size="sm"
                            onClick={cancelarConvite}
                            disabled={parecerCancelamento.trim() === ''}
                            loading={cancelarConviteProcessing}
                        >
                            Cancelar convite
                        </Button>
                    </div>
                </Modal>

            <Modal
                    isOpen={showVistoria}
                    onClose={() => {
                        setShowVistoria(false);
                        setErroEncaminhar(null);
                    }}
                    className="m-4 max-h-[90vh] max-w-[560px] overflow-y-auto p-6 lg:p-8"
                >
                    <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Encaminhar para a vistoria</h4>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        O processo vai para a caixa do setor de vistoria, sem responsável, para o apoio distribuir a
                        um vistoriador — e o status de análise muda para Vistoriar. Quando a ficha for concluída, o
                        processo retorna automaticamente para você. Informe o parecer (obrigatório).
                    </p>
                    <div className="mt-4">
                        <Label htmlFor="motivo-vistoria" required>
                            Parecer
                        </Label>
                        <Textarea
                            id="motivo-vistoria"
                            rows={3}
                            placeholder="Parecer do encaminhamento…"
                            value={motivoVistoria}
                            onChange={setMotivoVistoria}
                        />
                    </div>
                    <div className="mt-4">
                        <Label htmlFor="setor-vistoria" className="mb-1.5" required>
                            Setor de vistoria
                        </Label>
                        <Select
                            id="setor-vistoria"
                            value={setorVistoria}
                            onChange={setSetorVistoria}
                            placeholder="Selecione o setor de vistoria"
                            options={setores.map((setor) => ({ value: String(setor.id), label: setor.name }))}
                        />
                    </div>
                    {erroEncaminhar && (
                        <p className="mt-4 text-sm font-medium text-error-600 dark:text-error-500" role="alert">
                            {erroEncaminhar}
                        </p>
                    )}
                    <div className="mt-6 flex items-center justify-end gap-3">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                setShowVistoria(false);
                                setErroEncaminhar(null);
                            }}
                        >
                            Cancelar
                        </Button>
                        <Button
                            size="sm"
                            onClick={encaminharVistoria}
                            disabled={motivoVistoria.trim() === '' || setorVistoria === ''}
                            loading={vistoriaProcessing}
                        >
                            Encaminhar
                        </Button>
                    </div>
                </Modal>

            <Modal
                    isOpen={showMalhaFina}
                    onClose={() => {
                        setShowMalhaFina(false);
                        setErroMalhaFina(null);
                    }}
                    className="m-4 max-h-[90vh] max-w-[560px] overflow-y-auto p-6 lg:p-8"
                >
                    <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Encaminhar para a malha fina</h4>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        A malha fina é uma caixa à parte, para alterar o processo. Não muda o status da análise nem
                        gera número novo. Informe o motivo (obrigatório).
                    </p>
                    <div className="mt-4">
                        <Label htmlFor="motivo-malha-fina" required>
                            Motivo
                        </Label>
                        <Textarea
                            id="motivo-malha-fina"
                            rows={3}
                            placeholder="Motivo do encaminhamento…"
                            value={motivoMalhaFina}
                            onChange={setMotivoMalhaFina}
                        />
                    </div>
                    <div className="mt-4">
                        <Label htmlFor="setor-malha-fina" className="mb-1.5">
                            Setor de tramitação
                        </Label>
                        <Select
                            id="setor-malha-fina"
                            value={setorMalhaFina}
                            onChange={setSetorMalhaFina}
                            placeholder="Manter o setor atual"
                            options={[
                                { value: '', label: 'Manter o setor atual' },
                                ...setores.map((setor) => ({ value: String(setor.id), label: setor.name })),
                            ]}
                        />
                    </div>
                    {erroMalhaFina && (
                        <p className="mt-4 text-sm font-medium text-error-600 dark:text-error-500" role="alert">
                            {erroMalhaFina}
                        </p>
                    )}
                    <div className="mt-6 flex items-center justify-end gap-3">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                setShowMalhaFina(false);
                                setErroMalhaFina(null);
                            }}
                        >
                            Cancelar
                        </Button>
                        <Button
                            size="sm"
                            onClick={encaminharMalhaFina}
                            disabled={motivoMalhaFina.trim() === ''}
                            loading={malhaFinaProcessing}
                        >
                            Encaminhar
                        </Button>
                    </div>
                </Modal>

            <Modal isOpen={showDiff} onClose={() => setShowDiff(false)} className="m-4 max-h-[90vh] max-w-[640px] overflow-y-auto p-6 lg:p-8">
                    <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Comparar revisões</h4>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Mostra apenas o que mudou entre as revisões (RN-007).
                    </p>
                    <div className="mt-4 flex flex-wrap items-end gap-3">
                        <div className="w-28">
                            <Label htmlFor="diff-de">De (revisão)</Label>
                            <Input
                                id="diff-de"
                                type="number"
                                min={1}
                                max={ficha.revision}
                                value={diffDe}
                                onChange={(event) => setDiffDe(Number(event.target.value))}
                            />
                        </div>
                        <div className="w-28">
                            <Label htmlFor="diff-para">Para (revisão)</Label>
                            <Input
                                id="diff-para"
                                type="number"
                                min={1}
                                max={ficha.revision}
                                value={diffPara}
                                onChange={(event) => setDiffPara(Number(event.target.value))}
                            />
                        </div>
                        <Button size="sm" onClick={compararRevisoes} loading={diff.processing}>
                            Comparar
                        </Button>
                    </div>

                    {diffErro && <FieldError>{diffErro}</FieldError>}

                    {diffData && <DiffView diff={diffData.diff} />}
                </Modal>
        </>
    );
}

function DesfechoAnaliseCard({ desfecho }: { desfecho: DesfechoAnalise }) {
    return (
        <Card>
            <CardContent>
                <p
                    className={`text-2xl font-semibold tracking-tight ${
                        desfecho.tom === 'deferida' ? 'text-success-600 dark:text-success-500' : 'text-error-600 dark:text-error-500'
                    }`}
                >
                    {desfecho.titulo}
                </p>
            </CardContent>
        </Card>
    );
}

/** Skeleton da prop deferida de sugestões de IA (resumo HU-109 + alertas HU-107) enquanto carrega sob demanda. */
function SugestoesIaSkeleton() {
    return (
        <div className="space-y-6">
            <Card>
                <CardHeader title="Resumo" description="Carregando o resumo da IA…" />
                <CardContent>
                    <div className="space-y-3" aria-hidden="true">
                        <div className="h-4 w-1/2 animate-pulse rounded bg-gray-100 dark:bg-white/[0.06]" />
                        <div className="h-16 w-full animate-pulse rounded bg-gray-100 dark:bg-white/[0.06]" />
                    </div>
                    <p className="sr-only">Carregando o resumo de inteligência artificial.</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader title="Alertas de IA (sugestão — revise)" description="Carregando sugestões da IA…" />
                <CardContent>
                    <div className="space-y-3" aria-hidden="true">
                        <div className="h-4 w-2/3 animate-pulse rounded bg-gray-100 dark:bg-white/[0.06]" />
                        <div className="h-20 w-full animate-pulse rounded bg-gray-100 dark:bg-white/[0.06]" />
                    </div>
                    <p className="sr-only">Carregando alertas de inteligência artificial.</p>
                </CardContent>
            </Card>
        </div>
    );
}

function destinoIndisponibilidadeIa(motivo: string | null): { href: string; rotulo: string } {
    if (motivo?.includes('parâmetros')) {
        return { href: '/gestao/parametros?grupo=ia', rotulo: 'Abrir Parâmetros' };
    }

    return { href: '/gestao/config-ia', rotulo: 'Abrir Configuração de IA' };
}

/**
 * Card "Resumo do processo" (HU-109): apresenta a síntese gerada pela IA
 * (resumo + pontos-chave + fonte) no TOPO da ficha, sempre marcada como
 * "sugestão — revise". APENAS LEITURA — apoia a leitura do analista, não decide
 * nem antecipa o desfecho (AI-SPEC Failure Mode #1). Lê do mesmo ledger
 * ai_suggestions (prop deferida), filtrando o tipo resumo_processo; mostra a
 * síntese mais recente primeiro (o controller ordena por id desc).
 */
function ResumoProcessoCard({
    processo,
    sugestoes,
    iaFicha,
    restricoes,
    status,
    mensagem,
    gerando,
    onGerar,
    editavel,
    onAplicarMinuta,
}: {
    processo: Processo;
    sugestoes: SugestaoIa[];
    editavel: boolean;
    onAplicarMinuta: (minuta: string) => void;
    iaFicha: IaFicha;
    restricoes: string[];
    status: 'idle' | 'solicitando' | 'aguardando' | 'pronta' | 'indisponivel' | 'timeout';
    mensagem: string | null;
    gerando: boolean;
    onGerar: () => void;
}) {
    const resumos = sugestoes.filter((sugestao) => sugestao.type === 'resumo_processo');
    const minutas = sugestoes.filter((sugestao) => sugestao.type === 'parecer');
    const podeManterIa = usePage<SharedProps>().props.auth.permissions.includes('manter-config-ia');
    const destinoIa = destinoIndisponibilidadeIa(iaFicha.resumo_motivo);

    return (
        <Card>
            <CardHeader
                title="Resumo do processo"
                description="Identificação do processo e leitura de apoio da IA. A IA sugere, não decide nem antecipa o desfecho."
                actions={
                    resumos.length === 0 ? (
                        <Button
                            size="xs"
                            variant="outline"
                            onClick={onGerar}
                            disabled={!iaFicha.resumo_disponivel}
                            loading={gerando}
                        >
                            Gerar resumo
                        </Button>
                    ) : undefined
                }
            />
            <CardContent>
                <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <DescItem label="Protocolo BAP">{valorOuTraco(processo.bap)}</DescItem>
                    <DescItem label="Processo">{valorOuTraco(processo.protocol_number)}</DescItem>
                    <DescItem label="Serviço">
                        {processo.servicos.length === 0 ? (
                            '—'
                        ) : (
                            <ul className="space-y-0.5">
                                {processo.servicos.map((servico) => (
                                    <li key={servico}>{servico}</li>
                                ))}
                            </ul>
                        )}
                    </DescItem>
                    <DescItem label="Empresa">{valorOuTraco(processo.empresa)}</DescItem>
                </dl>

                <div className="mt-5 border-t border-gray-100 pt-5 dark:border-gray-800">
                <WhenVisible data="sugestoesIa" buffer={200} fallback={<SugestoesIaSkeleton />}>
                {restricoes.length > 0 && (
                    <ul className="mb-4 space-y-2" aria-label="Restrições do processo">
                        {restricoes.map((restricao) => (
                            <li
                                key={restricao}
                                className="rounded-lg border border-gray-200 p-3 text-theme-sm text-gray-700 dark:border-gray-800 dark:text-gray-300"
                            >
                                {restricao}
                            </li>
                        ))}
                    </ul>
                )}
                {resumos.length === 0 ? (
                    <div className="space-y-2">
                        {status === 'aguardando' || status === 'solicitando' ? (
                            <p className="text-theme-sm text-gray-500 dark:text-gray-400" role="status" aria-live="polite">
                                Gerando resumo com a IA. A síntese aparece aqui para revisão.
                            </p>
                        ) : status === 'timeout' || status === 'indisponivel' ? (
                            <p className="text-theme-sm text-warning-600 dark:text-warning-500" role="status">
                                {mensagem ?? 'A IA não gerou o resumo. Clique em “Gerar resumo” para tentar de novo.'}
                            </p>
                        ) : iaFicha.resumo_disponivel ? (
                            <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                Nenhum resumo ainda. Clique em “Gerar resumo” para solicitar à IA.
                            </p>
                        ) : (
                            <p className="text-theme-sm text-warning-600 dark:text-warning-500" role="status">
                                {iaFicha.resumo_motivo ?? 'Resumo de IA indisponível neste ambiente.'}
                                {podeManterIa ? (
                                    <>
                                        {' '}
                                        <Link href={destinoIa.href} className="underline underline-offset-2">
                                            {destinoIa.rotulo}
                                        </Link>
                                    </>
                                ) : (
                                    ' Peça a um administrador para ligar a função e cadastrar o provedor.'
                                )}
                            </p>
                        )}
                    </div>
                ) : (
                    <ul className="space-y-4" aria-label="Resumo do processo sugerido pela IA">
                        {resumos.map((sugestao) => {
                            const resumo = typeof sugestao.output.resumo === 'string' ? sugestao.output.resumo : '';
                            const pontos = Array.isArray(sugestao.output.pontos_chave) ? sugestao.output.pontos_chave : [];
                            const fonte = typeof sugestao.output.fonte === 'string' ? sugestao.output.fonte : null;

                            return (
                                <li key={sugestao.id}>
                                    <details
                                        open
                                        className="rounded-xl border border-brand-200 bg-brand-50 p-4 dark:border-brand-500/30 dark:bg-brand-500/10"
                                    >
                                    <summary className="flex cursor-pointer flex-wrap items-center justify-between gap-2">
                                        <Badge color="info" size="sm">
                                            Sugestão — revise
                                        </Badge>
                                        <Badge color={sugestao.status === 'escalada_humano' ? 'error' : 'light'} size="sm">
                                            {sugestao.status_label}
                                        </Badge>
                                    </summary>

                                    {resumo === '' ? (
                                        <p className="mt-3 text-theme-sm text-gray-600 dark:text-gray-300">
                                            A IA não retornou texto de resumo nesta geração.
                                        </p>
                                    ) : (
                                        <p className="mt-3 text-theme-sm whitespace-pre-line text-gray-800 dark:text-white/90">
                                            {resumo}
                                        </p>
                                    )}

                                    {pontos.length > 0 && (
                                        <div className="mt-3">
                                            <p className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                                Pontos de atenção
                                            </p>
                                            <ul className="mt-1 list-inside list-disc text-theme-sm text-gray-700 dark:text-gray-300">
                                                {pontos.map((ponto, indice) => (
                                                    <li key={indice}>{ponto}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}

                                    {fonte && (
                                        <p className="mt-3 text-theme-xs text-gray-500 dark:text-gray-400">
                                            Fonte: {fonte}
                                        </p>
                                    )}
                                    </details>
                                </li>
                            );
                        })}
                    </ul>
                )}

                {minutas.length > 0 && (
                    <div className="mt-4 space-y-3" aria-label="Minuta de parecer sugerida pela IA">
                        {minutas.map((sugestao) => {
                            const minutaTexto = typeof sugestao.output.minuta === 'string' ? sugestao.output.minuta : '';

                            if (minutaTexto === '') {
                                return null;
                            }

                            return (
                                <details
                                    key={sugestao.id}
                                    className="rounded-xl border border-brand-200 bg-brand-50 p-4 dark:border-brand-500/30 dark:bg-brand-500/10"
                                >
                                    <summary className="cursor-pointer text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                        Minuta de parecer (sugestão, revise)
                                    </summary>
                                    <p className="mt-3 text-theme-sm whitespace-pre-line text-gray-800 dark:text-white/90">{minutaTexto}</p>
                                    {editavel && (
                                        <div className="mt-3">
                                            <Button size="sm" variant="outline" onClick={() => onAplicarMinuta(minutaTexto)}>
                                                Aplicar ao parecer
                                            </Button>
                                        </div>
                                    )}
                                </details>
                            );
                        })}
                    </div>
                )}

                <p className="mt-4 text-theme-xs text-gray-400 dark:text-gray-500">
                    Apoio à leitura, sempre revisável — não substitui a análise nem antecipa a decisão (RN-001/RN-004).
                </p>
                </WhenVisible>
                </div>
            </CardContent>
        </Card>
    );
}

/**
 * Card "Justificativa da atividade" (regra SEDUR 22/09, item 6): exibe as
 * minutas de justificativa sugeridas pela IA por CNAE, sempre marcadas como
 * "sugestão — revise". A ação "Aplicar à justificativa" copia o texto para o
 * campo da atividade (client-side, editável) — NUNCA decide nem finaliza. A
 * sugestão só existe quando o analista já decidiu o enquadramento daquela
 * atividade (a regra do botão, revalidada no endpoint).
 */
function MinutaJustificativaCard({
    sugestoes,
    editavel,
    perCnae,
    onAplicar,
}: {
    sugestoes: SugestaoIa[];
    editavel: boolean;
    perCnae: PerCnae[];
    onAplicar: (cnae: string, texto: string) => void;
}) {
    const minutas = sugestoes.filter((sugestao) => sugestao.type === 'justificativa');

    if (minutas.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader
                title="Justificativa da atividade (IA — sugestão, revise)"
                description="Minuta de justificativa proposta pela IA conforme a SUA decisão na atividade, com base no enquadramento do motor. É apoio, não decisão: revise, edite e valide."
            />
            <CardContent>
                <ul className="space-y-4" aria-label="Justificativas sugeridas pela IA">
                    {minutas.map((sugestao) => {
                        const minutaTexto =
                            typeof sugestao.output.justificativa === 'string' ? sugestao.output.justificativa : '';
                        const fundamentacao =
                            typeof sugestao.output.fundamentacao === 'string' ? sugestao.output.fundamentacao : null;
                        const fonte = typeof sugestao.output.fonte === 'string' ? sugestao.output.fonte : null;
                        const cnaeItem = perCnae.find((item) => item.cnae === sugestao.cnae);
                        const rotuloCnae = cnaeItem?.cnae_formatado ?? sugestao.cnae ?? '';

                        return (
                            <li
                                key={sugestao.id}
                                className="rounded-xl border border-brand-200 bg-brand-50 p-4 dark:border-brand-500/30 dark:bg-brand-500/10"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge color="info" size="sm">
                                            Sugestão — revise
                                        </Badge>
                                        {rotuloCnae !== '' && (
                                            <Badge color="light" size="sm">
                                                CNAE {rotuloCnae}
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge color={sugestao.status === 'escalada_humano' ? 'error' : 'light'} size="sm">
                                            {sugestao.status_label}
                                        </Badge>
                                    </div>
                                </div>

                                {minutaTexto === '' ? (
                                    <p className="mt-3 text-theme-sm text-gray-600 dark:text-gray-300">
                                        A IA não retornou texto de justificativa nesta geração.
                                    </p>
                                ) : (
                                    <p className="mt-3 text-theme-sm whitespace-pre-line text-gray-800 dark:text-white/90">
                                        {minutaTexto}
                                    </p>
                                )}

                                {fundamentacao && (
                                    <div className="mt-3">
                                        <p className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                            Fundamentação (do motor)
                                        </p>
                                        <p className="mt-1 text-theme-sm whitespace-pre-line text-gray-700 dark:text-gray-300">
                                            {fundamentacao}
                                        </p>
                                    </div>
                                )}

                                {fonte && (
                                    <p className="mt-3 text-theme-xs text-gray-500 dark:text-gray-400">Fonte: {fonte}</p>
                                )}

                                {editavel && minutaTexto !== '' && sugestao.cnae && (
                                    <div className="mt-4">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => onAplicar(sugestao.cnae as string, minutaTexto)}
                                        >
                                            Aplicar à justificativa
                                        </Button>
                                    </div>
                                )}
                            </li>
                        );
                    })}
                </ul>

                <p className="mt-4 text-theme-xs text-gray-400 dark:text-gray-500">
                    “Aplicar à justificativa” copia o texto para o campo da atividade, onde você revisa e ajusta — nada é
                    gravado nem decidido automaticamente (RN-001).
                </p>
            </CardContent>
        </Card>
    );
}

/** Severidade da inconsistência → cor e rótulo em pt-BR (texto, nunca só cor). */
function severidadeBadge(severidade: SeveridadeIa): { cor: 'error' | 'warning' | 'light'; label: string } {    if (severidade === 'alta') {
        return { cor: 'error', label: 'Alta' };
    }

    if (severidade === 'media') {
        return { cor: 'warning', label: 'Média' };
    }

    return { cor: 'light', label: 'Baixa' };
}

/** Rótulo legível do campo apontado pela IA (ex.: "endereco_numero" → "Endereco numero"). */
function rotuloCampo(campo: string): string {
    const limpo = campo.replace(/_/g, ' ').trim();

    if (limpo === '') {
        return 'Campo';
    }

    return limpo.charAt(0).toUpperCase() + limpo.slice(1);
}

/**
 * Card "Alertas de IA" (HU-107): lista as inconsistências detectadas
 * (campo/declarado/documento/severidade/fonte) sempre marcadas como
 * "sugestão — revise". SINALIZA para a revisão humana — não decide nem penaliza
 * (RN-004) — e COMPLEMENTA as validações determinísticas, sem substituí-las
 * (RN-005). Espelha o padrão visual de divergência da própria ficha.
 */
function AlertasIaCard({ sugestoes }: { sugestoes: SugestaoIa[] }) {
    const inconsistencias = sugestoes.filter((sugestao) => sugestao.type === 'inconsistencias');

    if (inconsistencias.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader
                title="Alertas de IA (sugestão — revise)"
                description="Divergências apontadas pela IA entre o que foi declarado e o que os documentos/foto mostram. Complementa, não substitui, as validações técnicas."
            />
            <CardContent>
                <ul className="space-y-4" aria-label="Alertas de inconsistência sugeridos pela IA">
                        {inconsistencias.map((sugestao) => {
                            const itens = sugestao.output.inconsistencias ?? [];
                            const fonte = typeof sugestao.output.fonte === 'string' ? sugestao.output.fonte : null;

                            return (
                                <li
                                    key={sugestao.id}
                                    className="rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/15"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="inline-flex items-center gap-2">
                                            <AlertIcon className="size-4 shrink-0 fill-current text-warning-500" />
                                            <Badge color="warning" size="sm">
                                                Sugestão — revise
                                            </Badge>
                                        </span>
                                        <Badge color={sugestao.status === 'escalada_humano' ? 'error' : 'light'} size="sm">
                                            {sugestao.status_label}
                                        </Badge>
                                    </div>

                                    {itens.length === 0 ? (
                                        <p className="mt-3 text-theme-sm text-gray-600 dark:text-gray-300">
                                            A IA não apontou divergências específicas nesta verificação.
                                        </p>
                                    ) : (
                                        <ul className="mt-3 space-y-3">
                                            {itens.map((item, indice) => {
                                                const severidade = severidadeBadge(item.severidade);

                                                return (
                                                    <li
                                                        key={indice}
                                                        className="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
                                                    >
                                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                                            <span className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                                                {rotuloCampo(item.campo)}
                                                            </span>
                                                            <Badge color={severidade.cor} size="sm">
                                                                Severidade: {severidade.label}
                                                            </Badge>
                                                        </div>
                                                        <dl className="mt-2 grid gap-2 sm:grid-cols-2">
                                                            <div>
                                                                <dt className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                                                    Declarado
                                                                </dt>
                                                                <dd className="mt-1 text-theme-sm text-gray-800 dark:text-white/90">
                                                                    {item.declarado}
                                                                </dd>
                                                            </div>
                                                            <div>
                                                                <dt className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                                                    No documento
                                                                </dt>
                                                                <dd className="mt-1 text-theme-sm text-gray-800 dark:text-white/90">
                                                                    {item.documento}
                                                                </dd>
                                                            </div>
                                                        </dl>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    )}

                                    {fonte && (
                                        <p className="mt-3 text-theme-xs text-gray-500 dark:text-gray-400">
                                            Fonte: {fonte}
                                        </p>
                                    )}
                                </li>
                            );
                        })}
                    </ul>

                <p className="mt-4 text-theme-xs text-gray-400 dark:text-gray-500">
                    Sinais para revisão humana — não decidem nem penalizam (RN-004). As validações de lote (GIS) e
                    Receita seguem pendentes SEDUR (HU-036) e não são supridas por estes alertas.
                </p>
            </CardContent>
        </Card>
    );
}

/**
 * Painel de abrigados da inscrição (T02 — CA-F-03), exibido quando a ficha marca
 * a sede. Cada linha traz o nº TVL, a razão social e a VALIDADE. A validade do
 * produto NÃO é modelada (desfecho spec-2) e chega null → renderizada como "—"
 * (degradação honesta, jamais inventada). Vazio quando não há abrigado.
 */
function AbrigadosPanel({
    inscricao,
    abrigados,
}: {
    inscricao: string | null;
    abrigados: AbrigadoLinha[];
}) {
    return (
        <Card>
            <CardHeader
                title="Abrigados da inscrição"
                description={
                    inscricao
                        ? `Solicitações abrigadas na inscrição ${inscricao} (RN-EV-05).`
                        : 'Solicitações abrigadas nesta inscrição (RN-EV-05).'
                }
            />
            <CardContent>
                {abrigados.length === 0 ? (
                    <EmptyState
                        title="Nenhum abrigado"
                        description="Ainda não há inscrições abrigadas nesta sede de escritório virtual."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-theme-sm">
                            <thead>
                                <tr className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                    <th className="pb-2 pr-3">Nº da viabilidade</th>
                                    <th className="pb-2 pr-3">Razão social</th>
                                    <th className="pb-2">Validade</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                {abrigados.map((abrigado, indice) => (
                                    <tr key={`${abrigado.tvl ?? 'sem-tvl'}-${indice}`}>
                                        <td className="py-2 pr-3 text-gray-700 dark:text-gray-300">
                                            {abrigado.tvl ?? '—'}
                                        </td>
                                        <td className="py-2 pr-3 text-gray-700 dark:text-gray-300">
                                            {abrigado.razao_social ?? '—'}
                                        </td>
                                        {/* Validade não modelada (desfecho spec-2) — sempre "—". */}
                                        <td className="py-2 text-gray-500 dark:text-gray-400">
                                            {abrigado.validade ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <p className="mt-4 text-theme-xs text-gray-400 dark:text-gray-500">
                    Campos SEFAZ/externos (validade do produto, integrações) pendentes da SEDUR — exibidos como
                    "—" enquanto não modelados.
                </p>
            </CardContent>
        </Card>
    );
}

/** Painel de precedentes (HU-132): processos do imóvel + estatística do CNAE na zona. */
function PrecedentesPanel({
    carregando,
    erro,
    dados,
}: {
    carregando: boolean;
    erro: string | null;
    dados: PrecedentesResponse | null;
}) {
    return (
        <Card>
            <CardHeader title="Precedentes" description="Histórico do imóvel e estatística do CNAE na zona (HU-132)." />
            <CardContent>
                {carregando && (
                    <div className="space-y-2">
                        <div className="h-4 w-3/4 animate-pulse rounded bg-gray-100 dark:bg-white/[0.06]" />
                        <div className="h-4 w-1/2 animate-pulse rounded bg-gray-100 dark:bg-white/[0.06]" />
                    </div>
                )}

                {!carregando && erro && <FieldError>{erro}</FieldError>}

                {!carregando && !erro && dados && (
                    <div className="space-y-4">
                        <div>
                            <p className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                Processos do imóvel
                            </p>
                            <p className="mt-1 text-theme-sm text-gray-700 dark:text-gray-300">
                                Em análise neste imóvel: {dados.em_analise ?? 0}
                            </p>
                            {(dados.em_analise_processos ?? []).length > 0 && (
                                <ul className="mt-2 space-y-1" aria-label="Processos em análise neste imóvel">
                                    {(dados.em_analise_processos ?? []).map((processoEmAnalise) => (
                                        <li key={processoEmAnalise.viability_request_id}>
                                            <a
                                                href={`/gestao/processos/${processoEmAnalise.viability_request_id}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="text-theme-sm font-medium text-brand-500 hover:text-brand-600 hover:underline dark:text-brand-400"
                                            >
                                                {processoEmAnalise.bap ?? processoEmAnalise.protocol_number ?? `#${processoEmAnalise.viability_request_id}`}
                                                <span className="sr-only"> (abre em nova aba)</span>
                                            </a>
                                            {processoEmAnalise.bap && processoEmAnalise.protocol_number && (
                                                <span className="ml-2 text-theme-xs text-gray-400 dark:text-gray-500">
                                                    {processoEmAnalise.protocol_number}
                                                </span>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {dados.imovel.length === 0 ? (
                                <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                                    Sem precedentes para este imóvel.
                                </p>
                            ) : (
                                <>
                                    <p className="mt-3 text-theme-sm text-gray-700 dark:text-gray-300">Decididos neste imóvel</p>
                                    <ul className="mt-1 space-y-1" aria-label="Processos decididos neste imóvel">
                                        {dados.imovel.map((precedente) => (
                                            <li key={precedente.viability_request_id} className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                <a
                                                    href={`/gestao/processos/${precedente.viability_request_id}`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-theme-sm font-medium text-brand-500 hover:text-brand-600 hover:underline dark:text-brand-400"
                                                >
                                                    {precedente.bap ?? precedente.protocol_number ?? `#${precedente.viability_request_id}`}
                                                    <span className="sr-only"> (abre em nova aba)</span>
                                                </a>
                                                {precedente.bap && precedente.protocol_number && (
                                                    <span className="text-theme-xs text-gray-400 dark:text-gray-500">{precedente.protocol_number}</span>
                                                )}
                                                {precedente.outcome && (
                                                    <Badge color={statusColor(precedente.outcome)} size="sm">
                                                        {statusLabel(precedente.outcome)}
                                                    </Badge>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </>
                            )}
                        </div>

                        <div>
                            <p className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                CNAE na zona
                            </p>
                            {dados.cnae_zona.disponivel ? (
                                <p className="mt-1 text-theme-sm text-gray-600 dark:text-gray-300">
                                    Últimos {dados.cnae_zona.janela_meses} meses:{' '}
                                    <span className="font-medium text-success-600 dark:text-success-500">
                                        {dados.cnae_zona.deferidos ?? 0} deferimentos
                                    </span>
                                    ,{' '}
                                    <span className="font-medium text-error-600 dark:text-error-500">
                                        {dados.cnae_zona.indeferidos ?? 0} indeferimentos
                                    </span>{' '}
                                    (de {dados.cnae_zona.total ?? 0}).
                                </p>
                            ) : (
                                <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                                    {dados.cnae_zona.motivo ?? 'Estatística da zona indisponível (zona pendente SEDUR).'}
                                </p>
                            )}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

/** Renderiza o diff {campo: {de, para}} de forma legível. */
function DiffView({ diff }: { diff: Record<string, unknown> }) {
    const entradas = Object.entries(diff);

    if (entradas.length === 0) {
        return (
            <p className="mt-4 text-theme-sm text-gray-500 dark:text-gray-400">
                As revisões são idênticas — nenhuma diferença.
            </p>
        );
    }

    return (
        <div className="mt-4 space-y-3">
            {entradas.map(([campo, valor]) => (
                <div key={campo} className="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <p className="text-theme-xs font-medium text-gray-500 uppercase dark:text-gray-400">{campo}</p>
                    {ehDiffValor(valor) ? (
                        <DiffLinha valor={valor} />
                    ) : (
                        <div className="mt-2 space-y-2">
                            {Object.entries(valor as Record<string, unknown>).map(([subcampo, subvalor]) => (
                                <div key={subcampo}>
                                    <p className="text-theme-xs text-gray-400 dark:text-gray-500">{subcampo}</p>
                                    {ehDiffValor(subvalor) ? (
                                        <DiffLinha valor={subvalor} />
                                    ) : (
                                        <pre className="overflow-x-auto text-theme-xs text-gray-600 dark:text-gray-300">
                                            {JSON.stringify(subvalor, null, 2)}
                                        </pre>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

function ehDiffValor(valor: unknown): valor is DiffValor {
    return typeof valor === 'object' && valor !== null && 'de' in valor && 'para' in valor;
}

function DiffLinha({ valor }: { valor: DiffValor }) {
    return (
        <div className="mt-1 grid gap-2 sm:grid-cols-2">
            <div className="rounded bg-error-50 p-2 text-theme-xs text-gray-700 dark:bg-error-500/10 dark:text-gray-300">
                <span className="font-medium">De:</span> {formatarDiffValor(valor.de)}
            </div>
            <div className="rounded bg-success-50 p-2 text-theme-xs text-gray-700 dark:bg-success-500/10 dark:text-gray-300">
                <span className="font-medium">Para:</span> {formatarDiffValor(valor.para)}
            </div>
        </div>
    );
}

function formatarDiffValor(valor: unknown): string {
    if (valor === null || valor === undefined || valor === '') {
        return '—';
    }

    if (typeof valor === 'object') {
        return JSON.stringify(valor);
    }

    return String(valor);
}

/** Modal de seleção de trechos da biblioteca de textos-padrão (HU-081). */
function TextosPadraoPicker({
    textos,
    onSelect,
    onClose,
}: {
    textos: TextoPadrao[];
    onSelect: (conteudo: string) => void;
    onClose: () => void;
}) {
    const [categoria, setCategoria] = useState('');

    const categorias = useMemo(() => Array.from(new Set(textos.map((texto) => texto.category))), [textos]);
    const filtrados = categoria === '' ? textos : textos.filter((texto) => texto.category === categoria);

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[640px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Biblioteca de textos-padrão</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Selecione um trecho pré-aprovado para inserir.
            </p>

            {categorias.length > 0 && (
                <div className="mt-4 w-56">
                    <Select
                        value={categoria}
                        onChange={setCategoria}
                        placeholder="Todas as categorias"
                        options={categorias.map((cat) => ({ value: cat, label: cat }))}
                    />
                </div>
            )}

            <div className="mt-4 space-y-2">
                {filtrados.length === 0 ? (
                    <EmptyState
                        title="Nenhum texto-padrão ativo"
                        description="Cadastre trechos na administração de textos-padrão para reusá-los aqui."
                    />
                ) : (
                    filtrados.map((texto) => (
                        <button
                            key={texto.id}
                            type="button"
                            onClick={() => onSelect(texto.content)}
                            className="w-full rounded-lg border border-gray-200 p-3 text-left transition hover:border-brand-300 hover:bg-brand-50 dark:border-gray-800 dark:hover:border-brand-800 dark:hover:bg-brand-500/10"
                        >
                            <div className="flex items-center justify-between gap-2">
                                <Badge color="light" size="sm">
                                    {texto.category}
                                </Badge>
                                <span className="text-theme-xs text-gray-400 dark:text-gray-500">v{texto.version}</span>
                            </div>
                            <p className="mt-2 text-theme-sm text-gray-700 dark:text-gray-300">{texto.content}</p>
                        </button>
                    ))
                )}
            </div>

            <div className="mt-6 flex items-center justify-end gap-3">
                <Button variant="outline" size="sm" onClick={onClose}>
                    Fechar
                </Button>
            </div>
        </Modal>
    );
}

FichaAnaliseShow.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
