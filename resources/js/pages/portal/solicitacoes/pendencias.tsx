import FieldError from '@/components/form/field-error';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useRef, type FormEvent, type ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import { CalendarIcon, CheckCircleIcon, FileIcon, InfoIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import PortalLayout from '@/layouts/portal-layout';
import type { SharedProps } from '@/types';

interface StatusInfo {
    value: string;
    label: string;
    public_label: string;
}

interface Solicitacao {
    id: number;
    protocol_number: string | null;
    status: StatusInfo;
}

interface Pendencia {
    id: number;
    description: string;
    status: string;
    status_label: string;
    due_at: string | null;
    created_at: string | null;
}

interface PendenciasProps {
    solicitacao: Solicitacao;
    pendencias: Pendencia[];
    anexo_max_mb: number;
}

/** Formata ISO 8601 como 'DD/MM/YYYY' sem depender do fuso do navegador. */
function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (!match) {
        return value;
    }

    const [, year, month, day] = match;

    return `${day}/${month}/${year}`;
}

/** Dias inteiros até o prazo (negativo se vencido); null sem prazo. */
function diasAtePrazo(value: string | null): number | null {
    if (!value) {
        return null;
    }

    const prazo = new Date(value).getTime();

    if (Number.isNaN(prazo)) {
        return null;
    }

    return Math.ceil((prazo - Date.now()) / 86_400_000);
}

function PrazoBadge({ dueAt }: { dueAt: string | null }) {
    const dias = diasAtePrazo(dueAt);

    if (dias === null) {
        return null;
    }

    if (dias < 0) {
        return <Badge color="error" size="sm">Prazo encerrado</Badge>;
    }

    const texto = dias === 0 ? 'Vence hoje' : dias === 1 ? 'Falta 1 dia' : `Faltam ${dias} dias`;

    return (
        <Badge color={dias <= 2 ? 'error' : dias <= 5 ? 'warning' : 'info'} size="sm">
            {texto}
        </Badge>
    );
}

