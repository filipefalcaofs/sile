import { Head, useForm, useHttp } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import Switch from '@/components/form/switch';
import { MapaSection } from '@/components/geo/mapa-section';
import { SearchIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import GestaoLayout from '@/layouts/gestao-layout';

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

interface ContingenciaProps {
    serviceTypes: ServiceType[];
    documentRequirements: DocumentRequirementOption[];
    mapa: MapaConfig;
}

type PoligonoGeoJson = { type: 'Polygon'; coordinates: number[][][] };

interface Ponto {
    lat: number;
    lng: number;
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
 * Busca incremental de CNAEs ativos pela rota da gestão
 * (gestao.contingencia.cnaes-disponiveis), reusando o CnaeSearchController
 * (somente dados reais — máx. 20 itens). Local a esta tela.
 */
function CnaeSearch({
    onSelect,
    excludeIds = [],
    placeholder = 'Buscar CNAE por código ou descrição...',
}: {
    onSelect: (cnae: CnaeOption) => void;
    excludeIds?: number[];
    placeholder?: string;
}) {
    const [term, setTerm] = useState('');
    const [results, setResults] = useState<CnaeOption[]>([]);
    const [searched, setSearched] = useState(false);
    const [failed, setFailed] = useState(false);

    const http = useHttp<Record<string, never>, CnaeOption[]>({});
    const httpRef = useRef(http);
    httpRef.current = http;
    const requestSeq = useRef(0);

    useEffect(() => {
        const trimmed = term.trim();

        if (trimmed.length < MIN_SEARCH_LENGTH) {
            requestSeq.current += 1;
            setResults([]);
            setSearched(false);
            setFailed(false);

            return;
        }

        const timeout = setTimeout(() => {
            const seq = ++requestSeq.current;

            void httpRef.current.get(`/gestao/contingencia/cnaes-disponiveis?search=${encodeURIComponent(trimmed)}`, {
                onSuccess: (response) => {
                    if (seq !== requestSeq.current) {
                        return;
                    }
                    setResults(Array.isArray(response) ? response : []);
                    setSearched(true);
                    setFailed(false);
                },
                onError: () => {
                    if (seq !== requestSeq.current) {
                        return;
                    }
                    setResults([]);
                    setSearched(false);
                    setFailed(true);
                },
            });
        }, SEARCH_DEBOUNCE_MS);

        return () => clearTimeout(timeout);
    }, [term]);

    function select(cnae: CnaeOption) {
        onSelect(cnae);
        requestSeq.current += 1;
        setTerm('');
        setResults([]);
        setSearched(false);
        setFailed(false);
    }

    const visibleResults = results.filter((cnae) => !excludeIds.includes(cnae.id));

    return (
        <div className="flex flex-col gap-3">
            <div className="relative">
                <span className="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-gray-400 dark:text-gray-500">
                    <SearchIcon className="size-5" />
                </span>
                <input
                    type="search"
                    value={term}
                    onChange={(event) => setTerm(event.target.value)}
                    placeholder={placeholder}
                    autoComplete="off"
                    className="h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                />
            </div>

            {failed && (
                <Alert variant="error" title="Busca de CNAEs indisponível" message="Não foi possível consultar a tabela oficial de CNAEs. Tente novamente." />
            )}

            {searched && visibleResults.length === 0 && (
                <p className="text-sm text-gray-500 dark:text-gray-400">Nenhum CNAE ativo encontrado.</p>
            )}

            {visibleResults.length > 0 && (
                <ul className="max-h-64 divide-y divide-gray-100 overflow-y-auto rounded-xl border border-gray-200 dark:divide-white/[0.05] dark:border-gray-800">
                    {visibleResults.map((cnae) => (
                        <li key={cnae.id}>
                            <button
                                type="button"
                                onClick={() => select(cnae)}
                                className="flex w-full flex-col items-start gap-0.5 px-4 py-3 text-start transition hover:bg-gray-50 focus:bg-gray-50 focus:outline-hidden dark:hover:bg-white/[0.03] dark:focus:bg-white/[0.03]"
                            >
                                <span className="text-sm font-medium text-gray-800 dark:text-white/90">{cnae.formatted_code}</span>
                                <span className="text-theme-xs text-gray-500 dark:text-gray-400">{cnae.description}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/**
 * Registro de solicitação em CONTINGÊNCIA no console SEDUR (HU-148). O operador
 * autorizado preenche o MESMO conjunto de dados do formulário oficial; o sistema
 * protocola pelo MESMO motor do canal normal — muda só a origem (`contingencia`,
 * auditada) e o ator. O processo nasce com origem "contingência" (aviso visível).
 */
export default function RegistrarContingencia({ serviceTypes, documentRequirements, mapa }: ContingenciaProps) {
    const { data, setData, post, processing, errors } = useForm<ContingenciaForm>(EMPTY_FORM);

    const [ponto, setPonto] = useState<Ponto | null>(null);
    const [principalLabel, setPrincipalLabel] = useState<string | null>(null);
    const [complementares, setComplementares] = useState<CnaeOption[]>([]);

    function moverMarcador(latlng: Ponto) {
        setPonto(latlng);
        setData('property_polygon_geojson', quadradoAoRedor(latlng.lat, latlng.lng));
    }

    function selecionarPrincipal(cnae: CnaeOption) {
        setData('principal_cnae_id', cnae.id);
        setPrincipalLabel(`${cnae.formatted_code} — ${cnae.description}`);
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
                    message="Use apenas quando o canal normal estiver indisponível. O processo nasce com origem “contingência”, auditada com o seu usuário, e segue exatamente o mesmo fluxo decisório — sem atalho. Informe o motivo obrigatório."
                />
            </div>

            <form onSubmit={submit} className="flex flex-col gap-4 md:gap-6">
                <Card>
                    <CardHeader title="Beneficiário e empresa" description="Cidadão beneficiário (por CPF) e empresa (por CNPJ) já cadastrados no sistema." />
                    <CardContent>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="beneficiary_cpf" required>
                                    CPF do beneficiário
                                </Label>
                                <Input
                                    id="beneficiary_cpf"
                                    type="text"
                                    name="beneficiary_cpf"
                                    value={data.beneficiary_cpf}
                                    onChange={(event) => setData('beneficiary_cpf', event.target.value)}
                                    placeholder="000.000.000-00"
                                    error={!!errors.beneficiary_cpf}
                                    hint={errors.beneficiary_cpf}
                                />
                            </div>
                            <div>
                                <Label htmlFor="company_cnpj" required>
                                    CNPJ da empresa
                                </Label>
                                <Input
                                    id="company_cnpj"
                                    type="text"
                                    name="company_cnpj"
                                    value={data.company_cnpj}
                                    onChange={(event) => setData('company_cnpj', event.target.value)}
                                    placeholder="00.000.000/0000-00"
                                    error={!!errors.company_cnpj}
                                    hint={errors.company_cnpj}
                                />
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
                                    <p className="mt-1.5 text-theme-xs text-error-500">{errors.service_type_id}</p>
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
                                    <p className="mt-1.5 text-theme-xs text-error-500">{errors.contingency_reason}</p>
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
                    <CardHeader title="Imóvel" description="Posicione o imóvel no mapa (define o polígono) e informe a área utilizada e o endereço." />
                    <CardContent>
                        <div className="flex flex-col gap-5">
                            <div>
                                <MapaSection lat={centro.lat} lng={centro.lng} zoom={mapa.zoom} onMove={moverMarcador} />
                                <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                    {data.property_polygon_geojson
                                        ? 'Imóvel posicionado. Arraste o marcador para ajustar.'
                                        : 'Clique ou arraste o marcador no mapa para definir o imóvel.'}
                                </p>
                                {errors.property_polygon_geojson && (
                                    <p className="mt-1.5 text-theme-xs text-error-500">{errors.property_polygon_geojson}</p>
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
                                    <Input
                                        id="address_zip"
                                        type="text"
                                        name="address_zip"
                                        value={data.address_zip}
                                        onChange={(event) => setData('address_zip', event.target.value)}
                                        placeholder="00000000"
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
                    <CardHeader title="Atividades" description="Atividade principal (obrigatória) e CNAEs complementares — somente CNAEs ativos da tabela oficial." />
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
                                    <CnaeSearch onSelect={selecionarPrincipal} excludeIds={excludeIds} placeholder="Buscar atividade principal..." />
                                )}
                                {errors.principal_cnae_id && (
                                    <p className="mt-1.5 text-theme-xs text-error-500">{errors.principal_cnae_id}</p>
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
                                <CnaeSearch onSelect={adicionarComplementar} excludeIds={excludeIds} placeholder="Adicionar CNAE complementar..." />
                                {errors.complementares && (
                                    <p className="mt-1.5 text-theme-xs text-error-500">{errors.complementares}</p>
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
                                Nenhum requisito documental cadastrado no momento — a carga oficial dos requisitos por CNAE é pendente SEDUR.
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
                        {processing ? 'Registrando...' : 'Registrar e protocolar em contingência'}
                    </Button>
                </div>
            </form>
        </>
    );
}

RegistrarContingencia.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
