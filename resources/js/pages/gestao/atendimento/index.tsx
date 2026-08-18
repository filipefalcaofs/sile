import { Form, Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { AlertIcon } from '@/components/icons';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';
import GestaoLayout from '@/layouts/gestao-layout';

interface AttendingCitizen {
    citizen: { id: number; name: string; cpf_masked: string };
    expires_at: string | null;
}

interface CompanyOption {
    id: number;
    legal_name: string;
    formatted_cnpj: string;
}

interface ServiceTypeOption {
    id: number;
    name: string;
}

interface AtendimentoIndexProps {
    attending: AttendingCitizen | null;
    companies: CompanyOption[];
    serviceTypes: ServiceTypeOption[];
}

function StartCard() {
    return (
        <Card>
            <CardHeader
                title="Iniciar atendimento"
                description="Informe o CPF do cidadão presente no balcão para operar o sistema em nome dele, com trilha completa (HU-150)."
            />
            <CardContent>
                <Form action="/gestao/atendimento" method="post" resetOnSuccess className="max-w-md">
                    {({ errors, processing }) => (
                        <div className="flex flex-col gap-5">
                            <div>
                                <Label htmlFor="cpf">CPF do cidadão</Label>
                                <Input
                                    id="cpf"
                                    type="text"
                                    name="cpf"
                                    required
                                    placeholder="000.000.000-00"
                                    error={!!errors.cpf}
                                    hint={errors.cpf}
                                />
                            </div>
                            <div>
                                <Button size="sm" type="submit" disabled={processing}>
                                    {processing ? 'Iniciando...' : 'Iniciar atendimento'}
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

function AttendingBanner({ attending }: { attending: AttendingCitizen }) {
    return (
        <div className="rounded-xl border border-warning-500 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/15">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-3">
                    <span className="text-warning-500">
                        <AlertIcon className="size-6 fill-current" />
                    </span>
                    <p className="text-sm font-semibold text-gray-800 dark:text-white/90">
                        Atendendo: {attending.citizen.name} — CPF {attending.citizen.cpf_masked}
                    </p>
                </div>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => router.delete('/gestao/atendimento', { preserveScroll: true })}
                >
                    Encerrar atendimento
                </Button>
            </div>
        </div>
    );
}

function OpenRequestCard({ companies, serviceTypes }: { companies: CompanyOption[]; serviceTypes: ServiceTypeOption[] }) {
    const [serviceTypeId, setServiceTypeId] = useState('');
    const [companyId, setCompanyId] = useState('');

    if (companies.length === 0) {
        return (
            <Card>
                <CardHeader title="Abrir solicitação direta" description="Solicitação de viabilidade em nome do cidadão (HU-061)." />
                <CardContent>
                    <EmptyState
                        title="O cidadão não possui empresa vinculada"
                        description="Cadastre uma empresa para o cidadão antes de abrir a solicitação de viabilidade em nome dele."
                    />
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader
                title="Abrir solicitação direta"
                description="Solicitação de viabilidade em nome do cidadão (HU-061). O processo registra o cidadão como requerente e você como executor."
            />
            <CardContent>
                <Form action="/gestao/atendimento/solicitacoes" method="post" className="max-w-md">
                    {({ errors, processing }) => (
                        <div className="flex flex-col gap-5">
                            <div>
                                <Label htmlFor="service_type_id">Tipo de serviço</Label>
                                <Select
                                    id="service_type_id"
                                    name="service_type_id"
                                    value={serviceTypeId}
                                    onChange={setServiceTypeId}
                                    placeholder="Selecione o tipo de serviço"
                                    options={serviceTypes.map((type) => ({ value: String(type.id), label: type.name }))}
                                />
                                {errors.service_type_id && (
                                    <p className="mt-1.5 text-theme-xs text-error-500">{errors.service_type_id}</p>
                                )}
                            </div>
                            <div>
                                <Label htmlFor="company_id">Empresa</Label>
                                <Select
                                    id="company_id"
                                    name="company_id"
                                    value={companyId}
                                    onChange={setCompanyId}
                                    placeholder="Selecione a empresa"
                                    options={companies.map((company) => ({
                                        value: String(company.id),
                                        label: `${company.legal_name} — ${company.formatted_cnpj}`,
                                    }))}
                                />
                                {errors.company_id && (
                                    <p className="mt-1.5 text-theme-xs text-error-500">{errors.company_id}</p>
                                )}
                            </div>
                            <div>
                                <Button size="sm" type="submit" disabled={processing}>
                                    {processing ? 'Abrindo...' : 'Abrir solicitação'}
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

export default function AtendimentoIndex({ attending, companies, serviceTypes }: AtendimentoIndexProps) {
    return (
        <>
            <Head title="Atendimento presencial" />
            <PageHeader title="Atendimento presencial" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            {attending ? (
                <div className="space-y-6">
                    <AttendingBanner attending={attending} />
                    <OpenRequestCard companies={companies} serviceTypes={serviceTypes} />
                </div>
            ) : (
                <StartCard />
            )}
        </>
    );
}

AtendimentoIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
