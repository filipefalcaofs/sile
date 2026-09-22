import { Head, Link, router, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { rotuloSemSla, type ProcessoItem } from '@/components/analise/processo-ui';
import PageHeader from '@/components/app/page-header';
import Checkbox from '@/components/form/checkbox';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { CloseIcon, FileIcon, SearchIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import EmptyState from '@/components/ui/empty-state';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface SelectOption {
    value: string;
    label: string;
}

/** Filtros de texto/seleção do SAPS (sem o per_page, que é numérico). */
interface FiltrosTexto {
    grupo: string;
    status: string;
    protocolo: string;
    bap: string;
    produto_tvl: string;
    servico: string;
    setor: string;
    analista: string;
    categoria: string;
    inscricao: string;
    nome: string;
    cnpj: string;
    cep: string;
    logradouro: string;
    bairro: string;
    data_de: string;
    data_ate: string;
    analysis_status: string;
    busca: string;
    fluxo: string;
}

interface AbasContagem {
    todos: number;
    em_analise: number;
    distribuir: number;
    pendencia: number;
    concluido: number;
}

type AbaId = keyof AbasContagem;

interface ConsultaProps {
    processos: {
        data: ProcessoItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filtros: FiltrosTexto & { per_page: number; ordem: string };
    abas: AbasContagem;
    perPageOptions: number[];
    statusOptions: SelectOption[];
    categoriaOptions: SelectOption[];
    analysisStatusOptions: SelectOption[];
}

/** Grupos macro de status (espelham GRUPOS_STATUS do ProcessoQueryService). */
const FLUXO_OPTIONS: SelectOption[] = [
    { value: 'expresso', label: 'Expresso' },
    { value: 'em_analise', label: 'Em análise' },
    { value: 'analise_tecnica', label: 'Análise técnica' },
];

const GRUPO_OPTIONS: SelectOption[] = [
    { value: 'rascunho', label: 'Rascunho' },
    { value: 'em_andamento', label: 'Em andamento' },
    { value: 'concluido', label: 'Concluído' },
    { value: 'cancelado', label: 'Cancelado' },
];

const ORDEM_OPTIONS: SelectOption[] = [
    { value: 'recentes', label: 'Mais recentes' },
    { value: 'prazo', label: 'Prazo mais curto' },
    { value: 'status', label: 'Status' },
];

const ABAS: { id: AbaId; label: string }[] = [
    { id: 'todos', label: 'Todos' },
    { id: 'em_analise', label: 'Em análise' },
    { id: 'distribuir', label: 'Para distribuir' },
    { id: 'pendencia', label: 'Pendências' },
    { id: 'concluido', label: 'Concluídos' },
];

const ROTULOS_FILTRO: Record<keyof FiltrosTexto, string> = {
    grupo: 'Grupo de status',
    status: 'Status',
    analysis_status: 'Situação da análise',
    categoria: 'Categoria',
    fluxo: 'Fluxo',
    protocolo: 'Nº do processo',
    bap: 'BAP',
    produto_tvl: 'Produto TVL',
    nome: 'Empresa / requerente',
    cnpj: 'CNPJ',
    inscricao: 'Inscrição imobiliária',
    logradouro: 'Logradouro',
    bairro: 'Bairro',
    cep: 'CEP',
    servico: 'Serviço',
    setor: 'Setor',
    analista: 'Analista',
    data_de: 'Protocolado de',
    data_ate: 'Protocolado até',
    busca: 'Busca',
};

const CHAVES_FILTRO: (keyof FiltrosTexto)[] = [
    'grupo',
    'status',
    'protocolo',
    'bap',
    'produto_tvl',
    'servico',
    'setor',
    'analista',
    'categoria',
    'inscricao',
    'nome',
    'cnpj',
    'cep',
    'logradouro',
    'bairro',
    'data_de',
    'data_ate',
    'analysis_status',
    'busca',
    'fluxo',
];

const CHAVES_AVANCADAS = CHAVES_FILTRO.filter((chave) => chave !== 'busca');

function filtrosVazios(): FiltrosTexto {
    const vazio = {} as FiltrosTexto;

    for (const chave of CHAVES_FILTRO) {
        vazio[chave] = '';
    }

    return vazio;
}

function statusColor(status: string): 'success' | 'error' | 'warning' | 'info' | 'light' {
    if (status === 'deferida') {
        return 'success';
    }
    if (status === 'indeferida' || status === 'cancelada') {
        return 'error';
    }
    if (status === 'em_pendencia') {
        return 'warning';
    }
    if (status === 'em_analise') {
        return 'info';
    }
    return 'light';
}

function slaColor(status: string): 'success' | 'warning' | 'error' | 'light' {
    if (status === 'verde') {
        return 'success';
    }
    if (status === 'amarelo') {
        return 'warning';
    }
    if (status === 'vermelho') {
        return 'error';
    }
    return 'light';
}

function abaDe(filtros: FiltrosTexto): AbaId | null {
    const semGrupo = filtros.grupo === '';
    const semStatus = filtros.status === '';
    const semAnalise = filtros.analysis_status === '';

    if (semGrupo && semStatus && semAnalise) {
        return 'todos';
    }
    if (filtros.status === 'em_analise' && semGrupo && semAnalise) {
        return 'em_analise';
    }
    if (filtros.analysis_status === 'para_distribuir' && semGrupo && semStatus) {
        return 'distribuir';
    }
    if (filtros.status === 'em_pendencia' && semGrupo && semAnalise) {
        return 'pendencia';
    }
    if (filtros.grupo === 'concluido' && semStatus && semAnalise) {
        return 'concluido';
    }

    return null;
}

function aplicarAba(base: FiltrosTexto, aba: AbaId): FiltrosTexto {
    const proximo = { ...base, grupo: '', status: '', analysis_status: '' };

    if (aba === 'em_analise') {
        proximo.status = 'em_analise';
    }
    if (aba === 'distribuir') {
        proximo.analysis_status = 'para_distribuir';
    }
    if (aba === 'pendencia') {
        proximo.status = 'em_pendencia';
    }
    if (aba === 'concluido') {
        proximo.grupo = 'concluido';
    }

    return proximo;
}

function formatarData(iso: string | null): string | null {
    if (!iso) {
        return null;
    }

    const data = new Date(iso);

    if (Number.isNaN(data.getTime())) {
        return null;
    }

    return data.toLocaleDateString('pt-BR');
}

export default function ConsultaProcessos({
    processos,
    filtros,
    abas,
    perPageOptions,
    statusOptions,
    categoriaOptions,
    analysisStatusOptions,
}: ConsultaProps) {
    const { auth } = usePage<SharedProps>().props;
    const podeMalhaFina = auth.permissions.includes('encaminhar-malha-fina');

    const [form, setForm] = useState<FiltrosTexto>(() => {
        const inicial = filtrosVazios();

        for (const chave of CHAVES_FILTRO) {
            inicial[chave] = filtros[chave] ?? '';
        }

        return inicial;
    });
    const [perPage, setPerPage] = useState(filtros.per_page);
    const [ordem, setOrdem] = useState(filtros.ordem || 'recentes');
    const [filtrosAbertos, setFiltrosAbertos] = useState(false);
    const [selecionados, setSelecionados] = useState<number[]>([]);
    const [motivo, setMotivo] = useState('');
    const [erroMotivo, setErroMotivo] = useState<string | null>(null);
    const [enviando, setEnviando] = useState(false);

    function definir(chave: keyof FiltrosTexto, valor: string) {
        setForm((anterior) => ({ ...anterior, [chave]: valor }));
    }

    function montarParams(base: FiltrosTexto, pp: number, ordemAtual: string): Record<string, string | number> {
        const params: Record<string, string | number> = { per_page: pp };

        if (ordemAtual !== '' && ordemAtual !== 'recentes') {
            params.ordem = ordemAtual;
        }

        for (const chave of CHAVES_FILTRO) {
            const valor = base[chave].trim();

            if (valor !== '') {
                params[chave] = valor;
            }
        }

        return params;
    }

    function visitar(base: FiltrosTexto, pp: number, ordemAtual = ordem) {
        router.get('/gestao/processos', montarParams(base, pp, ordemAtual), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onSuccess: () => setSelecionados([]),
        });
    }

    useEffect(() => {
        const atual = (filtros.busca ?? '').trim();
        const proximo = form.busca.trim();

        if (proximo === atual) {
            return;
        }

        const timer = window.setTimeout(() => visitar(form, perPage), 400);

        return () => window.clearTimeout(timer);
        // A busca dispara a visita; os demais filtros entram no estado já atual.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.busca]);

    function aplicar(evento: FormEvent) {
        evento.preventDefault();
        setFiltrosAbertos(false);
        visitar(form, perPage);
    }

    function limpar() {
        const vazio = { ...filtrosVazios(), busca: form.busca };
        setForm(vazio);
        setFiltrosAbertos(false);
        visitar(vazio, perPage);
    }

    function limparTudo() {
        const vazio = filtrosVazios();
        setForm(vazio);
        setFiltrosAbertos(false);
        visitar(vazio, perPage);
    }

    function trocarPerPage(proximo: number) {
        setPerPage(proximo);
        visitar(form, proximo);
    }

    function trocarOrdem(proxima: string) {
        setOrdem(proxima);
        visitar(form, perPage, proxima);
    }

    function selecionarAba(aba: AbaId) {
        const proximo = aplicarAba(form, aba);
        setForm(proximo);
        visitar(proximo, perPage);
    }

    function removerFiltro(chave: keyof FiltrosTexto) {
        const proximo = { ...form, [chave]: '' };
        setForm(proximo);
        visitar(proximo, perPage);
    }

    function encaminharMalhaFina() {
        if (motivo.trim() === '') {
            setErroMotivo('Informe o motivo do encaminhamento.');

            return;
        }

        router.post(
            '/gestao/processos/malha-fina',
            { request_ids: selecionados, motivo },
            {
                preserveScroll: true,
                onStart: () => setEnviando(true),
                onFinish: () => setEnviando(false),
                onSuccess: () => {
                    setSelecionados([]);
                    setMotivo('');
                    setErroMotivo(null);
                },
            },
        );
    }

    const idsNaPagina = processos.data.map((item) => item.id);
    const todosSelecionados = idsNaPagina.length > 0 && idsNaPagina.every((id) => selecionados.includes(id));

    function alternarTodos() {
        setSelecionados(todosSelecionados ? [] : idsNaPagina);
    }

    function alternar(id: number) {
        setSelecionados((anterior) =>
            anterior.includes(id) ? anterior.filter((item) => item !== id) : [...anterior, id],
        );
    }

    const abaAtiva = abaDe(filtros);
    const chavesDaAba: (keyof FiltrosTexto)[] =
        abaAtiva === 'em_analise' || abaAtiva === 'pendencia'
            ? ['status']
            : abaAtiva === 'distribuir'
              ? ['analysis_status']
              : abaAtiva === 'concluido'
                ? ['grupo']
                : [];

    const opcoesPorChave: Partial<Record<keyof FiltrosTexto, SelectOption[]>> = {
        grupo: GRUPO_OPTIONS,
        status: statusOptions,
        analysis_status: analysisStatusOptions,
        categoria: categoriaOptions,
        fluxo: FLUXO_OPTIONS,
    };

    const chips = CHAVES_AVANCADAS.filter((chave) => (filtros[chave] ?? '') !== '' && !chavesDaAba.includes(chave)).map(
        (chave) => {
            const valor = filtros[chave] ?? '';
            const rotulo = opcoesPorChave[chave]?.find((opcao) => opcao.value === valor)?.label ?? valor;

            return { chave, label: `${ROTULOS_FILTRO[chave]}: ${rotulo}` };
        },
    );

    const csvParams = new URLSearchParams({ formato: 'csv' });
    for (const chave of CHAVES_FILTRO) {
        const valor = filtros[chave] ?? '';
        if (valor !== '') {
            csvParams.set(chave, valor);
        }
    }
    if (filtros.ordem && filtros.ordem !== 'recentes') {
        csvParams.set('ordem', filtros.ordem);
    }
    const csvHref = `/gestao/processos?${csvParams.toString()}`;

    const resumo =
        processos.total === 1 ? '1 processo encontrado' : `${processos.total} processos encontrados`;

    return (
        <>
            <Head title="Consulta de processos" />
            <PageHeader title="Consulta de processos" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <section className="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div className="flex flex-wrap items-center gap-3 px-5 py-4">
                    <div className="relative min-w-[240px] flex-1 basis-[340px]">
                        <SearchIcon className="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-gray-400" />
                        <Input
                            id="busca-processos"
                            value={form.busca}
                            onChange={(evento) => definir('busca', evento.target.value)}
                            placeholder="Buscar por processo, BAP, protocolo, TVL, CNPJ ou empresa"
                            className="pl-11"
                            aria-label="Buscar processos"
                        />
                    </div>
                    <Button type="button" variant="outline" onClick={() => setFiltrosAbertos(true)}>
                        Filtros avançados
                        {chips.length > 0 && (
                            <span className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-500 px-1.5 text-xs font-semibold text-white">
                                {chips.length}
                            </span>
                        )}
                    </Button>
                    <a
                        href={csvHref}
                        className="inline-flex h-11 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                    >
                        <FileIcon className="size-5" />
                        Exportar CSV
                    </a>
                </div>

                <div className="flex flex-wrap items-center gap-2 border-t border-gray-100 px-5 py-3 dark:border-gray-800">
                    {ABAS.map((aba) => {
                        const ativa = abaAtiva === aba.id;

                        return (
                            <button
                                key={aba.id}
                                type="button"
                                onClick={() => selecionarAba(aba.id)}
                                className={`inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm font-medium ${
                                    ativa
                                        ? 'border-brand-500 bg-brand-500 text-white'
                                        : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300'
                                }`}
                            >
                                {aba.label}
                                <span
                                    className={`inline-flex min-w-[22px] justify-center rounded-full px-1.5 text-xs font-semibold ${
                                        ativa
                                            ? 'bg-white/20 text-white'
                                            : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300'
                                    }`}
                                >
                                    {abas[aba.id]}
                                </span>
                            </button>
                        );
                    })}
                </div>

                {chips.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2 border-t border-gray-100 bg-gray-50/60 px-5 py-3 dark:border-gray-800 dark:bg-white/[0.02]">
                        <span className="text-xs tracking-wide text-gray-400 uppercase">Filtros ativos</span>
                        {chips.map((chip) => (
                            <span
                                key={chip.chave}
                                className="inline-flex items-center gap-1.5 rounded-full bg-brand-50 py-1 pr-1.5 pl-3 text-xs font-medium text-brand-800 dark:bg-brand-500/15 dark:text-brand-200"
                            >
                                {chip.label}
                                <button
                                    type="button"
                                    onClick={() => removerFiltro(chip.chave)}
                                    title={`Remover ${chip.label}`}
                                    className="inline-flex size-[18px] items-center justify-center rounded-full bg-brand-500/15 text-brand-500"
                                >
                                    <CloseIcon className="size-3" />
                                </button>
                            </span>
                        ))}
                        <button
                            type="button"
                            onClick={limparTudo}
                            className="px-1.5 text-xs font-medium text-gray-500 underline"
                        >
                            Limpar tudo
                        </button>
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-5 py-3 dark:border-gray-800">
                    <div className="flex items-center gap-2.5 text-sm text-gray-500">
                        {podeMalhaFina && (
                            <Checkbox checked={todosSelecionados} onChange={alternarTodos} />
                        )}
                        <span>{resumo}</span>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <label className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                            Ordenar
                            <select
                                value={ordem}
                                onChange={(evento) => trocarOrdem(evento.target.value)}
                                className="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            >
                                {ORDEM_OPTIONS.map((opcao) => (
                                    <option key={opcao.value} value={opcao.value}>
                                        {opcao.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                            Exibir
                            <select
                                value={perPage}
                                onChange={(evento) => trocarPerPage(Number(evento.target.value))}
                                className="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            >
                                {perPageOptions.map((opcao) => (
                                    <option key={opcao} value={opcao}>
                                        {opcao}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>
                </div>

                {podeMalhaFina && selecionados.length > 0 && (
                    <div className="flex flex-wrap items-center gap-3 border-t border-gray-100 bg-brand-50 px-5 py-3 dark:border-gray-800 dark:bg-brand-500/10">
                        <span className="text-sm font-medium text-brand-800 dark:text-brand-200">
                            {selecionados.length === 1
                                ? '1 processo selecionado'
                                : `${selecionados.length} processos selecionados`}
                        </span>
                        <div className="min-w-[200px] flex-1 basis-[260px]">
                            <Input
                                id="malha-fina-motivo"
                                value={motivo}
                                placeholder="Motivo do encaminhamento (obrigatório)"
                                error={erroMotivo !== null}
                                onChange={(evento) => {
                                    setMotivo(evento.target.value);
                                    setErroMotivo(null);
                                }}
                                aria-label="Motivo do encaminhamento"
                            />
                        </div>
                        <Button type="button" size="sm" onClick={encaminharMalhaFina} loading={enviando}>
                            Encaminhar para a Vistoria
                        </Button>
                        <button
                            type="button"
                            onClick={() => setSelecionados([])}
                            className="text-sm text-brand-800 underline dark:text-brand-200"
                        >
                            Cancelar
                        </button>
                        {erroMotivo && <p className="w-full text-theme-xs text-error-500">{erroMotivo}</p>}
                    </div>
                )}

                <div className="hidden items-center gap-4 border-t border-gray-100 bg-gray-50 px-5 py-2.5 text-xs font-medium text-gray-500 lg:flex dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-400">
                    {podeMalhaFina && <span className="w-5 shrink-0" />}
                    <span className="min-w-[150px] flex-[3]">Processo e requerente</span>
                    <span className="min-w-[150px] flex-[3]">Status e análise</span>
                    <span className="min-w-[120px] flex-[2]">Serviço e protocolo</span>
                    <span className="min-w-[110px] flex-[2]">Analista</span>
                    <span className="min-w-[100px] flex-1">Prazo</span>
                    <span className="w-16 shrink-0" />
                </div>

                {processos.data.length === 0 ? (
                    <div className="border-t border-gray-100 dark:border-gray-800">
                        <EmptyState
                            title={
                                chips.length > 0 || form.busca.trim() !== '' || abaAtiva !== 'todos'
                                    ? 'Nenhum processo para esta busca'
                                    : 'Nenhum processo cadastrado'
                            }
                            description={
                                chips.length > 0 || form.busca.trim() !== '' || abaAtiva !== 'todos'
                                    ? 'Ajuste a busca, troque de aba ou limpe os filtros ativos.'
                                    : 'Os processos protocolados aparecem aqui conforme avançam no fluxo.'
                            }
                        />
                    </div>
                ) : (
                    <ul className="divide-y divide-gray-100 border-t border-gray-100 dark:divide-gray-800 dark:border-gray-800">
                        {processos.data.map((item) => (
                            <LinhaProcesso
                                key={item.id}
                                item={item}
                                selecionavel={podeMalhaFina}
                                selecionado={selecionados.includes(item.id)}
                                onAlternar={() => alternar(item.id)}
                            />
                        ))}
                    </ul>
                )}

                <div className="border-t border-gray-100 px-5 py-4 dark:border-gray-800">
                    <Pagination
                        links={processos.links}
                        meta={{ from: processos.from, to: processos.to, total: processos.total }}
                    />
                    {processos.links.length <= 3 && processos.total > 0 && (
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Mostrando {processos.from}–{processos.to} de {processos.total}
                        </p>
                    )}
                </div>
            </section>

            {filtrosAbertos && (
                <PainelFiltros
                    form={form}
                    definir={definir}
                    onClose={() => setFiltrosAbertos(false)}
                    onSubmit={aplicar}
                    onLimpar={limpar}
                    statusOptions={statusOptions}
                    categoriaOptions={categoriaOptions}
                    analysisStatusOptions={analysisStatusOptions}
                />
            )}
        </>
    );
}

function LinhaProcesso({
    item,
    selecionavel,
    selecionado,
    onAlternar,
}: {
    item: ProcessoItem;
    selecionavel: boolean;
    selecionado: boolean;
    onAlternar: () => void;
}) {
    const identificadores = [
        item.bap ? item.protocol_number : null,
        item.tvl_product_number ? `Viabilidade ${item.tvl_product_number}` : null,
    ].filter((parte): parte is string => parte !== null && parte !== '');
    const protocolado = formatarData(item.protocoled_at);
    const motivo =
        item.sem_decisao_automatica && item.motivo_encaminhamento
            ? `Sem decisão automática — ${item.motivo_encaminhamento}`
            : null;

    return (
        <li
            className={`flex flex-wrap items-start gap-x-4 gap-y-2.5 px-5 py-3 ${
                selecionado ? 'bg-brand-50/70 dark:bg-brand-500/10' : 'bg-white hover:bg-gray-50 dark:bg-transparent dark:hover:bg-white/[0.03]'
            }`}
        >
            {selecionavel && (
                <div className="pt-0.5">
                    <Checkbox checked={selecionado} onChange={onAlternar} />
                </div>
            )}

            <div className="flex min-w-[150px] flex-[3] flex-col gap-0.5">
                <span className="text-sm font-semibold text-gray-800 tabular-nums dark:text-white/90">
                    {item.bap ?? item.protocol_number ?? '—'}
                </span>
                {identificadores.length > 0 && (
                    <span className="text-xs text-gray-400">{identificadores.join('  ·  ')}</span>
                )}
                <span className="text-[13px] text-gray-600 dark:text-gray-300">
                    {item.empresa ?? 'Requerente não informado'}
                </span>
            </div>

            <div className="flex min-w-[150px] flex-[3] flex-col gap-1.5">
                <div className="flex flex-wrap items-center gap-1.5">
                    <Badge color={statusColor(item.status)} size="sm">
                        {item.status_label}
                    </Badge>
                    {item.fluxo_label && item.fluxo !== 'em_analise' && (
                        <Badge color={item.fluxo === 'expresso' ? 'success' : 'light'} size="sm">
                            {item.fluxo_label}
                        </Badge>
                    )}
                    {item.analysis_status_label && (
                        <Badge color="light" size="sm">
                            {item.analysis_status_label}
                        </Badge>
                    )}
                </div>
                {motivo && <p className="line-clamp-2 text-xs leading-snug text-gray-500 dark:text-gray-400">{motivo}</p>}
            </div>

            <div className="flex min-w-[120px] flex-[2] flex-col gap-0.5">
                <span className="text-[13px] text-gray-700 dark:text-gray-300">{item.servico ?? item.categoria ?? '—'}</span>
                <span className="text-xs text-gray-400">
                    {protocolado ? `Protocolado em ${protocolado}` : 'Sem data de protocolo'}
                </span>
            </div>

            <div className="flex min-w-[110px] flex-[2] flex-col gap-0.5">
                <span className="text-[13px] text-gray-700 dark:text-gray-300">{item.analista ?? 'Não atribuído'}</span>
                <span className="text-xs text-gray-400">{item.setor ?? '—'}</span>
            </div>

            <div className="flex min-w-[100px] flex-1 flex-col items-start gap-0.5">
                {item.sla ? (
                    <>
                        <Badge color={slaColor(item.sla.status)} size="sm">
                            {item.sla.status_label}
                        </Badge>
                        {item.sla.restante && <span className="text-xs text-gray-400">{item.sla.restante}</span>}
                    </>
                ) : (
                    <Badge color={item.fluxo === 'expresso' ? 'success' : 'light'} size="sm">
                        {rotuloSemSla(item.fluxo)}
                    </Badge>
                )}
            </div>

            <Link
                href={`/gestao/processos/${item.id}`}
                className="inline-flex h-9 items-center rounded-lg border border-gray-300 bg-white px-3.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
            >
                Abrir
            </Link>
        </li>
    );
}

function PainelFiltros({
    form,
    definir,
    onClose,
    onSubmit,
    onLimpar,
    statusOptions,
    categoriaOptions,
    analysisStatusOptions,
}: {
    form: FiltrosTexto;
    definir: (chave: keyof FiltrosTexto, valor: string) => void;
    onClose: () => void;
    onSubmit: (evento: FormEvent) => void;
    onLimpar: () => void;
    statusOptions: SelectOption[];
    categoriaOptions: SelectOption[];
    analysisStatusOptions: SelectOption[];
}) {
    useEffect(() => {
        function aoTeclar(evento: KeyboardEvent) {
            if (evento.key === 'Escape') {
                onClose();
            }
        }

        document.addEventListener('keydown', aoTeclar);

        return () => document.removeEventListener('keydown', aoTeclar);
    }, [onClose]);

    return createPortal(
        <div className="fixed inset-0 z-[80] flex justify-end">
            <button type="button" className="absolute inset-0 bg-gray-900/45" aria-label="Fechar filtros" onClick={onClose} />
            <form
                onSubmit={onSubmit}
                role="dialog"
                aria-modal="true"
                aria-labelledby="titulo-filtros"
                className="relative flex h-full w-[440px] max-w-[92vw] flex-col overflow-y-auto border-l border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900"
            >
                <div className="sticky top-0 z-10 flex items-start justify-between gap-3 border-b border-gray-100 bg-white px-6 py-5 dark:border-gray-800 dark:bg-gray-900">
                    <div>
                        <h3 id="titulo-filtros" className="text-base font-medium text-gray-800 dark:text-white/90">
                            Filtros avançados
                        </h3>
                        <p className="mt-1 text-sm text-gray-500">Use quando a busca única não bastar.</p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        title="Fechar"
                        className="inline-flex size-9 items-center justify-center rounded-lg bg-gray-50 text-gray-500 dark:bg-white/5"
                    >
                        <CloseIcon className="size-4.5" />
                    </button>
                </div>

                <div className="flex flex-col gap-6 px-6 py-5 pb-28">
                    <GrupoFiltro titulo="Identificadores">
                        <CampoTexto id="filtro-protocolo" label="Nº do processo" value={form.protocolo} onChange={(valor) => definir('protocolo', valor)} />
                        <CampoTexto id="filtro-bap" label="BAP" value={form.bap} onChange={(valor) => definir('bap', valor)} />
                        <CampoTexto id="filtro-produto-tvl" label="Produto TVL" value={form.produto_tvl} onChange={(valor) => definir('produto_tvl', valor)} />
                    </GrupoFiltro>
                    <GrupoFiltro titulo="Requerente">
                        <CampoTexto id="filtro-nome" label="Empresa / requerente" value={form.nome} onChange={(valor) => definir('nome', valor)} />
                        <CampoTexto id="filtro-cnpj" label="CNPJ" value={form.cnpj} onChange={(valor) => definir('cnpj', valor)} />
                    </GrupoFiltro>
                    <GrupoFiltro titulo="Imóvel">
                        <CampoTexto id="filtro-inscricao" label="Inscrição imobiliária" value={form.inscricao} onChange={(valor) => definir('inscricao', valor)} />
                        <CampoTexto id="filtro-logradouro" label="Logradouro" value={form.logradouro} onChange={(valor) => definir('logradouro', valor)} />
                        <CampoTexto id="filtro-bairro" label="Bairro" value={form.bairro} onChange={(valor) => definir('bairro', valor)} />
                        <CampoTexto id="filtro-cep" label="CEP" value={form.cep} onChange={(valor) => definir('cep', valor)} />
                    </GrupoFiltro>
                    <GrupoFiltro titulo="Classificação e responsáveis">
                        <div>
                            <Label htmlFor="filtro-grupo">Grupo de status</Label>
                            <Select id="filtro-grupo" value={form.grupo} onChange={(valor) => definir('grupo', valor)} placeholder="Todos" options={GRUPO_OPTIONS} />
                        </div>
                        <div>
                            <Label htmlFor="filtro-status">Status</Label>
                            <Select id="filtro-status" value={form.status} onChange={(valor) => definir('status', valor)} placeholder="Todos" options={statusOptions} />
                        </div>
                        <div>
                            <Label htmlFor="filtro-analysis-status">Situação da análise</Label>
                            <Select id="filtro-analysis-status" value={form.analysis_status} onChange={(valor) => definir('analysis_status', valor)} placeholder="Todas" options={analysisStatusOptions} />
                        </div>
                        <div>
                            <Label htmlFor="filtro-fluxo">Fluxo</Label>
                            <Select id="filtro-fluxo" value={form.fluxo} onChange={(valor) => definir('fluxo', valor)} placeholder="Todos" options={FLUXO_OPTIONS} />
                        </div>
                        <div>
                            <Label htmlFor="filtro-categoria">Categoria</Label>
                            <Select id="filtro-categoria" value={form.categoria} onChange={(valor) => definir('categoria', valor)} placeholder="Todas" options={categoriaOptions} />
                        </div>
                        <CampoTexto id="filtro-servico" label="Serviço (ID)" type="number" value={form.servico} onChange={(valor) => definir('servico', valor)} />
                        <CampoTexto id="filtro-setor" label="Setor (ID)" type="number" value={form.setor} onChange={(valor) => definir('setor', valor)} />
                        <CampoTexto id="filtro-analista" label="Analista (ID)" type="number" value={form.analista} onChange={(valor) => definir('analista', valor)} />
                    </GrupoFiltro>
                    <GrupoFiltro titulo="Período">
                        <CampoTexto id="filtro-data-de" label="Protocolado de" type="date" value={form.data_de} onChange={(valor) => definir('data_de', valor)} />
                        <CampoTexto id="filtro-data-ate" label="Protocolado até" type="date" value={form.data_ate} onChange={(valor) => definir('data_ate', valor)} />
                    </GrupoFiltro>
                </div>

                <div className="sticky bottom-0 mt-auto flex items-center gap-3 border-t border-gray-100 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-900">
                    <Button type="submit" className="flex-1">
                        Aplicar filtros
                    </Button>
                    <Button type="button" variant="outline" onClick={onLimpar}>
                        Limpar
                    </Button>
                </div>
            </form>
        </div>,
        document.body,
    );
}

function GrupoFiltro({ titulo, children }: { titulo: string; children: ReactNode }) {
    return (
        <div className="flex flex-col gap-3">
            <span className="text-xs tracking-wide text-gray-400 uppercase">{titulo}</span>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">{children}</div>
        </div>
    );
}

function CampoTexto({
    id,
    label,
    value,
    onChange,
    type = 'text',
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    type?: string;
}) {
    return (
        <div>
            <Label htmlFor={id}>{label}</Label>
            <Input id={id} type={type} value={value} onChange={(evento) => onChange(evento.target.value)} />
        </div>
    );
}

ConsultaProcessos.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
