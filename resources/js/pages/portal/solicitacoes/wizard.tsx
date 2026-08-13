import { Head, Link, useForm } from '@inertiajs/react';
import type { Polygon } from 'geojson';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import EtapaAtividades from '@/components/solicitacao/etapa-atividades';
import EtapaDocumentos from '@/components/solicitacao/etapa-documentos';
import EtapaImovel from '@/components/solicitacao/etapa-imovel';
import EtapaRevisao, { type SugestaoResumo } from '@/components/solicitacao/etapa-revisao';
import EtapaSimulacao, { type SimulacaoData } from '@/components/solicitacao/etapa-simulacao';
import { CheckCircleIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import PortalLayout from '@/layouts/portal-layout';

interface Option {
    value: number;
    label: string;
}

interface CompanyOption extends Option {
    formatted_cnpj: string;
}

interface StatusInfo {
    value: string;
    label: string;
    public_label: string;
}

interface CnaeItem {
    id: number;
    formatted_code: string;
    description: string;
    is_primary: boolean;
}

interface DocumentoItem {
    id: number;
    original_name: string;
    mime_type: string | null;
    size: number | null;
    requirement_id: number | null;
    requirement_name: string | null;
    download_url: string;
}

interface Requisito {
    id: number;
    code: string;
    name: string;
    description?: string | null;
}

export interface SolicitacaoDraft {
    id: number;
    protocol_number: string | null;
    status: StatusInfo;
    service_type: string | null;
    service_type_id: number | null;
    company: { id: number; legal_name: string; formatted_cnpj: string } | null;
    used_area_m2: string | number | null;
    address: {
        street: string | null;
        number: string | null;
        complement: string | null;
        neighborhood: string | null;
        zip: string | null;
        reference: string | null;
    };
    property_polygon_geojson: Polygon | null;
    indicators: {
        is_virtual_office: boolean;
        is_public_area: boolean;
        has_independent_access: boolean;
    };
    cnaes: CnaeItem[];
    documentos: DocumentoItem[];
    simulation: SimulacaoData | null;
}

interface TerritorioResumo {
    bairro?: { status: string; nome?: string | null; motivo?: string | null };
    via?: { status: string; nome?: string | null; motivo?: string | null };
    zona?: { status: string; nome?: string | null; motivo?: string | null };
}

interface AreaAlert {
    area_declarada_m2: number;
    area_poligono_m2: number;
    tolerancia_percentual: number;
    divergencia_percentual: number;
}

interface WizardProps {
    solicitacao: SolicitacaoDraft | null;
    serviceTypes: Option[];
    companies: CompanyOption[];
    requisitosObrigatorios: Requisito[];
    requisitosFaltantes: Requisito[];
    anexosConfig: { max_mb: number; mime_permitidos: string[] };
    cnaesComplementaresMax: number;
    simulacaoEnabled: boolean;
    solicitacaoEnabled: boolean;
    territorio: TerritorioResumo | null;
    areaAlert: AreaAlert | null;
    /** Resumo de conferência por IA (HU-116) — prop deferida (Inertia::optional). */
    sugestoesResumo?: SugestaoResumo[];
}

type StepId = 'imovel' | 'atividades' | 'documentos' | 'simulacao' | 'revisao';

const STEPS: Array<{ id: StepId; label: string }> = [
    { id: 'imovel', label: 'Imóvel e área' },
    { id: 'atividades', label: 'Atividades' },
    { id: 'documentos', label: 'Documentos' },
    { id: 'simulacao', label: 'Simulação' },
    { id: 'revisao', label: 'Revisão' },
];

/** Etapas obrigatórias (na ordem) para retomar no primeiro ponto incompleto. */
const MANDATORY_STEPS: StepId[] = ['imovel', 'atividades', 'documentos'];

function imovelCompleto(draft: SolicitacaoDraft): boolean {
    return (
        draft.property_polygon_geojson !== null &&
        draft.used_area_m2 !== null &&
        String(draft.used_area_m2) !== '' &&
        (draft.address.reference ?? '') !== ''
    );
}

function atividadesCompleto(draft: SolicitacaoDraft): boolean {
    return draft.cnaes.some((cnae) => cnae.is_primary);
}

/**
 * Início de uma NOVA solicitação (HU-061): escolhe o tipo de serviço e a empresa
 * do dono e cria o rascunho (POST solicitacoes.store). O fluxo segue na edição.
 */
function IniciarStep({
    serviceTypes,
    companies,
    enabled,
}: {
    serviceTypes: Option[];
    companies: CompanyOption[];
    enabled: boolean;
}) {
    const { data, setData, post, processing, errors } = useForm({
        service_type_id: '',
        company_id: '',
    });

    const semEmpresa = companies.length === 0;

    function submit(event: React.FormEvent) {
        event.preventDefault();
        post('/portal/solicitacoes');
    }

    return (
        <form onSubmit={submit} className="flex flex-col gap-4 md:gap-6">
            {!enabled && (
                <Alert
                    variant="info"
                    title="Novas solicitações indisponíveis"
                    message="A abertura de novas solicitações está temporariamente desativada pelo administrador."
                />
            )}

            {semEmpresa && (
                <Alert
                    variant="warning"
                    title="Cadastre uma empresa primeiro"
                    message="Você precisa de uma empresa com vínculo ativo para iniciar uma solicitação de viabilidade."
                    showLink
                    linkHref="/portal/empresas/cadastrar"
                    linkText="Cadastrar empresa"
                />
            )}

            <Card>
                <CardHeader
                    title="Iniciar solicitação"
                    description="Escolha o tipo de serviço e a empresa para a qual deseja consultar a viabilidade."
                />
                <CardContent>
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor="service_type_id" required>
                                Tipo de serviço
                            </Label>
                            <Select
                                id="service_type_id"
                                value={data.service_type_id}
                                onChange={(value) => setData('service_type_id', value)}
                                options={serviceTypes.map((type) => ({ value: String(type.value), label: type.label }))}
                                placeholder="Selecione o tipo de serviço"
                                disabled={!enabled}
                            />
                            {errors.service_type_id && (
                                <p className="mt-1.5 text-theme-xs text-error-500">{errors.service_type_id}</p>
                            )}
                        </div>

                        <div>
                            <Label htmlFor="company_id" required>
                                Empresa
                            </Label>
                            <Select
                                id="company_id"
                                value={data.company_id}
                                onChange={(value) => setData('company_id', value)}
                                options={companies.map((company) => ({
                                    value: String(company.value),
                                    label: `${company.label} — ${company.formatted_cnpj}`,
                                }))}
                                placeholder="Selecione a empresa"
                                disabled={!enabled || semEmpresa}
                            />
                            {errors.company_id && (
                                <p className="mt-1.5 text-theme-xs text-error-500">{errors.company_id}</p>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div className="flex items-center justify-end gap-3">
                <Link href="/portal/solicitacoes">
                    <Button type="button" variant="outline" size="sm" disabled={processing}>
                        Cancelar
                    </Button>
                </Link>
                <Button
                    type="submit"
                    size="sm"
                    disabled={processing || !enabled || semEmpresa || data.service_type_id === '' || data.company_id === ''}
                    loading={processing}
                >
                    Iniciar e continuar
                </Button>
            </div>
        </form>
    );
}

/** Indicador de progresso do wizard (acessível: lista ordenada + aria-current). */
function Stepper({
    steps,
    current,
    concluidos,
    onSelect,
}: {
    steps: Array<{ id: StepId; label: string }>;
    current: StepId;
    concluidos: Record<StepId, boolean>;
    onSelect: (step: StepId) => void;
}) {
    return (
        <ol className="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-1">
            {steps.map((step, index) => {
                const ativo = step.id === current;
                const feito = concluidos[step.id];

                return (
                    <li key={step.id} className="flex flex-1 items-center gap-2">
                        <button
                            type="button"
                            onClick={() => onSelect(step.id)}
                            aria-current={ativo ? 'step' : undefined}
                            className={`flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left transition ${
                                ativo
                                    ? 'border-brand-500 bg-brand-50 dark:border-brand-500/40 dark:bg-brand-500/10'
                                    : 'border-gray-200 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.03]'
                            }`}
                        >
                            <span
                                className={`flex size-7 shrink-0 items-center justify-center rounded-full text-theme-xs font-semibold ${
                                    feito
                                        ? 'bg-success-500 text-white'
                                        : ativo
                                          ? 'bg-brand-500 text-white'
                                          : 'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400'
                                }`}
                            >
                                {feito ? <CheckCircleIcon className="size-4 fill-current" /> : index + 1}
                            </span>
                            <span
                                className={`text-sm font-medium ${
                                    ativo ? 'text-brand-600 dark:text-brand-400' : 'text-gray-700 dark:text-gray-300'
                                }`}
                            >
                                {step.label}
                            </span>
                        </button>
                    </li>
                );
            })}
        </ol>
    );
}

export default function Wizard(props: WizardProps) {
    const { solicitacao, serviceTypes, companies, solicitacaoEnabled } = props;

    if (!solicitacao) {
        return (
            <>
                <Head title="Nova solicitação" />
                <PageHeader
                    title="Nova solicitação de viabilidade"
                    breadcrumbs={[
                        { label: 'Meu painel', href: '/portal/painel' },
                        { label: 'Minhas solicitações', href: '/portal/solicitacoes' },
                    ]}
                />
                <IniciarStep serviceTypes={serviceTypes} companies={companies} enabled={solicitacaoEnabled} />
            </>
        );
    }

    return <WizardEdicao {...props} solicitacao={solicitacao} />;
}

function WizardEdicao({
    solicitacao,
    cnaesComplementaresMax,
    requisitosObrigatorios,
    requisitosFaltantes,
    anexosConfig,
    simulacaoEnabled,
    territorio,
    areaAlert,
    sugestoesResumo,
}: WizardProps & { solicitacao: SolicitacaoDraft }) {
    const concluidos: Record<StepId, boolean> = {
        imovel: imovelCompleto(solicitacao),
        atividades: atividadesCompleto(solicitacao),
        documentos: requisitosFaltantes.length === 0,
        simulacao: solicitacao.simulation !== null,
        revisao: false,
    };

    const [step, setStep] = useState<StepId>(
        () => MANDATORY_STEPS.find((id) => !concluidos[id]) ?? 'revisao',
    );

    function avancar() {
        const index = STEPS.findIndex((item) => item.id === step);
        const proxima = STEPS[index + 1];

        if (proxima) {
            setStep(proxima.id);
        }
    }

    const titulo = solicitacao.protocol_number ?? 'Solicitação em preenchimento';

    return (
        <>
            <Head title="Solicitação de viabilidade" />
            <PageHeader
                title={titulo}
                breadcrumbs={[
                    { label: 'Meu painel', href: '/portal/painel' },
                    { label: 'Minhas solicitações', href: '/portal/solicitacoes' },
                ]}
                actions={
                    <Badge size="sm" color="light">
                        {solicitacao.status.public_label}
                    </Badge>
                }
            />

            <Stepper steps={STEPS} current={step} concluidos={concluidos} onSelect={setStep} />

            {step === 'imovel' && (
                <EtapaImovel
                    solicitacaoId={solicitacao.id}
                    initialPolygon={solicitacao.property_polygon_geojson}
                    address={solicitacao.address}
                    usedArea={solicitacao.used_area_m2}
                    indicators={solicitacao.indicators}
                    territorio={territorio}
                    areaAlert={areaAlert}
                    onSaved={avancar}
                />
            )}

            {step === 'atividades' && (
                <EtapaAtividades
                    solicitacaoId={solicitacao.id}
                    cnaes={solicitacao.cnaes}
                    max={cnaesComplementaresMax}
                    onSaved={avancar}
                />
            )}

            {step === 'documentos' && (
                <EtapaDocumentos
                    solicitacaoId={solicitacao.id}
                    documentos={solicitacao.documentos}
                    requisitosObrigatorios={requisitosObrigatorios}
                    requisitosFaltantes={requisitosFaltantes}
                    anexosConfig={anexosConfig}
                    onContinue={avancar}
                />
            )}

            {step === 'simulacao' && (
                <EtapaSimulacao
                    solicitacaoId={solicitacao.id}
                    simulation={solicitacao.simulation}
                    simulacaoEnabled={simulacaoEnabled}
                    onContinue={avancar}
                />
            )}

            {step === 'revisao' && (
                <EtapaRevisao
                    solicitacao={solicitacao}
                    requisitosFaltantes={requisitosFaltantes}
                    simulation={solicitacao.simulation}
                    sugestoesResumo={sugestoesResumo}
                />
            )}
        </>
    );
}

Wizard.layout = (page: ReactNode) => <PortalLayout>{page}</PortalLayout>;
