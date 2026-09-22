import { Form, Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import RiscoMunicipalFields from '@/components/cnae/risco-municipal-fields';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { ArrowRightIcon, InfoIcon } from '@/components/icons';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface NivelOption {
    value: string;
    label: string;
}

interface CnaeEditData {
    id: number;
    code: string;
    formatted_code: string;
    description: string;
    section_code: string;
    section_description: string;
    division_code: string;
    division_description: string;
    group_code: string;
    group_description: string;
    class_code: string;
    class_description: string;
    active: boolean;
    exige_rt: boolean;
    exige_rt_se_alto: boolean;
    exige_fator_multiplicador: boolean;
    exige_detalhamento_multiplicador: boolean;
    risco_municipal: string | null;
}

interface CnaesEditarProps {
    cnae: CnaeEditData;
    niveisMunicipais: NivelOption[];
    semVersaoMunicipal: boolean;
}

function AvisoSemVersao({ mensagem }: { mensagem: string }) {
    return (
        <div className="mb-5 flex items-start gap-3 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/15">
            <InfoIcon className="size-5 shrink-0 fill-current text-warning-500" />
            <p className="text-theme-sm text-gray-600 dark:text-gray-300">{mensagem}</p>
        </div>
    );
}

export default function CnaesEditar({
    cnae,
    niveisMunicipais,
    semVersaoMunicipal,
}: CnaesEditarProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-cnaes');

    const [active, setActive] = useState(cnae.active ? '1' : '0');
    const [riscoMunicipal, setRiscoMunicipal] = useState(cnae.risco_municipal ?? '');
    const [exigeRt, setExigeRt] = useState(cnae.exige_rt ? '1' : '0');
    const [exigeRtSeAlto, setExigeRtSeAlto] = useState(cnae.exige_rt_se_alto ? '1' : '0');
    const [exigeFatorMultiplicador, setExigeFatorMultiplicador] = useState(cnae.exige_fator_multiplicador ? '1' : '0');
    const [exigeDetalhamentoMultiplicador, setExigeDetalhamentoMultiplicador] = useState(
        cnae.exige_detalhamento_multiplicador ? '1' : '0',
    );

    return (
        <>
            <Head title={`Editar CNAE ${cnae.formatted_code}`} />
            <PageHeader
                title="Editar CNAE"
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'CNAEs', href: '/gestao/cnaes' },
                ]}
                actions={
                    <Link
                        href="/gestao/cnaes"
                        className="inline-flex items-center gap-1.5 text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                    >
                        <ArrowRightIcon className="size-4 rotate-180" />
                        Voltar
                    </Link>
                }
            />

            <div className="space-y-6">
                <Form action={`/gestao/cnaes/${cnae.id}`} method="put">
                    {({ errors, processing }) => (
                        <div className="space-y-6">
                            <Card>
                                <CardHeader
                                    title="Dados do CNAE"
                                    description="Atualize o código, a denominação e a hierarquia oficial (IBGE/CONCLA)."
                                />
                                <CardContent>
                                    <div className="flex flex-col gap-5">
                                        <div className="grid gap-5 sm:grid-cols-2">
                                            <div>
                                                <Label htmlFor="edit-code" required>
                                                    Código (DDDD-D/SS)
                                                </Label>
                                                <Input
                                                    id="edit-code"
                                                    type="text"
                                                    name="code"
                                                    defaultValue={cnae.formatted_code}
                                                    required
                                                    placeholder="0000-0/00"
                                                    error={!!errors.code}
                                                    hint={errors.code}
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor="edit-active" required>
                                                    Situação
                                                </Label>
                                                <Select
                                                    id="edit-active"
                                                    name="active"
                                                    value={active}
                                                    onChange={setActive}
                                                    options={[
                                                        { value: '1', label: 'Ativo' },
                                                        { value: '0', label: 'Inativo' },
                                                    ]}
                                                />
                                                {errors.active && (
                                                    <p className="mt-1.5 text-theme-xs text-error-500">{errors.active}</p>
                                                )}
                                            </div>
                                            <div className="sm:col-span-2">
                                                <Label htmlFor="edit-description" required>
                                                    Denominação
                                                </Label>
                                                <Input
                                                    id="edit-description"
                                                    type="text"
                                                    name="description"
                                                    defaultValue={cnae.description}
                                                    required
                                                    error={!!errors.description}
                                                    hint={errors.description}
                                                />
                                            </div>
                                        </div>

                                        <div className="grid gap-5 sm:grid-cols-2">
                                            <div>
                                                <Label htmlFor="edit-section-code" required>
                                                    Seção (código e descrição)
                                                </Label>
                                                <div className="flex gap-2">
                                                    <div className="w-16 shrink-0">
                                                        <Input
                                                            id="edit-section-code"
                                                            type="text"
                                                            name="section_code"
                                                            defaultValue={cnae.section_code}
                                                            required
                                                            maxLength={1}
                                                            placeholder="A"
                                                            error={!!errors.section_code}
                                                            hint={errors.section_code}
                                                        />
                                                    </div>
                                                    <div className="flex-1">
                                                        <Input
                                                            type="text"
                                                            name="section_description"
                                                            defaultValue={cnae.section_description}
                                                            required
                                                            aria-label="Descrição da seção"
                                                            error={!!errors.section_description}
                                                            hint={errors.section_description}
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                <Label htmlFor="edit-division-code" required>
                                                    Divisão (código e descrição)
                                                </Label>
                                                <div className="flex gap-2">
                                                    <div className="w-16 shrink-0">
                                                        <Input
                                                            id="edit-division-code"
                                                            type="text"
                                                            name="division_code"
                                                            defaultValue={cnae.division_code}
                                                            required
                                                            maxLength={2}
                                                            placeholder="01"
                                                            error={!!errors.division_code}
                                                            hint={errors.division_code}
                                                        />
                                                    </div>
                                                    <div className="flex-1">
                                                        <Input
                                                            type="text"
                                                            name="division_description"
                                                            defaultValue={cnae.division_description}
                                                            required
                                                            aria-label="Descrição da divisão"
                                                            error={!!errors.division_description}
                                                            hint={errors.division_description}
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                <Label htmlFor="edit-group-code" required>
                                                    Grupo (código e descrição)
                                                </Label>
                                                <div className="flex gap-2">
                                                    <div className="w-20 shrink-0">
                                                        <Input
                                                            id="edit-group-code"
                                                            type="text"
                                                            name="group_code"
                                                            defaultValue={cnae.group_code}
                                                            required
                                                            maxLength={5}
                                                            placeholder="01.1"
                                                            error={!!errors.group_code}
                                                            hint={errors.group_code}
                                                        />
                                                    </div>
                                                    <div className="flex-1">
                                                        <Input
                                                            type="text"
                                                            name="group_description"
                                                            defaultValue={cnae.group_description}
                                                            required
                                                            aria-label="Descrição do grupo"
                                                            error={!!errors.group_description}
                                                            hint={errors.group_description}
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                <Label htmlFor="edit-class-code" required>
                                                    Classe (código e descrição)
                                                </Label>
                                                <div className="flex gap-2">
                                                    <div className="w-24 shrink-0">
                                                        <Input
                                                            id="edit-class-code"
                                                            type="text"
                                                            name="class_code"
                                                            defaultValue={cnae.class_code}
                                                            required
                                                            maxLength={7}
                                                            placeholder="01.11-3"
                                                            error={!!errors.class_code}
                                                            hint={errors.class_code}
                                                        />
                                                    </div>
                                                    <div className="flex-1">
                                                        <Input
                                                            type="text"
                                                            name="class_description"
                                                            defaultValue={cnae.class_description}
                                                            required
                                                            aria-label="Descrição da classe"
                                                            error={!!errors.class_description}
                                                            hint={errors.class_description}
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader
                                    title="Classificação de risco"
                                    description="Decreto nº 41.758/2026 e regras de responsável técnico/fator multiplicador."
                                />
                                <CardContent>
                                    {semVersaoMunicipal && (
                                        <AvisoSemVersao mensagem="Não há versão vigente de risco municipal — o grau de risco não poderá ser salvo até que uma versão seja carregada." />
                                    )}
                                    <RiscoMunicipalFields
                                        niveisMunicipais={niveisMunicipais}
                                        riscoMunicipal={riscoMunicipal}
                                        onRiscoMunicipalChange={setRiscoMunicipal}
                                        exigeRt={exigeRt}
                                        onExigeRtChange={setExigeRt}
                                        exigeRtSeAlto={exigeRtSeAlto}
                                        onExigeRtSeAltoChange={setExigeRtSeAlto}
                                        exigeFatorMultiplicador={exigeFatorMultiplicador}
                                        onExigeFatorMultiplicadorChange={setExigeFatorMultiplicador}
                                        exigeDetalhamentoMultiplicador={exigeDetalhamentoMultiplicador}
                                        onExigeDetalhamentoMultiplicadorChange={setExigeDetalhamentoMultiplicador}
                                        errors={errors}
                                    />
                                </CardContent>
                            </Card>

                            <div className="flex items-center justify-end gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Salvando...' : 'Salvar'}
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}

CnaesEditar.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
