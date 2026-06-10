import { Head, Link } from '@inertiajs/react';
import Badge from '@/components/ui/badge';
import EmptyState from '@/components/ui/empty-state';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
import GestaoLayout from '@/layouts/gestao-layout';

interface HistoryEntry {
    id: number;
    valor_anterior: string | null;
    valor_novo: string | null;
    responsavel: string | null;
    data: string;
}

interface ParameterHistoryProps {
    parameter: {
        key: string;
        description: string;
        sensitive: boolean;
    };
    entries: {
        data: HistoryEntry[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
}

const headerCellStyles = 'px-5 py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400';

function PageBreadcrumb({ pageTitle }: { pageTitle: string }) {
    const separator = (
        <svg
            className="stroke-current"
            width="17"
            height="16"
            viewBox="0 0 17 16"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366"
                strokeWidth="1.2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );

    return (
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-xl font-semibold text-gray-800 dark:text-white/90">{pageTitle}</h2>
            <nav aria-label="Trilha de navegação">
                <ol className="flex flex-wrap items-center gap-1.5">
                    <li>
                        <Link
                            className="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"
                            href="/gestao"
                        >
                            Painel
                            {separator}
                        </Link>
                    </li>
                    <li>
                        <Link
                            className="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"
                            href="/gestao/parametros"
                        >
                            Parâmetros do sistema
                            {separator}
                        </Link>
                    </li>
                    <li className="text-sm text-gray-800 dark:text-white/90">Histórico</li>
                </ol>
            </nav>
        </div>
    );
}

function HistoryValue({ value }: { value: string | null }) {
    if (value === '[criptografado]') {
        return (
            <Badge size="sm" color="light">
                [criptografado]
            </Badge>
        );
    }

    return <>{value ?? '—'}</>;
}

function HistoryTable({ entries }: { entries: ParameterHistoryProps['entries'] }) {
    if (entries.data.length === 0) {
        return (
            <EmptyState
                title="Nenhuma alteração registrada"
                description="As alterações deste parâmetro aparecem aqui com valor anterior, valor novo e responsável."
            />
        );
    }

    return (
        <div className="space-y-6">
            <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-white/[0.05]">
                <div className="max-w-full overflow-x-auto">
                    <Table>
                        <TableHeader className="border-b border-gray-100 dark:border-white/[0.05]">
                            <TableRow>
                                <TableCell isHeader className={headerCellStyles}>
                                    Data/hora
                                </TableCell>
                                <TableCell isHeader className={headerCellStyles}>
                                    Responsável
                                </TableCell>
                                <TableCell isHeader className={headerCellStyles}>
                                    Valor anterior
                                </TableCell>
                                <TableCell isHeader className={headerCellStyles}>
                                    Valor novo
                                </TableCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
                            {entries.data.map((entry) => (
                                <TableRow
                                    key={entry.id}
                                    className="transition hover:bg-gray-50 dark:hover:bg-white/[0.03]"
                                >
                                    <TableCell className="px-5 py-4 text-start text-theme-sm font-medium whitespace-nowrap text-gray-800 dark:text-white/90">
                                        {new Date(entry.data).toLocaleString('pt-BR')}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                        {entry.responsavel ?? '—'}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                        <HistoryValue value={entry.valor_anterior} />
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                        <HistoryValue value={entry.valor_novo} />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>

            <Pagination
                links={entries.links}
                meta={{ from: entries.from, to: entries.to, total: entries.total }}
            />
        </div>
    );
}

export default function ParameterHistory({ parameter, entries }: ParameterHistoryProps) {
    return (
        <GestaoLayout>
            <Head title={`Histórico — ${parameter.description}`} />
            <PageBreadcrumb pageTitle={`Histórico — ${parameter.description}`} />

            <div className="flex flex-col gap-4 md:gap-6">
                <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div className="px-6 py-5">
                        <h3 className="text-base font-medium text-gray-800 dark:text-white/90">
                            Alterações registradas
                        </h3>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Valor anterior, valor novo, responsável e data/hora de cada alteração.
                        </p>
                        <p className="mt-1 font-mono text-theme-xs text-gray-400 dark:text-gray-500">
                            {parameter.key}
                        </p>
                    </div>
                    <div className="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-6">
                        <HistoryTable entries={entries} />
                    </div>
                </div>

                <div>
                    <Link
                        href="/gestao/parametros"
                        className="text-sm font-medium text-brand-500 underline-offset-2 transition hover:underline dark:text-brand-400"
                    >
                        Voltar para parâmetros
                    </Link>
                </div>
            </div>
        </GestaoLayout>
    );
}
