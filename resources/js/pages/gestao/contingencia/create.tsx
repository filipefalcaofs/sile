import FieldError from '@/components/form/field-error';
import { Head, useForm, useHttp } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import CnaePicker from '@/components/cnae/cnae-picker';
import Input from '@/components/form/input';
import MaskedInput from '@/components/form/masked-input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import Switch from '@/components/form/switch';
import { MapaSection } from '@/components/geo/mapa-section';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import GestaoLayout from '@/layouts/gestao-layout';
import { mensagemDaConsultaSedur, resumoConsultaCpfSedur } from '@/lib/sedur-consulta';

interface ServiceType {
    id: number;
    code: string;
    name: string;
    flow_hint: string | null;
}

interface DocumentRequirementOption {
    id: number;
    name: string;
    required: boolean;
}

interface MapaConfig {
    centro: { lat: number; lng: number };
    zoom: number;
}

interface CnaeOption {
    id: number;
    formatted_code: string;
    description: string;
}

interface TipoImovelOption {
    value: string;
    label: string;
}

interface ContingenciaProps {
    serviceTypes: ServiceType[];
    documentRequirements: DocumentRequirementOption[];
    tiposImovel: TipoImovelOption[];
    mapa: MapaConfig;
}

type PoligonoGeoJson = { type: 'Polygon'; coordinates: number[][][] };

interface Ponto {
    lat: number;
    lng: number;
}

interface ConsultaInscricao {
    address_street: string | null;
    address_number: string | null;
    address_complement: string | null;
    address_neighborhood: string | null;
    address_zip: string | null;
    latitude: number | null;
    longitude: number | null;
    message?: string;
}

interface ContingenciaForm {
    beneficiary_cpf: string;
    company_cnpj: string;
    service_type_id: string;
    contingency_reason: string;
    external_reference: string;
    used_area_m2: string;
    address_street: string;
    address_number: string;
    address_complement: string;
    address_neighborhood: string;
    address_zip: string;
    address_reference: string;
    tipo_imovel: string;
    property_registration: string;
    is_virtual_office: boolean;
    is_public_area: boolean;
    has_independent_access: boolean;
    principal_cnae_id: number | null;
    complementares: number[];
    property_polygon_geojson: PoligonoGeoJson | null;
    documents: Record<string, File>;
}

const EMPTY_FORM: ContingenciaForm = {
    beneficiary_cpf: '',
    company_cnpj: '',
    service_type_id: '',
    contingency_reason: '',
    external_reference: '',
    used_area_m2: '',
    address_street: '',
    address_number: '',
    address_complement: '',
    address_neighborhood: '',
    address_zip: '',
    address_reference: '',
    tipo_imovel: '',
    property_registration: '',
    is_virtual_office: false,
    is_public_area: false,
    has_independent_access: false,
    principal_cnae_id: null,
    complementares: [],
    property_polygon_geojson: null,
    documents: {},
};

const SEARCH_DEBOUNCE_MS = 350;
const MIN_SEARCH_LENGTH = 2;

function texto(valor: string | null | undefined): boolean {
    return valor !== null && valor !== undefined && valor.trim() !== '';
}

function mensagemDaConsulta(data: unknown, fallback = 'Não foi possível consultar a inscrição. Preencha o endereço manualmente.'): string {
    let body: unknown = data;

    if (typeof data === 'string') {
        try {
            body = JSON.parse(data) as unknown;
        } catch {
            return fallback;
        }
    }

    if (body && typeof body === 'object' && 'message' in body && typeof body.message === 'string' && body.message.trim() !== '') {
        return body.message;
    }

    return fallback;
}

function resumoSedur(nome: string | null, cadastrado: boolean, documento: 'CPF' | 'CNPJ'): string {
    if (documento === 'CPF') {
        return resumoConsultaCpfSedur(nome, cadastrado, 'contingencia');
    }

    const situacao = cadastrado
        ? 'Já cadastrado no Viabiliza.'
        : 'Encontrado na SEDUR, mas este CNPJ ainda não está cadastrado no Viabiliza.';

    return nome ? `${nome}. ${situacao}` : situacao;
}

