import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import { EyeIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import TableToolbar from '@/components/ui/data-table/table-toolbar';
import type { ColumnDef, SortDirection } from '@/components/ui/data-table/types';
import { useServerTable } from '@/components/ui/data-table/use-server-table';
import EmptyState from '@/components/ui/empty-state';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import TableAction from '@/components/ui/table-action';
import PortalLayout from '@/layouts/portal-layout';

interface CompanyRow {
    id: number;
    legal_name: string;
    trade_name: string | null;
    formatted_cnpj: string;
    source: { value: string; label: string };
    primary_cnae: { formatted_code: string; description: string } | null;
    link: {
        role: string;
        role_label: string;
        active: boolean;
        started_at: string | null;
        ended_at: string | null;
    } | null;
}

interface EmpresasIndexProps {
    companies: {
        data: CompanyRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search: string;
        sort: string;
        direction: SortDirection;
        per_page: number;
    };
    perPageOptions: number[];
    totalCompanies: number;
}

function SourceBadge({ source }: { source: CompanyRow['source'] }) {
    return (
        <Badge size="sm" color={source.value === 'redesim' ? 'info' : 'light'}>
            {source.label}
        </Badge>
    );
}

function LinkBadge({ link }: { link: NonNullable<CompanyRow['link']> }) {
    return (
        <div className="flex flex-wrap items-center gap-2">
            <Badge size="sm" color={link.active ? 'success' : 'light'}>
                {link.active ? 'Ativo' : 'Encerrado'}
            </Badge>
            <span className="text-theme-xs text-gray-500 dark:text-gray-400">{link.role_label}</span>
        </div>
    );
}

export default function EmpresasIndex({ companies, filters, perPageOptions }: EmpresasIndexProps) {
    const table = useServerTable({
        url: '/portal/empresas',
        initialSearch: filters.search,
        initialSort: { column: filters.sort, direction: filters.direction },
        initialPerPage: filters.per_page,
    });

    const filtering = table.search.trim() !== '';

    const columns: ColumnDef<CompanyRow>[] = [
        {
            id: 'legal_name',
            header: 'Empresa',
            sortable: true,
            cell: (company) => (
                <div className="flex flex-col">
                    <span className="font-medium text-gray-800 dark:text-white/90">{company.legal_name}</span>
                    <span className="text-theme-xs text-gray-500 dark:text-gray-400">
                        {company.formatted_cnpj}
                        {company.trade_name ? ` · ${company.trade_name}` : ''}
                    </span>
                </div>
            ),
        },
        {
            id: 'primary_cnae',
            header: 'CNAE principal',
            cell: (company) =>
                company.primary_cnae ? (
                    <div className="flex flex-col">
                        <span className="whitespace-nowrap text-gray-800 dark:text-white/90">
                            {company.primary_cnae.formatted_code}
                        </span>
                        <span
                            className="max-w-[28ch] truncate text-theme-xs text-gray-500 dark:text-gray-400"
                            title={company.primary_cnae.description}
                        >
                            {company.primary_cnae.description}
                        </span>
                    </div>
                ) : (
                    <span className="text-gray-400 dark:text-gray-500">—</span>
                ),
        },
        {
            id: 'source',
            header: 'Origem',
            cell: (company) => <SourceBadge source={company.source} />,
        },
        {
            id: 'link',
            header: 'Vínculo',
            cell: (company) =>
                company.link ? (
                    <LinkBadge link={company.link} />
                ) : (
                    <span className="text-gray-400 dark:text-gray-500">—</span>
                ),
        },
        {
            id: 'actions',
            header: 'Ações',
            align: 'end',
            cellClassName: 'whitespace-nowrap',
            cell: (company) => (
                <div className="flex justify-end">
                    <TableAction
                        tone="brand"
                        icon={<EyeIcon className="size-4.5" />}
                        label="Ver detalhes"
                        title="Ver detalhes"
                        href={`/portal/empresas/${company.id}`}
                    />
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Minhas empresas" />
            <PageHeader title="Minhas empresas" breadcrumbs={[{ label: 'Meu painel', href: '/portal/painel' }]} />

            <Card>
                <CardHeader
                    title="Empresas vinculadas"
                    description="Empresas em que você é responsável ou procurador — base para suas solicitações de viabilidade."
                    actions={
                        <Link href="/portal/empresas/cadastrar">
                            <Button size="sm">Cadastrar empresa</Button>
                        </Link>
                    }
                />
                <CardContent>
                    <div className="space-y-5">
                        <TableToolbar
                            search={{
                                value: table.search,
                                onChange: table.setSearch,
                                placeholder: 'Buscar por razão social ou CNPJ...',
                                label: 'Buscar empresas',
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
                            columns={columns}
                            rows={companies.data}
                            rowKey={(company) => company.id}
                            sort={table.sort}
                            onSortChange={table.setSort}
                            loading={table.processing}
                            skeletonRows={6}
                            emptyState={
                                <EmptyState
                                    title={filtering ? 'Nenhuma empresa encontrada' : 'Nenhuma empresa cadastrada'}
                                    description={
                                        filtering
                                            ? 'Ajuste o termo da busca e tente novamente.'
                                            : 'Cadastre sua primeira empresa para iniciar solicitações de viabilidade.'
                                    }
                                    action={
                                        !filtering ? (
                                            <Link href="/portal/empresas/cadastrar">
                                                <Button size="sm">Cadastrar empresa</Button>
                                            </Link>
                                        ) : undefined
                                    }
                                />
                            }
                        />

                        <Pagination
                            links={companies.links}
                            meta={{ from: companies.from, to: companies.to, total: companies.total }}
                        />
                    </div>
                </CardContent>
            </Card>
        </>
    );
}

EmpresasIndex.layout = (page: ReactNode) => <PortalLayout>{page}</PortalLayout>;
