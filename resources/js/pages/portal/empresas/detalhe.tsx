import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { CloseIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
import PortalLayout from '@/layouts/portal-layout';
import type { SharedProps } from '@/types';
import CnaePicker, { type CnaeOption } from './cnae-picker';

interface CompanyDetail {
    id: number;
    legal_name: string;
    trade_name: string | null;
    formatted_cnpj: string;
    legal_nature_code: string | null;
    legal_nature: string | null;
    size_code: string | null;
    size: string | null;
    street: string | null;
    number: string | null;
    complement: string | null;
    neighborhood: string | null;
    city: string | null;
    state: string | null;
    zip_code: string | null;
    email: string | null;
    phone: string | null;
    source: { value: string; label: string };
    redesim_protocol: string | null;
    redesim_synced_at: string | null;
}

interface LinkRow {
    id: number;
    user_name: string | null;
    role_label: string;
    started_at: string | null;
    ended_at: string | null;
    ended_reason: string | null;
    is_current_user: boolean;
}

interface Abilities {
    update: boolean;
    manageCnaes: boolean;
    endLink: boolean;
}

interface CompanyCnaes {
    primary: CnaeOption | null;
    secondaries: CnaeOption[];
}

interface DetalheEmpresaProps {
    company: CompanyDetail;
    cnaes: CompanyCnaes;
    links: LinkRow[];
    abilities: Abilities;
}

interface CompanyForm {
    legal_name: string;
    trade_name: string;
    legal_nature_code: string;
    legal_nature: string;
    size_code: string;
    size: string;
    street: string;
    number: string;
    complement: string;
    neighborhood: string;
    city: string;
    state: string;
    zip_code: string;
    email: string;
    phone: string;
}

const headerCellStyles = 'px-5 py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400';

const bodyCellStyles = 'px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400';

/** Formata 'YYYY-MM-DD' como 'DD/MM/YYYY' sem passar por Date (evita deslocamento de fuso). */
function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const [year, month, day] = value.split('-');

    return `${day}/${month}/${year}`;
}

/** Formata 'YYYY-MM-DD HH:MM:SS' como 'DD/MM/YYYY HH:MM' sem depender de Date (evita deslocamento de fuso). */
function formatDateTime(value: string): string {
    const [date, time] = value.split(' ');
    const [year, month, day] = date.split('-');

    return `${day}/${month}/${year}${time ? ` ${time.slice(0, 5)}` : ''}`;
}

function HeaderBadges({ company, links }: { company: CompanyDetail; links: LinkRow[] }) {
    const userLinks = links.filter((link) => link.is_current_user);
    const hasActiveLink = userLinks.some((link) => link.ended_at === null);
    const sourceBadge = (
        <Badge size="sm" color={company.source.value === 'redesim' ? 'info' : 'light'}>
            {company.source.label}
        </Badge>
    );

    return (
        <div className="flex flex-wrap items-center gap-2">
            {company.redesim_synced_at ? (
                <span title={`Sincronizada com a REDESIM em ${formatDateTime(company.redesim_synced_at)}`}>
                    {sourceBadge}
                </span>
            ) : (
                sourceBadge
            )}
            {userLinks.length > 0 && (
                <Badge size="sm" color={hasActiveLink ? 'success' : 'light'}>
                    {hasActiveLink ? 'Vínculo ativo' : 'Vínculo encerrado'}
                </Badge>
            )}
        </div>
    );
}

