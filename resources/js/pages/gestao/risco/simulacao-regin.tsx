import { Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import { InfoIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
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

interface ClassificacaoCnae {
    cnae: string;
    perguntas: { codigo?: string; texto: string; resposta: string; valor?: boolean }[];
    risco: {
        municipal: { status: string; nivel_label?: string | null };
        encaminhamento: { fluxo: string; motivo?: string | null };
    };
}

interface Relatorio {
    origem: string;
    aviso: string;
    codigo: string;
    rotulo: string;
    processo: string;
    area_utilizada: number | null;
    tipo_imovel: string | null;
    tipo_imovel_normalized: string | null;
    tipo_imovel_reconhecimento: string;
    tipo_imovel_dirige_regra: boolean;
    tipo_imovel_permite_decisao_automatica: boolean;
    zona: string | null;
    via: string | null;
    por_cnae: ClassificacaoCnae[];
}

interface Props {
    protocolos: ProtocoloResumo[];
    aviso: string;
    relatorio?: Relatorio | null;
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

export default function SimulacaoRegin({ protocolos, aviso, relatorio = null }: Props) {
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
                        description="O motor classifica com tipo de imóvel e área como se tivessem chegado do REGIN."
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
                    <Card>
                        <CardHeader title={relatorio.rotulo} description={relatorio.processo} />
                        <CardContent className="space-y-5">
                            <div className="flex flex-wrap gap-2">
                                <Badge color="light">Tipo: {relatorio.tipo_imovel ?? 'ausente'}</Badge>
                                <Badge color={relatorio.tipo_imovel_dirige_regra ? 'warning' : 'light'}>
                                    {reconhecimentoLabel(relatorio.tipo_imovel_reconhecimento)}
                                </Badge>
                                <Badge color="light">
                                    Área {relatorio.area_utilizada?.toLocaleString('pt-BR') ?? '—'} m²
                                </Badge>
                                <Badge color="light">
                                    {relatorio.zona ?? '—'} · {relatorio.via ?? '—'}
                                </Badge>
                            </div>

                            <ul className="space-y-3">
                                {relatorio.por_cnae.map((item) => (
                                    <li
                                        key={item.cnae}
                                        className="rounded-xl border border-gray-200 p-4 dark:border-gray-800"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="font-medium text-gray-800 dark:text-white/90">{item.cnae}</p>
                                            <Badge color={item.risco.encaminhamento.fluxo === 'expresso' ? 'success' : 'warning'}>
                                                {item.risco.municipal.nivel_label ?? item.risco.municipal.status} →{' '}
                                                {item.risco.encaminhamento.fluxo}
                                            </Badge>
                                        </div>
                                        {item.perguntas.length > 0 && (
                                            <p className="mt-2 text-theme-sm text-gray-500 dark:text-gray-400">
                                                {item.perguntas
                                                    .map((pergunta) => `${pergunta.texto} ${pergunta.resposta}`)
                                                    .join(' · ')}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

SimulacaoRegin.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
