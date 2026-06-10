import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface CnaeItem {
    id: number;
    code: string;
    formatted_code: string;
    description: string;
    active: boolean;
    class_code: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface CnaesIndexProps {
    cnaes: {
        data: CnaeItem[];
        links: PaginationLink[];
    };
    filters: {
        search: string;
    };
}

const inputStyles =
    'mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 focus:outline-none dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100';

const labelStyles = 'block text-sm font-medium text-neutral-700 dark:text-neutral-300';

function SituationBadge({ active }: { active: boolean }) {
    const styles = active
        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100'
        : 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-100';

    return (
        <span className={`inline-block rounded-lg px-2 py-0.5 text-xs font-medium ${styles}`}>
            {active ? 'Ativo' : 'Inativo'}
        </span>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="mt-1 text-sm text-red-600 dark:text-red-400">{message}</p>;
}

function CreateCnaeForm() {
    return (
        <Form action="/gestao/cnaes" method="post" resetOnSuccess className="mt-4">
            {({ errors, processing }) => (
                <div className="flex flex-col gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label htmlFor="create-code" className={labelStyles}>
                                Código (DDDD-D/SS)
                            </label>
                            <input
                                id="create-code"
                                type="text"
                                name="code"
                                required
                                placeholder="0000-0/00"
                                className={inputStyles}
                            />
                            <FieldError message={errors.code} />
                        </div>
                        <div>
                            <label htmlFor="create-description" className={labelStyles}>
                                Denominação
                            </label>
                            <input
                                id="create-description"
                                type="text"
                                name="description"
                                required
                                className={inputStyles}
                            />
                            <FieldError message={errors.description} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label htmlFor="create-section-code" className={labelStyles}>
                                Seção (código e descrição)
                            </label>
                            <div className="flex gap-2">
                                <input
                                    id="create-section-code"
                                    type="text"
                                    name="section_code"
                                    required
                                    maxLength={1}
                                    placeholder="A"
                                    className={`${inputStyles} w-16`}
                                />
                                <input
                                    type="text"
                                    name="section_description"
                                    required
                                    aria-label="Descrição da seção"
                                    className={inputStyles}
                                />
                            </div>
                            <FieldError message={errors.section_code} />
                            <FieldError message={errors.section_description} />
                        </div>
                        <div>
                            <label htmlFor="create-division-code" className={labelStyles}>
                                Divisão (código e descrição)
                            </label>
                            <div className="flex gap-2">
                                <input
                                    id="create-division-code"
                                    type="text"
                                    name="division_code"
                                    required
                                    maxLength={2}
                                    placeholder="01"
                                    className={`${inputStyles} w-16`}
                                />
                                <input
                                    type="text"
                                    name="division_description"
                                    required
                                    aria-label="Descrição da divisão"
                                    className={inputStyles}
                                />
                            </div>
                            <FieldError message={errors.division_code} />
                            <FieldError message={errors.division_description} />
                        </div>
                        <div>
                            <label htmlFor="create-group-code" className={labelStyles}>
                                Grupo (código e descrição)
                            </label>
                            <div className="flex gap-2">
                                <input
                                    id="create-group-code"
                                    type="text"
                                    name="group_code"
                                    required
                                    maxLength={5}
                                    placeholder="01.1"
                                    className={`${inputStyles} w-20`}
                                />
                                <input
                                    type="text"
                                    name="group_description"
                                    required
                                    aria-label="Descrição do grupo"
                                    className={inputStyles}
                                />
                            </div>
                            <FieldError message={errors.group_code} />
                            <FieldError message={errors.group_description} />
                        </div>
                        <div>
                            <label htmlFor="create-class-code" className={labelStyles}>
                                Classe (código e descrição)
                            </label>
                            <div className="flex gap-2">
                                <input
                                    id="create-class-code"
                                    type="text"
                                    name="class_code"
                                    required
                                    maxLength={7}
                                    placeholder="01.11-3"
                                    className={`${inputStyles} w-24`}
                                />
                                <input
                                    type="text"
                                    name="class_description"
                                    required
                                    aria-label="Descrição da classe"
                                    className={inputStyles}
                                />
                            </div>
                            <FieldError message={errors.class_code} />
                            <FieldError message={errors.class_description} />
                        </div>
                    </div>

                    <div>
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500"
                        >
                            {processing ? 'Salvando...' : 'Salvar'}
                        </button>
                    </div>
                </div>
            )}
        </Form>
    );
}

function EditCnaeRow({ cnae, onClose }: { cnae: CnaeItem; onClose: () => void }) {
    return (
        <tr className="border-b border-neutral-100 bg-neutral-50 last:border-0 dark:border-neutral-800 dark:bg-neutral-950/50">
            <td colSpan={5} className="px-2 py-4">
                <Form
                    action={`/gestao/cnaes/${cnae.id}`}
                    method="put"
                    onSuccess={onClose}
                >
                    {({ errors, processing }) => (
                        <div className="flex flex-col gap-4">
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <span className={labelStyles}>Código</span>
                                    <p className="mt-1 px-1 py-2 text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                        {cnae.formatted_code}
                                    </p>
                                    <p className="text-xs text-neutral-500 dark:text-neutral-400">
                                        O código não pode ser alterado.
                                    </p>
                                </div>
                                <div>
                                    <label htmlFor={`edit-description-${cnae.id}`} className={labelStyles}>
                                        Denominação
                                    </label>
                                    <input
                                        id={`edit-description-${cnae.id}`}
                                        type="text"
                                        name="description"
                                        defaultValue={cnae.description}
                                        required
                                        className={inputStyles}
                                    />
                                    <FieldError message={errors.description} />
                                </div>
                                <div>
                                    <label htmlFor={`edit-active-${cnae.id}`} className={labelStyles}>
                                        Situação
                                    </label>
                                    <select
                                        id={`edit-active-${cnae.id}`}
                                        name="active"
                                        defaultValue={cnae.active ? '1' : '0'}
                                        className={inputStyles}
                                    >
                                        <option value="1">Ativo</option>
                                        <option value="0">Inativo</option>
                                    </select>
                                    <FieldError message={errors.active} />
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500"
                                >
                                    {processing ? 'Salvando...' : 'Salvar alterações'}
                                </button>
                                <button
                                    type="button"
                                    onClick={onClose}
                                    className="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-900 transition hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-100 dark:hover:bg-neutral-800"
                                >
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    )}
                </Form>
            </td>
        </tr>
    );
}

function CnaeActions({ cnae, onEdit }: { cnae: CnaeItem; onEdit: () => void }) {
    return (
        <div className="flex flex-wrap gap-2">
            <button
                type="button"
                onClick={onEdit}
                className="rounded-lg border border-blue-300 px-3 py-1 text-xs font-medium text-blue-700 transition hover:bg-blue-50 dark:border-blue-800 dark:text-blue-400 dark:hover:bg-blue-950"
            >
                Editar
            </button>
            <Form action={`/gestao/cnaes/${cnae.id}`} method="put" className="inline">
                <input type="hidden" name="description" value={cnae.description} />
                <input type="hidden" name="active" value={cnae.active ? '0' : '1'} />
                <button
                    type="submit"
                    className="rounded-lg border border-amber-300 px-3 py-1 text-xs font-medium text-amber-700 transition hover:bg-amber-50 dark:border-amber-800 dark:text-amber-400 dark:hover:bg-amber-950"
                >
                    {cnae.active ? 'Desativar' : 'Reativar'}
                </button>
            </Form>
            <Form action={`/gestao/cnaes/${cnae.id}`} method="delete" className="inline">
                <button
                    type="submit"
                    onClick={(event) => {
                        if (!window.confirm('Excluir este CNAE?')) {
                            event.preventDefault();
                        }
                    }}
                    className="rounded-lg border border-red-300 px-3 py-1 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-950"
                >
                    Excluir
                </button>
            </Form>
        </div>
    );
}

export default function CnaesIndex({ cnaes, filters }: CnaesIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-cnaes');

    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstRender = useRef(true);
    const [showCreate, setShowCreate] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        const timeout = setTimeout(() => {
            router.get('/gestao/cnaes', { search }, { preserveState: true, replace: true });
        }, 350);
        return () => clearTimeout(timeout);
    }, [search]);

    return (
        <GestaoLayout>
            <Head title="CNAEs" />
            <div className="flex flex-col gap-6">
                <div>
                    <h2 className="text-xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">
                        CNAEs
                    </h2>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        Estrutura oficial CNAE-Subclasses 2.3 (IBGE/CONCLA)
                    </p>
                </div>

                {canMaintain && (
                    <section className="rounded-xl bg-white p-6 shadow-sm dark:bg-neutral-900">
                        <button
                            type="button"
                            onClick={() => setShowCreate((current) => !current)}
                            className="flex w-full items-center justify-between text-left"
                        >
                            <span className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                                Cadastrar CNAE
                            </span>
                            <span className="text-sm text-neutral-500 dark:text-neutral-400">
                                {showCreate ? 'Recolher' : 'Expandir'}
                            </span>
                        </button>
                        {showCreate && <CreateCnaeForm />}
                    </section>
                )}

                <section className="rounded-xl bg-white p-6 shadow-sm dark:bg-neutral-900">
                    <label htmlFor="search" className="sr-only">
                        Buscar CNAEs
                    </label>
                    <input
                        id="search"
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Buscar por código ou denominação..."
                        className="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 focus:outline-none sm:max-w-sm dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100"
                    />

                    {cnaes.data.length === 0 ? (
                        <p className="mt-3 text-sm text-neutral-500 dark:text-neutral-400">
                            Nenhum CNAE encontrado.
                        </p>
                    ) : (
                        <div className="mt-3 overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                                        <th className="py-2 pr-4 font-medium">Código</th>
                                        <th className="py-2 pr-4 font-medium">Denominação</th>
                                        <th className="py-2 pr-4 font-medium">Classe</th>
                                        <th className="py-2 pr-4 font-medium">Situação</th>
                                        {canMaintain && <th className="py-2 font-medium">Ações</th>}
                                    </tr>
                                </thead>
                                <tbody>
                                    {cnaes.data.map((cnae) =>
                                        editingId === cnae.id ? (
                                            <EditCnaeRow
                                                key={cnae.id}
                                                cnae={cnae}
                                                onClose={() => setEditingId(null)}
                                            />
                                        ) : (
                                            <tr
                                                key={cnae.id}
                                                className="border-b border-neutral-100 text-neutral-900 last:border-0 dark:border-neutral-800 dark:text-neutral-100"
                                            >
                                                <td className="py-2.5 pr-4 font-medium whitespace-nowrap">
                                                    {cnae.formatted_code}
                                                </td>
                                                <td className="py-2.5 pr-4">{cnae.description}</td>
                                                <td className="py-2.5 pr-4 whitespace-nowrap">
                                                    {cnae.class_code}
                                                </td>
                                                <td className="py-2.5 pr-4">
                                                    <SituationBadge active={cnae.active} />
                                                </td>
                                                {canMaintain && (
                                                    <td className="py-2.5">
                                                        <CnaeActions
                                                            cnae={cnae}
                                                            onEdit={() => setEditingId(cnae.id)}
                                                        />
                                                    </td>
                                                )}
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {cnaes.links.length > 3 && (
                        <nav className="mt-4 flex flex-wrap gap-1">
                            {cnaes.links.map((link, index) =>
                                link.url ? (
                                    <Link
                                        key={index}
                                        href={link.url}
                                        className={`rounded-lg px-3 py-1.5 text-sm transition ${
                                            link.active
                                                ? 'bg-blue-700 font-medium text-white dark:bg-blue-600'
                                                : 'text-neutral-700 hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span
                                        key={index}
                                        className="rounded-lg px-3 py-1.5 text-sm text-neutral-400 dark:text-neutral-600"
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ),
                            )}
                        </nav>
                    )}
                </section>
            </div>
        </GestaoLayout>
    );
}
