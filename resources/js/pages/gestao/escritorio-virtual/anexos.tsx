import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { AlertIcon, FileIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface VersaoVigente {
    version: string;
    published_at: string | null;
    publicado_por: string | null;
    fonte: string | null;
    anexo_a: number;
    anexo_b: number;
}

interface DiffAnexo {
    adicionados: string[];
    removidos: string[];
}

interface Rascunho {
    version: string;
    autor: { id: number | null; name: string | null };
    anexo_a: number;
    anexo_b: number;
    diff: { A: DiffAnexo; B: DiffAnexo };
}

interface VersaoHistorico {
    id: number;
    version: string;
    status: string;
    status_label: string;
    valid_from: string | null;
    valid_to: string | null;
    total: number;
}

interface AnexosProps {
    vigente: VersaoVigente | null;
    rascunho: Rascunho | null;
    historico: VersaoHistorico[];
    versaoSugerida: string;
}

/** Formata a data ISO (YYYY-MM-DD) para dd/mm/aaaa sem deslocamento de fuso. */
function formatarData(iso: string | null): string | null {
    if (!iso) {
        return null;
    }

    const [ano, mes, dia] = iso.split('-');

    return dia && mes && ano ? `${dia}/${mes}/${ano}` : iso;
}

/** Formata o código CNAE armazenado em dígitos (6204000 → 6204-0/00). */
function formatarCnae(digitos: string): string {
    return digitos.replace(/^(\d{4})(\d)(\d{2})$/, '$1-$2/$3');
}

const LIMITE_LISTA_DIFF = 12;

function ListaDiff({ titulo, codigos, cor }: { titulo: string; codigos: string[]; cor: 'success' | 'error' }) {
    if (codigos.length === 0) {
        return null;
    }

    const visiveis = codigos.slice(0, LIMITE_LISTA_DIFF);
    const restantes = codigos.length - visiveis.length;

    return (
        <div>
            <div className="flex items-center gap-2">
                <Badge color={cor} size="sm">
                    {titulo}: {codigos.length}
                </Badge>
            </div>
            <p className="mt-2 text-theme-xs text-gray-500 dark:text-gray-400">
                {visiveis.map(formatarCnae).join(', ')}
                {restantes > 0 ? ` e mais ${restantes}` : ''}
            </p>
        </div>
    );
}

function DiffAnexoCard({ anexo, diff }: { anexo: string; diff: DiffAnexo }) {
    const vazio = diff.adicionados.length === 0 && diff.removidos.length === 0;

    return (
        <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
            <h4 className="text-sm font-medium text-gray-800 dark:text-white/90">Anexo {anexo}</h4>
            {vazio ? (
                <p className="mt-2 text-theme-sm text-gray-500 dark:text-gray-400">
                    Nenhuma diferença em relação à versão vigente.
                </p>
            ) : (
                <div className="mt-3 space-y-3">
                    <ListaDiff titulo="Adicionados" codigos={diff.adicionados} cor="success" />
                    <ListaDiff titulo="Removidos" codigos={diff.removidos} cor="error" />
                </div>
            )}
        </div>
    );
}

function ImportarCard({ versaoSugerida }: { versaoSugerida: string }) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        versao: string;
        fonte: string;
        anexo_a: File | null;
        anexo_b: File | null;
    }>({
        versao: versaoSugerida,
        fonte: '',
        anexo_a: null,
        anexo_b: null,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        post('/gestao/escritorio-virtual/anexos', {
            preserveScroll: true,
            onSuccess: () => {
                reset('anexo_a', 'anexo_b');
            },
        });
    }

    const inputArquivo =
        'block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-800 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1 file:text-xs file:text-gray-700 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';

    return (
        <Card>
            <CardHeader
                title="Importar nova versão"
                description="Os dois CSVs (cabeçalho cnae_code,cnae_description) entram num rascunho nomeado. A versão vigente só muda quando outro mantenedor publicar — quatro olhos."
            />
            <CardContent>
                <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
                    <div>
                        <Label htmlFor="versao" required>
                            Versão
                        </Label>
                        <Input
                            id="versao"
                            value={data.versao}
                            onChange={(event) => setData('versao', event.target.value)}
                            error={!!errors.versao}
                            hint={errors.versao}
                            placeholder="ev-anexos-aaaa-mm-dd"
                        />
                    </div>
                    <div>
                        <Label htmlFor="fonte" required>
                            Fonte
                        </Label>
                        <Input
                            id="fonte"
                            value={data.fonte}
                            onChange={(event) => setData('fonte', event.target.value)}
                            error={!!errors.fonte}
                            hint={errors.fonte}
                            placeholder="Ex.: Anexos A e B do Decreto 35.062/2021 (revisão de ...)"
                        />
                    </div>
                    <div>
                        <Label htmlFor="anexo_a" required>
                            CSV do Anexo A (sede)
                        </Label>
                        <input
                            id="anexo_a"
                            type="file"
                            accept=".csv,text/csv,text/plain"
                            onChange={(event) => setData('anexo_a', event.target.files?.[0] ?? null)}
                            className={inputArquivo}
                        />
                        {errors.anexo_a && <p className="mt-1 text-theme-xs text-error-500">{errors.anexo_a}</p>}
                    </div>
                    <div>
                        <Label htmlFor="anexo_b" required>
                            CSV do Anexo B (abrigado)
                        </Label>
                        <input
                            id="anexo_b"
                            type="file"
                            accept=".csv,text/csv,text/plain"
                            onChange={(event) => setData('anexo_b', event.target.files?.[0] ?? null)}
                            className={inputArquivo}
                        />
                        {errors.anexo_b && <p className="mt-1 text-theme-xs text-error-500">{errors.anexo_b}</p>}
                    </div>
                    <div className="md:col-span-2">
                        <Button
                            size="sm"
                            type="submit"
                            loading={processing}
                            disabled={!data.anexo_a || !data.anexo_b}
                        >
                            Importar para rascunho
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function PublicarModal({
    rascunho,
    isAutor,
    onClose,
}: {
    rascunho: Rascunho;
    isAutor: boolean;
    onClose: () => void;
}) {
    const [processing, setProcessing] = useState(false);

    function confirmar() {
        setProcessing(true);
        router.put(
            `/gestao/escritorio-virtual/anexos/${rascunho.version}/publicar`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onClose(),
            },
        );
    }

    const totalAdicionados = rascunho.diff.A.adicionados.length + rascunho.diff.B.adicionados.length;
    const totalRemovidos = rascunho.diff.A.removidos.length + rascunho.diff.B.removidos.length;

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-w-lg p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Publicar anexos</h4>
            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                A versão <strong>{rascunho.version}</strong> passará a ser a vigente:{' '}
                <strong>
                    {totalAdicionados} {totalAdicionados === 1 ? 'CNAE adicionado' : 'CNAEs adicionados'} e{' '}
                    {totalRemovidos} {totalRemovidos === 1 ? 'removido' : 'removidos'}
                </strong>{' '}
                nos dois anexos. A versão em uso hoje é fechada e permanece no histórico — nenhuma linha é
                apagada.
            </p>
            {isAutor && (
                <div className="mt-3 flex items-start gap-2 rounded-lg border border-warning-200 bg-warning-50 p-3 dark:border-warning-500/30 dark:bg-warning-500/10">
                    <AlertIcon className="size-4 shrink-0 fill-current text-warning-500" />
                    <p className="text-theme-xs text-gray-600 dark:text-gray-300">
                        <span className="font-medium text-warning-600 dark:text-orange-400">Quatro olhos.</span>{' '}
                        Você é o autor deste rascunho — a publicação exige outro mantenedor com a permissão de
                        CNAEs.
                    </p>
                </div>
            )}
            <div className="mt-6 flex items-center justify-end gap-3">
                <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                    Cancelar
                </Button>
                <Button size="sm" onClick={confirmar} loading={processing} disabled={isAutor}>
                    Publicar esta versão
                </Button>
            </div>
        </Modal>
    );
}