function formatarTamanho(bytes: number): string {
    return bytes >= 1024 * 1024 ? `${(bytes / (1024 * 1024)).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/**
 * Formulário de resposta de UMA pendência (cada pendência tem o seu, com estado
 * de submissão isolado). Submete pelo portal e reabre a análise no servidor.
 */
function PendenciaForm({ solicitacaoId, pendencia, anexoMaxMb }: { solicitacaoId: number; pendencia: Pendencia; anexoMaxMb: number }) {
    const { data, setData, post, processing, errors, reset } = useForm<{ response: string; attachment: File | null }>({ response: '', attachment: null });
    const errorId = `pendencia-${pendencia.id}-erro`;
    const entradaArquivo = useRef<HTMLInputElement>(null);
    const limite = 5000;

    function removerArquivo() {
        setData('attachment', null);

        if (entradaArquivo.current) {
            entradaArquivo.current.value = '';
        }
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        post(`/portal/solicitacoes/${solicitacaoId}/pendencias/${pendencia.id}/responder`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                reset('response', 'attachment');

                if (entradaArquivo.current) {
                    entradaArquivo.current.value = '';
                }
            },
        });
    }

    return (
        <Card>
            <CardHeader
                title="Convite aberto"
                description={`Solicitado em ${formatDate(pendencia.created_at)}`}
                actions={
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="inline-flex items-center gap-1.5 text-theme-xs text-gray-500 dark:text-gray-400">
                            <CalendarIcon className="size-4" aria-hidden="true" />
                            Responder até {formatDate(pendencia.due_at)}
                        </span>
                        <PrazoBadge dueAt={pendencia.due_at} />
                    </div>
                }
            />
            <CardContent>
                <div className="flex flex-col gap-5">
                    <div className="flex items-start gap-3 rounded-xl border border-blue-light-100 bg-blue-light-50 p-4 dark:border-blue-light-500/20 dark:bg-blue-light-500/10">
                        <span className="mt-0.5 text-blue-light-500">
                            <InfoIcon className="size-5 fill-current" />
                        </span>
                        <p className="text-sm text-gray-700 dark:text-gray-300">{pendencia.description}</p>
                    </div>

                    <form onSubmit={submit} className="flex flex-col gap-3">
                        <label
                            htmlFor={`pendencia-${pendencia.id}-resposta`}
                            className="text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Sua resposta
                        </label>
                        <textarea
                            id={`pendencia-${pendencia.id}-resposta`}
                            value={data.response}
                            onChange={(event) => setData('response', event.target.value)}
                            rows={6}
                            maxLength={limite}
                            required
                            aria-invalid={errors.response ? true : undefined}
                            aria-describedby={errors.response ? errorId : undefined}
                            placeholder="Descreva a complementação ou informe o documento solicitado."
                            className="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        />
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                {errors.response && (
                                    <FieldError id={errorId}>
                                        {errors.response}
                                    </FieldError>
                                )}
                            </div>
                            <span className="shrink-0 text-theme-xs text-gray-500 dark:text-gray-400" aria-live="off">
                                {data.response.length}/{limite}
                            </span>
                        </div>
                        <div className="flex flex-col gap-2">
                            <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Anexar documento <span className="font-normal text-gray-500">(opcional)</span>
                            </span>
                            {data.attachment === null ? (
                                <label
                                    htmlFor={`pendencia-${pendencia.id}-anexo`}
                                    className="flex min-h-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-gray-300 px-4 py-5 text-center transition-colors hover:border-brand-400 hover:bg-brand-50/40 focus-within:border-brand-500 focus-within:ring-3 focus-within:ring-brand-500/10 dark:border-gray-700 dark:hover:bg-brand-500/5"
                                >
                                    <span className="text-gray-400">
                                        <FileIcon className="size-7" aria-hidden="true" />
                                    </span>
                                    <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Escolher arquivo
                                    </span>
                                    <span id={`${errorId}-ajuda`} className="text-theme-xs text-gray-500 dark:text-gray-400">
                                        PDF, JPG ou PNG, até {anexoMaxMb} MB.
                                    </span>
                                    <input
                                        id={`pendencia-${pendencia.id}-anexo`}
                                        ref={entradaArquivo}
                                        type="file"
                                        accept="application/pdf,image/jpeg,image/png"
                                        onChange={(event) => setData('attachment', event.target.files?.[0] ?? null)}
                                        aria-invalid={errors.attachment ? true : undefined}
                                        aria-describedby={errors.attachment ? `${errorId}-anexo` : `${errorId}-ajuda`}
                                        className="sr-only"
                                    />
                                </label>
                            ) : (
                                <div className="flex items-center justify-between gap-3 rounded-xl border border-success-200 bg-success-50 p-3 dark:border-success-500/30 dark:bg-success-500/10">
                                    <div className="flex min-w-0 items-center gap-3">
                                        <span className="text-success-600 dark:text-success-500">
                                            <CheckCircleIcon className="size-6 fill-current" aria-hidden="true" />
                                        </span>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium text-gray-800 dark:text-white/90">{data.attachment.name}</p>
                                            <p className="text-theme-xs text-gray-500 dark:text-gray-400">{formatarTamanho(data.attachment.size)}</p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={removerArquivo}
                                        className="min-h-11 shrink-0 rounded-lg px-3 text-sm font-medium text-error-600 hover:bg-error-50 focus-visible:outline-2 focus-visible:outline-error-500 dark:text-error-500 dark:hover:bg-error-500/10"
                                    >
                                        Remover<span className="sr-only"> arquivo</span>
                                    </button>
                                </div>
                            )}
                            {errors.attachment && <FieldError id={`${errorId}-anexo`}>{errors.attachment}</FieldError>}
                        </div>
                        <div className="flex justify-end">
                            <Button type="submit" loading={processing}>
                                Enviar resposta
                            </Button>
                        </div>
                    </form>
                </div>
            </CardContent>
        </Card>
    );
}

export default function Pendencias({ solicitacao, pendencias, anexo_max_mb: anexoMaxMb }: PendenciasProps) {
    const { flash } = usePage<SharedProps>().props;
    const titulo = solicitacao.protocol_number ?? 'Solicitação de viabilidade';

    return (
        <>
            <Head title={`Convites ${titulo}`} />
            <PageHeader
                title="Responder convite"
                breadcrumbs={[
                    { label: 'Meu painel', href: '/portal/painel' },
                    { label: titulo, href: `/portal/solicitacoes/${solicitacao.id}` },
                ]}
                actions={
                    <Badge size="sm" color="warning">
                        {solicitacao.status.public_label}
                    </Badge>
                }
            />

            <div className="flex flex-col gap-4 md:gap-6">
                {flash.error && <Alert variant="error" title="Não foi possível concluir" message={flash.error} />}

                {pendencias.length === 0 ? (
                    <Card>
                        <CardContent>
                            <div className="flex flex-col items-center gap-3 py-6 text-center">
                                <span className="flex size-12 items-center justify-center rounded-full bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500">
                                    <CheckCircleIcon className="size-6 fill-current" />
                                </span>
                                <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                                    Nenhum convite aberto nesta solicitação.
                                </p>
                                <p className="text-theme-xs text-gray-500 dark:text-gray-400">
                                    Quando a análise técnica solicitar uma complementação, ela aparecerá aqui para você
                                    responder.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    pendencias.map((pendencia) => (
                        <PendenciaForm key={pendencia.id} solicitacaoId={solicitacao.id} pendencia={pendencia} anexoMaxMb={anexoMaxMb} />
                    ))
                )}
            </div>
        </>
    );
}

Pendencias.layout = (page: ReactNode) => <PortalLayout>{page}</PortalLayout>;
