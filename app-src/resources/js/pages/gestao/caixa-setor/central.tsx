import { Head, router, useForm } from '@inertiajs/react';
import { type ReactNode, useMemo, useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ProgressBar from '@/components/ui/progress-bar';
import GestaoLayout from '@/layouts/gestao-layout';

interface ProcessoSelecionado {
    id: number;
    bap: string | null;
    protocol_number: string | null;
    imovel: string;
    analysis_stage_label: string | null;
}

interface AnalistaCarga {
    analista_id: number;
    analista: string;
    total: number;
    por_grupo: Record<string, number>;
}

interface CentralProps {
    processos: ProcessoSelecionado[];
    analistas: AnalistaCarga[];
    totalSelecionados: number;
    totalEmAnalise: number;
}

type Ordenacao = 'menor' | 'maior' | 'nome';

export default function CentralDistribuicao({ processos, analistas, totalSelecionados, totalEmAnalise }: CentralProps) {
    const [busca, setBusca] = useState('');
    const [ordenacao, setOrdenacao] = useState<Ordenacao>('menor');
    const [selecionado, setSelecionado] = useState<number | null>(null);

    const form = useForm<{ request_ids: number[]; analista_id: string }>({
        request_ids: processos.map((p) => p.id),
        analista_id: '',
    });

    const maiorCarga = useMemo(
        () => analistas.reduce((max, a) => Math.max(max, a.total), 0),
        [analistas],
    );

    const listaVisivel = useMemo(() => {
        const termo = busca.trim().toLowerCase();
        const filtrados = termo === ''
            ? analistas
            : analistas.filter((a) => a.analista.toLowerCase().includes(termo));

        const ordenados = [...filtrados];
        ordenados.sort((a, b) => {
            if (ordenacao === 'nome') {
                return a.analista.localeCompare(b.analista, 'pt-BR');
            }
            if (ordenacao === 'maior') {
                return b.total - a.total;
            }
            return a.total - b.total;
        });

        return ordenados;
    }, [analistas, busca, ordenacao]);

    const escolhido = analistas.find((a) => a.analista_id === selecionado) ?? null;

    function confirmar() {
        if (selecionado === null) {
            return;
        }

        form.transform((data) => ({ ...data, analista_id: String(selecionado) }));
        form.post('/gestao/caixa-setor/distribuir', {
            preserveScroll: true,
            onSuccess: () => router.visit('/gestao/caixa-setor'),
        });
    }

    return (
        <>
            <Head title="Central de distribuição" />
            <PageHeader
                title="Central de distribuição"
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Caixa do setor', href: '/gestao/caixa-setor' },
                ]}
            />

            <div className="space-y-6">
                <Card>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <ResumoItem rotulo="Processos selecionados" valor={totalSelecionados} />
                            <ResumoItem rotulo="Analistas disponíveis" valor={analistas.length} />
                            <ResumoItem rotulo="Processos em análise no setor" valor={totalEmAnalise} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        title="Escolha o analista"
                        description="A carga atual apoia a decisão. A escolha do destinatário é sua — o sistema não distribui automaticamente."
                    />
                    <CardContent>
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div className="w-full sm:max-w-xs">
                                <Input
                                    id="busca-analista"
                                    value={busca}
                                    onChange={(e) => setBusca(e.target.value)}
                                    placeholder="Pesquisar analista..."
                                    aria-label="Pesquisar analista"
                                />
                            </div>
                            <label className="flex items-center gap-2 text-theme-sm text-gray-500 dark:text-gray-400">
                                Ordenar
                                <select
                                    value={ordenacao}
                                    onChange={(e) => setOrdenacao(e.target.value as Ordenacao)}
                                    className="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                >
                                    <option value="menor">Menor carga</option>
                                    <option value="maior">Maior carga</option>
                                    <option value="nome">Ordem alfabética</option>
                                </select>
                            </label>
                        </div>

                        <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                            {listaVisivel.map((a) => {
                                const ativo = a.analista_id === selecionado;
                                const percentual = maiorCarga > 0 ? (a.total / maiorCarga) * 100 : 0;
                                const grupos = Object.entries(a.por_grupo).filter(([, n]) => n > 0);

                                return (
                                    <li key={a.analista_id}>
                                        <button
                                            type="button"
                                            onClick={() => setSelecionado(a.analista_id)}
                                            aria-pressed={ativo}
                                            className={`flex w-full flex-col gap-2 px-3 py-3 text-left transition-colors sm:flex-row sm:items-center sm:gap-4 ${
                                                ativo ? 'bg-brand-50 dark:bg-brand-500/10' : 'hover:bg-gray-50 dark:hover:bg-white/[0.03]'
                                            }`}
                                        >
                                            <span className="min-w-[200px] flex-1 font-medium text-gray-800 dark:text-white/90">
                                                {a.analista}
                                            </span>
                                            <span className="flex min-w-[240px] flex-1 items-center gap-3">
                                                <span className="inline-flex min-w-[3.5rem] justify-center rounded-full bg-gray-100 px-2.5 py-0.5 text-theme-sm font-semibold tabular-nums text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                                    {a.total}
                                                </span>
                                                <span className="w-full max-w-[220px]">
                                                    <ProgressBar
                                                        value={percentual}
                                                        tone={a.total >= maiorCarga && maiorCarga > 0 ? 'warning' : 'brand'}
                                                        label={`Carga de ${a.analista}: ${a.total} processos`}
                                                    />
                                                </span>
                                            </span>
                                            <span className="min-w-[160px] text-theme-xs text-gray-500 dark:text-gray-400">
                                                {grupos.length > 0
                                                    ? grupos.map(([g, n]) => `${n} ${g}`).join(' · ')
                                                    : 'sem carga ativa'}
                                            </span>
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    </CardContent>
                </Card>

                {escolhido && (
                    <Card>
                        <CardContent>
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                        Você está encaminhando <strong>{totalSelecionados}</strong> processo(s) para
                                    </p>
                                    <p className="text-lg font-semibold text-gray-800 dark:text-white/90">{escolhido.analista}</p>
                                    <p className="mt-1 text-theme-sm text-gray-600 dark:text-gray-300">
                                        Carga atual: <strong className="tabular-nums">{escolhido.total}</strong>
                                        {'  +  '}
                                        Novos: <strong className="tabular-nums">{totalSelecionados}</strong>
                                        {'  =  '}
                                        Após envio: <strong className="tabular-nums">{escolhido.total + totalSelecionados}</strong>
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <Button variant="outline" onClick={() => router.visit('/gestao/caixa-setor')} disabled={form.processing}>
                                        Cancelar
                                    </Button>
                                    <Button variant="primary" onClick={confirmar} loading={form.processing}>
                                        Confirmar envio
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function ResumoItem({ rotulo, valor }: { rotulo: string; valor: number }) {
    return (
        <div className="rounded-xl border border-gray-100 p-4 dark:border-gray-800">
            <p className="text-theme-xs tracking-wide text-gray-400 uppercase">{rotulo}</p>
            <p className="mt-1 text-2xl font-semibold tabular-nums text-gray-800 dark:text-white/90">{valor}</p>
        </div>
    );
}

CentralDistribuicao.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
