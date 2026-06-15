import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import { FileIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import GestaoLayout from '@/layouts/gestao-layout';

type BadgeColor = 'primary' | 'success' | 'error' | 'warning' | 'info' | 'light' | 'dark';

interface SelectOption {
    value: string;
    label: string;
}

/** Resumo de usuário relacionado (causer/representado); null = sistema. */
interface UsuarioResumo {
    id: number;
    nome: string | null;
}

/** Diff estruturado da mudança (HU-098), exposto pelo ActivityResource. */
interface AttributeChanges {
    attributes?: Record<string, unknown> | null;
    old?: Record<string, unknown> | null;
}

/** Linha das fontes atividade/alteracoes (shape do ActivityResource). */
interface AtividadeRow {
    id: number;
    created_at: string | null;
    log_name: string | null;
    event: string | null;
    description: string | null;
    causer: UsuarioResumo | null;
    acting_for: UsuarioResumo | null;
    subject: { type: string; id: number | null; label: string | null } | null;
    result: string | null;
    rules_version: string | null;
    ip_address: string | null;
    channel: string | null;
    personal_data: boolean;
    attribute_changes: AttributeChanges | null;
}

/** Linha da fonte secundária de acessos (mapeamento mínimo do AccessLog). */
interface AcessoRow {
    id: number;
    created_at: string | null;
    event: string | null;
    usuario: string | null;
    email: string | null;
    ip_address: string | null;
    channel: string | null;
}

type AuditRow = AtividadeRow | AcessoRow;

interface Filtros {
    data_de: string;
    data_ate: string;
    usuario_id: string;
    usuario: string;
    entidade_tipo: string;
    entidade_id: string;
    log_name: string;
    event: string;
    resultado: string;
    per_page: number;
}

interface AuditoriaIndexProps {
    registros: {
        data: AuditRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    fonte: string;
    filtros: Filtros;
    fonteOptions: SelectOption[];
    perPageOptions: number[];
}

/** Campos de filtro textuais/data ecoados pelo backend (sem per_page). */
type FiltrosForm = Omit<Filtros, 'per_page'>;

const FILTER_KEYS: (keyof FiltrosForm)[] = [
    'data_de',
    'data_ate',
    'usuario_id',
    'usuario',
    'entidade_tipo',
    'entidade_id',
    'log_name',
    'event',
    'resultado',
];

/** Campos textuais com visita debounced (datas e fonte são imediatos). */
const TEXTO_KEYS: (keyof FiltrosForm)[] = [
    'usuario',
    'usuario_id',
    'entidade_tipo',
    'entidade_id',
    'log_name',
    'event',
    'resultado',
];

const URL_TRILHA = '/gestao/auditoria';

/** Formata a data-hora ISO para o padrão pt-BR (dd/mm/aaaa hh:mm). */
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

/**
 * Cor do badge do resultado da ação auditada. Heurística conservadora sobre o
 * texto cru (que é sempre exibido) — desconhecido fica neutro, nunca inventa
 * sucesso/erro.
 */
function resultadoColor(result: string | null): BadgeColor {
    if (!result) {
        return 'light';
    }

    const r = result.toLowerCase();
    const positivos = ['sucesso', 'permitido', 'deferid', 'aprovad', 'concl', 'reativ', 'ativo'];
    const negativos = ['bloquead', 'negad', 'indeferid', 'falha', 'erro', 'recusad', 'inativ', 'cancelad'];

    if (positivos.some((termo) => r.includes(termo))) {
        return 'success';
    }

    if (negativos.some((termo) => r.includes(termo))) {
        return 'error';
    }

    return 'light';
}

/** Coage um valor do diff a texto legível, sem despejar estruturas cruas. */
function valorLegivel(valor: unknown): string {
    if (valor === null || valor === undefined) {
        return '∅';
    }

    if (typeof valor === 'boolean') {
        return valor ? 'sim' : 'não';
    }

    if (typeof valor === 'string' || typeof valor === 'number') {
        return String(valor);
    }

    try {
        return JSON.stringify(valor);
    } catch {
        return '—';
    }
}

/** Monta a URL de export CSV preservando a fonte e os MESMOS filtros atuais. */
function buildExportHref(fonte: string, form: FiltrosForm): string {
    const params = new URLSearchParams();
    params.set('fonte', fonte);

    for (const key of FILTER_KEYS) {
        const valor = form[key];

        if (valor && valor.trim() !== '') {
            params.set(key, valor);
        }
    }

    params.set('formato', 'csv');

    return `/gestao/auditoria/export?${params.toString()}`;
}

export default function AuditoriaIndex({
    registros,
    fonte: fonteInicial,
    filtros,
    fonteOptions,
    perPageOptions,
}: AuditoriaIndexProps) {
    const [fonte, setFonte] = useState(fonteInicial);
    const [perPage, setPerPage] = useState(filtros.per_page);
    const [form, setForm] = useState<FiltrosForm>({
        data_de: filtros.data_de ?? '',
        data_ate: filtros.data_ate ?? '',
        usuario_id: filtros.usuario_id ?? '',
        usuario: filtros.usuario ?? '',
        entidade_tipo: filtros.entidade_tipo ?? '',
        entidade_id: filtros.entidade_id ?? '',
        log_name: filtros.log_name ?? '',
        event: filtros.event ?? '',
        resultado: filtros.resultado ?? '',
    });
    const [processing, setProcessing] = useState(false);

    const stateRef = useRef({ fonte, perPage, form });
    stateRef.current = { fonte, perPage, form };

    const hasMounted = useRef(false);

    const visitar = useCallback((state: { fonte: string; perPage: number; form: FiltrosForm }) => {
        const params: Record<string, string | number> = {
            fonte: state.fonte,
            per_page: state.perPage,
        };

        for (const key of FILTER_KEYS) {
            const valor = state.form[key];

            if (valor && valor.trim() !== '') {
                params[key] = valor;
            }
        }

        router.get(URL_TRILHA, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        });
    }, []);

    // Campos textuais: debounce para não disparar uma visita por tecla.
    useEffect(() => {
        if (!hasMounted.current) {
            return;
        }

        const timeout = setTimeout(() => visitar(stateRef.current), 350);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.usuario, form.usuario_id, form.entidade_tipo, form.entidade_id, form.log_name, form.event, form.resultado, visitar]);

    useEffect(() => {
        hasMounted.current = true;

        return () => {
            hasMounted.current = false;
        };
    }, []);

    function trocarFonte(novaFonte: string) {
        if (novaFonte === fonte) {
            return;
        }

        setFonte(novaFonte);
        visitar({ ...stateRef.current, fonte: novaFonte });
    }

    function trocarPerPage(novo: number) {
        setPerPage(novo);
        visitar({ ...stateRef.current, perPage: novo });
    }

    // Datas disparam visita imediata (igual aos selects do projeto).
    function setData(key: 'data_de' | 'data_ate', valor: string) {
        const proximo = { ...stateRef.current.form, [key]: valor };
        setForm(proximo);
        visitar({ ...stateRef.current, form: proximo });
    }

    // Texto só atualiza o estado local; o effect debounced visita.
    function setTexto(key: keyof FiltrosForm, valor: string) {
        setForm((anterior) => ({ ...anterior, [key]: valor }));
    }

    function limparFiltros() {
        const vazio: FiltrosForm = {
            data_de: '',
            data_ate: '',
            usuario_id: '',
            usuario: '',
            entidade_tipo: '',
            entidade_id: '',
            log_name: '',
            event: '',
            resultado: '',
        };
        setForm(vazio);
        visitar({ ...stateRef.current, form: vazio });
    }

    const filtrando = FILTER_KEYS.some((key) => (form[key] ?? '').trim() !== '');
    const exportHref = useMemo(() => buildExportHref(fonte, form), [fonte, form]);

    // O backend garante o shape por fonte; o cast é seguro e as células ainda
    // renderizam defensivamente.
    const linhas = Array.isArray(registros.data) ? registros.data : [];
    const fonteAtual = fonteOptions.find((opcao) => opcao.value === fonte)?.label ?? 'Trilha';

    return (
        <>
            <Head title="Trilha de auditoria" />
            <PageHeader title="Trilha de auditoria" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <Card>
                <CardHeader
                    title="Consulta da trilha de auditoria"
                    description="Registros imutáveis de quem fez o quê, quando, de onde e com qual resultado (RN-002). Consulta somente leitura, server-driven — a própria consulta é auditada."
                    actions={
                        <a
                            href={exportHref}
                            className="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 focus:outline-hidden"
                        >
                            <FileIcon className="size-4.5" />
                            Exportar CSV
                        </a>
                    }
                />
                <CardContent>
                    <div className="space-y-5">
                        {/* Seletor de fonte da trilha unificada */}
                        <div
                            role="tablist"
                            aria-label="Fonte da trilha"
                            className="flex flex-wrap gap-2"
                        >
                            {fonteOptions.map((opcao) => {
                                const ativa = opcao.value === fonte;

                                return (
                                    <button
                                        key={opcao.value}
                                        type="button"
                                        role="tab"
                                        aria-selected={ativa}
                                        onClick={() => trocarFonte(opcao.value)}
                                        className={`rounded-lg px-3 py-1.5 text-theme-sm font-medium transition-colors ${
                                            ativa
                                                ? 'bg-brand-500 text-white'
                                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                                        }`}
                                    >
                                        {opcao.label}
                                    </button>
                                );
                            })}
                        </div>

                        {/* Filtros (disparam visita Inertia — server-driven) */}
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            <FiltroCampo label="Período de">
                                <Input
                                    type="date"
                                    value={form.data_de}
                                    onChange={(event) => setData('data_de', event.target.value)}
                                    aria-label="Período de"
                                />
                            </FiltroCampo>
                            <FiltroCampo label="Período até">
                                <Input
                                    type="date"
                                    value={form.data_ate}
                                    onChange={(event) => setData('data_ate', event.target.value)}
                                    aria-label="Período até"
                                />
                            </FiltroCampo>
                            <FiltroCampo label="Usuário (nome)">
                                <Input
                                    type="text"
                                    value={form.usuario}
                                    onChange={(event) => setTexto('usuario', event.target.value)}
                                    placeholder="Nome do responsável"
                                    aria-label="Usuário"
                                />
                            </FiltroCampo>
                            <FiltroCampo label="Evento">
                                <Input
                                    type="text"
                                    value={form.event}
                                    onChange={(event) => setTexto('event', event.target.value)}
                                    placeholder="ex.: login, atualizado"
                                    aria-label="Evento"
                                />
                            </FiltroCampo>

                            {fonte !== 'acessos' && (
                                <>
                                    <FiltroCampo label="Entidade (tipo)">
                                        <Input
                                            type="text"
                                            value={form.entidade_tipo}
                                            onChange={(event) => setTexto('entidade_tipo', event.target.value)}
                                            placeholder="ex.: Cnae, User"
                                            aria-label="Tipo de entidade"
                                        />
                                    </FiltroCampo>
                                    <FiltroCampo label="Entidade (ID)">
                                        <Input
                                            type="text"
                                            value={form.entidade_id}
                                            onChange={(event) => setTexto('entidade_id', event.target.value)}
                                            placeholder="ID do registro"
                                            aria-label="ID da entidade"
                                        />
                                    </FiltroCampo>
                                    <FiltroCampo label="Log">
                                        <Input
                                            type="text"
                                            value={form.log_name}
                                            onChange={(event) => setTexto('log_name', event.target.value)}
                                            placeholder="ex.: auditoria, seguranca"
                                            aria-label="Log"
                                        />
                                    </FiltroCampo>
                                    <FiltroCampo label="Resultado">
                                        <Input
                                            type="text"
                                            value={form.resultado}
                                            onChange={(event) => setTexto('resultado', event.target.value)}
                                            placeholder="ex.: sucesso, bloqueado"
                                            aria-label="Resultado"
                                        />
                                    </FiltroCampo>
                                </>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-3">
                                {filtrando && (
                                    <button
                                        type="button"
                                        onClick={limparFiltros}
                                        className="text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                                    >
                                        Limpar filtros
                                    </button>
                                )}
                            </div>
                            <PerPageSelect value={perPage} options={perPageOptions} onChange={trocarPerPage} />
                        </div>

                        {fonte === 'acessos' ? (
                            <DataTable<AcessoRow>
                                columns={colunasAcessos}
                                rows={linhas as AcessoRow[]}
                                rowKey={(linha) => linha.id}
                                loading={processing}
                                skeletonRows={8}
                                density="compact"
                                emptyState={<TrilhaVazia fonte={fonteAtual} filtrando={filtrando} />}
                            />
                        ) : (
                            <DataTable<AtividadeRow>
                                columns={fonte === 'alteracoes' ? colunasAlteracoes : colunasAtividade}
                                rows={linhas as AtividadeRow[]}
                                rowKey={(linha) => linha.id}
                                loading={processing}
                                skeletonRows={8}
                                density="compact"
                                emptyState={<TrilhaVazia fonte={fonteAtual} filtrando={filtrando} />}
                            />
                        )}

                        <Pagination
                            links={registros.links}
                            meta={{ from: registros.from, to: registros.to, total: registros.total }}
                        />
                    </div>
                </CardContent>
            </Card>
        </>
    );
}

/** Campo de filtro com rótulo acessível acima do controle. */
function FiltroCampo({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="flex flex-col gap-1.5">
            <span className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">{label}</span>
            {children}
        </label>
    );
}

/** Estado vazio honesto da trilha (diferencia "sem registros" de "sem resultado de busca"). */
function TrilhaVazia({ fonte, filtrando }: { fonte: string; filtrando: boolean }) {
    return (
        <EmptyState
            title={filtrando ? 'Nenhum registro para os filtros' : 'Nenhum registro nesta fonte'}
            description={
                filtrando
                    ? 'Ajuste o período ou os filtros e tente novamente.'
                    : `Ainda não há registros em "${fonte}". A trilha é alimentada automaticamente conforme as ações ocorrem no sistema.`
            }
        />
    );
}

/** Identificação da ação (log + evento) com a descrição como apoio. */
function AcaoCell({ linha }: { linha: AtividadeRow }) {
    return (
        <div className="flex flex-col gap-1">
            <div className="flex flex-wrap items-center gap-1.5">
                {linha.log_name && (
                    <Badge color="light" size="sm">
                        {linha.log_name}
                    </Badge>
                )}
                <span className="text-theme-sm font-medium text-gray-700 dark:text-gray-200">{linha.event ?? '—'}</span>
                {linha.personal_data && (
                    <Badge color="warning" size="sm">
                        Dado pessoal
                    </Badge>
                )}
            </div>
            {linha.description && (
                <span className="text-theme-xs text-gray-400 dark:text-gray-500">{linha.description}</span>
            )}
        </div>
    );
}

/** Usuário responsável (causer); null = sistema. Mostra "em nome de" quando há. */
function UsuarioCell({ causer, actingFor }: { causer: UsuarioResumo | null; actingFor: UsuarioResumo | null }) {
    return (
        <div className="flex flex-col">
            <span className="text-gray-700 dark:text-gray-300">
                {causer?.nome ?? <span className="text-gray-400 dark:text-gray-500">Sistema</span>}
            </span>
            {actingFor?.nome && (
                <span className="text-theme-xs text-gray-400 dark:text-gray-500">em nome de {actingFor.nome}</span>
            )}
        </div>
    );
}

/** Entidade auditada (tipo legível + rótulo/ID quando há). */
function EntidadeCell({ subject }: { subject: AtividadeRow['subject'] }) {
    if (!subject) {
        return <span className="text-gray-400 dark:text-gray-500">—</span>;
    }

    return (
        <div className="flex flex-col">
            <span className="text-gray-700 dark:text-gray-300">{subject.type}</span>
            <span className="text-theme-xs text-gray-400 dark:text-gray-500">
                {subject.label ?? (subject.id !== null ? `#${subject.id}` : '—')}
            </span>
        </div>
    );
}

/** Diff estruturado da alteração (HU-098): campo: antigo → novo. */
function DiffCell({ changes }: { changes: AttributeChanges | null }) {
    if (!changes || typeof changes !== 'object') {
        return <span className="text-gray-400 dark:text-gray-500">—</span>;
    }

    const novos = changes.attributes && typeof changes.attributes === 'object' ? changes.attributes : {};
    const antigos = changes.old && typeof changes.old === 'object' ? changes.old : {};
    const campos = Object.keys(novos);

    if (campos.length === 0) {
        return <span className="text-gray-400 dark:text-gray-500">—</span>;
    }

    return (
        <ul className="space-y-1">
            {campos.map((campo) => (
                <li key={campo} className="text-theme-xs">
                    <span className="font-medium text-gray-600 dark:text-gray-300">{campo}:</span>{' '}
                    <span className="text-error-600 line-through dark:text-error-400">{valorLegivel(antigos[campo])}</span>{' '}
                    <span className="text-gray-400">→</span>{' '}
                    <span className="text-success-600 dark:text-success-400">{valorLegivel(novos[campo])}</span>
                </li>
            ))}
        </ul>
    );
}

const colunaData: ColumnDef<AtividadeRow> = {
    id: 'data',
    header: 'Data/hora',
    cellClassName: 'whitespace-nowrap text-gray-800 dark:text-white/90',
    cell: (linha) => formatarDataHora(linha.created_at),
};

const colunaAcao: ColumnDef<AtividadeRow> = {
    id: 'acao',
    header: 'Ação',
    cell: (linha) => <AcaoCell linha={linha} />,
};

const colunaUsuario: ColumnDef<AtividadeRow> = {
    id: 'usuario',
    header: 'Usuário',
    cell: (linha) => <UsuarioCell causer={linha.causer} actingFor={linha.acting_for} />,
};

const colunaEntidade: ColumnDef<AtividadeRow> = {
    id: 'entidade',
    header: 'Entidade',
    cell: (linha) => <EntidadeCell subject={linha.subject} />,
};

const colunaResultado: ColumnDef<AtividadeRow> = {
    id: 'resultado',
    header: 'Resultado',
    cellClassName: 'whitespace-nowrap',
    cell: (linha) =>
        linha.result ? (
            <Badge color={resultadoColor(linha.result)} size="sm">
                {linha.result}
            </Badge>
        ) : (
            <span className="text-gray-400 dark:text-gray-500">—</span>
        ),
};

const colunaVersao: ColumnDef<AtividadeRow> = {
    id: 'versao',
    header: 'Versão de regra',
    cellClassName: 'whitespace-nowrap text-gray-600 dark:text-gray-400',
    cell: (linha) => linha.rules_version ?? <span className="text-gray-400 dark:text-gray-500">—</span>,
};

const colunaAlteracoes: ColumnDef<AtividadeRow> = {
    id: 'alteracoes',
    header: 'Alterações',
    cell: (linha) => <DiffCell changes={linha.attribute_changes} />,
};

const colunasAtividade: ColumnDef<AtividadeRow>[] = [
    colunaData,
    colunaAcao,
    colunaUsuario,
    colunaEntidade,
    colunaResultado,
    colunaVersao,
];

const colunasAlteracoes: ColumnDef<AtividadeRow>[] = [
    colunaData,
    colunaAcao,
    colunaUsuario,
    colunaEntidade,
    colunaAlteracoes,
];

const colunasAcessos: ColumnDef<AcessoRow>[] = [
    {
        id: 'data',
        header: 'Data/hora',
        cellClassName: 'whitespace-nowrap text-gray-800 dark:text-white/90',
        cell: (linha) => formatarDataHora(linha.created_at),
    },
    {
        id: 'evento',
        header: 'Evento',
        cellClassName: 'whitespace-nowrap',
        cell: (linha) =>
            linha.event ? (
                <Badge color="light" size="sm">
                    {linha.event}
                </Badge>
            ) : (
                <span className="text-gray-400 dark:text-gray-500">—</span>
            ),
    },
    {
        id: 'usuario',
        header: 'Usuário',
        cell: (linha) => (
            <div className="flex flex-col">
                <span className="text-gray-700 dark:text-gray-300">
                    {linha.usuario ?? <span className="text-gray-400 dark:text-gray-500">—</span>}
                </span>
                {linha.email && (
                    <span className="text-theme-xs text-gray-400 dark:text-gray-500">{linha.email}</span>
                )}
            </div>
        ),
    },
    {
        id: 'ip',
        header: 'IP',
        cellClassName: 'whitespace-nowrap text-gray-600 dark:text-gray-400',
        cell: (linha) => linha.ip_address ?? '—',
    },
    {
        id: 'canal',
        header: 'Canal',
        cellClassName: 'whitespace-nowrap text-gray-600 dark:text-gray-400',
        cell: (linha) => linha.channel ?? '—',
    },
];

AuditoriaIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
