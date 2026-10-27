import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import { EyeIcon, TrashIcon } from '@/components/icons';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ConfirmDialog from '@/components/ui/confirm-dialog';

export interface AnexoAnalise {
    id: number;
    original_name: string;
    mime_type: string;
    size: number;
    created_at: string | null;
    enviado_por: string | null;
    download_url: string;
}

function formatarTamanho(bytes: number): string {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024)).toLocaleString('pt-BR')} KB`;
}

/** Documentos que o analista junta à análise do processo; só quem edita a ficha anexa ou remove. */
export default function AnexosAnaliseCard({
    processoId,
    anexos,
    editavel,
}: {
    processoId: number;
    anexos: AnexoAnalise[];
    editavel: boolean;
}) {
    const arquivoRef = useRef<HTMLInputElement>(null);
    const [enviando, setEnviando] = useState(false);
    const [paraRemover, setParaRemover] = useState<AnexoAnalise | null>(null);
    const baseUrl = `/gestao/processos/${processoId}/analise/anexos`;

    const enviar = (event: ChangeEvent<HTMLInputElement>) => {
        const arquivos = Array.from(event.target.files ?? []);

        if (arquivos.length === 0) {
            return;
        }

        setEnviando(true);
        router.post(
            baseUrl,
            { files: arquivos },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setEnviando(false);

                    if (arquivoRef.current) {
                        arquivoRef.current.value = '';
                    }
                },
            },
        );
    };

    const remover = () => {
        if (!paraRemover) {
            return;
        }

        router.delete(`${baseUrl}/${paraRemover.id}`, {
            preserveScroll: true,
            onFinish: () => setParaRemover(null),
        });
    };

    return (
        <>
            <Card>
                <CardHeader
                    title="Documentos da análise"
                    description="Documentação que o analista anexa ao processo (PDF, JPG, PNG ou WebP)."
                    actions={
                        editavel ? (
                            <>
                                <input
                                    ref={arquivoRef}
                                    type="file"
                                    multiple
                                    accept="image/jpeg,image/png,image/webp,application/pdf"
                                    className="hidden"
                                    onChange={enviar}
                                />
                                <Button size="sm" variant="outline" loading={enviando} onClick={() => arquivoRef.current?.click()}>
                                    Anexar arquivo
                                </Button>
                            </>
                        ) : undefined
                    }
                />
                <CardContent>
                    {anexos.length === 0 ? (
                        <p className="text-theme-sm text-gray-500 dark:text-gray-400">Nenhum documento anexado à análise.</p>
                    ) : (
                        <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                            {anexos.map((anexo) => (
                                <li key={anexo.id} className="flex items-center justify-between gap-3 py-2.5">
                                    <div className="min-w-0">
                                        <p className="truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                            {anexo.original_name}
                                        </p>
                                        <p className="text-theme-xs text-gray-400">
                                            {anexo.mime_type.split('/')[1]?.toUpperCase()} · {formatarTamanho(anexo.size)}
                                            {anexo.enviado_por ? ` · ${anexo.enviado_por}` : ''}
                                            {anexo.created_at ? ` · ${new Date(anexo.created_at).toLocaleString('pt-BR')}` : ''}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-1">
                                        <a
                                            href={anexo.download_url}
                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-brand-500 dark:hover:bg-white/[0.05]"
                                            aria-label={`Visualizar ${anexo.original_name}`}
                                            title="Visualizar anexo"
                                        >
                                            <EyeIcon className="size-4 fill-current" />
                                        </a>
                                        {editavel && (
                                            <button
                                                type="button"
                                                onClick={() => setParaRemover(anexo)}
                                                aria-label={`Remover ${anexo.original_name}`}
                                                title="Remover anexo"
                                                className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-error-50 hover:text-error-500 dark:hover:bg-error-500/10 dark:hover:text-error-400"
                                            >
                                                <TrashIcon className="size-4 fill-current" />
                                            </button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>

            <ConfirmDialog
                isOpen={paraRemover !== null}
                onClose={() => setParaRemover(null)}
                onConfirm={remover}
                title="Remover anexo?"
                description={paraRemover ? `O arquivo "${paraRemover.original_name}" será removido da análise.` : ''}
                confirmLabel="Remover"
                variant="danger"
            />
        </>
    );
}