function CompanyDataCard({ company, abilities }: { company: CompanyDetail; abilities: Abilities }) {
    const { data, setData, put, processing, errors } = useForm<CompanyForm>({
        legal_name: company.legal_name,
        trade_name: company.trade_name ?? '',
        legal_nature_code: company.legal_nature_code ?? '',
        legal_nature: company.legal_nature ?? '',
        size_code: company.size_code ?? '',
        size: company.size ?? '',
        street: company.street ?? '',
        number: company.number ?? '',
        complement: company.complement ?? '',
        neighborhood: company.neighborhood ?? '',
        city: company.city ?? '',
        state: company.state ?? '',
        zip_code: company.zip_code ?? '',
        email: company.email ?? '',
        phone: company.phone ?? '',
    });

    const readOnly = !abilities.update;

    function submit(event: React.FormEvent) {
        event.preventDefault();
        put(`/portal/empresas/${company.id}`, { preserveScroll: true });
    }

    function textField(field: keyof CompanyForm, label: string, options: { required?: boolean; span2?: boolean; maxLength?: number; type?: string } = {}) {
        return (
            <div className={options.span2 ? 'sm:col-span-2' : undefined}>
                <Label htmlFor={field} required={options.required}>
                    {label}
                </Label>
                <Input
                    id={field}
                    type={options.type ?? 'text'}
                    name={field}
                    value={data[field]}
                    maxLength={options.maxLength}
                    onChange={(event) => setData(field, event.target.value)}
                    disabled={readOnly}
                    error={!!errors[field]}
                    hint={errors[field]}
                />
            </div>
        );
    }

    return (
        <Card>
            <CardHeader
                title="Dados da empresa"
                description="Dados cadastrais e de contato. O CNPJ identifica a empresa e não pode ser alterado."
            />
            <CardContent>
                <form onSubmit={submit} className="flex flex-col gap-5">
                    {readOnly && (
                        <Alert
                            variant="warning"
                            title="Somente leitura"
                            message="Seu vínculo com esta empresa está encerrado — os dados são somente leitura."
                        />
                    )}

                    <div className="grid gap-5 sm:grid-cols-2">
                        <div>
                            <Label htmlFor="cnpj">CNPJ</Label>
                            <Input
                                id="cnpj"
                                type="text"
                                name="cnpj"
                                value={company.formatted_cnpj}
                                disabled
                                hint="O CNPJ não pode ser alterado."
                            />
                        </div>
                        {textField('legal_name', 'Razão social', { required: true })}
                        {textField('trade_name', 'Nome fantasia')}
                        {textField('legal_nature_code', 'Natureza jurídica (código)', { maxLength: 4 })}
                        {textField('legal_nature', 'Natureza jurídica')}
                        {textField('size_code', 'Porte (código)', { maxLength: 2 })}
                        {textField('size', 'Porte')}
                    </div>

                    <h4 className="border-t border-gray-100 pt-5 text-sm font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90">
                        Endereço
                    </h4>
                    <div className="grid gap-5 sm:grid-cols-2">
                        {textField('street', 'Logradouro', { span2: true })}
                        {textField('number', 'Número', { maxLength: 20 })}
                        {textField('complement', 'Complemento')}
                        {textField('neighborhood', 'Bairro')}
                        {textField('city', 'Município')}
                        <div>
                            <Label htmlFor="state">UF</Label>
                            <Input
                                id="state"
                                type="text"
                                name="state"
                                maxLength={2}
                                value={data.state}
                                onChange={(event) => setData('state', event.target.value.toUpperCase())}
                                placeholder="BA"
                                disabled={readOnly}
                                error={!!errors.state}
                                hint={errors.state}
                            />
                        </div>
                        {textField('zip_code', 'CEP', { maxLength: 9 })}
                    </div>

                    <h4 className="border-t border-gray-100 pt-5 text-sm font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90">
                        Contato
                    </h4>
                    <div className="grid gap-5 sm:grid-cols-2">
                        {textField('email', 'E-mail', { type: 'email' })}
                        {textField('phone', 'Telefone', { maxLength: 11 })}
                    </div>

                    <div className="flex justify-end border-t border-gray-100 pt-5 dark:border-gray-800">
                        <Button type="submit" size="sm" disabled={processing || !abilities.update}>
                            {processing ? 'Salvando...' : 'Salvar alterações'}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function CnaeLine({ cnae, action }: { cnae: CnaeOption; action?: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-3 rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
            <div className="flex flex-col">
                <span className="text-sm font-medium text-gray-800 dark:text-white/90">{cnae.formatted_code}</span>
                <span className="text-theme-xs text-gray-500 dark:text-gray-400">{cnae.description}</span>
            </div>
            {action}
        </div>
    );
}

function CnaesCard({
    company,
    cnaes,
    canManage,
}: {
    company: CompanyDetail;
    cnaes: CompanyCnaes;
    canManage: boolean;
}) {
    const { errors } = usePage<SharedProps>().props;

    const [showPrimaryPicker, setShowPrimaryPicker] = useState(false);
    const [primaryCandidate, setPrimaryCandidate] = useState<CnaeOption | null>(null);
    const [primaryProcessing, setPrimaryProcessing] = useState(false);

    const [secondaries, setSecondaries] = useState<CnaeOption[]>(cnaes.secondaries);
    const [secondariesProcessing, setSecondariesProcessing] = useState(false);

    const serverIdsKey = cnaes.secondaries
        .map((cnae) => cnae.id)
        .sort((a, b) => a - b)
        .join(',');
    const localIdsKey = secondaries
        .map((cnae) => cnae.id)
        .sort((a, b) => a - b)
        .join(',');
    const dirty = localIdsKey !== serverIdsKey;

    /*
     * Re-sincroniza o estado local quando o conjunto do servidor muda
     * (ex.: troca de principal demove o antigo para os secundários).
     */
    useEffect(() => {
        setSecondaries(cnaes.secondaries);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [serverIdsKey]);

    const cnaeErrors = Object.entries(errors as Record<string, string>)
        .filter(([key]) => key === 'cnae_id' || key === 'cnaes' || key.startsWith('cnaes.'))
        .map(([, message]) => message);

    function confirmPrimaryChange() {
        if (!primaryCandidate) {
            return;
        }

        router.put(
            `/portal/empresas/${company.id}/cnae-principal`,
            { cnae_id: primaryCandidate.id },
            {
                preserveScroll: true,
                onStart: () => setPrimaryProcessing(true),
                onSuccess: () => setShowPrimaryPicker(false),
                onFinish: () => {
                    setPrimaryProcessing(false);
                    setPrimaryCandidate(null);
                },
            },
        );
    }

    function saveSecondaries() {
        router.put(
            `/portal/empresas/${company.id}/cnaes-secundarios`,
            { cnaes: secondaries.map((cnae) => cnae.id) },
            {
                preserveScroll: true,
                onStart: () => setSecondariesProcessing(true),
                onFinish: () => setSecondariesProcessing(false),
            },
        );
    }

    const primaryExcludeIds = cnaes.primary ? [cnaes.primary.id] : [];
    const secondariesExcludeIds = [
        ...(cnaes.primary ? [cnaes.primary.id] : []),
        ...secondaries.map((cnae) => cnae.id),
    ];

    return (
        <Card>
            <CardHeader
                title="Atividades econômicas (CNAEs)"
                description="CNAE principal e secundários vinculados à empresa — base do enquadramento das solicitações."
            />
            <CardContent>
                <div className="flex flex-col gap-6">
                    {cnaeErrors.length > 0 && (
                        <Alert variant="error" title="Não foi possível salvar" message={cnaeErrors.join(' ')} />
                    )}

                    <div className="flex flex-col gap-3">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h4 className="text-sm font-semibold text-gray-800 dark:text-white/90">CNAE principal</h4>
                            {canManage && (
                                <Button
                                    size="xs"
                                    variant="outline"
                                    onClick={() => setShowPrimaryPicker((current) => !current)}
                                >
                                    {showPrimaryPicker
                                        ? 'Cancelar'
                                        : cnaes.primary
                                          ? 'Alterar principal'
                                          : 'Definir principal'}
                                </Button>
                            )}
                        </div>

                        {cnaes.primary ? (
                            <CnaeLine cnae={cnaes.primary} action={<Badge size="sm">Principal</Badge>} />
                        ) : (
                            <p className="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                Nenhum CNAE principal definido.
                            </p>
                        )}

                        {canManage && showPrimaryPicker && (
                            <CnaePicker
                                onSelect={(cnae) => setPrimaryCandidate(cnae)}
                                excludeIds={primaryExcludeIds}
                                placeholder="Buscar novo CNAE principal..."
                            />
                        )}
                    </div>

                    <div className="flex flex-col gap-3 border-t border-gray-100 pt-5 dark:border-gray-800">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex flex-wrap items-center gap-2">
                                <h4 className="text-sm font-semibold text-gray-800 dark:text-white/90">
                                    CNAEs secundários
                                </h4>
                                {canManage && dirty && (
                                    <Badge size="sm" color="warning">
                                        Alterações não salvas
                                    </Badge>
                                )}
                            </div>
                            {canManage && (
                                <Button
                                    size="xs"
                                    onClick={saveSecondaries}
                                    disabled={!dirty || secondariesProcessing}
                                >
                                    {secondariesProcessing ? 'Salvando...' : 'Salvar secundários'}
                                </Button>
                            )}
                        </div>

                        {secondaries.length > 0 ? (
                            <div className="flex flex-col gap-2">
                                {secondaries.map((cnae) => (
                                    <CnaeLine
                                        key={cnae.id}
                                        cnae={cnae}
                                        action={
                                            canManage ? (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setSecondaries((current) =>
                                                            current.filter((item) => item.id !== cnae.id),
                                                        )
                                                    }
                                                    aria-label={`Remover ${cnae.formatted_code}`}
                                                    title="Remover da seleção"
                                                    className="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-error-50 hover:text-error-600 dark:hover:bg-error-500/10 dark:hover:text-error-400"
                                                >
                                                    <CloseIcon className="size-4" />
                                                </button>
                                            ) : undefined
                                        }
                                    />
                                ))}
                            </div>
                        ) : (
                            <p className="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                Nenhum CNAE secundário selecionado.
                            </p>
                        )}

                        {canManage && (
                            <CnaePicker
                                onSelect={(cnae) => setSecondaries((current) => [...current, cnae])}
                                excludeIds={secondariesExcludeIds}
                                placeholder="Adicionar CNAE secundário..."
                            />
                        )}
                    </div>
                </div>
            </CardContent>

            <ConfirmDialog
                isOpen={primaryCandidate !== null}
                onClose={() => setPrimaryCandidate(null)}
                onConfirm={confirmPrimaryChange}
                title="Alterar CNAE principal"
                description={
                    primaryCandidate
                        ? `A troca do CNAE principal impacta o enquadramento da atividade nas próximas solicitações. Confirmar a alteração para ${primaryCandidate.formatted_code} — ${primaryCandidate.description}?`
                        : ''
                }
                confirmLabel="Alterar principal"
                variant="warning"
                processing={primaryProcessing}
            />
        </Card>
    );
}

function LinksCard({ company, links, canEndLink }: { company: CompanyDetail; links: LinkRow[]; canEndLink: boolean }) {
    const { errors } = usePage<SharedProps>().props;

    const [confirmingEnd, setConfirmingEnd] = useState(false);
    const [endReason, setEndReason] = useState('');
    const [endProcessing, setEndProcessing] = useState(false);

    function endLink() {
        router.delete(`/portal/empresas/${company.id}/vinculo`, {
            data: { ended_reason: endReason.trim() === '' ? null : endReason.trim() },
            preserveScroll: true,
            onStart: () => setEndProcessing(true),
            onSuccess: () => setEndReason(''),
            onFinish: () => {
                setEndProcessing(false);
                setConfirmingEnd(false);
            },
        });
    }

    return (
        <Card>
            <CardHeader
                title="Vínculos"
                description="Pessoas vinculadas à empresa — o histórico de vínculos encerrados é preservado."
                actions={
                    canEndLink ? (
                        <Button size="sm" variant="danger" onClick={() => setConfirmingEnd(true)}>
                            Encerrar meu vínculo
                        </Button>
                    ) : undefined
                }
            />
            <CardContent flush>
                <div className="flex flex-col">
                    {errors.vinculo && (
                        <div className="px-5 pt-5">
                            <Alert variant="error" title="Não foi possível encerrar o vínculo" message={errors.vinculo} />
                        </div>
                    )}

                    <div className="max-w-full overflow-x-auto">
                        <Table>
                            <TableHeader className="border-b border-gray-100 dark:border-white/[0.05]">
                                <TableRow>
                                    <TableCell isHeader className={headerCellStyles}>
                                        Usuário
                                    </TableCell>
                                    <TableCell isHeader className={headerCellStyles}>
                                        Papel
                                    </TableCell>
                                    <TableCell isHeader className={headerCellStyles}>
                                        Início
                                    </TableCell>
                                    <TableCell isHeader className={headerCellStyles}>
                                        Fim
                                    </TableCell>
                                    <TableCell isHeader className={headerCellStyles}>
                                        Motivo
                                    </TableCell>
                                </TableRow>
                            </TableHeader>
                            <TableBody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
                                {links.map((link) => (
                                    <TableRow
                                        key={link.id}
                                        className={
                                            link.is_current_user
                                                ? 'bg-brand-50/60 dark:bg-brand-500/[0.08]'
                                                : 'transition hover:bg-gray-50 dark:hover:bg-white/[0.03]'
                                        }
                                    >
                                        <TableCell className="px-5 py-4 text-start text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                            <div className="flex flex-wrap items-center gap-2">
                                                {link.user_name ?? '—'}
                                                {link.is_current_user && (
                                                    <Badge size="sm" color="primary">
                                                        Você
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className={bodyCellStyles}>{link.role_label}</TableCell>
                                        <TableCell className={`${bodyCellStyles} whitespace-nowrap`}>
                                            {formatDate(link.started_at)}
                                        </TableCell>
                                        <TableCell className={`${bodyCellStyles} whitespace-nowrap`}>
                                            {link.ended_at ? (
                                                formatDate(link.ended_at)
                                            ) : (
                                                <Badge size="sm" color="success">
                                                    Ativo
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className={bodyCellStyles}>{link.ended_reason ?? '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </CardContent>

            <ConfirmDialog
                isOpen={confirmingEnd}
                onClose={() => setConfirmingEnd(false)}
                onConfirm={endLink}
                title="Encerrar vínculo com a empresa"
                description={
                    <div className="flex flex-col gap-4 text-start">
                        <p>
                            Esta ação não exclui o histórico — o vínculo ficará registrado como encerrado e a empresa
                            passará a ser somente leitura para você.
                        </p>
                        <div>
                            <Label htmlFor="ended_reason">Motivo (opcional)</Label>
                            <Input
                                id="ended_reason"
                                type="text"
                                name="ended_reason"
                                value={endReason}
                                maxLength={255}
                                onChange={(event) => setEndReason(event.target.value)}
                                placeholder="Ex.: encerramento das atividades"
                            />
                        </div>
                    </div>
                }
                confirmLabel="Encerrar vínculo"
                variant="danger"
                processing={endProcessing}
            />
        </Card>
    );
}

export default function DetalheEmpresa({ company, cnaes, links, abilities }: DetalheEmpresaProps) {
    return (
        <>
            <Head title={company.legal_name} />
            <PageHeader
                title={company.legal_name}
                breadcrumbs={[
                    { label: 'Meu painel', href: '/portal/painel' },
                    { label: 'Minhas empresas', href: '/portal/empresas' },
                ]}
                actions={<HeaderBadges company={company} links={links} />}
            />

            <div className="flex flex-col gap-4 md:gap-6">
                <CompanyDataCard company={company} abilities={abilities} />
                <CnaesCard company={company} cnaes={cnaes} canManage={abilities.manageCnaes} />
                <LinksCard company={company} links={links} canEndLink={abilities.endLink} />
            </div>
        </>
    );
}

DetalheEmpresa.layout = (page: ReactNode) => <PortalLayout>{page}</PortalLayout>;
