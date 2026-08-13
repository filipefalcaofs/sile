import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import { EyeIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import TableAction from '@/components/ui/table-action';
import {
    ResultadoViabilidade,
    type EntradaConsulta,
    type ResultadoConsulta,
    type VereditoResultado,
} from '@/components/viabilidade/resultado-viabilidade';
import PortalLayout from '@/layouts/portal-layout';

type EntryType = EntradaConsulta['tipo'];

/** Item da listagem do histórico (contrato do HistoricoConsultaController, 07-07). */
interface ConsultaHistoricoItem {
    id: number;
    entry_type: EntryType;
    resultado: VereditoResultado | null;
    resultado_label: string | null;
    input: EntradaConsulta;
    created_at: string;
    result: ResultadoConsulta;
}

interface HistoricoProps {
    consultas: {
        data: ConsultaHistoricoItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
}

const ENTRY_TYPE_LABELS: Record<EntryType, string> = {
    endereco: 'Endereço',
    cnae: 'CNAE',
    inscricao: 'Inscrição',
};

/**
 * Cor do veredito alinhada ao componente ResultadoViabilidade (07-08): `pendente`
 * é distinto (info) e NUNCA herda a aparência de permitido/não permitido.
 */
const RESULTADO_COLORS: Record<VereditoResultado, 'success' | 'warning' | 'error' | 'info'> = {
    permitido: 'success',
    permitido_com_condicoes: 'warning',
    nao_permitido: 'error',
    pendente: 'info',
};

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString('pt-BR');
}

/** Resumo legível da entrada: endereço, CNAE + área ou inscrição. */
function entradaResumo(input: EntradaConsulta): string {
    if (input.tipo === 'endereco') {
        return input.endereco ?? 'Endereço informado';
    }

    if (input.tipo === 'inscricao') {
        return input.inscricao ? `Inscrição ${input.inscricao}` : 'Inscrição informada';
    }

    const cnae = input.cnae_formatado ?? input.cnae;

    return input.area != null ? `${cnae} · ${input.area} m²` : cnae;
}

export default function Historico({ consultas }: HistoricoProps) {
    const [selecionada, setSelecionada] = useState<ConsultaHistoricoItem | null>(null);

    const columns: ColumnDef<ConsultaHistoricoItem>[] = [
        {
            id: 'created_at',
            header: 'Data',
            cell: (item) => (
                <span className="whitespace-nowrap text-gray-800 dark:text-white/90">
                    {formatDateTime(item.created_at)}
                </span>
            ),
        },
        {
            id: 'entry_type',
            header: 'Tipo',
            cell: (item) => (
                <Badge size="sm" color="light">
                    {ENTRY_TYPE_LABELS[item.entry_type]}
                </Badge>
            ),
        },
        {
            id: 'entrada',
            header: 'Entrada',
            cell: (item) => (
                <span
                    className="block max-w-[36ch] truncate text-gray-600 dark:text-gray-300"
                    title={entradaResumo(item.input)}
                >
                    {entradaResumo(item.input)}
                </span>
            ),
        },
        {
            id: 'veredito',
            header: 'Veredito',
            cell: (item) =>
                item.resultado && item.resultado_label ? (
                    <Badge color={RESULTADO_COLORS[item.resultado]}>{item.resultado_label}</Badge>
                ) : (
                    <Badge color="light">Indisponível</Badge>
                ),
        },
        {
            id: 'actions',
            header: 'Ações',
            align: 'end',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex justify-end">
                    <TableAction
                        tone="brand"
                        icon={<EyeIcon className="size-4.5" />}
                        label="Ver resultado"
                        title="Ver resultado"
                        onClick={() => setSelecionada(item)}
                    />
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Consultas de viabilidade" />
            <PageHeader title="Consultas de viabilidade" breadcrumbs={[{ label: 'Meu painel', href: '/portal/painel' }]} />

            <Card>
                <CardHeader
                    title="Histórico de consultas"
                    description="Suas consultas prévias de viabilidade. Cada resultado é o registro da época — reflete as regras e os dados vigentes na consulta, sem reprocessar."
                    actions={
                        <Link href="/portal/viabilidade">
                            <Button size="sm">Nova consulta</Button>
                        </Link>
                    }
                />
                <CardContent>
                    <div className="space-y-5">
                        <DataTable
                            columns={columns}
                            rows={consultas.data}
                            rowKey={(item) => item.id}
                            emptyState={
                                <EmptyState
                                    title="Você ainda não realizou consultas"
                                    description="Faça uma consulta prévia de viabilidade para acompanhar o histórico aqui."
                                    action={
                                        <Link href="/portal/viabilidade">
                                            <Button size="sm">Fazer consulta</Button>
                                        </Link>
                                    }
                                />
                            }
                        />

                        <Pagination
                            links={consultas.links}
                            meta={{ from: consultas.from, to: consultas.to, total: consultas.total }}
                        />
                    </div>
                </CardContent>
            </Card>

            {selecionada && (
                <Modal
                    isOpen
                    onClose={() => setSelecionada(null)}
                    className="m-4 max-h-[90vh] max-w-[900px] overflow-y-auto p-6 lg:p-8"
                >
                    <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Resultado da consulta</h4>
                    <p className="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                        Resultado registrado em {formatDateTime(selecionada.created_at)} — reflete as regras e os dados
                        vigentes na época da consulta (não é reprocessado).
                    </p>
                    <div className="mt-6">
                        <ResultadoViabilidade result={selecionada.result} />
                    </div>
                </Modal>
            )}
        </>
    );
}

Historico.layout = (page: ReactNode) => <PortalLayout>{page}</PortalLayout>;
