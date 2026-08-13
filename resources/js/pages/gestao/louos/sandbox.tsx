import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { AlertIcon, InfoIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import DataTable from '@/components/ui/data-table/data-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import GestaoLayout from '@/layouts/gestao-layout';

type ResultadoColor = 'success' | 'warning' | 'error' | 'light';

interface Rascunho {
    quadro: string;
    dominio: string;
    label: string;
    version: string;
    author_id: number | null;
    created_at: string | null;
}

interface Divergencia {
    cenario: {
        cnae: string;
        cnae_formatado: string;
        area: number;
        zona: string;
    };
    resultado_vigente: string;
    resultado_simulado: string;
    motivo_simulado: string | null;
}

interface Simulacao {
    dominio: string;
    versao_rascunho: string;
    amostra_usada: number;
    total: number;
    mudariam: number;
    distribuicao: Record<string, number>;
    divergencias: Divergencia[];
}

interface SandboxProps {
    rascunhos: Rascunho[];
    amostraPadrao: number;
    simulacao?: Simulacao | null;
}

const RESULTADO_META: Record<string, { label: string; color: ResultadoColor }> = {
    permitido: { label: 'Permitido', color: 'success' },
    permitido_com_condicoes: { label: 'Permitido com condições', color: 'warning' },
    nao_permitido: { label: 'Não permitido', color: 'error' },
    pendente: { label: 'Pendente de análise', color: 'light' },
};

function resultadoMeta(value: string): { label: string; color: ResultadoColor } {
    return RESULTADO_META[value] ?? { label: value, color: 'light' };
}

/** Rótulo legível de uma transição "de→para" da distribuição. */
function transicaoLabel(chave: string): string {
    const [de, para] = chave.split('→');

    return `${resultadoMeta(de ?? '').label} → ${resultadoMeta(para ?? '').label}`;
}

const COMPOSITE_SEPARATOR = '::';

export default function LouosSandbox({ rascunhos, amostraPadrao, simulacao = null }: SandboxProps) {
    const { data, setData, post, processing, errors, transform } = useForm<{
        quadro: string;
        versao_rascunho: string;
        amostra: string;
    }>({
        quadro: simulacao?.dominio ? quadroDoDominio(rascunhos, simulacao.dominio) : '',
        versao_rascunho: simulacao?.versao_rascunho ?? '',
        amostra: '',
    });

    const [confirmingPublish, setConfirmingPublish] = useState(false);
    const [publishing, setPublishing] = useState(false);

    const rascunhoOptions = rascunhos.map((rascunho) => ({
        value: `${rascunho.quadro}${COMPOSITE_SEPARATOR}${rascunho.version}`,
        label: `${rascunho.label} — ${rascunho.version}`,
    }));

    const selectedValue =
        data.quadro && data.versao_rascunho ? `${data.quadro}${COMPOSITE_SEPARATOR}${data.versao_rascunho}` : '';

    const rascunhoSelecionado = rascunhos.find(
        (rascunho) => rascunho.quadro === data.quadro && rascunho.version === data.versao_rascunho,
    );

    function selecionarRascunho(value: string) {
        const separadorIndex = value.indexOf(COMPOSITE_SEPARATOR);
        const quadro = value.slice(0, separadorIndex);
        const version = value.slice(separadorIndex + COMPOSITE_SEPARATOR.length);

        setData((previous) => ({ ...previous, quadro, versao_rascunho: version }));
    }

    function simular(event: FormEvent) {
        event.preventDefault();

        transform((current) => ({
            quadro: current.quadro,
            versao_rascunho: current.versao_rascunho,
            amostra: current.amostra.trim() === '' ? null : Number(current.amostra),
        }));

        post('/gestao/louos/simulacao', {
            preserveScroll: true,
            preserveState: true,
        });
    }

    function publicar() {
        setPublishing(true);

        router.put(
            '/gestao/louos/simulacao/publicar',
            { quadro: data.quadro, versao_rascunho: data.versao_rascunho },
            {
                preserveScroll: true,
                onFinish: () => {
                    setPublishing(false);
                    setConfirmingPublish(false);
                },
            },
        );
    }

    const semRascunho = data.quadro === '' || data.versao_rascunho === '';

    return (
        <>
            <Head title="Simulação de regras da LOUOS" />
            <PageHeader
                title="Simulação de regras"
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Quadros da LOUOS', href: '/gestao/louos' },
                ]}
            />

            <div className="space-y-6">
                <Card>
                    <CardHeader
                        title="Versão candidata"
                        description="Simule o impacto de um rascunho de Quadro contra cenários reais antes de publicar."
                    />
                    <CardContent>
                        {rascunhos.length === 0 ? (
                            <EmptyState
                                title="Nenhum rascunho disponível"
                                description="Abra um rascunho de um Quadro da LOUOS (versão candidata) para simular o impacto antes de publicar."
                            />
                        ) : (
                            <form onSubmit={simular} className="space-y-6">
                                <div className="flex items-start gap-3 rounded-xl border border-blue-light-200 bg-blue-light-50 p-4 dark:border-blue-light-500/30 dark:bg-blue-light-500/15">
                                    <InfoIcon className="size-5 shrink-0 fill-current text-blue-light-500" />
                                    <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                                        A simulação reexecuta o motor de enquadramento com a versão rascunho{' '}
                                        <strong>sem afetar a versão vigente</strong> e sem publicar. Você vê quantos
                                        resultados mudariam e só então decide publicar.
                                    </p>
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <Label htmlFor="rascunho" required>
                                            Rascunho a simular
                                        </Label>
                                        <Select
                                            id="rascunho"
                                            value={selectedValue}
                                            onChange={selecionarRascunho}
                                            placeholder="Selecione um rascunho"
                                            options={rascunhoOptions}
                                        />
                                        {errors.versao_rascunho && (
                                            <p className="mt-1.5 text-theme-xs text-error-500">
                                                {errors.versao_rascunho}
                                            </p>
                                        )}
                                    </div>
                                    <div>
                                        <Label htmlFor="amostra">Tamanho da amostra</Label>
                                        <Input
                                            id="amostra"
                                            type="number"
                                            min={1}
                                            value={data.amostra}
                                            onChange={(event) => setData('amostra', event.target.value)}
                                            placeholder={`padrão: ${amostraPadrao}`}
                                            error={!!errors.amostra}
                                            hint={errors.amostra}
                                        />
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center justify-end gap-3">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={semRascunho || processing}
                                        onClick={() => setConfirmingPublish(true)}
                                    >
                                        Publicar versão
                                    </Button>
                                    <Button type="submit" size="sm" disabled={semRascunho || processing}>
                                        {processing ? 'Simulando...' : 'Simular impacto'}
                                    </Button>
                                </div>
                            </form>
                        )}
                    </CardContent>
                </Card>

                {simulacao && <SimulacaoResultado simulacao={simulacao} />}
            </div>

            <ConfirmDialog
                isOpen={confirmingPublish}
                onClose={() => setConfirmingPublish(false)}
                onConfirm={publicar}
                title="Publicar versão por quatro olhos"
                variant="warning"
                confirmLabel="Publicar versão"
                processing={publishing}
                description={
                    <span>
                        A publicação cria uma nova versão vigente do{' '}
                        <strong>{rascunhoSelecionado?.label ?? 'Quadro'}</strong> e preserva a anterior como histórico.
                        Exige <strong>quatro olhos</strong>: o publicador deve ser diferente do autor do rascunho — caso
                        contrário a publicação é bloqueada.
                    </span>
                }
            />
        </>
    );
}

