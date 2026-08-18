import { Form, Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import RiscoMunicipalFields from '@/components/cnae/risco-municipal-fields';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { ArrowRightIcon, InfoIcon, PencilIcon, TrashIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

const textareaClassName =
    'w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';

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

interface RegraReclassificacao {
    resposta_gatilho: boolean;
    reclassifica_para: string | null;
    fundamento?: string | null;
}

interface CondicionanteItem {
    id: number;
    pergunta: string;
    regra_reclassificacao: RegraReclassificacao | null;
    texto_parecer: string | null;
}

interface CnaesEditarProps {
    cnae: CnaeEditData;
    condicionantes: CondicionanteItem[];
    niveisMunicipais: NivelOption[];
    niveisReclassificacao: NivelOption[];
    semVersaoMunicipal: boolean;
    semVersaoSanitaria: boolean;
}

function AvisoSemVersao({ mensagem }: { mensagem: string }) {
    return (
        <div className="mb-5 flex items-start gap-3 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/15">
            <InfoIcon className="size-5 shrink-0 fill-current text-warning-500" />
            <p className="text-theme-sm text-gray-600 dark:text-gray-300">{mensagem}</p>
        </div>
    );
}

function CondicionanteRow({
    item,
    niveisReclassificacao,
    canMaintain,
    onEdit,
    onDelete,
}: {
    item: CondicionanteItem;
    niveisReclassificacao: NivelOption[];
    canMaintain: boolean;
    onEdit: () => void;
    onDelete: () => void;
}) {
    const regra = item.regra_reclassificacao;
    const gatilho = regra?.resposta_gatilho ? 'Sim' : 'Não';
    const alvoLabel =
        regra?.reclassifica_para == null
            ? 'Análise técnica'
            : (niveisReclassificacao.find((nivel) => nivel.value === regra.reclassifica_para)?.label ??
              regra.reclassifica_para);

    return (
        <div className="flex flex-col gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800 sm:flex-row sm:items-start sm:justify-between">
            <div className="space-y-1.5">
                <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">{item.pergunta}</p>
                <p className="text-theme-xs text-gray-500 dark:text-gray-400">
                    Resposta <strong>{gatilho}</strong> reclassifica para{' '}
                    <Badge size="sm" color={alvoLabel === 'Análise técnica' ? 'light' : 'warning'}>
                        {alvoLabel}
                    </Badge>
                </p>
                {item.texto_parecer && (
                    <p className="text-theme-xs text-gray-400 dark:text-gray-500">Parecer: {item.texto_parecer}</p>
                )}
            </div>

            {canMaintain && (
                <div className="flex shrink-0 gap-2">
                    <TableAction tone="brand" icon={<PencilIcon className="size-4.5" />} label="Editar" onClick={onEdit} />
                    <TableAction tone="error" icon={<TrashIcon className="size-4.5" />} label="Excluir" onClick={onDelete} />
                </div>
            )}
        </div>
    );
}

function CondicionanteModal({
    cnaeId,
    niveisReclassificacao,
    condicionante,
    onClose,
}: {
    cnaeId: number;
    niveisReclassificacao: NivelOption[];
    condicionante: CondicionanteItem | null;
    onClose: () => void;
}) {
    const isEdit = condicionante !== null;

    const { data, setData, post, put, processing, errors, reset, transform } = useForm<{
        pergunta: string;
        resposta_gatilho: string;
        reclassifica_para: string;
        fundamento: string;
        texto_parecer: string;
    }>({
        pergunta: condicionante?.pergunta ?? '',
        resposta_gatilho: condicionante?.regra_reclassificacao?.resposta_gatilho === false ? '0' : '1',
        reclassifica_para: condicionante?.regra_reclassificacao?.reclassifica_para ?? '',
        fundamento: condicionante?.regra_reclassificacao?.fundamento ?? '',
        texto_parecer: condicionante?.texto_parecer ?? '',
    });

    // O payload enviado ao servidor (via transform, abaixo) aninha
    // resposta_gatilho/reclassifica_para/fundamento dentro de
    // regra_reclassificacao — o Laravel devolve erro de validação nessa
    // chave aninhada (`regra_reclassificacao.reclassifica_para`), que não
    // existe no tipo local do useForm (campos soltos). Sem esse cast, o
    // acesso a essa chave não compila: FormDataErrors<T> só conhece as
    // chaves do T local, nunca as do payload pós-transform.
    const serverErrors = errors as Record<string, string | undefined>;

    function submit(event: FormEvent) {
        event.preventDefault();

        transform((current) => ({
            pergunta: current.pergunta,
            regra_reclassificacao: {
                resposta_gatilho: current.resposta_gatilho === '1',
                reclassifica_para: current.reclassifica_para === '' ? null : current.reclassifica_para,
                fundamento: current.fundamento === '' ? null : current.fundamento,
            },
            texto_parecer: current.texto_parecer === '' ? null : current.texto_parecer,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        };

        if (isEdit && condicionante) {
            put(`/gestao/cnaes/${cnaeId}/condicionantes/${condicionante.id}`, options);
        } else {
            post(`/gestao/cnaes/${cnaeId}/condicionantes`, options);
        }
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[700px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                {isEdit ? 'Editar pergunta' : 'Adicionar pergunta'}
            </h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                A resposta do requerente a esta pergunta pode reclassificar o risco sanitário do CNAE.
            </p>

            <form onSubmit={submit} className="mt-6 flex flex-col gap-5">
                <div>
                    <Label htmlFor="pergunta-texto" required>
                        Texto da pergunta
                    </Label>
                    <textarea
                        id="pergunta-texto"
                        rows={2}
                        maxLength={1000}
                        className={textareaClassName}
                        value={data.pergunta}
                        onChange={(event) => setData('pergunta', event.target.value)}
                    />
                    {errors.pergunta && <p className="mt-1.5 text-theme-xs text-error-500">{errors.pergunta}</p>}
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                        <Label htmlFor="resposta-gatilho" required>
                            Resposta que reclassifica
                        </Label>
                        <Select
                            id="resposta-gatilho"
                            value={data.resposta_gatilho}
                            onChange={(value) => setData('resposta_gatilho', value)}
                            options={[
                                { value: '1', label: 'Sim' },
                                { value: '0', label: 'Não' },
                            ]}
                        />
                    </div>
                    <div>
                        <Label htmlFor="reclassifica-para">Nível resultante</Label>
                        <Select
                            id="reclassifica-para"
                            value={data.reclassifica_para}
                            onChange={(value) => setData('reclassifica_para', value)}
                            placeholder="Análise técnica (sem reclassificação automática)"
                            options={niveisReclassificacao}
                        />
                        {serverErrors['regra_reclassificacao.reclassifica_para'] && (
                            <p className="mt-1.5 text-theme-xs text-error-500">
                                {serverErrors['regra_reclassificacao.reclassifica_para']}
                            </p>
                        )}
                    </div>
                </div>

                <div>
                    <Label htmlFor="fundamento">Fundamento (aparece no parecer)</Label>
                    <textarea
                        id="fundamento"
                        rows={2}
                        maxLength={2000}
                        className={textareaClassName}
                        value={data.fundamento}
                        onChange={(event) => setData('fundamento', event.target.value)}
                    />
                </div>

                <div>
                    <Label htmlFor="texto-parecer">Texto do parecer</Label>
                    <textarea
                        id="texto-parecer"
                        rows={2}
                        maxLength={2000}
                        className={textareaClassName}
                        value={data.texto_parecer}
                        onChange={(event) => setData('texto_parecer', event.target.value)}
                    />
                </div>

                <div className="flex items-center justify-end gap-3">
                    <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                        Cancelar
                    </Button>
                    <Button size="sm" type="submit" disabled={processing}>
                        {processing ? 'Salvando...' : 'Salvar'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

export default function CnaesEditar({
    cnae,
    condicionantes,
    niveisMunicipais,
    niveisReclassificacao,
    semVersaoMunicipal,
    semVersaoSanitaria,
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

    const [showCondicionanteModal, setShowCondicionanteModal] = useState(false);
    const [editingCondicionante, setEditingCondicionante] = useState<CondicionanteItem | null>(null);
    const [deletingCondicionante, setDeletingCondicionante] = useState<CondicionanteItem | null>(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    function confirmDelete() {
        if (!deletingCondicionante) {
            return;
        }

        router.delete(`/gestao/cnaes/${cnae.id}/condicionantes/${deletingCondicionante.id}`, {
            preserveScroll: true,
            onStart: () => setDeleteProcessing(true),
            onFinish: () => setDeleteProcessing(false),
            onSuccess: () => setDeletingCondicionante(null),
        });
    }

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
                                    description="Decreto nº 32.636/2020 e regras de responsável técnico/fator multiplicador."
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

                <Card>
                    <CardHeader
                        title="Perguntas de classificação de risco"
                        description={`${condicionantes.length} pergunta(s) — risco sanitário (VISA)`}
                        actions={
                            canMaintain ? (
                                <Button size="sm" variant="outline" onClick={() => setShowCondicionanteModal(true)}>
                                    Adicionar pergunta
                                </Button>
                            ) : undefined
                        }
                    />
                    <CardContent>
                        {semVersaoSanitaria && (
                            <AvisoSemVersao mensagem="Não há versão vigente de risco sanitário — novas perguntas não poderão ser cadastradas até que uma versão seja carregada." />
                        )}

                        {condicionantes.length === 0 ? (
                            <EmptyState
                                title="Nenhuma pergunta cadastrada"
                                description="Cadastre a primeira pergunta de classificação de risco sanitário deste CNAE."
                            />
                        ) : (
                            <div className="flex flex-col gap-3">
                                {condicionantes.map((item) => (
                                    <CondicionanteRow
                                        key={item.id}
                                        item={item}
                                        niveisReclassificacao={niveisReclassificacao}
                                        canMaintain={canMaintain}
                                        onEdit={() => setEditingCondicionante(item)}
                                        onDelete={() => setDeletingCondicionante(item)}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {canMaintain && showCondicionanteModal && (
                <CondicionanteModal
                    cnaeId={cnae.id}
                    niveisReclassificacao={niveisReclassificacao}
                    condicionante={null}
                    onClose={() => setShowCondicionanteModal(false)}
                />
            )}

            {canMaintain && editingCondicionante && (
                <CondicionanteModal
                    cnaeId={cnae.id}
                    niveisReclassificacao={niveisReclassificacao}
                    condicionante={editingCondicionante}
                    onClose={() => setEditingCondicionante(null)}
                />
            )}

            {deletingCondicionante && (
                <ConfirmDialog
                    isOpen
                    onClose={() => setDeletingCondicionante(null)}
                    onConfirm={confirmDelete}
                    title="Excluir pergunta"
                    description={`Confirma a exclusão da pergunta "${deletingCondicionante.pergunta}"? Esta ação não pode ser desfeita.`}
                    confirmLabel="Excluir"
                    processing={deleteProcessing}
                />
            )}
        </>
    );
}

CnaesEditar.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
