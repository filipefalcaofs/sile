import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import { InfoIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import GestaoLayout from '@/layouts/gestao-layout';

interface ProtocoloResumo {
    codigo: string;
    rotulo: string;
    processo: string;
    servico: string;
    tipo_imovel: string | null;
    area_utilizada: number;
    zona: string;
    via: string;
    atividades: number;
}

interface PerguntaProtocolo {
    codigo?: string;
    texto: string;
    resposta: string;
    valor?: boolean;
}

interface CondicionanteSanitaria {
    pergunta?: string;
    resposta?: boolean | string | null;
    acionou?: boolean;
    reclassifica_para?: string | null;
}

interface GatilhoAcionado {
    codigo?: string;
    motivo?: string;
}

interface ClassificacaoCnae {
    cnae: string;
    perguntas: PerguntaProtocolo[];
    risco: {
        municipal: {
            status: string;
            nivel?: string | null;
            nivel_label?: string | null;
            versao_regras?: string | null;
            condicionantes?: string[];
        };
        sanitario: {
            status: string;
            nivel_original?: string | null;
            nivel_final?: string | null;
            reclassificado?: boolean;
            versao_regras?: string | null;
            condicionantes_perguntas?: CondicionanteSanitaria[];
        };
        encaminhamento: {
            fluxo: string;
            motivo?: string | null;
            dimensao_decisiva?: string | null;
            gatilhos_acionados?: GatilhoAcionado[];
        };
        fundamentacao: string[];
        versoes?: Record<string, string | null>;
    };
}

interface Relatorio {
    origem: string;
    aviso: string;
    codigo: string;
    rotulo: string;
    processo: string;
    servico?: string | null;
    area_utilizada: number | null;
    tipo_imovel: string | null;
    tipo_imovel_normalized: string | null;
    tipo_imovel_reconhecimento: string;
    tipo_imovel_dirige_regra: boolean;
    tipo_imovel_permite_decisao_automatica: boolean;
    zona: string | null;
    via: string | null;
    por_cnae: ClassificacaoCnae[];
    consolidado: {
        cnae: string | null;
        nivel: string | null;
        nivel_label: string | null;
        fluxo: string;
        motivo: string;
    };
    processo_id?: number | null;
    protocol_number?: string | null;
    status?: string | null;
    tvl?: string | null;
    processo_url?: string | null;
}

interface ExecucaoSalva {
    codigo: string;
    rotulo: string;
    processo: string | null;
    processo_id?: number | null;
    protocol_number?: string | null;
    status?: string | null;
    tvl?: string | null;
    consolidado: Relatorio['consolidado'] | null;
    atualizado_em: string | null;
}

interface Props {
    protocolos: ProtocoloResumo[];
    aviso: string;
    relatorio?: Relatorio | null;
    execucoes?: ExecucaoSalva[];
}

function reconhecimentoLabel(valor: string): string {
    if (valor === 'dirige_regra') {
        return 'Dirige regra (galpão/container/residencial)';
    }
    if (valor === 'ramo_comum') {
        return 'Ramo comum (conhecido, não dirige regra)';
    }
    if (valor === 'ausente') {
        return 'Ausente no protocolo';
    }

    return 'Desconhecido — iria à análise';
}

function statusLabel(status?: string | null): string {
    if (status === 'deferida') {
        return 'Deferida';
    }
    if (status === 'em_analise') {
        return 'Em análise';
    }
    if (status === 'indeferida') {
        return 'Indeferida';
    }
    if (status === 'protocolada') {
        return 'Protocolada';
    }

    return status ?? '—';
}

function sanitarioLabel(nivel?: string | null): string {
    if (nivel === 'baixo') {
        return 'Baixo';
    }
    if (nivel === 'medio') {
        return 'Médio';
    }
    if (nivel === 'alto') {
        return 'Alto';
    }

    return nivel ?? '—';
}

function nivelColor(nivel?: string | null): 'success' | 'warning' | 'error' | 'light' {
    if (nivel === 'baixo_a' || nivel === 'baixo') {
        return 'success';
    }
    if (nivel === 'baixo_b' || nivel === 'medio') {
        return 'warning';
    }
    if (nivel === 'alto') {
        return 'error';
    }

    return 'light';
}

function fluxoColor(fluxo?: string | null): 'success' | 'warning' | 'light' {
    if (fluxo === 'expresso') {
        return 'success';
    }
    if (fluxo === 'analise') {
        return 'warning';
    }

    return 'light';
}

function fluxoLabel(fluxo?: string | null): string {
    if (fluxo === 'expresso') {
        return 'Expresso';
    }
    if (fluxo === 'analise') {
        return 'Análise técnica';
    }

    return fluxo ?? '—';
}

export default function SimulacaoRegin({ protocolos, aviso, relatorio = null, execucoes = [] }: Props) {
    const resultadoRef = useRef<HTMLDivElement>(null);
    const [apagando, setApagando] = useState<string | null>(null);

    useEffect(() => {
        if (relatorio) {
            resultadoRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, [relatorio?.codigo]);

    const colunas: ColumnDef<ProtocoloResumo>[] = [
        { id: 'rotulo', header: 'Protocolo', cell: (row) => row.rotulo },
        { id: 'processo', header: 'Processo', cell: (row) => row.processo },
        {
            id: 'tipo_imovel',
            header: 'Tipo de imóvel (REGIN)',
            cell: (row) => row.tipo_imovel ?? '—',
        },
        {
            id: 'area_utilizada',
            header: 'Área (m²)',
            cellClassName: 'whitespace-nowrap',
            cell: (row) => row.area_utilizada.toLocaleString('pt-BR'),
        },
        { id: 'zona', header: 'Zona', cell: (row) => row.zona },
        {
            id: 'atividades',
            header: 'CNAEs',
            cell: (row) => String(row.atividades),
        },
        {
            id: 'acao',
            header: '',
            align: 'end',
            cell: (row) => (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => router.post('/gestao/risco/simulacao-regin', { codigo: row.codigo })}
                >
                    Simular no motor
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title="Simulação REGIN — protocolos SEDUR" />
            <PageHeader
                title="Simulação REGIN"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }]}
            />

            <div className="space-y-6">
                <Card>
                    <CardHeader
                        title="Protocolos de validação"
                        description="O motor classifica e cria o processo: Alto vai para análise; Baixo e Médio seguem o expresso."
                    />
                    <CardContent>
                        <div className="mb-5 flex items-start gap-3 rounded-xl border border-blue-light-200 bg-blue-light-50 p-4 dark:border-blue-light-500/30 dark:bg-blue-light-500/15">
                            <InfoIcon className="size-5 shrink-0 fill-current text-blue-light-500" />
                            <p className="text-theme-sm text-gray-600 dark:text-gray-300">{aviso}</p>
                        </div>

                        <DataTable
                            columns={colunas}
                            rows={protocolos}
                            rowKey={(row) => row.codigo}
                            density="compact"
                        />
                    </CardContent>
                </Card>

                {relatorio && (
                    <div ref={resultadoRef} id="resultado-motor">
                        <Card>
                            <CardHeader
                                title={`O que o motor rodou — ${relatorio.rotulo}`}
                                description={`${relatorio.processo}${relatorio.servico ? ` · ${relatorio.servico}` : ''}. O processo abaixo é real.`}
                                actions={
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setApagando(relatorio.codigo)}
                                    >
                                        Apagar e refazer
                                    </Button>
                                }
                            />
                            <CardContent className="space-y-8">
                                <ol className="space-y-8">
                                    <PassoMotor numero={1} titulo="Entrada do protocolo">
                                        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                            <Dado label="Tipo de imóvel">{relatorio.tipo_imovel ?? 'ausente'}</Dado>
                                            <Dado label="Área utilizada">
                                                {relatorio.area_utilizada?.toLocaleString('pt-BR') ?? '—'} m²
                                            </Dado>
                                            <Dado label="Zona">{relatorio.zona ?? '—'}</Dado>
                                            <Dado label="Via">{relatorio.via ?? '—'}</Dado>
                                        </dl>
                                    </PassoMotor>

                                    <PassoMotor numero={2} titulo="Reconhecimento do tipo de imóvel">
                                        <div className="flex flex-wrap gap-2">
                                            <Badge color={relatorio.tipo_imovel_dirige_regra ? 'warning' : 'light'}>
                                                {reconhecimentoLabel(relatorio.tipo_imovel_reconhecimento)}
                                            </Badge>
                                            <Badge color="light">
                                                Código: {relatorio.tipo_imovel_normalized ?? 'sem código'}
                                            </Badge>
                                            <Badge color={relatorio.tipo_imovel_permite_decisao_automatica ? 'success' : 'warning'}>
                                                {relatorio.tipo_imovel_permite_decisao_automatica
                                                    ? 'Permite decisão automática'
                                                    : 'Não permite decisão automática'}
                                            </Badge>
                                        </div>
                                    </PassoMotor>

                                    {relatorio.por_cnae.map((item, index) => (
                                        <PassoMotor
                                            key={item.cnae}
                                            numero={3 + index}
                                            titulo={`CNAE ${item.cnae}`}
                                            destaque={item.cnae === relatorio.consolidado.cnae}
                                            destaqueLabel="CNAE mais gravoso do conjunto"
                                        >
                                            <CnaeMotor item={item} />
                                        </PassoMotor>
                                    ))}

                                    <PassoMotor
                                        numero={3 + relatorio.por_cnae.length}
                                        titulo="Viabilidade do conjunto"
                                    >
                                        <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                            A empresa é classificada pelo CNAE mais gravoso. As linhas acima são a
                                            composição, não decisões isoladas.
                                        </p>
                                        <div className="mt-3 flex flex-wrap items-center gap-2">
                                            <p className="font-medium text-gray-800 dark:text-white/90">
                                                {relatorio.consolidado.cnae ?? '—'}
                                            </p>
                                            <Badge color={nivelColor(relatorio.consolidado.nivel)}>
                                                {relatorio.consolidado.nivel_label ?? '—'}
                                            </Badge>
                                            <Badge color={fluxoColor(relatorio.consolidado.fluxo)}>
                                                {fluxoLabel(relatorio.consolidado.fluxo)}
                                            </Badge>
                                        </div>
                                        <p className="mt-2 text-theme-sm text-gray-600 dark:text-gray-300">
                                            {relatorio.consolidado.motivo}
                                        </p>
                                    </PassoMotor>

                                    <PassoMotor
                                        numero={4 + relatorio.por_cnae.length}
                                        titulo="Processo criado"
                                    >
                                        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                            <Dado label="Protocolo">{relatorio.protocol_number ?? '—'}</Dado>
                                            <Dado label="Situação">{statusLabel(relatorio.status)}</Dado>
                                            <Dado label="TVL">{relatorio.tvl ?? '—'}</Dado>
                                            <Dado label="Número SEDUR">{relatorio.processo}</Dado>
                                        </dl>
                                        {relatorio.processo_url && (
                                            <p className="mt-3">
                                                <Link
                                                    href={relatorio.processo_url}
                                                    className="text-theme-sm font-medium text-brand-500 hover:underline"
                                                >
                                                    Abrir o processo
                                                </Link>
                                            </p>
                                        )}
                                    </PassoMotor>
                                </ol>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {execucoes.length > 0 && (
                    <Card>
                        <CardHeader
                            title="Simulações feitas"
                            description="Cada item é um processo real. Apague para excluir o processo e poder simular de novo."
                        />
                        <CardContent>
                            <ul className="space-y-3">
                                {execucoes.map((execucao) => (
                                    <li
                                        key={execucao.codigo}
                                        className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800"
                                    >
                                        <div>
                                            <p className="font-medium text-gray-800 dark:text-white/90">{execucao.rotulo}</p>
                                            <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                                {execucao.protocol_number ?? execucao.processo ?? execucao.codigo}
                                                {execucao.status ? ` · ${statusLabel(execucao.status)}` : ''}
                                                {execucao.tvl ? ` · ${execucao.tvl}` : ''}
                                                {execucao.consolidado?.nivel_label
                                                    ? ` · ${execucao.consolidado.nivel_label} → ${execucao.consolidado.fluxo}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => router.post('/gestao/risco/simulacao-regin', { codigo: execucao.codigo })}
                                            >
                                                Refazer
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => setApagando(execucao.codigo)}
                                            >
                                                Apagar
                                            </Button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}
            </div>

            <ConfirmDialog
                isOpen={apagando !== null}
                onClose={() => setApagando(null)}
                onConfirm={() => {
                    if (apagando) {
                        router.delete(`/gestao/risco/simulacao-regin/${apagando}`, {
                            onFinish: () => setApagando(null),
                        });
                    }
                }}
                title="Apagar resultado e o processo?"
                description="O relatório e o processo criado somem. O motor não muda — você pode simular de novo o mesmo protocolo."
                confirmLabel="Apagar"
                variant="danger"
            />
        </>
    );
}

function PassoMotor({
    numero,
    titulo,
    destaque = false,
    destaqueLabel,
    children,
}: {
    numero: number;
    titulo: string;
    destaque?: boolean;
    destaqueLabel?: string;
    children: ReactNode;
}) {
    return (
        <li>
            <div className="flex items-start gap-4">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-500 text-sm font-medium text-white">
                    {numero}
                </span>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h4 className="font-medium text-gray-800 dark:text-white/90">{titulo}</h4>
                        {destaque && destaqueLabel && (
                            <Badge color="error" size="sm">
                                {destaqueLabel}
                            </Badge>
                        )}
                    </div>
                    <div
                        className={`mt-3 rounded-xl border p-4 ${
                            destaque
                                ? 'border-error-200 bg-error-50/60 dark:border-error-800 dark:bg-error-900/15'
                                : 'border-gray-200 dark:border-gray-800'
                        }`}
                    >
                        {children}
                    </div>
                </div>
            </div>
        </li>
    );
}

function Dado({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-theme-xs tracking-wide text-gray-400 uppercase dark:text-gray-500">{label}</dt>
            <dd className="mt-1 text-theme-sm font-medium text-gray-800 dark:text-white/90">{children}</dd>
        </div>
    );
}

function CnaeMotor({ item }: { item: ClassificacaoCnae }) {
    const municipal = item.risco.municipal;
    const sanitario = item.risco.sanitario;
    const encaminhamento = item.risco.encaminhamento;
    const fundamentacao = Array.isArray(item.risco.fundamentacao) ? item.risco.fundamentacao : [];
    const gatilhos = Array.isArray(encaminhamento.gatilhos_acionados) ? encaminhamento.gatilhos_acionados : [];
    const perguntasSanitarias = Array.isArray(sanitario.condicionantes_perguntas)
        ? sanitario.condicionantes_perguntas
        : [];

    return (
        <div className="space-y-5">
            <div className="grid gap-4 lg:grid-cols-3">
                <div className="rounded-xl border border-gray-100 p-3 dark:border-gray-800">
                    <p className="text-theme-xs tracking-wide text-gray-400 uppercase dark:text-gray-500">
                        Risco municipal
                    </p>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <Badge color={nivelColor(municipal.nivel)}>
                            {municipal.nivel_label ?? municipal.status}
                        </Badge>
                        <span className="text-theme-xs text-gray-400 dark:text-gray-500">{municipal.status}</span>
                    </div>
                    {municipal.versao_regras && (
                        <p className="mt-2 text-theme-xs text-gray-400 dark:text-gray-500">
                            Decreto · {municipal.versao_regras}
                        </p>
                    )}
                </div>

                <div className="rounded-xl border border-gray-100 p-3 dark:border-gray-800">
                    <p className="text-theme-xs tracking-wide text-gray-400 uppercase dark:text-gray-500">
                        Risco sanitário
                    </p>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <Badge color={nivelColor(sanitario.nivel_final)}>
                            {sanitario.status === 'classificado'
                                ? sanitarioLabel(sanitario.nivel_final)
                                : sanitario.status}
                        </Badge>
                        {sanitario.reclassificado && (
                            <Badge color="warning" size="sm">
                                reclassificado de {sanitarioLabel(sanitario.nivel_original)}
                            </Badge>
                        )}
                    </div>
                    {sanitario.versao_regras && (
                        <p className="mt-2 text-theme-xs text-gray-400 dark:text-gray-500">
                            VISA · {sanitario.versao_regras}
                        </p>
                    )}
                </div>

                <div className="rounded-xl border border-gray-100 p-3 dark:border-gray-800">
                    <p className="text-theme-xs tracking-wide text-gray-400 uppercase dark:text-gray-500">
                        Encaminhamento
                    </p>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <Badge color={fluxoColor(encaminhamento.fluxo)}>{fluxoLabel(encaminhamento.fluxo)}</Badge>
                        {encaminhamento.dimensao_decisiva && (
                            <Badge color="light" size="sm">
                                dimensão {encaminhamento.dimensao_decisiva}
                            </Badge>
                        )}
                    </div>
                    {encaminhamento.motivo && (
                        <p className="mt-2 text-theme-sm text-gray-600 dark:text-gray-300">{encaminhamento.motivo}</p>
                    )}
                </div>
            </div>

            {item.perguntas.length > 0 && (
                <div>
                    <p className="text-theme-xs tracking-wide text-gray-400 uppercase dark:text-gray-500">
                        Perguntas do protocolo
                    </p>
                    <ul className="mt-2 space-y-2">
                        {item.perguntas.map((pergunta) => (
                            <li
                                key={`${pergunta.codigo ?? pergunta.texto}-${pergunta.resposta}`}
                                className="text-theme-sm text-gray-600 dark:text-gray-300"
                            >
                                <span className="font-medium text-gray-800 dark:text-white/90">
                                    {pergunta.codigo ? `${pergunta.codigo} · ` : ''}
                                    {pergunta.texto}
                                </span>
                                <span className="text-gray-500"> — {pergunta.resposta}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {perguntasSanitarias.length > 0 && (
                <div>
                    <p className="text-theme-xs tracking-wide text-gray-400 uppercase dark:text-gray-500">
                        Condicionantes sanitárias aplicadas
                    </p>
                    <ul className="mt-2 space-y-2">
                        {perguntasSanitarias.map((pergunta, index) => (
                            <li key={`${pergunta.pergunta ?? 's'}-${index}`} className="text-theme-sm text-gray-600 dark:text-gray-300">
                                {pergunta.pergunta ?? 'Condicionante'}
                                {pergunta.resposta !== null && pergunta.resposta !== undefined && (
                                    <span> — resposta {String(pergunta.resposta)}</span>
                                )}
                                {pergunta.acionou && (
                                    <Badge color="warning" size="sm">
                                        acionou
                                    </Badge>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {gatilhos.length > 0 && (
                <div>
                    <p className="text-theme-xs tracking-wide text-gray-400 uppercase dark:text-gray-500">
                        Gatilhos acionados
                    </p>
                    <ul className="mt-2 space-y-1 text-theme-sm text-gray-600 dark:text-gray-300">
                        {gatilhos.map((gatilho, index) => (
                            <li key={`${gatilho.codigo ?? 'g'}-${index}`}>
                                {gatilho.codigo ? `${gatilho.codigo}: ` : ''}
                                {gatilho.motivo}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {fundamentacao.length > 0 && (
                <div>
                    <p className="text-theme-xs tracking-wide text-gray-400 uppercase dark:text-gray-500">
                        Fundamentação
                    </p>
                    <ul className="mt-2 list-inside list-disc space-y-1 text-theme-sm text-gray-600 dark:text-gray-300">
                        {fundamentacao.map((ref) => (
                            <li key={ref}>{ref}</li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}

SimulacaoRegin.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
