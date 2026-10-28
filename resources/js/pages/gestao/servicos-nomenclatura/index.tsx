import { Form, Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import FieldError from '@/components/form/field-error';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { EyeCloseIcon, EyeIcon, PencilIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import TableToolbar from '@/components/ui/data-table/table-toolbar';
import type { ColumnDef, SortDirection } from '@/components/ui/data-table/types';
import { useServerTable } from '@/components/ui/data-table/use-server-table';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';

interface NomenclaturaItem {
    id: number;
    de: string;
    condicao_label: string | null;
    para: string;
    exibir: boolean;
}

interface NomenclaturaIndexProps {
    nomenclaturas: {
        data: NomenclaturaItem[];
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
        exibir: string;
    };
    perPageOptions: number[];
    sugestoesDe: string[];
    condicaoOptions: { value: string; label: string }[];
}

const EXIBIR_OPTIONS = [
    { value: '1', label: 'Exibir' },
    { value: '0', label: 'Oculto' },
];

function ExibirBadge({ exibir }: { exibir: boolean }) {
    return (
        <Badge size="sm" color={exibir ? 'success' : 'light'}>
            {exibir ? 'Exibe' : 'Oculto'}
        </Badge>
    );
}

function CreateModal({
    isOpen,
    onClose,
    sugestoesDe,
    condicaoOptions,
}: {
    isOpen: boolean;
    onClose: () => void;
    sugestoesDe: string[];
    condicaoOptions: { value: string; label: string }[];
}) {
    const [exibir, setExibir] = useState('1');
    const [condicao, setCondicao] = useState('');

    return (
        <Modal isOpen={isOpen} onClose={onClose} className="m-4 max-h-[90vh] max-w-[600px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Nova nomenclatura</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                O nome recebido é o serviço como a JUCEB envia e não poderá ser alterado depois.
            </p>

            <Form action="/gestao/servicos-nomenclatura" method="post" resetOnSuccess onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label required htmlFor="create-de">Nome recebido (De)</Label>
                            <Input
                                id="create-de"
                                type="text"
                                name="de"
                                required
                                list="sugestoes-de"
                                placeholder="Inscrição de primeiro estabelecimento"
                                error={!!errors.de}
                                hint={errors.de}
                            />
                            <datalist id="sugestoes-de">
                                {sugestoesDe.map((nome) => (
                                    <option key={nome} value={nome} />
                                ))}
                            </datalist>
                        </div>
                        <div>
                            <Label htmlFor="create-condicao">Condição</Label>
                            <Select
                                id="create-condicao"
                                name="condicao"
                                value={condicao}
                                onChange={setCondicao}
                                options={[{ value: '', label: 'Sem condição' }, ...condicaoOptions]}
                            />
                            {errors.condicao && <FieldError>{errors.condicao}</FieldError>}
                        </div>
                        <div>
                            <Label required htmlFor="create-para">Nome exibido (Para)</Label>
                            <Input
                                id="create-para"
                                type="text"
                                name="para"
                                required
                                placeholder="Viabilidade de Localização"
                                error={!!errors.para}
                                hint={errors.para}
                            />
                        </div>
                        <div>
                            <Label htmlFor="create-exibir">Quando vier junto com outros serviços</Label>
                            <Select id="create-exibir" name="exibir" value={exibir} onChange={setExibir} options={EXIBIR_OPTIONS} />
                        </div>

                        <div className="flex items-center justify-end gap-3">
                            <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                                Cancelar
                            </Button>
                            <Button size="sm" type="submit" disabled={processing}>
                                {processing ? 'Salvando...' : 'Salvar'}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </Modal>
    );
}

function EditModal({ linha, onClose }: { linha: NomenclaturaItem; onClose: () => void }) {
    const [exibir, setExibir] = useState(linha.exibir ? '1' : '0');

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[600px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Editar nomenclatura</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{linha.de}, o nome recebido não pode ser alterado.</p>
            {linha.condicao_label && <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">*{linha.condicao_label}</p>}

            <Form action={`/gestao/servicos-nomenclatura/${linha.id}`} method="put" onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label required htmlFor={`edit-para-${linha.id}`}>Nome exibido (Para)</Label>
                            <Input
                                id={`edit-para-${linha.id}`}
                                type="text"
                                name="para"
                                defaultValue={linha.para}
                                required
                                error={!!errors.para}
                                hint={errors.para}
                            />
                        </div>
                        <div>
                            <Label htmlFor={`edit-exibir-${linha.id}`}>Quando vier junto com outros serviços</Label>
                            <Select id={`edit-exibir-${linha.id}`} name="exibir" value={exibir} onChange={setExibir} options={EXIBIR_OPTIONS} />
                            {errors.exibir && <FieldError>{errors.exibir}</FieldError>}
                        </div>
                        <div className="flex items-center justify-end gap-3">
                            <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                                Cancelar
                            </Button>
                            <Button size="sm" type="submit" disabled={processing}>
                                {processing ? 'Salvando...' : 'Salvar alterações'}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </Modal>
    );
}

export default function ServicosNomenclaturaIndex({ nomenclaturas, filters, perPageOptions, sugestoesDe, condicaoOptions }: NomenclaturaIndexProps) {
    const table = useServerTable({
        url: '/gestao/servicos-nomenclatura',
        initialSearch: filters.search,
        initialSort: { column: filters.sort, direction: filters.direction },
        initialPerPage: filters.per_page,
        initialFilters: { exibir: filters.exibir },
    });

    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<NomenclaturaItem | null>(null);

    const filtering = table.search.trim() !== '' || table.filters.exibir !== '';

    function toggleExibir(linha: NomenclaturaItem) {
        router.put(`/gestao/servicos-nomenclatura/${linha.id}/exibir`, {}, { preserveScroll: true });
    }

    const columns: ColumnDef<NomenclaturaItem>[] = [
        {
            id: 'de',
            header: 'Nome recebido (De)',
            sortable: true,
            cell: (linha) => linha.de,
        },
        {
            id: 'para',
            header: 'Nome exibido (Para)',
            sortable: true,
            cellClassName: 'font-medium text-gray-800 dark:text-white/90',
            cell: (linha) => (
                <div>
                    <div>{linha.para}</div>
                    {linha.condicao_label && (
                        <div className="mt-0.5 text-theme-xs font-normal text-gray-500 dark:text-gray-400">*{linha.condicao_label}</div>
                    )}
                </div>
            ),
        },
        {
            id: 'exibir',
            header: 'Com outros serviços',
            cell: (linha) => <ExibirBadge exibir={linha.exibir} />,
        },
        {
            id: 'actions',
            header: 'Ações',
            align: 'end',
            cellClassName: 'whitespace-nowrap',
            cell: (linha) => (
                <div className="flex justify-end gap-2">
                    <TableAction tone="brand" icon={<PencilIcon className="size-4.5" />} label="Editar" onClick={() => setEditing(linha)} />
                    <TableAction
                        tone={linha.exibir ? 'warning' : 'success'}
                        icon={linha.exibir ? <EyeCloseIcon className="size-4.5" /> : <EyeIcon className="size-4.5" />}
                        label={linha.exibir ? 'Ocultar' : 'Exibir'}
                        onClick={() => toggleExibir(linha)}
                    />
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Nomenclatura dos serviços" />
            <PageHeader title="Nomenclatura dos serviços" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <Card>
                <CardHeader
                    title="Nomenclatura dos serviços"
                    description="Nome exibido no Viabiliza para cada serviço enviado pela JUCEB. Num processo com mais de um serviço, aparecem só os marcados como Exibir; se nenhum estiver marcado, aparecem todos. Serviço novo entra aqui sozinho, com o mesmo nome."
                    actions={
                        <Button size="sm" onClick={() => setShowCreate(true)}>
                            Nova nomenclatura
                        </Button>
                    }
                />
                <CardContent>
                    <div className="space-y-5">
                        <TableToolbar
                            search={{
                                value: table.search,
                                onChange: table.setSearch,
                                placeholder: 'Buscar pelo nome recebido ou exibido...',
                                label: 'Buscar nomenclatura',
                            }}
                            filters={
                                <div className="w-40">
                                    <label htmlFor="filter-exibir" className="sr-only">
                                        Filtrar por exibição
                                    </label>
                                    <Select
                                        id="filter-exibir"
                                        value={table.filters.exibir}
                                        onChange={(value) => table.setFilter('exibir', value)}
                                        placeholder="Exibição"
                                        options={EXIBIR_OPTIONS}
                                    />
                                </div>
                            }
                            actions={<PerPageSelect value={table.perPage} options={perPageOptions} onChange={table.setPerPage} />}
                        />

                        <DataTable
                            columns={columns}
                            rows={nomenclaturas.data}
                            rowKey={(linha) => linha.id}
                            sort={table.sort}
                            onSortChange={table.setSort}
                            loading={table.processing}
                            skeletonRows={8}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title={filtering ? 'Nenhum resultado para a busca' : 'Nenhuma nomenclatura cadastrada'}
                                    description={
                                        filtering
                                            ? 'Ajuste o termo de busca ou o filtro e tente novamente.'
                                            : 'Os serviços recebidos da JUCEB aparecem aqui automaticamente.'
                                    }
                                />
                            }
                        />

                        <Pagination
                            links={nomenclaturas.links}
                            meta={{ from: nomenclaturas.from, to: nomenclaturas.to, total: nomenclaturas.total }}
                        />
                    </div>
                </CardContent>
            </Card>

            <CreateModal
                isOpen={showCreate}
                onClose={() => setShowCreate(false)}
                sugestoesDe={sugestoesDe}
                condicaoOptions={condicaoOptions}
            />

            {editing && <EditModal linha={editing} onClose={() => setEditing(null)} />}
        </>
    );
}

ServicosNomenclaturaIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
