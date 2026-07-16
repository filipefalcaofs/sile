import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import { CheckCircleIcon, InfoIcon } from '@/components/icons';
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

/**
 * Formulário de resposta de UMA pendência (cada pendência tem o seu, com estado
 * de submissão isolado). Submete pelo portal e reabre a análise no servidor.
 */
function PendenciaForm({ solicitacaoId, pendencia }: { solicitacaoId: number; pendencia: Pendencia }) {
    const { data, setData, post, processing, errors, reset } = useForm({ response: '' });
    const errorId = `pendencia-${pendencia.id}-erro`;

    function submit(event: FormEvent) {
        event.preventDefault();
        post(`/portal/solicitacoes/${solicitacaoId}/pendencias/${pendencia.id}/responder`, {
            preserveScroll: true,
            onSuccess: () => reset('response'),
        });
    }

    return (
        <Card>
            <CardHeader
                title="Convite aberto"
                description={`Solicitada em ${formatDate(pendencia.created_at)} · prazo de resposta até ${formatDate(pendencia.due_at)}.`}
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
                            rows={5}
                            required
                            aria-invalid={errors.response ? true : undefined}
                            aria-describedby={errors.response ? errorId : undefined}
                            placeholder="Descreva a complementação ou informe o documento solicitado."
                            className="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        />
                        {errors.response && (
                            <p id={errorId} role="alert" className="text-theme-xs text-error-500">
                                {errors.response}
                            </p>
                        )}
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

export default function Pendencias({ solicitacao, pendencias }: PendenciasProps) {
    const { flash } = usePage<SharedProps>().props;
    const titulo = solicitacao.protocol_number ?? 'Solicitação de viabilidade';

    return (
        <>
            <Head title={`Convites ${titulo}`} />
            <PageHeader
                title="Responder convite"
                breadcrumbs={[
                    { label: 'Meu painel', href: '/portal/painel' },
                    { label: 'Minhas solicitações', href: '/portal/solicitacoes' },
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
                        <PendenciaForm key={pendencia.id} solicitacaoId={solicitacao.id} pendencia={pendencia} />
                    ))
                )}
            </div>
        </>
    );
}

Pendencias.layout = (page: ReactNode) => <PortalLayout>{page}</PortalLayout>;
