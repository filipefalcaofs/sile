import { Head, router, useForm } from '@inertiajs/react';
import { type ReactNode, useMemo, useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ProgressBar from '@/components/ui/progress-bar';
import Select from '@/components/form/select';
import GestaoLayout from '@/layouts/gestao-layout';
import { alocarPorQuantidade } from './alocar-lote';
import { escolhaDeSetor, type SetorDoAnalista } from './setor-destino';

interface ProcessoSelecionado {
    id: number;
    sector_id: number | null;
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
    setores: SetorDoAnalista[];
    perfis: string[];
}

interface HistoricoItem {
    em: string | null;
    ator: string;
    analista: string;
    qtd: number;
}

interface CentralProps {
    processos: ProcessoSelecionado[];
    analistas: AnalistaCarga[];
    totalSelecionados: number;
    totalEmAnalise: number;
    historico: HistoricoItem[];
    distribuiEmQualquerSetor: boolean;
}

type Ordenacao = 'menor' | 'maior' | 'nome';

function IdentidadeDoAnalista({ analista, className = '' }: { analista: AnalistaCarga; className?: string }) {
    const perfil = analista.perfis.length > 0 ? analista.perfis.join(', ') : 'Sem perfil';
    const setores = analista.setores.length > 0 ? analista.setores.map((setor) => setor.name).join(', ') : 'Sem setor vinculado';

    return (
        <span className={`block ${className}`}>
            <span className="block font-medium text-gray-800 dark:text-white/90">{analista.analista}</span>
            <span className="block text-theme-xs text-gray-500 dark:text-gray-400">
                {perfil} · {setores}
            </span>
        </span>
    );
}

export default function CentralDistribuicao({
    processos,
    analistas,
    totalSelecionados,
    totalEmAnalise,
    historico,
    distribuiEmQualquerSetor,
}: CentralProps) {
    const [busca, setBusca] = useState('');
    const [ordenacao, setOrdenacao] = useState<Ordenacao>('menor');
    const [selecionado, setSelecionado] = useState<number | null>(null);
    const [modo, setModo] = useState<'um' | 'varios'>('um');
    const [quantidades, setQuantidades] = useState<Record<number, number>>({});
    const [historicoAberto, setHistoricoAberto] = useState(false);
    const [setorDestino, setSetorDestino] = useState('');

    const form = useForm<{ request_ids: number[]; analista_id: string }>({
        request_ids: processos.map((p) => p.id),
        analista_id: '',
    });

    const loteForm = useForm<{ alocacoes: { analista_id: number; request_ids: number[] }[] }>({ alocacoes: [] });

    const maiorCarga = useMemo(
        () => analistas.reduce((max, a) => Math.max(max, a.total), 0),
        [analistas],
    );

    const totalAlocado = useMemo(
        () => Object.values(quantidades).reduce((soma, q) => soma + (q || 0), 0),
        [quantidades],
    );

    const faltam = totalSelecionados - totalAlocado;

    const analistasOrdenados = useMemo(() => {
        const ordenados = [...analistas];
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
    }, [analistas, ordenacao]);

    const alocacaoPrevia = useMemo(
        () => alocarPorQuantidade(processos, analistasOrdenados, quantidades),
        [analistasOrdenados, quantidades, processos],
    );

    const processoPorId = useMemo(() => new Map(processos.map((p) => [p.id, p])), [processos]);

    const idsAlocados = useMemo(
        () => new Set(alocacaoPrevia.flatMap((grupo) => grupo.request_ids)),
        [alocacaoPrevia],
    );

    const semDestino = useMemo(
        () => processos.filter((processo) => !idsAlocados.has(processo.id)),
        [idsAlocados, processos],
    );

    const listaVisivel = useMemo(() => {
        const termo = busca.trim().toLowerCase();
        if (termo === '') {
            return analistasOrdenados;
        }

        return analistasOrdenados.filter((a) => a.analista.toLowerCase().includes(termo));
    }, [analistasOrdenados, busca]);

    const escolhido = analistas.find((a) => a.analista_id === selecionado) ?? null;
    const escolhaDoSetor = escolhaDeSetor(
        distribuiEmQualquerSetor,
        escolhido?.setores ?? [],
        processos.map((processo) => processo.sector_id),
    );

    function definirQuantidade(analistaId: number, valor: number) {
        const restante = totalSelecionados - (totalAlocado - (quantidades[analistaId] || 0));
        const limitado = Math.max(0, Math.min(Math.trunc(valor), restante));
        setQuantidades((atual) => ({ ...atual, [analistaId]: limitado }));
    }

    function confirmar() {
        if (selecionado === null) {
            return;
        }

        form.transform((data) => ({ ...data, analista_id: String(selecionado), sector_id: setorDestino || null }));
        form.post('/gestao/caixa-setor/distribuir', {
            preserveScroll: true,
            onSuccess: () => router.visit('/gestao/caixa-setor'),
        });
    }

    function confirmarLote() {
        if (totalAlocado !== totalSelecionados) {
            return;
        }
        loteForm.transform(() => ({
            alocacoes: alocacaoPrevia.map((g) => ({ analista_id: g.analista_id, request_ids: g.request_ids })),
        }));
        loteForm.post('/gestao/caixa-setor/distribuir-lote', {
            preserveScroll: true,
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
                            <ResumoItem rotulo={distribuiEmQualquerSetor ? "Processos em análise" : "Processos em análise no setor"} valor={totalEmAnalise} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        title={modo === 'varios' ? 'Divida a quantidade' : 'Escolha o analista'}
                        description={
                            modo === 'varios'
                                ? 'Diga quantos processos cada analista recebe. A soma tem que fechar com o total. Quem está no topo da lista recebe primeiro os de prazo mais curto.'
                                : 'A carga atual apoia a decisão. A escolha do destinatário é sua — o sistema não distribui automaticamente.'
                        }
                        actions={
                            <Button
                                variant="outline"
                                onClick={() => setHistoricoAberto((v) => !v)}
                                aria-expanded={historicoAberto}
                            >
                                {historicoAberto ? 'Ocultar histórico' : 'Ver histórico'}
                            </Button>
                        }
                    />
                    <CardContent>
                        <div className="mb-4 inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 dark:border-gray-800 dark:bg-white/[0.03]">
                            {([
                                ['um', 'Enviar todos para um'],
                                ['varios', 'Dividir entre vários'],
                            ] as const).map(([valor, rotulo]) => (
                                <button
                                    key={valor}
                                    type="button"
                                    onClick={() => setModo(valor)}
                                    aria-pressed={modo === valor}
                                    className={`rounded-md px-4 py-2 text-theme-sm font-medium ${
                                        modo === valor
                                            ? 'bg-white text-brand-500 shadow-theme-xs dark:bg-gray-900 dark:text-brand-400'
                                            : 'text-gray-500'
                                    }`}
                                >
                                    {rotulo}
                                </button>
                            ))}
                        </div>

                        {modo === 'varios' && (
                            <p
                                id="resumo-divisao"
                                className={`mb-4 rounded-xl px-4 py-3 text-theme-sm ${
                                    faltam === 0
                                        ? 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400'
                                        : 'bg-gray-50 text-gray-700 dark:bg-white/[0.03] dark:text-gray-300'
                                }`}
                            >
                                {faltam === 0
                                    ? `Divisão fechada: ${textoProcessos(totalSelecionados)} com destinatário.`
                                    : `Faltam ${textoProcessos(faltam)} para fechar a divisão de ${totalSelecionados}.`}
                            </p>
                        )}

                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            {modo === 'um' && (
                                <div className="w-full sm:max-w-xs">
                                    <Input
                                        id="busca-analista"
                                        value={busca}
                                        onChange={(e) => setBusca(e.target.value)}
                                        placeholder="Pesquisar analista..."
                                        aria-label="Pesquisar analista"
                                    />
                                </div>
                            )}
                            <label className="flex items-center gap-2 text-theme-sm text-gray-500 dark:text-gray-400">
                                {modo === 'varios' ? 'Quem recebe os mais urgentes primeiro' : 'Ordenar'}
                                <select
                                    value={ordenacao}
                                    onChange={(e) => setOrdenacao(e.target.value as Ordenacao)}
                                    className="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 scheme-light dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:scheme-dark"
                                >
                                    <option value="menor">Menor carga primeiro</option>
                                    <option value="maior">Maior carga primeiro</option>
                                    <option value="nome">Ordem alfabética</option>
                                </select>
                            </label>
                        </div>

                        {modo === 'varios' && (
                            <div className="mb-1 hidden gap-3 px-3 text-theme-xs font-medium tracking-wide text-gray-400 uppercase sm:grid sm:grid-cols-[minmax(12rem,1.4fr)_minmax(10rem,1fr)_8.5rem_6rem]">
                                <span>Analista</span>
                                <span>Carga atual</span>
                                <span>Recebe</span>
                                <span>Depois</span>
                            </div>
                        )}

                        <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                            {(modo === 'varios' ? analistasOrdenados : listaVisivel).map((a) => {
                                const ativo = a.analista_id === selecionado;
                                const percentual = maiorCarga > 0 ? (a.total / maiorCarga) * 100 : 0;
                                const grupos = Object.entries(a.por_grupo).filter(([, n]) => n > 0);
                                const quantidade = quantidades[a.analista_id] || 0;
                                const restante = totalSelecionados - (totalAlocado - quantidade);

                                if (modo === 'varios') {
                                    return (
                                        <li key={a.analista_id}>
                                            <div className="grid gap-3 px-3 py-4 sm:grid-cols-[minmax(12rem,1.4fr)_minmax(10rem,1fr)_8.5rem_6rem] sm:items-center">
                                                <div>
                                                    <IdentidadeDoAnalista analista={a} />
                                                    <p className="text-theme-xs text-gray-500 dark:text-gray-400">
                                                        {grupos.length > 0
                                                            ? grupos.map(([g, n]) => `${n} ${g}`).join(' · ')
                                                            : 'sem carga ativa'}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-theme-xs text-gray-500 sm:sr-only">Carga atual</p>
                                                    <div className="flex items-center gap-3">
                                                        <span className="inline-flex min-w-[3.5rem] justify-center rounded-full bg-gray-100 px-2.5 py-0.5 text-theme-sm font-semibold tabular-nums text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                                            {a.total}
                                                        </span>
                                                        <span className="w-full max-w-[160px]">
                                                            <ProgressBar
                                                                value={percentual}
                                                                tone={a.total >= maiorCarga && maiorCarga > 0 ? 'warning' : 'brand'}
                                                                label={`Carga de ${a.analista}: ${a.total} processos`}
                                                            />
                                                        </span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label
                                                        htmlFor={`qtd-${a.analista_id}`}
                                                        className="mb-1 block text-theme-xs font-medium text-gray-700 sm:sr-only dark:text-gray-300"
                                                    >
                                                        Recebe
                                                    </label>
                                                    <Input
                                                        id={`qtd-${a.analista_id}`}
                                                        type="number"
                                                        inputMode="numeric"
                                                        min={0}
                                                        max={restante}
                                                        step={1}
                                                        placeholder="0"
                                                        value={quantidade === 0 ? '' : quantidade}
                                                        onChange={(e) =>
                                                            definirQuantidade(a.analista_id, Number(e.target.value) || 0)
                                                        }
                                                        aria-label={`Quantos processos ${a.analista} recebe`}
                                                        aria-describedby="resumo-divisao"
                                                        className="tabular-nums"
                                                    />
                                                </div>
                                                <div>
                                                    <p className="text-theme-xs text-gray-500 sm:sr-only">Depois</p>
                                                    <p className="text-lg font-semibold tabular-nums text-gray-800 dark:text-white/90">
                                                        {a.total + quantidade}
                                                    </p>
                                                </div>
                                            </div>
                                        </li>
                                    );
                                }

                                return (
                                    <li key={a.analista_id}>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setSelecionado(a.analista_id);
                                                setSetorDestino('');
                                            }}
                                            aria-pressed={ativo}
                                            className={`flex w-full flex-col gap-2 px-3 py-3 text-left transition-colors sm:flex-row sm:items-center sm:gap-4 ${
                                                ativo ? 'bg-brand-50 dark:bg-brand-500/10' : 'hover:bg-gray-50 dark:hover:bg-white/[0.03]'
                                            }`}
                                        >
                                            <IdentidadeDoAnalista analista={a} className="min-w-[200px] flex-1" />
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

                {modo === 'varios' && (
                    <Card>
                        <CardHeader
                            title="Quem recebe cada processo"
                            description="Os processos entram na ordem do prazo, do mais curto para o mais longo. O primeiro analista da lista com quantidade preenchida leva os primeiros."
                        />
                        <CardContent>
                            {alocacaoPrevia.length === 0 ? (
                                <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                    Nenhuma quantidade informada. Preencha a coluna Recebe.
                                </p>
                            ) : (
                                <div className="space-y-4">
                                    {alocacaoPrevia.map((grupo) => (
                                        <div key={grupo.analista_id}>
                                            <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                                {grupo.analista} recebe {textoProcessos(grupo.request_ids.length)}
                                            </p>
                                            <ol className="mt-1 list-decimal space-y-1 pl-5 text-theme-sm text-gray-600 dark:text-gray-300">
                                                {grupo.request_ids.map((id) => (
                                                    <li key={id}>{rotuloProcesso(processoPorId.get(id), id)}</li>
                                                ))}
                                            </ol>
                                        </div>
                                    ))}
                                    {semDestino.length > 0 && (
                                        <div>
                                            <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                                Ainda sem analista: {textoProcessos(semDestino.length)}
                                            </p>
                                            <ol className="mt-1 list-decimal space-y-1 pl-5 text-theme-sm text-gray-600 dark:text-gray-300">
                                                {semDestino.map((processo) => (
                                                    <li key={processo.id}>{rotuloProcesso(processo, processo.id)}</li>
                                                ))}
                                            </ol>
                                        </div>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {modo === 'varios' && (
                    <Card>
                        <CardContent>
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                                    {faltam === 0
                                        ? 'A divisão está completa. Confirme para encaminhar.'
                                        : `Ainda faltam ${textoProcessos(faltam)}. A confirmação libera quando a soma for ${totalSelecionados}.`}
                                </p>
                                <div className="flex items-center gap-3">
                                    <Button
                                        variant="outline"
                                        onClick={() => router.visit('/gestao/caixa-setor')}
                                        disabled={loteForm.processing}
                                    >
                                        Cancelar
                                    </Button>
                                    <Button
                                        variant="primary"
                                        onClick={confirmarLote}
                                        loading={loteForm.processing}
                                        disabled={totalAlocado !== totalSelecionados}
                                    >
                                        Confirmar distribuição
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {modo === 'um' && escolhido && (
                    <Card>
                        <CardContent>
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                        Você está encaminhando <strong>{totalSelecionados}</strong> processo(s) para
                                    </p>
                                    <p className="text-lg font-semibold text-gray-800 dark:text-white/90">{escolhido.analista}</p>
                                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                        {escolhido.perfis.length > 0 ? escolhido.perfis.join(', ') : 'Sem perfil'} ·{' '}
                                        {escolhido.setores.length > 0 ? escolhido.setores.map((setor) => setor.name).join(', ') : 'Sem setor vinculado'}
                                    </p>
                                    <p className="mt-1 text-theme-sm text-gray-600 dark:text-gray-300">
                                        Carga atual: <strong className="tabular-nums">{escolhido.total}</strong>
                                        {'  +  '}
                                        Novos: <strong className="tabular-nums">{totalSelecionados}</strong>
                                        {'  =  '}
                                        Após envio: <strong className="tabular-nums">{escolhido.total + totalSelecionados}</strong>
                                    </p>
                                    {escolhaDoSetor !== 'nenhuma' && (
                                        <div className="mt-3 w-full sm:w-80">
                                            <label htmlFor="central-setor" className="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300">
                                                Setor que recebe os processos
                                            </label>
                                            <Select
                                                id="central-setor"
                                                value={setorDestino}
                                                onChange={setSetorDestino}
                                                placeholder={escolhaDoSetor === 'opcional' ? 'Manter o setor atual dos processos' : 'Selecione o setor'}
                                                options={escolhido.setores.map((setor) => ({ value: String(setor.id), label: setor.name }))}
                                            />
                                        </div>
                                    )}
                                </div>
                                <div className="flex items-center gap-3">
                                    <Button variant="outline" onClick={() => router.visit('/gestao/caixa-setor')} disabled={form.processing}>
                                        Cancelar
                                    </Button>
                                    <Button
                                        variant="primary"
                                        onClick={confirmar}
                                        loading={form.processing}
                                        disabled={escolhaDoSetor === 'obrigatoria' && setorDestino === ''}
                                    >
                                        Confirmar envio
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {historicoAberto && (
                    <Card>
                        <CardHeader title="Histórico de distribuições" description="Últimas distribuições em lote do setor." />
                        <CardContent>
                            {historico.length === 0 ? (
                                <p className="text-theme-sm text-gray-500 dark:text-gray-400">Sem distribuições registradas.</p>
                            ) : (
                                <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {historico.map((h, i) => (
                                        <li key={i} className="flex flex-wrap items-center justify-between gap-2 py-2 text-theme-sm">
                                            <span className="text-gray-700 dark:text-gray-300">
                                                {h.em ? new Date(h.em).toLocaleString('pt-BR') : '—'}
                                            </span>
                                            <span className="text-gray-600 dark:text-gray-400">{h.ator}</span>
                                            <span className="font-medium text-gray-800 dark:text-white/90">
                                                {h.qtd} → {h.analista}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function textoProcessos(quantidade: number): string {
    return quantidade === 1 ? '1 processo' : `${quantidade} processos`;
}

function rotuloProcesso(processo: ProcessoSelecionado | undefined, id: number): string {
    if (!processo) {
        return `#${id}`;
    }

    const codigo = processo.bap ?? processo.protocol_number ?? `#${processo.id}`;

    return processo.imovel !== '' ? `${codigo} — ${processo.imovel}` : codigo;
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