export default function EscritorioVirtualAnexos({ vigente, rascunho, historico, versaoSugerida }: AnexosProps) {
    const { auth } = usePage<SharedProps>().props;
    const [showPublicar, setShowPublicar] = useState(false);

    const isAutor = rascunho !== null && auth.user?.id === rascunho.autor.id;

    return (
        <>
            <Head title="Anexos de escritório virtual" />
            <PageHeader
                title="Anexos de escritório virtual"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }]}
            />

            <div className="space-y-6">
                <Card>
                    <CardHeader
                        title="Versão vigente"
                        description="Anexos A (sede) e B (abrigado) do Decreto 35.062/2021 em uso pelo motor."
                    />
                    <CardContent>
                        {vigente === null ? (
                            <EmptyState
                                title="Nenhuma versão vigente"
                                description="Importe os dois CSVs e publique para iniciar a base de anexos."
                            />
                        ) : (
                            <div className="flex flex-wrap items-center gap-x-6 gap-y-2 text-theme-sm text-gray-600 dark:text-gray-400">
                                <span className="font-medium text-gray-800 dark:text-white/90">
                                    {vigente.version}
                                </span>
                                <span>Anexo A: {vigente.anexo_a} CNAEs</span>
                                <span>Anexo B: {vigente.anexo_b} CNAEs</span>
                                {vigente.published_at && <span>publicada em {formatarData(vigente.published_at)}</span>}
                                {vigente.publicado_por && <span>por {vigente.publicado_por}</span>}
                            </div>
                        )}
                        {vigente?.fonte && (
                            <p className="mt-3 text-theme-xs text-gray-500 dark:text-gray-400">
                                Fonte: {vigente.fonte}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <ImportarCard versaoSugerida={versaoSugerida} />

                {rascunho !== null && (
                    <Card>
                        <CardHeader
                            title={`Rascunho ${rascunho.version}`}
                            description={`Autor: ${rascunho.autor.name ?? '—'} · Anexo A: ${rascunho.anexo_a} CNAEs · Anexo B: ${rascunho.anexo_b} CNAEs`}
                            actions={
                                <Button size="sm" onClick={() => setShowPublicar(true)}>
                                    <FileIcon className="size-4 fill-current" />
                                    Publicar
                                </Button>
                            }
                        />
                        <CardContent>
                            <p className="mb-4 text-theme-sm text-gray-500 dark:text-gray-400">
                                Diferenças do rascunho em relação à versão vigente:
                            </p>
                            <div className="grid gap-4 md:grid-cols-2">
                                <DiffAnexoCard anexo="A" diff={rascunho.diff.A} />
                                <DiffAnexoCard anexo="B" diff={rascunho.diff.B} />
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader
                        title="Histórico de versões"
                        description="Versões publicadas e rascunhos dos anexos. A vigente é a única em uso pelo motor."
                    />
                    <CardContent>
                        {historico.length === 0 ? (
                            <EmptyState
                                title="Nenhuma versão registrada"
                                description="Importe e publique um rascunho para criar a primeira versão dos anexos."
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-left text-theme-sm">
                                    <thead>
                                        <tr className="border-b border-gray-200 text-theme-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                            <th className="px-3 py-2 font-medium">Versão</th>
                                            <th className="px-3 py-2 font-medium">Situação</th>
                                            <th className="px-3 py-2 font-medium">Vigência</th>
                                            <th className="px-3 py-2 font-medium">Registros</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {historico.map((versao) => (
                                            <tr
                                                key={versao.id}
                                                className="border-b border-gray-100 last:border-0 dark:border-gray-800"
                                            >
                                                <td className="px-3 py-3 font-medium text-gray-800 dark:text-white/90">
                                                    {versao.version}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <Badge
                                                        color={
                                                            versao.status === 'vigente'
                                                                ? 'success'
                                                                : versao.status === 'rascunho'
                                                                  ? 'warning'
                                                                  : 'light'
                                                        }
                                                        size="sm"
                                                    >
                                                        {versao.status_label}
                                                    </Badge>
                                                </td>
                                                <td className="px-3 py-3 text-gray-500 dark:text-gray-400">
                                                    {formatarData(versao.valid_from) ?? '—'}
                                                    {versao.valid_to ? ` até ${formatarData(versao.valid_to)}` : ''}
                                                </td>
                                                <td className="px-3 py-3 text-gray-700 dark:text-gray-300">
                                                    {versao.total}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {showPublicar && rascunho !== null && (
                <PublicarModal rascunho={rascunho} isAutor={isAutor} onClose={() => setShowPublicar(false)} />
            )}
        </>
    );
}

EscritorioVirtualAnexos.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
