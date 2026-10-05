import FieldError from '@/components/form/field-error';
import { Form, Head, router, useHttp } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import MaskedInput from '@/components/form/masked-input';
import Label from '@/components/form/label';
import SearchableSelect from '@/components/form/searchable-select';
import Select from '@/components/form/select';
import { AlertIcon } from '@/components/icons';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';
import GestaoLayout from '@/layouts/gestao-layout';
import { mensagemDaConsultaSedur, resumoConsultaCpfSedur } from '@/lib/sedur-consulta';

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
    const [cpf, setCpf] = useState('');
    const consultaCpf = useHttp({ cpf: '' });
    const [cpfErro, setCpfErro] = useState<string | null>(null);
    const [cpfResumo, setCpfResumo] = useState<string | null>(null);

    function buscarCpf() {
        const cpfInformado = cpf.trim();

        if (cpfInformado === '') {
            setCpfErro('Informe o CPF do cidadão.');
            setCpfResumo(null);

            return;
        }

        setCpfErro(null);
        setCpfResumo(null);
        consultaCpf.setData('cpf', cpfInformado);
        consultaCpf.post('/gestao/atendimento/consultar-cpf', {
            onSuccess: (response: unknown) => {
                const payload = response as { nome: string | null; cadastrado: boolean };
                setCpfResumo(resumoConsultaCpfSedur(payload.nome, payload.cadastrado));
            },
            onError: (erros: { cpf?: string }) => {
                setCpfErro(erros.cpf ?? 'Não foi possível consultar o CPF na SEDUR.');
            },
            onHttpException: (response: { data?: unknown }) => {
                setCpfErro(mensagemDaConsultaSedur(response.data, 'Não foi possível consultar o CPF na SEDUR.'));
            },
            onNetworkError: () => {
                setCpfErro('Não foi possível consultar o CPF na SEDUR.');
            },
        });
    }

    return (
        <Card>
            <CardHeader
                title="Iniciar atendimento"
                description="Informe o CPF do cidadão presente no balcão. Busque na SEDUR para conferir o nome antes de iniciar o vínculo."
            />
            <CardContent>
                <Form action="/gestao/atendimento" method="post" resetOnSuccess className="max-w-md">
                    {({ errors, processing }) => (
                        <div className="flex flex-col gap-5">
                            <div>
                                <Label htmlFor="cpf">CPF do cidadão</Label>
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                                    <div className="flex-1">
                                        <MaskedInput
                                            id="cpf"
                                            mask="cpf"
                                            name="cpf"
                                            value={cpf}
                                            onChange={(valor) => {
                                                setCpf(valor);
                                                setCpfErro(null);
                                                setCpfResumo(null);
                                            }}
                                            required
                                            autoComplete="off"
                                            placeholder="000.000.000-00"
                                            error={!!errors.cpf || !!cpfErro}
                                            hint={errors.cpf}
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={buscarCpf}
                                        disabled={consultaCpf.processing}
                                        loading={consultaCpf.processing}
                                    >
                                        {consultaCpf.processing ? 'Buscando...' : 'Buscar CPF'}
                                    </Button>
                                </div>
                                {cpfErro && <FieldError>{cpfErro}</FieldError>}
                                {cpfResumo && !cpfErro && (
                                    <p className="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400">{cpfResumo}</p>
                                )}
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
                <CardHeader title="Abrir solicitação direta" description="Solicitação de viabilidade em nome do cidadão." />
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
                description="Solicitação de viabilidade em nome do cidadão. O processo registra o cidadão como requerente e você como executor."
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
                                    <FieldError>{errors.service_type_id}</FieldError>
                                )}
                            </div>
                            <div>
                                <Label htmlFor="company_id">Empresa</Label>
                                <SearchableSelect
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
                                    <FieldError>{errors.company_id}</FieldError>
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
