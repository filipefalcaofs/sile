import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { CheckCircleIcon, FileIcon, TrashIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';

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

interface RequisitoFaltante {
    id: number;
    code: string;
    name: string;
}

interface EtapaDocumentosProps {
    solicitacaoId: number;
    documentos: DocumentoItem[];
    requisitosObrigatorios: Requisito[];
    requisitosFaltantes: RequisitoFaltante[];
    anexosConfig: { max_mb: number; mime_permitidos: string[] };
    onContinue: () => void;
}

const MIME_LABELS: Record<string, string> = {
    'application/pdf': 'PDF',
    'image/jpeg': 'JPG',
    'image/png': 'PNG',
};

function mimeLabel(mime: string): string {
    return MIME_LABELS[mime] ?? mime;
}

function formatSize(bytes: number | null): string {
    if (bytes === null) {
        return '';
    }
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * Etapa de documentos (HU-066/067): anexa arquivos (POST solicitacoes.documentos)
 * e mostra os requisitos obrigatórios — os FALTANTES aparecem como AVISO honesto
 * (nunca silencioso); o bloqueio do protocolo é na revisão. O download é
 * autenticado (08-08). Tipos e tamanho aceitos são comunicados (HU-014).
 */
export default function EtapaDocumentos({
    solicitacaoId,
    documentos,
    requisitosObrigatorios,
    requisitosFaltantes,
    anexosConfig,
    onContinue,
}: EtapaDocumentosProps) {
    const { data, setData, post, transform, processing, errors, reset } = useForm<{
        file: File | null;
        requirement_id: string;
    }>({
        file: null,
        requirement_id: '',
    });
    const [removing, setRemoving] = useState<number | null>(null);
    // Remonta o input nativo de arquivo após cada envio (limpa o nome exibido).
    const [fileKey, setFileKey] = useState(0);

    const faltantesIds = new Set(requisitosFaltantes.map((requisito) => requisito.id));
    const tiposAceitos = anexosConfig.mime_permitidos.map(mimeLabel).join(', ');

    function enviar(event: React.FormEvent) {
        event.preventDefault();

        // requirement_id vazio = anexo avulso → envia null (o backend valida
        // nullable|integer; string vazia quebraria a regra integer).
        transform((current) => ({
            file: current.file,
            requirement_id: current.requirement_id === '' ? null : Number(current.requirement_id),
        }));

        post(`/portal/solicitacoes/${solicitacaoId}/documentos`, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                reset();
                setFileKey((current) => current + 1);
            },
        });
    }

    function remover(documento: DocumentoItem) {
        router.delete(`/portal/solicitacoes/${solicitacaoId}/documentos/${documento.id}`, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setRemoving(documento.id),
            onFinish: () => setRemoving(null),
        });
    }

    return (
        <div className="flex flex-col gap-4 md:gap-6">
            {requisitosFaltantes.length > 0 && (
                <Alert
                    variant="warning"
                    title="Documentos obrigatórios pendentes"
                    message={`Anexe antes de protocolar: ${requisitosFaltantes.map((requisito) => requisito.name).join('; ')}.`}
                />
            )}

            <Card>
                <CardHeader
                    title="Documentos obrigatórios"
                    description="Documentos exigidos para esta solicitação (variam conforme as atividades e as características do imóvel)."
                />
                <CardContent>
                    {requisitosObrigatorios.length > 0 ? (
                        <ul className="flex flex-col gap-2">
                            {requisitosObrigatorios.map((requisito) => {
                                const pendente = faltantesIds.has(requisito.id);

                                return (
                                    <li
                                        key={requisito.id}
                                        className="flex items-start justify-between gap-3 rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800"
                                    >
                                        <div className="flex flex-col">
                                            <span className="text-sm font-medium text-gray-800 dark:text-white/90">
                                                {requisito.name}
                                            </span>
                                            {requisito.description && (
                                                <span className="text-theme-xs text-gray-500 dark:text-gray-400">
                                                    {requisito.description}
                                                </span>
                                            )}
                                        </div>
                                        {pendente ? (
                                            <Badge size="sm" color="warning">
                                                Pendente
                                            </Badge>
                                        ) : (
                                            <Badge size="sm" color="success" startIcon={<CheckCircleIcon className="size-3.5 fill-current" />}>
                                                Anexado
                                            </Badge>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    ) : (
                        <p className="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            Nenhum documento obrigatório identificado para esta solicitação até o momento.
                        </p>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    title="Anexar documento"
                    description={`Tipos aceitos: ${tiposAceitos || 'PDF, JPG, PNG'} · tamanho máximo: ${anexosConfig.max_mb} MB.`}
                />
                <CardContent>
                    <form onSubmit={enviar} className="flex flex-col gap-5">
                        {requisitosObrigatorios.length > 0 && (
                            <div>
                                <Label htmlFor="requirement_id">Vincular a um requisito (opcional)</Label>
                                <Select
                                    id="requirement_id"
                                    value={data.requirement_id}
                                    onChange={(value) => setData('requirement_id', value)}
                                    options={requisitosObrigatorios.map((requisito) => ({
                                        value: String(requisito.id),
                                        label: requisito.name,
                                    }))}
                                    placeholder="Anexo avulso (sem requisito)"
                                />
                            </div>
                        )}

                        <div>
                            <Label htmlFor="file" required>
                                Arquivo
                            </Label>
                            <input
                                key={fileKey}
                                id="file"
                                type="file"
                                name="file"
                                onChange={(event) => setData('file', event.target.files?.[0] ?? null)}
                                className="block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-brand-600 hover:file:bg-brand-100 dark:text-gray-400 dark:file:bg-brand-500/15 dark:file:text-brand-400"
                            />
                            {errors.file && <p className="mt-1.5 text-theme-xs text-error-500">{errors.file}</p>}
                        </div>

                        <div className="flex justify-end">
                            <Button type="submit" size="sm" disabled={processing} loading={processing}>
                                Anexar documento
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader title="Anexos enviados" description="Arquivos já anexados a esta solicitação." />
                <CardContent>
                    {documentos.length > 0 ? (
                        <div className="flex flex-col gap-2">
                            {documentos.map((documento) => (
                                <div
                                    key={documento.id}
                                    className="flex items-center justify-between gap-3 rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800"
                                >
                                    <div className="flex min-w-0 items-center gap-3">
                                        <span className="text-gray-400">
                                            <FileIcon className="size-5" />
                                        </span>
                                        <div className="flex min-w-0 flex-col">
                                            <a
                                                href={documento.download_url}
                                                className="truncate text-sm font-medium text-brand-600 underline-offset-2 hover:underline dark:text-brand-400"
                                            >
                                                {documento.original_name}
                                            </a>
                                            <span className="text-theme-xs text-gray-500 dark:text-gray-400">
                                                {documento.requirement_name ? `${documento.requirement_name} · ` : ''}
                                                {documento.mime_type ? mimeLabel(documento.mime_type) : ''}
                                                {documento.size ? ` · ${formatSize(documento.size)}` : ''}
                                            </span>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => remover(documento)}
                                        disabled={removing === documento.id}
                                        aria-label={`Remover ${documento.original_name}`}
                                        title="Remover anexo"
                                        className="flex size-9 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-error-50 hover:text-error-600 disabled:opacity-60 dark:hover:bg-error-500/10 dark:hover:text-error-400"
                                    >
                                        <TrashIcon className="size-4.5" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            Nenhum anexo enviado ainda.
                        </p>
                    )}
                </CardContent>
            </Card>

            <div className="flex justify-end">
                <Button size="sm" onClick={onContinue}>
                    Continuar
                </Button>
            </div>
        </div>
    );
}