/** Resumo do impacto + tabela de divergências (resultado vigente × simulado). */
function SimulacaoResultado({ simulacao }: { simulacao: Simulacao }) {
    const columns: ColumnDef<Divergencia>[] = [
        {
            id: 'cnae',
            header: 'CNAE',
            cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
            cell: (row) => row.cenario.cnae_formatado,
        },
        { id: 'zona', header: 'Zona', cell: (row) => row.cenario.zona },
        {
            id: 'area',
            header: 'Área (m²)',
            cellClassName: 'whitespace-nowrap',
            cell: (row) => row.cenario.area.toLocaleString('pt-BR'),
        },
        {
            id: 'vigente',
            header: 'Resultado vigente',
            cellClassName: 'whitespace-nowrap',
            cell: (row) => {
                const meta = resultadoMeta(row.resultado_vigente);

                return (
                    <Badge color={meta.color} size="sm">
                        {meta.label}
                    </Badge>
                );
            },
        },
        {
            id: 'simulado',
            header: 'Resultado simulado',
            cellClassName: 'whitespace-nowrap',
            cell: (row) => {
                const meta = resultadoMeta(row.resultado_simulado);

                return (
                    <Badge color={meta.color} size="sm">
                        {meta.label}
                    </Badge>
                );
            },
        },
        { id: 'motivo', header: 'Motivo (simulado)', cell: (row) => row.motivo_simulado ?? '—' },
    ];

    const distribuicao = Object.entries(simulacao.distribuicao);

    return (
        <Card>
            <CardHeader
                title="Impacto simulado"
                description={`Amostra de ${simulacao.amostra_usada} — ${simulacao.total} cenário(s) reprocessado(s) com o motor real.`}
            />
            <CardContent>
                <div className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <ResumoCard rotulo="Reprocessados" valor={simulacao.total} />
                        <ResumoCard rotulo="Mudariam de resultado" valor={simulacao.mudariam} destaque />
                        <ResumoCard rotulo="Amostra usada" valor={simulacao.amostra_usada} />
                    </div>

                    {distribuicao.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            {distribuicao.map(([chave, quantidade]) => (
                                <span
                                    key={chave}
                                    className="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-theme-xs text-gray-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300"
                                >
                                    {transicaoLabel(chave)}
                                    <span className="font-semibold text-gray-800 dark:text-white/90">{quantidade}</span>
                                </span>
                            ))}
                        </div>
                    )}

                    {simulacao.mudariam === 0 ? (
                        <div className="flex items-start gap-3 rounded-xl border border-success-200 bg-success-50 p-4 dark:border-success-500/30 dark:bg-success-500/10">
                            <InfoIcon className="size-5 shrink-0 fill-current text-success-500" />
                            <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                                Nenhum resultado mudaria com este rascunho na amostra avaliada.
                            </p>
                        </div>
                    ) : (
                        <div>
                            <div className="mb-4 flex items-start gap-3 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/10">
                                <AlertIcon className="size-5 shrink-0 fill-current text-warning-500" />
                                <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                                    <span className="font-medium text-warning-600 dark:text-orange-400">
                                        {simulacao.mudariam} resultado(s) mudariam.
                                    </span>{' '}
                                    Revise as divergências abaixo antes de publicar.
                                </p>
                            </div>
                            <DataTable
                                columns={columns}
                                rows={simulacao.divergencias}
                                rowKey={(row) => row.cenario.cnae}
                                density="compact"
                                emptyState={
                                    <EmptyState
                                        title="Sem divergências"
                                        description="A versão candidata não altera os resultados da amostra."
                                    />
                                }
                            />
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function ResumoCard({ rotulo, valor, destaque = false }: { rotulo: string; valor: number; destaque?: boolean }) {
    return (
        <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <p className="text-theme-sm text-gray-500 dark:text-gray-400">{rotulo}</p>
            <p
                className={`mt-2 text-2xl font-semibold ${
                    destaque ? 'text-warning-600 dark:text-orange-400' : 'text-gray-800 dark:text-white/90'
                }`}
            >
                {valor.toLocaleString('pt-BR')}
            </p>
        </div>
    );
}

/** Quadro (param) correspondente a um domínio de regra, a partir dos rascunhos. */
function quadroDoDominio(rascunhos: Rascunho[], dominio: string): string {
    return rascunhos.find((rascunho) => rascunho.dominio === dominio)?.quadro ?? '';
}

LouosSandbox.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
