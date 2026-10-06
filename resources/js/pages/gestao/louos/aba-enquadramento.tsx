import FieldError from '@/components/form/field-error';
import { router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import TableToolbar from '@/components/ui/data-table/table-toolbar';
import type { ColumnDef } from '@/components/ui/data-table/types';
import { useServerTable } from '@/components/ui/data-table/use-server-table';
import EmptyState from '@/components/ui/empty-state';
import Label from '@/components/form/label';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import type { SharedProps } from '@/types';
import { apresentacaoClassificacao, apresentacaoRisco, faixasDaLinha } from './faixas-enquadramento';

interface Pergunta {
    numero: number;
    texto: string;
    referencia: string | null;
}

interface Pendencia {
    regra: number | null;
    motivo: string;
}

interface Regra {
    numero: number;
    tratamento: string;
    referencia: string | null;
}

interface Ramo {
    regra: number;
    pergunta: number | null;
    resposta: boolean | null;
    faixa: string | null;
    tipo_dirige: boolean | null;
    codigo_louos: string | null;
    fluxo: string;
    bloqueia: boolean;
}

interface Condicionante {
    numero: number;
    texto: string;
    observacao: string | null;
}

interface Cnlu {
    codigo: string;
    texto: string;
}

interface Nota {
    chave: string;
    texto: string;
}

interface EnquadramentoLinha {
    cnae: string;
    denominacao: string | null;
    codigo_louos: string;
    denominacao_louos: string | null;
    subcategoria: string;
    grupo: string;
    risco: string;
    ate_m2: string | number | null;
    enquadramento2: string | null;
    ate_m2_2: string | number | null;
    enquadramento3: string | null;
    acima_m2: string | number | null;
    classificacao: string | null;
    codigo_tll: string | null;
    regra: number | null;
}

export interface EnquadramentoData {
    versao: string | null;
    contagens: {
        cnaes: number;
        enquadramentos: number;
        perguntas: number;
        regras: number;
        ramos: number;
        condicionantes: number;
        bindings: number;
    };
    perguntas: Pergunta[];
    regras: Regra[];
    ramos: Ramo[];
    condicionantes: Condicionante[];
    cnlu: Cnlu[];
    notas: Nota[];
    enquadramentos: {
        data: EnquadramentoLinha[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    rascunho: {
        id: number;
        versao: string;
        arquivo: string | null;
        autor_id: number | null;
        pendencias: Pendencia[];
    } | null;
}

/**
 * Aba Enquadramento do hub Motor de regras — a planilha de tratamento vigente
 * (perguntas, enquadramento, risco e TLL), migrada da tela "Planilha de
 * regras". Quem permite ou proíbe na zona continua sendo o Quadro 10; na via,
 * o Quadro 11A.
 */
function comoLista<T>(valor: T[] | Record<string, T> | null | undefined): T[] {
    if (Array.isArray(valor)) {
        return valor;
    }

    if (valor && typeof valor === 'object') {
        return Object.values(valor);
    }

    return [];
}

function CelulaCodigo({ codigo, nome }: { codigo: string; nome: string | null }) {
    return (
        <div className="max-w-64">
            <div className="font-medium whitespace-nowrap text-gray-800 dark:text-white/90">{codigo}</div>
            {nome && <p className="mt-0.5 line-clamp-2 text-theme-xs text-gray-500 dark:text-gray-400">{nome}</p>}
        </div>
    );
}

function CelulaFaixas({ linha }: { linha: EnquadramentoLinha }) {
    const faixas = faixasDaLinha(linha);

    if (faixas.length === 0) {
        return <>—</>;
    }

    return (
        <ul className="flex min-w-52 flex-col gap-1">
            {faixas.map((faixa) => (
                <li key={`${faixa.codigo}-${faixa.limite ?? 'qualquer'}`} className="flex items-baseline gap-2">
                    <span className="w-16 shrink-0 font-medium text-gray-800 dark:text-white/90">{faixa.codigo}</span>
                    <span className="text-theme-xs tabular-nums text-gray-500 dark:text-gray-400">
                        {faixa.limite ?? 'qualquer área'}
                    </span>
                </li>
            ))}
        </ul>
    );
}

const colunasEnquadramento: ColumnDef<EnquadramentoLinha>[] = [
    {
        id: 'cnae',
        header: 'CNAE',
        cell: (linha) => <CelulaCodigo codigo={linha.cnae} nome={linha.denominacao} />,
    },
    {
        id: 'louos',
        header: 'LOUOS',
        cell: (linha) => <CelulaCodigo codigo={linha.codigo_louos} nome={linha.denominacao_louos} />,
    },
    {
        id: 'risco',
        header: 'Risco',
        cell: (linha) => {
            const risco = apresentacaoRisco(linha.risco);

            return (
                <Badge color={risco.cor} size="sm">
                    {risco.rotulo}
                </Badge>
            );
        },
    },
    {
        id: 'faixas',
        header: 'Faixas de área',
        cell: (linha) => <CelulaFaixas linha={linha} />,
    },
    {
        id: 'classificacao',
        header: 'Classificação',
        cell: (linha) => {
            const classificacao = apresentacaoClassificacao(linha.classificacao);

            if (!classificacao) {
                return '—';
            }

            return (
                <Badge color={classificacao.cor} size="sm">
                    {classificacao.rotulo}
                </Badge>
            );
        },
    },
    {
        id: 'tll',
        header: 'TLL',
        cellClassName: 'font-medium tabular-nums whitespace-nowrap text-gray-800 dark:text-white/90',
        cell: (linha) => linha.codigo_tll ?? '—',
    },
];

export default function AbaEnquadramento({
    enquadramento,
    filtros,
    perPageOptions,
}: {
    enquadramento: EnquadramentoData;
    filtros: { search: string; per_page: number };
    perPageOptions: number[];
}) {
    const { versao, contagens } = enquadramento;
    const perguntas = comoLista(enquadramento.perguntas);
    const regras = comoLista(enquadramento.regras);
    const ramos = comoLista(enquadramento.ramos);
    const condicionantes = comoLista(enquadramento.condicionantes);
    const cnlu = comoLista(enquadramento.cnlu);
    const notas = comoLista(enquadramento.notas);
    const paginado = enquadramento.enquadramentos;
    const enquadramentos = {
        data: comoLista<EnquadramentoLinha>(paginado?.data),
        links: paginado?.links ?? [],
        from: paginado?.from ?? null,
        to: paginado?.to ?? null,
        total: paginado?.total ?? 0,
    };
    const rascunho = enquadramento.rascunho
        ? { ...enquadramento.rascunho, pendencias: comoLista(enquadramento.rascunho.pendencias) }
        : null;
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-louos');
    const table = useServerTable({
        url: '/gestao/louos',
        initialSearch: filtros.search,
        initialSort: { column: 'cnae', direction: 'asc' },
        initialPerPage: filtros.per_page,
        initialFilters: { aba: 'enquadramento' },
    });
    const buscando = table.search.trim() !== '';
    const form = useForm<{ arquivo: File | null }>({ arquivo: null });

    function importar(event: FormEvent) {
        event.preventDefault();
        form.post('/gestao/louos/enquadramento/importar', { forceFormData: true, preserveScroll: true });
    }

    return (
        <div className="flex flex-col gap-6">
            {canMaintain && (
                <Card>
                    <CardHeader
                        title="Importar planilha"
                        description="O arquivo .xlsx neste layout abre um rascunho. O mesmo conteúdo não cria outra versão. Quem importou pode publicar."
                    />
                    <CardContent>
                        <form onSubmit={importar} className="flex flex-col gap-4">
                            <div>
                                <Label htmlFor="planilha-arquivo" required>
                                    Planilha de regras
                                </Label>
                                <input
                                    id="planilha-arquivo"
                                    type="file"
                                    accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                    onChange={(event) => form.setData('arquivo', event.target.files?.[0] ?? null)}
                                    className="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-800 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1 file:text-xs file:text-gray-700 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:scheme-dark dark:file:bg-white/10 dark:file:text-gray-200"
                                />
                                {form.errors.arquivo && <FieldError>{form.errors.arquivo}</FieldError>}
                            </div>
                            <button
                                type="submit"
                                disabled={form.processing || form.data.arquivo === null}
                                className="w-fit rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
                            >
                                Importar
                            </button>
                        </form>
                    </CardContent>
                </Card>
            )}

            {rascunho !== null && (
                <Card>
                    <CardHeader
                        title={`Rascunho ${rascunho.versao}`}
                        description={rascunho.arquivo ?? 'Rascunho aguardando publicação.'}
                    />
                    <CardContent>
                        <div className="flex flex-col gap-4">
                            {rascunho.pendencias.length > 0 && (
                                <ul className="flex flex-col gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    {rascunho.pendencias.map((pendencia) => (
                                        <li key={`${pendencia.regra}-${pendencia.motivo}`}>
                                            {pendencia.regra !== null ? `Regra ${pendencia.regra}. ` : ''}
                                            {pendencia.motivo}
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {canMaintain && (
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.put(
                                            `/gestao/louos/enquadramento/versoes/${rascunho.id}/publicar`,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                    className="w-fit rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-800 hover:bg-gray-50 dark:border-gray-700 dark:text-white/90 dark:hover:bg-white/5"
                                >
                                    Publicar rascunho
                                </button>
                            )}
                        </div>
                    </CardContent>
                </Card>
            )}

            {versao === null ? (
                <EmptyState
                    title="Planilha sem versão vigente"
                    description="Não há versão publicada da planilha de tratamento. O enquadramento fica pendente até a carga versionada."
                />
            ) : (
                <>
            <Card>
                <CardHeader
                    title="Versão vigente"
                    description="Fonte do enquadramento de uso. Não substitui os Quadros 10 e 11A."
                />
                <CardContent>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge color="success">{versao}</Badge>
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            {contagens.cnaes} CNAEs · {contagens.enquadramentos} enquadramentos · {contagens.regras} regras ·{' '}
                            {contagens.ramos} ramos · {contagens.condicionantes} condicionantes
                        </span>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    title="Enquadramentos"
                    description="Cada linha é o uso que o motor escolhe: CNAE, LOUOS e o corte de área de cada faixa."
                />
                <CardContent>
                    <div className="flex flex-col gap-4">
                        <TableToolbar
                            search={{
                                value: table.search,
                                onChange: table.setSearch,
                                placeholder: 'CNAE, atividade, LOUOS ou uso',
                                label: 'Buscar enquadramentos',
                            }}
                            actions={
                                <PerPageSelect
                                    value={table.perPage}
                                    options={perPageOptions}
                                    onChange={table.setPerPage}
                                />
                            }
                        />
                        <DataTable
                            columns={colunasEnquadramento}
                            rows={enquadramentos.data}
                            rowKey={(linha) => `${linha.cnae}-${linha.codigo_louos}-${linha.subcategoria}`}
                            loading={table.processing}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title={buscando ? 'Nenhum enquadramento para a busca' : 'Nenhuma linha nesta versão'}
                                    description={
                                        buscando
                                            ? 'Tente o código do CNAE, o nome da atividade ou o código LOUOS.'
                                            : 'A versão vigente não tem linhas de enquadramento.'
                                    }
                                />
                            }
                        />
                        <Pagination
                            links={enquadramentos.links}
                            meta={{ from: enquadramentos.from, to: enquadramentos.to, total: enquadramentos.total }}
                        />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader title="Perguntas" description="Perguntas que escolhem o ramo de cada CNAE." />
                <CardContent>
                    {perguntas.length === 0 ? (
                        <p className="text-sm text-gray-500 dark:text-gray-400">Nenhuma pergunta nesta versão.</p>
                    ) : (
                        <ol className="flex flex-col gap-3">
                            {perguntas.map((pergunta) => (
                                <li key={pergunta.numero} className="text-sm text-gray-800 dark:text-white/90">
                                    <span className="font-medium">P{pergunta.numero}.</span> {pergunta.texto}
                                    {pergunta.referencia && (
                                        <span className="mt-1 block text-gray-500 dark:text-gray-400">
                                            {pergunta.referencia}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ol>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader title="Regras" description="Texto de cada regra da versão vigente. A Regra 45 não existe na planilha." />
                <CardContent>
                    <ol className="flex flex-col gap-4">
                        {regras.map((regra) => (
                            <li key={regra.numero} className="text-sm text-gray-800 dark:text-white/90">
                                <span className="font-medium">R{regra.numero}.</span>
                                <span className="mt-1 block whitespace-pre-wrap">{regra.tratamento}</span>
                                {regra.referencia && (
                                    <span className="mt-1 block text-gray-500 dark:text-gray-400">{regra.referencia}</span>
                                )}
                            </li>
                        ))}
                    </ol>
                </CardContent>
            </Card>

            <Card>
                <CardHeader title="Ramos" description="Pergunta, resposta, faixa, tipo de imóvel, código LOUOS e fluxo que o motor casa." />
                <CardContent>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="text-gray-500 dark:text-gray-400">
                                    <th className="py-2 pr-3">Regra</th>
                                    <th className="py-2 pr-3">Pergunta</th>
                                    <th className="py-2 pr-3">Resposta</th>
                                    <th className="py-2 pr-3">Faixa</th>
                                    <th className="py-2 pr-3">Tipo</th>
                                    <th className="py-2 pr-3">LOUOS</th>
                                    <th className="py-2 pr-3">Fluxo</th>
                                    <th className="py-2">Bloqueia</th>
                                </tr>
                            </thead>
                            <tbody>
                                {ramos.map((ramo, indice) => (
                                    <tr key={`${ramo.regra}-${indice}`} className="border-t border-gray-200 dark:border-gray-800">
                                        <td className="py-2 pr-3">{ramo.regra}</td>
                                        <td className="py-2 pr-3">{ramo.pergunta ?? '—'}</td>
                                        <td className="py-2 pr-3">{ramo.resposta === null ? '—' : ramo.resposta ? 'Sim' : 'Não'}</td>
                                        <td className="py-2 pr-3">{ramo.faixa ?? '—'}</td>
                                        <td className="py-2 pr-3">{ramo.tipo_dirige ? 'Dirige regra' : '—'}</td>
                                        <td className="py-2 pr-3">{ramo.codigo_louos ?? '—'}</td>
                                        <td className="py-2 pr-3">{ramo.fluxo}</td>
                                        <td className="py-2">{ramo.bloqueia ? 'Sim' : 'Não'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader title="Condicionantes" description="Textos que acompanham a decisão quando a linha do CNAE marca o número." />
                <CardContent>
                    {condicionantes.length === 0 ? (
                        <p className="text-sm text-gray-500 dark:text-gray-400">Nenhuma condicionante nesta versão.</p>
                    ) : (
                        <ol className="flex flex-col gap-3">
                            {condicionantes.map((item) => (
                                <li key={item.numero} className="text-sm text-gray-800 dark:text-white/90">
                                    <span className="font-medium">{item.numero}.</span> {item.texto}
                                </li>
                            ))}
                        </ol>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader title="Análise CNLU" description="Códigos que o motor manda para análise quando o subgrupo casa com a lista." />
                <CardContent>
                    {cnlu.length === 0 ? (
                        <p className="text-sm text-gray-500 dark:text-gray-400">Nenhum código CNLU nesta versão.</p>
                    ) : (
                        <ul className="flex flex-col gap-2 text-sm text-gray-800 dark:text-white/90">
                            {cnlu.map((item) => (
                                <li key={item.codigo}>
                                    <span className="font-medium">{item.codigo}.</span> {item.texto}
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>

            {notas.length > 0 && (
                <Card>
                    <CardHeader title="Notas da planilha" description="Condicionante geral e pergunta de regra geral." />
                    <CardContent>
                        <ul className="flex flex-col gap-3 text-sm text-gray-800 dark:text-white/90">
                            {notas.map((nota) => (
                                <li key={nota.chave}>
                                    <span className="font-medium">{nota.chave}.</span>
                                    <span className="mt-1 block whitespace-pre-wrap">{nota.texto}</span>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            )}

                </>
            )}
        </div>
    );
}