/**
 * Quadrado (~33 m) ao redor do ponto informado — perímetro mínimo do imóvel
 * nesta fase (o lote real chega com a base cadastral). GeoJSON usa [lng, lat].
 */
function quadradoAoRedor(lat: number, lng: number, delta = 0.0003): PoligonoGeoJson {
    return {
        type: 'Polygon',
        coordinates: [
            [
                [lng - delta, lat - delta],
                [lng + delta, lat - delta],
                [lng + delta, lat + delta],
                [lng - delta, lat + delta],
                [lng - delta, lat - delta],
            ],
        ],
    };
}

/**
 * Registro de solicitação em CONTINGÊNCIA no console SEDUR (HU-137). O operador
 * autorizado preenche o MESMO conjunto de dados do formulário oficial; o sistema
 * protocola pelo MESMO motor do canal normal — muda só a origem (`contingencia`,
 * auditada) e o ator. O processo nasce com origem "contingência" (aviso visível).
 */
export default function RegistrarContingencia({ serviceTypes, documentRequirements, tiposImovel, mapa }: ContingenciaProps) {
    const { data, setData, post, processing, errors } = useForm<ContingenciaForm>(EMPTY_FORM);
    const consultaInscricao = useHttp({ inscricao: '' });
    const consultaCpf = useHttp({ cpf: '' });
    const consultaCnpj = useHttp({ cnpj: '' });

    const [ponto, setPonto] = useState<Ponto | null>(null);
    const [consultaErro, setConsultaErro] = useState<string | null>(null);
    const [cpfResumo, setCpfResumo] = useState<string | null>(null);
    const [cpfErro, setCpfErro] = useState<string | null>(null);
    const [cnpjResumo, setCnpjResumo] = useState<string | null>(null);
    const [cnpjErro, setCnpjErro] = useState<string | null>(null);
    const [principalLabel, setPrincipalLabel] = useState<string | null>(null);
    const [complementares, setComplementares] = useState<CnaeOption[]>([]);

    function moverMarcador(latlng: Ponto) {
        setPonto(latlng);
        setData('property_polygon_geojson', quadradoAoRedor(latlng.lat, latlng.lng));
    }

    function buscarInscricao() {
        const inscricao = data.property_registration.trim();

        if (inscricao === '') {
            setConsultaErro('Informe a inscrição imobiliária.');

            return;
        }

        setConsultaErro(null);
        consultaInscricao.setData('inscricao', inscricao);
        consultaInscricao.post('/gestao/contingencia/consultar-inscricao', {
            onSuccess: (response: unknown) => {
                const payload = response as ConsultaInscricao;
                const latitude = payload.latitude;
                const longitude = payload.longitude;
                const temPonto = latitude !== null && longitude !== null && latitude !== undefined && longitude !== undefined;

                setData((current) => ({
                    ...current,
                    ...(texto(payload.address_street) ? { address_street: payload.address_street as string } : {}),
                    ...(texto(payload.address_number) ? { address_number: payload.address_number as string } : {}),
                    ...(texto(payload.address_complement) ? { address_complement: payload.address_complement as string } : {}),
                    ...(texto(payload.address_neighborhood) ? { address_neighborhood: payload.address_neighborhood as string } : {}),
                    ...(texto(payload.address_zip) ? { address_zip: payload.address_zip as string } : {}),
                    ...(temPonto ? { property_polygon_geojson: quadradoAoRedor(latitude, longitude) } : {}),
                }));

                if (temPonto) {
                    setPonto({ lat: latitude, lng: longitude });
                }

                const preencheu = texto(payload.address_street) || texto(payload.address_neighborhood) || temPonto;

                if (!preencheu) {
                    setConsultaErro('Inscrição localizada, mas o cadastro não trouxe endereço. Preencha o imóvel manualmente.');
                }
            },
            onError: (erros: { inscricao?: string }) => {
                setConsultaErro(erros.inscricao ?? 'Não foi possível consultar a inscrição. Preencha o endereço manualmente.');
            },
            onHttpException: (response: { data?: unknown }) => {
                setConsultaErro(mensagemDaConsulta(response.data));
            },
            onNetworkError: () => {
                setConsultaErro('Não foi possível consultar a inscrição. Preencha o endereço manualmente.');
            },
        });
    }

    function buscarCpf() {
        const cpf = data.beneficiary_cpf.trim();

        if (cpf === '') {
            setCpfErro('Informe o CPF do beneficiário.');
            setCpfResumo(null);

            return;
        }

        setCpfErro(null);
        setCpfResumo(null);
        consultaCpf.setData('cpf', cpf);
        consultaCpf.post('/gestao/contingencia/consultar-cpf', {
            onSuccess: (response: unknown) => {
                const payload = response as { nome: string | null; cadastrado: boolean };
                setCpfResumo(resumoSedur(payload.nome, payload.cadastrado, 'CPF'));
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

    function buscarCnpj() {
        const cnpj = data.company_cnpj.trim();

        if (cnpj === '') {
            setCnpjErro('Informe o CNPJ da empresa.');
            setCnpjResumo(null);

            return;
        }

        setCnpjErro(null);
        setCnpjResumo(null);
        consultaCnpj.setData('cnpj', cnpj);
        consultaCnpj.post('/gestao/contingencia/consultar-cnpj', {
            onSuccess: (response: unknown) => {
                const payload = response as { razao_social: string | null; nome_fantasia: string | null; cadastrado: boolean };
                const nome = [payload.razao_social, payload.nome_fantasia].filter(texto).join(': ');
                setCnpjResumo(resumoSedur(nome || null, payload.cadastrado, 'CNPJ'));
            },
            onError: (erros: { cnpj?: string }) => {
                setCnpjErro(erros.cnpj ?? 'Não foi possível consultar o CNPJ na SEDUR.');
            },
            onHttpException: (response: { data?: unknown }) => {
                setCnpjErro(mensagemDaConsultaSedur(response.data, 'Não foi possível consultar o CNPJ na SEDUR.'));
            },
            onNetworkError: () => {
                setCnpjErro('Não foi possível consultar o CNPJ na SEDUR.');
            },
        });
    }

    function selecionarPrincipal(cnae: CnaeOption) {
        setData('principal_cnae_id', cnae.id);
        setPrincipalLabel(`${cnae.formatted_code}: ${cnae.description}`);
    }

    function adicionarComplementar(cnae: CnaeOption) {
        if (complementares.some((item) => item.id === cnae.id)) {
            return;
        }
        const next = [...complementares, cnae];
        setComplementares(next);
        setData('complementares', next.map((item) => item.id));
    }

    function removerComplementar(id: number) {
        const next = complementares.filter((item) => item.id !== id);
        setComplementares(next);
        setData('complementares', next.map((item) => item.id));
    }

    function definirAnexo(requirementId: number, file: File | null) {
        const next = { ...data.documents };
        if (file) {
            next[String(requirementId)] = file;
        } else {
            delete next[String(requirementId)];
        }
        setData('documents', next);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();
        post('/gestao/contingencia', { forceFormData: true });
    }

    const excludeIds = [
        ...(data.principal_cnae_id !== null ? [data.principal_cnae_id] : []),
        ...complementares.map((item) => item.id),
    ];

    const centro = ponto ?? mapa.centro;

    return (
        <>
            <Head title="Nova solicitação (contingência)" />
            <PageHeader
                title="Nova solicitação (contingência)"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }]}
            />

            <div className="mb-6">
                <Alert
                    variant="warning"
                    title="Registro em contingência"
                    message="Use quando o REGIN estiver indisponível. Informe os mesmos dados que o integrador enviaria, tipo de imóvel, inscrição, CNAE e imóvel. O processo nasce com origem “contingência”, é protocolado e submetido ao motor no mesmo tramitação do canal oficial."
                />
            </div>

            <form onSubmit={submit} className="flex flex-col gap-4 md:gap-6">
                <Card>
                    <CardHeader title="Beneficiário e empresa" description="Busque o CPF e o CNPJ na base da SEDUR para conferir o nome. O protocolo exige que o cidadão e a empresa já estejam cadastrados no Viabiliza." />
                    <CardContent>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="beneficiary_cpf" required>
                                    CPF do beneficiário
                                </Label>
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                                    <div className="flex-1">
                                        <MaskedInput
                                            id="beneficiary_cpf"
                                            mask="cpf"
                                            name="beneficiary_cpf"
                                            value={data.beneficiary_cpf}
                                            onChange={(valor) => setData('beneficiary_cpf', valor)}
                                            placeholder="000.000.000-00"
                                            error={!!errors.beneficiary_cpf || !!cpfErro}
                                            hint={errors.beneficiary_cpf}
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
                                {cpfResumo && !cpfErro && <p className="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400">{cpfResumo}</p>}
                            </div>
                            <div>
                                <Label htmlFor="company_cnpj" required>
                                    CNPJ da empresa
                                </Label>
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                                    <div className="flex-1">
                                        <MaskedInput
                                            id="company_cnpj"
                                            mask="cnpj"
                                            name="company_cnpj"
                                            value={data.company_cnpj}
                                            onChange={(valor) => setData('company_cnpj', valor)}
                                            placeholder="00.000.000/0000-00"
                                            error={!!errors.company_cnpj || !!cnpjErro}
                                            hint={errors.company_cnpj}
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={buscarCnpj}
                                        disabled={consultaCnpj.processing}
                                        loading={consultaCnpj.processing}
                                    >
                                        {consultaCnpj.processing ? 'Buscando...' : 'Buscar CNPJ'}
                                    </Button>
                                </div>
                                {cnpjErro && <FieldError>{cnpjErro}</FieldError>}
                                {cnpjResumo && !cnpjErro && <p className="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400">{cnpjResumo}</p>}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader title="Origem da contingência" description="Motivo obrigatório e a referência externa (BAP/Regin) informada pelo requerente, quando houver." />
                    <CardContent>
                        <div className="flex flex-col gap-5">
                            <div>
                                <Label htmlFor="service_type_id">Tipo de serviço</Label>
                                <Select
                                    id="service_type_id"
                                    name="service_type_id"
                                    value={data.service_type_id}
                                    onChange={(value) => setData('service_type_id', value)}
                                    placeholder="Selecione o tipo de serviço (opcional)"
                                    options={serviceTypes.map((type) => ({ value: String(type.id), label: type.name }))}
                                />
                                {errors.service_type_id && (
                                    <FieldError>{errors.service_type_id}</FieldError>
                                )}
                            </div>
                            <div>
                                <Label htmlFor="contingency_reason" required>
                                    Motivo da contingência
                                </Label>
                                <textarea
                                    id="contingency_reason"
                                    name="contingency_reason"
                                    rows={3}
                                    value={data.contingency_reason}
                                    onChange={(event) => setData('contingency_reason', event.target.value)}
                                    placeholder="Ex.: integrador Regin indisponível; atendimento presencial do requerente."
                                    className="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                                />
                                {errors.contingency_reason && (
                                    <FieldError>{errors.contingency_reason}</FieldError>
                                )}
                            </div>
                            <div>
                                <Label htmlFor="external_reference">Referência externa (BAP/Regin)</Label>
                                <Input
                                    id="external_reference"
                                    type="text"
                                    name="external_reference"
                                    value={data.external_reference}
                                    onChange={(event) => setData('external_reference', event.target.value)}
                                    placeholder="Ex.: protocolo Regin informado pelo requerente"
                                    error={!!errors.external_reference}
                                    hint={errors.external_reference ?? 'O vínculo posterior do BAP não duplica o processo.'}
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader title="Imóvel" description="Informe a inscrição para buscar o endereço no Cadastro Imobiliário, ou preencha manualmente." />
                    <CardContent>
                        <div className="flex flex-col gap-5">
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="tipo_imovel">Tipo de imóvel</Label>
                                    <Select
                                        id="tipo_imovel"
                                        name="tipo_imovel"
                                        value={data.tipo_imovel}
                                        onChange={(value) => setData('tipo_imovel', value)}
                                        placeholder="Selecione o tipo de imóvel"
                                        options={tiposImovel.map((tipo) => ({ value: tipo.value, label: tipo.label }))}
                                    />
                                    {errors.tipo_imovel && (
                                        <FieldError>{errors.tipo_imovel}</FieldError>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="property_registration">Inscrição imobiliária</Label>
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                                        <div className="flex-1">
                                            <MaskedInput
                                                id="property_registration"
                                                mask="inscricao"
                                                name="property_registration"
                                                value={data.property_registration}
                                                onChange={(valor) => setData('property_registration', valor)}
                                                placeholder="0010010010"
                                                error={!!errors.property_registration || !!consultaErro}
                                                hint={errors.property_registration}
                                            />
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={buscarInscricao}
                                            disabled={consultaInscricao.processing}
                                            loading={consultaInscricao.processing}
                                        >
                                            {consultaInscricao.processing ? 'Buscando...' : 'Buscar inscrição'}
                                        </Button>
                                    </div>
                                    {consultaErro && (
                                        <FieldError>{consultaErro}</FieldError>
                                    )}
                                </div>
                            </div>

                            <div>
                                <MapaSection lat={centro.lat} lng={centro.lng} zoom={mapa.zoom} onMove={moverMarcador} />
                                <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                    {data.property_polygon_geojson
                                        ? 'Imóvel posicionado. Arraste o marcador para ajustar.'
                                        : 'Clique ou arraste o marcador no mapa para definir o imóvel.'}
                                </p>
                                {errors.property_polygon_geojson && (
                                    <FieldError>{errors.property_polygon_geojson}</FieldError>
                                )}
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="used_area_m2" required>
                                        Área utilizada (m²)
                                    </Label>
                                    <Input
                                        id="used_area_m2"
                                        type="number"
                                        name="used_area_m2"
                                        value={data.used_area_m2}
                                        onChange={(event) => setData('used_area_m2', event.target.value)}
                                        placeholder="120"
                                        error={!!errors.used_area_m2}
                                        hint={errors.used_area_m2}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="address_reference">Ponto de referência</Label>
                                    <Input
                                        id="address_reference"
                                        type="text"
                                        name="address_reference"
                                        value={data.address_reference}
                                        onChange={(event) => setData('address_reference', event.target.value)}
                                        error={!!errors.address_reference}
                                        hint={errors.address_reference}
                                    />
                                </div>
                                <div className="sm:col-span-2">
                                    <Label htmlFor="address_street">Logradouro</Label>
                                    <Input
                                        id="address_street"
                                        type="text"
                                        name="address_street"
                                        value={data.address_street}
                                        onChange={(event) => setData('address_street', event.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="address_number">Número</Label>
                                    <Input
                                        id="address_number"
                                        type="text"
                                        name="address_number"
                                        value={data.address_number}
                                        onChange={(event) => setData('address_number', event.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="address_complement">Complemento</Label>
                                    <Input
                                        id="address_complement"
                                        type="text"
                                        name="address_complement"
                                        value={data.address_complement}
                                        onChange={(event) => setData('address_complement', event.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="address_neighborhood">Bairro</Label>
                                    <Input
                                        id="address_neighborhood"
                                        type="text"
                                        name="address_neighborhood"
                                        value={data.address_neighborhood}
                                        onChange={(event) => setData('address_neighborhood', event.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="address_zip">CEP</Label>
                                    <MaskedInput
                                        id="address_zip"
                                        mask="cep"
                                        name="address_zip"
                                        value={data.address_zip}
                                        onChange={(valor) => setData('address_zip', valor)}
                                        placeholder="00000-000"
                                    />
                                </div>
                            </div>

                            <div className="flex flex-col gap-3 border-t border-gray-100 pt-4 dark:border-gray-800 sm:flex-row sm:gap-8">
                                <Switch
                                    label="Escritório virtual"
                                    defaultChecked={data.is_virtual_office}
                                    onChange={(checked) => setData('is_virtual_office', checked)}
                                />
                                <Switch
                                    label="Área pública"
                                    defaultChecked={data.is_public_area}
                                    onChange={(checked) => setData('is_public_area', checked)}
                                />
                                <Switch
                                    label="Acesso independente"
                                    defaultChecked={data.has_independent_access}
                                    onChange={(checked) => setData('has_independent_access', checked)}
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader title="Atividades" description="Atividade principal (obrigatória) e CNAEs complementares: somente CNAEs ativos da tabela oficial." />
                    <CardContent>
                        <div className="flex flex-col gap-5">
                            <div>
                                <Label required>Atividade principal</Label>
                                {principalLabel ? (
                                    <div className="mb-3 flex items-center justify-between gap-2 rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-800">
                                        <span className="text-sm text-gray-800 dark:text-white/90">{principalLabel}</span>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setData('principal_cnae_id', null);
                                                setPrincipalLabel(null);
                                            }}
                                            className="text-theme-xs font-medium text-error-500 hover:underline"
                                        >
                                            Trocar
                                        </button>
                                    </div>
                                ) : (
                                    <CnaePicker endpoint="/gestao/contingencia/cnaes-disponiveis" onSelect={selecionarPrincipal} excludeIds={excludeIds} placeholder="Buscar atividade principal..." />
                                )}
                                {errors.principal_cnae_id && (
                                    <FieldError>{errors.principal_cnae_id}</FieldError>
                                )}
                            </div>

                            <div>
                                <Label>CNAEs complementares</Label>
                                {complementares.length > 0 && (
                                    <div className="mb-3 flex flex-wrap gap-2">
                                        {complementares.map((cnae) => (
                                            <span
                                                key={cnae.id}
                                                className="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-theme-xs text-gray-700 dark:bg-white/[0.06] dark:text-gray-300"
                                            >
                                                {cnae.formatted_code}
                                                <button
                                                    type="button"
                                                    onClick={() => removerComplementar(cnae.id)}
                                                    className="text-gray-400 hover:text-error-500"
                                                    aria-label={`Remover ${cnae.formatted_code}`}
                                                >
                                                    ×
                                                </button>
                                            </span>
                                        ))}
                                    </div>
                                )}
                                <CnaePicker endpoint="/gestao/contingencia/cnaes-disponiveis" onSelect={adicionarComplementar} excludeIds={excludeIds} placeholder="Adicionar CNAE complementar..." />
                                {errors.complementares && (
                                    <FieldError>{errors.complementares}</FieldError>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader title="Anexos" description="Documentos obrigatórios da solicitação. O protocolo aplica a MESMA validação documental do canal normal." />
                    <CardContent>
                        {documentRequirements.length === 0 ? (
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Nenhum requisito documental cadastrado no momento, a carga oficial dos requisitos por CNAE é pendente SEDUR.
                            </p>
                        ) : (
                            <div className="flex flex-col gap-4">
                                {documentRequirements.map((requisito) => (
                                    <div key={requisito.id} className="flex flex-col gap-1">
                                        <Label htmlFor={`doc-${requisito.id}`}>
                                            {requisito.name}{' '}
                                            {requisito.required && <Badge color="warning">Obrigatório</Badge>}
                                        </Label>
                                        <input
                                            id={`doc-${requisito.id}`}
                                            type="file"
                                            onChange={(event) => definirAnexo(requisito.id, event.target.files?.[0] ?? null)}
                                            className="block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200 dark:text-gray-400 dark:file:bg-white/[0.06] dark:file:text-gray-200"
                                        />
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="flex items-center justify-end gap-3">
                    <Button type="submit" size="sm" disabled={processing} loading={processing}>
                        {processing ? 'Registrando...' : 'Registrar, protocolar e submeter ao motor'}
                    </Button>
                </div>
            </form>
        </>
    );
}

RegistrarContingencia.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
