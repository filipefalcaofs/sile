import { Form, Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import RiscoMunicipalFields from '@/components/cnae/risco-municipal-fields';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { ArrowRightIcon } from '@/components/icons';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import GestaoLayout from '@/layouts/gestao-layout';

interface NivelOption {
    value: string;
    label: string;
}

interface CnaesCriarProps {
    niveisMunicipais: NivelOption[];
}

export default function CnaesCriar({ niveisMunicipais }: CnaesCriarProps) {
    const [riscoMunicipal, setRiscoMunicipal] = useState('');
    const [exigeRt, setExigeRt] = useState('0');
    const [exigeRtSeAlto, setExigeRtSeAlto] = useState('0');
    const [exigeFatorMultiplicador, setExigeFatorMultiplicador] = useState('0');
    const [exigeDetalhamentoMultiplicador, setExigeDetalhamentoMultiplicador] = useState('0');

    return (
        <>
            <Head title="Novo CNAE" />
            <PageHeader
                title="Novo CNAE"
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

            <Form action="/gestao/cnaes" method="post">
                {({ errors, processing }) => (
                    <div className="space-y-6">
                        <Card>
                            <CardHeader
                                title="Dados do CNAE"
                                description="Informe o código da subclasse, a denominação e a hierarquia oficial (IBGE/CONCLA)."
                            />
                            <CardContent>
                                <div className="flex flex-col gap-5">
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <div>
                                            <Label htmlFor="code" required>
                                                Código (DDDD-D/SS)
                                            </Label>
                                            <Input
                                                id="code"
                                                type="text"
                                                name="code"
                                                required
                                                placeholder="0000-0/00"
                                                error={!!errors.code}
                                                hint={errors.code}
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="description" required>
                                                Denominação
                                            </Label>
                                            <Input
                                                id="description"
                                                type="text"
                                                name="description"
                                                required
                                                error={!!errors.description}
                                                hint={errors.description}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <div>
                                            <Label htmlFor="section-code" required>
                                                Seção (código e descrição)
                                            </Label>
                                            <div className="flex gap-2">
                                                <div className="w-16 shrink-0">
                                                    <Input
                                                        id="section-code"
                                                        type="text"
                                                        name="section_code"
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
                                                        required
                                                        aria-label="Descrição da seção"
                                                        error={!!errors.section_description}
                                                        hint={errors.section_description}
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <Label htmlFor="division-code" required>
                                                Divisão (código e descrição)
                                            </Label>
                                            <div className="flex gap-2">
                                                <div className="w-16 shrink-0">
                                                    <Input
                                                        id="division-code"
                                                        type="text"
                                                        name="division_code"
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
                                                        required
                                                        aria-label="Descrição da divisão"
                                                        error={!!errors.division_description}
                                                        hint={errors.division_description}
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <Label htmlFor="group-code" required>
                                                Grupo (código e descrição)
                                            </Label>
                                            <div className="flex gap-2">
                                                <div className="w-20 shrink-0">
                                                    <Input
                                                        id="group-code"
                                                        type="text"
                                                        name="group_code"
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
                                                        required
                                                        aria-label="Descrição do grupo"
                                                        error={!!errors.group_description}
                                                        hint={errors.group_description}
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <Label htmlFor="class-code" required>
                                                Classe (código e descrição)
                                            </Label>
                                            <div className="flex gap-2">
                                                <div className="w-24 shrink-0">
                                                    <Input
                                                        id="class-code"
                                                        type="text"
                                                        name="class_code"
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
                                description="Decreto nº 32.636/2020 e regras de responsável técnico/fator multiplicador (parametrizável por CNAE)."
                            />
                            <CardContent>
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
                            <Link
                                href="/gestao/cnaes"
                                className="inline-flex h-10 items-center rounded-lg px-4 text-theme-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 dark:text-gray-300 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
                            >
                                Cancelar
                            </Link>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Salvando...' : 'Salvar'}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

CnaesCriar.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
