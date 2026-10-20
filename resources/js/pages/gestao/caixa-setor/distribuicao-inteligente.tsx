import { Head, router, useForm } from '@inertiajs/react';
import { type ReactNode, useMemo, useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Select from '@/components/form/select';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import GestaoLayout from '@/layouts/gestao-layout';

interface ProcessoSugerido {
    id: number;
    bap: string | null;
    protocol_number: string | null;
    elegiveis: number[];
}

interface Alocacao {
    analista_id: number;
    analista: string;
    atual: number;
    recebera: number;
    apos: number;
    processos: ProcessoSugerido[];
}

interface NaoAlocado {
    id: number;
    bap: string | null;
    protocol_number: string | null;
    motivo: string;
}

interface Props {
    criterio: string;
    alocacoes: Alocacao[];
    naoAlocados: NaoAlocado[];
    totalSelecionados: number;
    retorno: string;
}

function rotulo(processo: { bap: string | null; protocol_number: string | null; id: number }): string {
    return processo.bap ?? processo.protocol_number ?? `#${processo.id}`;
}

export default function DistribuicaoInteligente({ criterio, alocacoes, naoAlocados, totalSelecionados, retorno }: Props) {
    const urlDaCaixa = retorno === '' ? '/gestao/caixa-setor' : `/gestao/caixa-setor?${retorno}`;
    const [proposta] = useState<Alocacao[]>(alocacoes);
    const [atual, setAtual] = useState<Alocacao[]>(alocacoes);
    const [abertos, setAbertos] = useState<number[]>([]);

    const form = useForm<{
        alocacoes: { analista_id: number; request_ids: number[] }[];
        origem: string;
        proposta: { analista_id: number; request_ids: number[] }[];
        alteracoes: { request_id: number; de_analista_id: number; para_analista_id: number }[];
    }>({
        alocacoes: [],
        origem: 'inteligente',
        proposta: [],
        alteracoes: [],
    });

    const nomes = useMemo(() => {
        const mapa = new Map<number, string>();
        for (const linha of proposta) {
            mapa.set(linha.analista_id, linha.analista);
        }
        return mapa;
    }, [proposta]);

    const totalRecebera = atual.reduce((soma, linha) => soma + linha.processos.length, 0);
    const podeConfirmar = naoAlocados.length === 0 && totalRecebera === totalSelecionados && totalSelecionados > 0;

    function alternar(id: number) {
        setAbertos((lista) => (lista.includes(id) ? lista.filter((item) => item !== id) : [...lista, id]));
    }

    function recalcular() {
        setAtual(proposta.map((linha) => ({ ...linha, processos: [...linha.processos] })));
    }

    function mover(processoId: number, paraId: number) {
        setAtual((linhas) => {
            const processo = linhas
                .flatMap((linha) => linha.processos)
                .find((item) => item.id === processoId);

            if (!processo || !processo.elegiveis.includes(paraId)) {
                return linhas;
            }

            const sem = linhas.map((linha) => ({
                ...linha,
                processos: linha.processos.filter((item) => item.id !== processoId),
            }));

            return sem.map((linha) => {
                const processos = linha.analista_id === paraId ? [...linha.processos, processo] : linha.processos;
                return { ...linha, recebera: processos.length, apos: linha.atual + processos.length, processos };
            });
        });
    }

    function confirmar() {
        if (!podeConfirmar) {
            return;
        }

        const original = new Map<number, number>();
        for (const linha of proposta) {
            for (const processo of linha.processos) {
                original.set(processo.id, linha.analista_id);
            }
        }

        const alteracoes: { request_id: number; de_analista_id: number; para_analista_id: number }[] = [];
        for (const linha of atual) {
            for (const processo of linha.processos) {
                const de = original.get(processo.id);
                if (de !== undefined && de !== linha.analista_id) {
                    alteracoes.push({
                        request_id: processo.id,
                        de_analista_id: de,
                        para_analista_id: linha.analista_id,
                    });
                }
            }
        }

        form.transform(() => ({
            origem: 'inteligente',
            retorno,
            alocacoes: atual
                .filter((linha) => linha.processos.length > 0)
                .map((linha) => ({
                    analista_id: linha.analista_id,
                    request_ids: linha.processos.map((processo) => processo.id),
                })),
            proposta: proposta
                .filter((linha) => linha.processos.length > 0)
                .map((linha) => ({
                    analista_id: linha.analista_id,
                    request_ids: linha.processos.map((processo) => processo.id),
                })),
            alteracoes,
        }));

        form.post('/gestao/caixa-setor/distribuir-lote', { preserveScroll: true });
    }

    return (
        <>
            <Head title="Distribuição inteligente em lote" />
            <PageHeader
                title="Distribuição inteligente em lote"
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Caixa do setor', href: urlDaCaixa },
                ]}
            />

            <div className="space-y-6">
                <Card>
                    <CardContent>
                        <p className="text-theme-sm text-gray-700 dark:text-gray-300">{criterio}</p>
                        <p className="mt-2 text-lg font-semibold text-gray-800 dark:text-white/90">
                            {totalSelecionados} processo(s) selecionado(s)
                        </p>
                        <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                            A proposta não tramita sozinha. Revise e confirme para encaminhar.
                        </p>
                    </CardContent>
                </Card>

                {naoAlocados.length > 0 && (
                    <Card>
                        <CardHeader title="Processos sem analista elegível" />
                        <CardContent>
                            <ul className="space-y-1 text-theme-sm text-gray-700 dark:text-gray-300">
                                {naoAlocados.map((processo) => (
                                    <li key={processo.id}>
                                        {rotulo(processo)} — {processo.motivo}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader title="Proposta" description="Carga atual, o que cada analista receberia e o total depois da distribuição." />
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-theme-sm">
                                <thead className="text-theme-xs tracking-wide text-gray-400 uppercase">
                                    <tr>
                                        <th className="py-2 pr-3">Analista</th>
                                        <th className="py-2 pr-3 text-right">Atual</th>
                                        <th className="py-2 pr-3 text-right">Receberá</th>
                                        <th className="py-2 text-right">Após</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {atual.map((linha) => (
                                        <tr key={linha.analista_id} className="border-t border-gray-100 dark:border-gray-800">
                                            <td className="py-3 pr-3 font-medium text-gray-800 dark:text-white/90">{linha.analista}</td>
                                            <td className="py-3 pr-3 text-right tabular-nums">{linha.atual}</td>
                                            <td className="py-3 pr-3 text-right tabular-nums">{linha.processos.length}</td>
                                            <td className="py-3 text-right tabular-nums">{linha.atual + linha.processos.length}</td>
                                        </tr>
                                    ))}
                                    <tr className="border-t border-gray-200 font-semibold dark:border-gray-700">
                                        <td className="py-3 pr-3" colSpan={2}>Total</td>
                                        <td className="py-3 pr-3 text-right tabular-nums">{totalRecebera}</td>
                                        <td />
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <ul className="mt-6 divide-y divide-gray-100 dark:divide-gray-800">
                            {atual.map((linha) => {
                                const aberto = abertos.includes(linha.analista_id);
                                return (
                                    <li key={linha.analista_id} className="py-3">
                                        <button
                                            type="button"
                                            className="text-theme-sm font-medium text-brand-600 dark:text-brand-400"
                                            aria-expanded={aberto}
                                            onClick={() => alternar(linha.analista_id)}
                                        >
                                            {aberto ? 'Ocultar' : 'Ver'} processos de {linha.analista} ({linha.processos.length})
                                        </button>
                                        {aberto && (
                                            <ul className="mt-2 space-y-2">
                                                {linha.processos.map((processo) => (
                                                    <li key={processo.id} className="flex flex-wrap items-center justify-between gap-3">
                                                        <span className="text-theme-sm text-gray-700 dark:text-gray-300">{rotulo(processo)}</span>
                                                        <label className="flex items-center gap-2 text-theme-xs text-gray-500">
                                                            Destinatário
                                                            <span className="w-56">
                                                                <Select
                                                                    hidePlaceholder
                                                                    ariaLabel={`Destinatário de ${rotulo(processo)}`}
                                                                    value={String(linha.analista_id)}
                                                                    onChange={(valor) => mover(processo.id, Number(valor))}
                                                                    options={processo.elegiveis.map((id) => ({
                                                                        value: String(id),
                                                                        label: nomes.get(id) ?? `Analista ${id}`,
                                                                    }))}
                                                                />
                                                            </span>
                                                        </label>
                                                    </li>
                                                ))}
                                                {linha.processos.length === 0 && (
                                                    <li className="text-theme-sm text-gray-400">Nenhum processo nesta proposta.</li>
                                                )}
                                            </ul>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    </CardContent>
                </Card>

                <div className="flex flex-wrap justify-end gap-3">
                    <Button variant="outline" onClick={() => router.visit(urlDaCaixa)} disabled={form.processing}>
                        Cancelar
                    </Button>
                    <Button variant="outline" onClick={recalcular} disabled={form.processing}>
                        Recalcular
                    </Button>
                    <Button variant="primary" onClick={confirmar} loading={form.processing} disabled={!podeConfirmar}>
                        Confirmar distribuição
                    </Button>
                </div>
            </div>
        </>
    );
}

DistribuicaoInteligente.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
