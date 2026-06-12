import { Head, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import PortalLayout from '@/layouts/portal-layout';

interface CnaeOption {
    id: number;
    formatted_code: string;
    description: string;
}

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

interface DetalheEmpresaProps {
    company: CompanyDetail;
    cnaes: { primary: CnaeOption | null; secondaries: CnaeOption[] };
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

export default function DetalheEmpresa({ company, links, abilities }: DetalheEmpresaProps) {
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
            </div>
        </>
    );
}

DetalheEmpresa.layout = (page: ReactNode) => <PortalLayout>{page}</PortalLayout>;
