import { router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';
import Label from '@/components/form/label';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import type { SharedProps } from '@/types';

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
    codigo_louos: string;
    subcategoria: string;
    grupo: string;
    risco: string;
    ate_m2: string | null;
    enquadramento2: string | null;
    enquadramento3: string | null;
    acima_m2: string | null;
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
export default function AbaEnquadramento({ enquadramento }: { enquadramento: EnquadramentoData }) {
    const { versao, contagens, perguntas, regras, ramos, condicionantes, cnlu, notas, enquadramentos, rascunho } = enquadramento;
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-louos');
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
                                    className="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-800 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1 file:text-xs file:text-gray-700 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                />
                                {form.errors.arquivo && <p className="mt-1 text-theme-xs text-error-500">{form.errors.arquivo}</p>}
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

            <Card>
                <CardHeader
                    title="Enquadramentos"
                    description="Linha que o motor escolhe: CNAE, código LOUOS, faixas de área, risco, classificação e TLL."
                />
                <CardContent>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="text-gray-500 dark:text-gray-400">
                                    <th className="py-2 pr-3">CNAE</th>
                                    <th className="py-2 pr-3">LOUOS</th>
                                    <th className="py-2 pr-3">Subcategoria</th>
                                    <th className="py-2 pr-3">Risco</th>
                                    <th className="py-2 pr-3">Até m²</th>
                                    <th className="py-2 pr-3">Faixa 2</th>
                                    <th className="py-2 pr-3">Faixa 3</th>
                                    <th className="py-2 pr-3">Classificação</th>
                                    <th className="py-2">TLL</th>
                                </tr>
                            </thead>
                            <tbody>
                                {enquadramentos.data.map((linha) => (
                                    <tr key={`${linha.cnae}-${linha.codigo_louos}-${linha.subcategoria}`} className="border-t border-gray-200 dark:border-gray-800">
                                        <td className="py-2 pr-3">{linha.cnae}</td>
                                        <td className="py-2 pr-3">{linha.codigo_louos}</td>
                                        <td className="py-2 pr-3">{linha.subcategoria}</td>
                                        <td className="py-2 pr-3">{linha.risco}</td>
                                        <td className="py-2 pr-3">{linha.ate_m2 ?? '—'}</td>
                                        <td className="py-2 pr-3">{linha.enquadramento2 ?? '—'}</td>
                                        <td className="py-2 pr-3">{linha.enquadramento3 ?? '—'}</td>
                                        <td className="py-2 pr-3">{linha.classificacao ?? '—'}</td>
                                        <td className="py-2">{linha.codigo_tll ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="mt-4">
                        <Pagination
                            links={enquadramentos.links}
                            meta={{ from: enquadramentos.from, to: enquadramentos.to, total: enquadramentos.total }}
                        />
                    </div>
                </CardContent>
            </Card>
                </>
            )}
        </div>
    );
}
