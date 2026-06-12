import { Head, Link, useForm, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Alert from '@/components/ui/alert';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import PortalLayout from '@/layouts/portal-layout';

interface CadastrarEmpresaProps {
    cnpjLookupEnabled: boolean;
}

interface CompanyForm {
    cnpj: string;
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

const EMPTY_FORM: CompanyForm = {
    cnpj: '',
    legal_name: '',
    trade_name: '',
    legal_nature_code: '',
    legal_nature: '',
    size_code: '',
    size: '',
    street: '',
    number: '',
    complement: '',
    neighborhood: '',
    city: '',
    state: '',
    zip_code: '',
    email: '',
    phone: '',
};

/** Campos preenchidos pelo lookup — chaves snake_case do CnpjData::toArray ([03-02]). */
const LOOKUP_FIELDS: (keyof CompanyForm)[] = [
    'legal_name',
    'trade_name',
    'legal_nature_code',
    'legal_nature',
    'size_code',
    'size',
    'street',
    'number',
    'complement',
    'neighborhood',
    'city',
    'state',
    'zip_code',
    'email',
    'phone',
];

/**
 * Aplica a máscara XX.XXX.XXX/XXXX-XX sobre o conteúdo alfanumérico em
 * maiúsculas. O CNPJ alfanumérico (RFB, julho/2026) tem letras na raiz, por
 * isso NÃO restringimos a dígitos — só os 2 DV finais são numéricos.
 */
function formatCnpj(raw: string): string {
    const value = raw.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 14);
    const parts = [
        value.slice(0, 2),
        value.slice(2, 5),
        value.slice(5, 8),
        value.slice(8, 12),
        value.slice(12, 14),
    ];

    let result = parts[0];
    if (parts[1]) {
        result += `.${parts[1]}`;
    }
    if (parts[2]) {
        result += `.${parts[2]}`;
    }
    if (parts[3]) {
        result += `/${parts[3]}`;
    }
    if (parts[4]) {
        result += `-${parts[4]}`;
    }

    return result;
}

export default function CadastrarEmpresa({ cnpjLookupEnabled }: CadastrarEmpresaProps) {
    const { data, setData, post, processing, errors } = useForm<CompanyForm>(EMPTY_FORM);
    const lookup = useHttp({ cnpj: '' });
    const [lookupError, setLookupError] = useState<string | null>(null);

    function handleCnpjChange(value: string) {
        setData('cnpj', formatCnpj(value));
    }

    function buscarCnpj() {
        if (!cnpjLookupEnabled) {
            return;
        }

        setLookupError(null);
        lookup.setData('cnpj', data.cnpj);
        lookup.post('/portal/empresas/consultar-cnpj', {
            onSuccess: (response: unknown) => {
                const payload = response as Partial<Record<keyof CompanyForm, string | null>>;
                const filled: Partial<CompanyForm> = {};
                for (const field of LOOKUP_FIELDS) {
                    const incoming = payload[field];
                    if (incoming !== null && incoming !== undefined && incoming !== '') {
                        filled[field] = String(incoming);
                    }
                }
                setData((current) => ({ ...current, ...filled }));
            },
            onError: (payload: { message?: string }) => {
                setLookupError(
                    payload?.message ?? 'Não foi possível consultar o CNPJ. Preencha os dados manualmente.',
                );
            },
        });
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();
        post('/portal/empresas');
    }

    return (
        <PortalLayout>
            <Head title="Cadastrar empresa" />
            <PageHeader
                title="Cadastrar empresa"
                breadcrumbs={[
                    { label: 'Meu painel', href: '/portal/painel' },
                    { label: 'Minhas empresas', href: '/portal/empresas' },
                ]}
            />

            {lookupError && (
                <div className="mb-6">
                    <Alert variant="error" title="Consulta de CNPJ" message={lookupError} />
                </div>
            )}

            <form onSubmit={submit} className="flex flex-col gap-4 md:gap-6">
                <Card>
                    <CardHeader
                        title="Identificação"
                        description="Informe o CNPJ e busque os dados automaticamente, ou preencha manualmente."
                    />
                    <CardContent>
                        <div className="flex flex-col gap-5">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                                <div className="flex-1">
                                    <Label htmlFor="cnpj" required>
                                        CNPJ
                                    </Label>
                                    <Input
                                        id="cnpj"
                                        type="text"
                                        name="cnpj"
                                        value={data.cnpj}
                                        onChange={(event) => handleCnpjChange(event.target.value)}
                                        placeholder="00.000.000/0000-00"
                                        error={!!errors.cnpj}
                                        hint={
                                            errors.cnpj ??
                                            (cnpjLookupEnabled
                                                ? undefined
                                                : 'Consulta automática indisponível — preencha os dados manualmente.')
                                        }
                                    />
                                </div>
                                <div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={buscarCnpj}
                                        disabled={!cnpjLookupEnabled || lookup.processing}
                                    >
                                        {lookup.processing ? 'Buscando...' : 'Buscar CNPJ'}
                                    </Button>
                                </div>
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="legal_name" required>
                                        Razão social
                                    </Label>
                                    <Input
                                        id="legal_name"
                                        type="text"
                                        name="legal_name"
                                        value={data.legal_name}
                                        onChange={(event) => setData('legal_name', event.target.value)}
                                        error={!!errors.legal_name}
                                        hint={errors.legal_name}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="trade_name">Nome fantasia</Label>
                                    <Input
                                        id="trade_name"
                                        type="text"
                                        name="trade_name"
                                        value={data.trade_name}
                                        onChange={(event) => setData('trade_name', event.target.value)}
                                        error={!!errors.trade_name}
                                        hint={errors.trade_name}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="legal_nature_code">Natureza jurídica (código)</Label>
                                    <Input
                                        id="legal_nature_code"
                                        type="text"
                                        name="legal_nature_code"
                                        value={data.legal_nature_code}
                                        onChange={(event) => setData('legal_nature_code', event.target.value)}
                                        error={!!errors.legal_nature_code}
                                        hint={errors.legal_nature_code}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="legal_nature">Natureza jurídica</Label>
                                    <Input
                                        id="legal_nature"
                                        type="text"
                                        name="legal_nature"
                                        value={data.legal_nature}
                                        onChange={(event) => setData('legal_nature', event.target.value)}
                                        error={!!errors.legal_nature}
                                        hint={errors.legal_nature}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="size_code">Porte (código)</Label>
                                    <Input
                                        id="size_code"
                                        type="text"
                                        name="size_code"
                                        value={data.size_code}
                                        onChange={(event) => setData('size_code', event.target.value)}
                                        error={!!errors.size_code}
                                        hint={errors.size_code}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="size">Porte</Label>
                                    <Input
                                        id="size"
                                        type="text"
                                        name="size"
                                        value={data.size}
                                        onChange={(event) => setData('size', event.target.value)}
                                        error={!!errors.size}
                                        hint={errors.size}
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader title="Endereço" description="Endereço do estabelecimento (texto nesta fase)." />
                    <CardContent>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <Label htmlFor="street">Logradouro</Label>
                                <Input
                                    id="street"
                                    type="text"
                                    name="street"
                                    value={data.street}
                                    onChange={(event) => setData('street', event.target.value)}
                                    error={!!errors.street}
                                    hint={errors.street}
                                />
                            </div>
                            <div>
                                <Label htmlFor="number">Número</Label>
                                <Input
                                    id="number"
                                    type="text"
                                    name="number"
                                    value={data.number}
                                    onChange={(event) => setData('number', event.target.value)}
                                    error={!!errors.number}
                                    hint={errors.number}
                                />
                            </div>
                            <div>
                                <Label htmlFor="complement">Complemento</Label>
                                <Input
                                    id="complement"
                                    type="text"
                                    name="complement"
                                    value={data.complement}
                                    onChange={(event) => setData('complement', event.target.value)}
                                    error={!!errors.complement}
                                    hint={errors.complement}
                                />
                            </div>
                            <div>
                                <Label htmlFor="neighborhood">Bairro</Label>
                                <Input
                                    id="neighborhood"
                                    type="text"
                                    name="neighborhood"
                                    value={data.neighborhood}
                                    onChange={(event) => setData('neighborhood', event.target.value)}
                                    error={!!errors.neighborhood}
                                    hint={errors.neighborhood}
                                />
                            </div>
                            <div>
                                <Label htmlFor="city">Município</Label>
                                <Input
                                    id="city"
                                    type="text"
                                    name="city"
                                    value={data.city}
                                    onChange={(event) => setData('city', event.target.value)}
                                    error={!!errors.city}
                                    hint={errors.city}
                                />
                            </div>
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
                                    error={!!errors.state}
                                    hint={errors.state}
                                />
                            </div>
                            <div>
                                <Label htmlFor="zip_code">CEP</Label>
                                <Input
                                    id="zip_code"
                                    type="text"
                                    name="zip_code"
                                    value={data.zip_code}
                                    onChange={(event) => setData('zip_code', event.target.value)}
                                    placeholder="00000000"
                                    error={!!errors.zip_code}
                                    hint={errors.zip_code}
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader title="Contato" description="Canais de contato da empresa." />
                    <CardContent>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="email">E-mail</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    onChange={(event) => setData('email', event.target.value)}
                                    error={!!errors.email}
                                    hint={errors.email}
                                />
                            </div>
                            <div>
                                <Label htmlFor="phone">Telefone</Label>
                                <Input
                                    id="phone"
                                    type="text"
                                    name="phone"
                                    value={data.phone}
                                    onChange={(event) => setData('phone', event.target.value)}
                                    error={!!errors.phone}
                                    hint={errors.phone}
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex items-center justify-end gap-3">
                    <Link href="/portal/empresas">
                        <Button type="button" variant="outline" size="sm" disabled={processing}>
                            Cancelar
                        </Button>
                    </Link>
                    <Button type="submit" size="sm" disabled={processing}>
                        {processing ? 'Salvando...' : 'Salvar empresa'}
                    </Button>
                </div>
            </form>
        </PortalLayout>
    );
}
