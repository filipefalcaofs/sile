import { Head } from '@inertiajs/react';
import AccessibilityBar from '@/components/app/accessibility-bar';
import Logo from '@/components/app/logo';
import { CheckCircleIcon, InfoIcon, MoonIcon, SunIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { ThemeProvider, useTheme } from '@/contexts/theme-context';

type BadgeColor = 'primary' | 'success' | 'error' | 'warning' | 'info' | 'light';

interface TimelineStep {
    rotulo: string;
    data: string | null;
}

interface PrazoEstimado {
    dias: number;
    ressalva: string;
}

interface Timeline {
    status_atual: { value: string; public_label: string };
    etapas: TimelineStep[];
    pendencias: string[];
    prazo_estimado: PrazoEstimado | null;
}

interface Solicitacao {
    protocol_number: string | null;
    status: { value: string; public_label: string };
    protocoled_at: string | null;
}

interface ProtocoloPublicoProps {
    solicitacao: Solicitacao;
    timeline: Timeline;
}

function statusColor(value: string): BadgeColor {
    switch (value) {
        case 'protocolada':
            return 'info';
        case 'deferida':
            return 'success';
        case 'indeferida':
        case 'cancelada':
            return 'error';
        case 'em_pendencia':
        case 'aguardando_bap':
            return 'warning';
        default:
            return 'light';
    }
}

/** Formata ISO 8601 como 'DD/MM/YYYY HH:MM' sem depender de fuso do navegador. */
function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/);

    if (!match) {
        return value;
    }

    const [, year, month, day, hour, minute] = match;

    return `${day}/${month}/${year} ${hour}:${minute}`;
}

function ThemeToggleButton() {
    const { toggleTheme } = useTheme();

    return (
        <button
            type="button"
            onClick={toggleTheme}
            aria-label="Alternar tema"
            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
        >
            <SunIcon className="hidden dark:block" />
            <MoonIcon className="dark:hidden" />
        </button>
    );
}

function PublicTimeline({ timeline }: { timeline: Timeline }) {
    return (
        <ol className="flex flex-col">
            {timeline.etapas.length === 0 && (
                <li className="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    Ainda não há etapas registradas para este protocolo.
                </li>
            )}
            {timeline.etapas.map((etapa, index) => {
                const isLast = index === timeline.etapas.length - 1;

                return (
                    <li key={`${etapa.rotulo}-${index}`} className="flex gap-4">
                        <div className="flex flex-col items-center">
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500">
                                <CheckCircleIcon className="size-5 fill-current" />
                            </span>
                            {!isLast && <span className="my-1 w-px grow bg-gray-200 dark:bg-gray-700" aria-hidden="true" />}
                        </div>
                        <div className="pb-6">
                            <p className="text-sm font-medium text-gray-800 dark:text-white/90">{etapa.rotulo}</p>
                            <p className="mt-0.5 text-theme-xs text-gray-500 dark:text-gray-400">
                                {formatDateTime(etapa.data)}
                            </p>
                        </div>
                    </li>
                );
            })}
            <li className="flex gap-4">
                <div className="flex flex-col items-center">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-500 ring-2 ring-brand-200 dark:bg-brand-500/15 dark:text-brand-400 dark:ring-brand-500/30">
                        <span className="size-2.5 rounded-full bg-current" aria-hidden="true" />
                    </span>
                </div>
                <div>
                    <p className="text-theme-xs font-medium text-brand-600 dark:text-brand-400">Situação atual</p>
                    <p className="text-sm font-semibold text-gray-800 dark:text-white/90">
                        {timeline.status_atual.public_label}
                    </p>
                </div>
            </li>
        </ol>
    );
}

export default function ProtocoloPublico({ solicitacao, timeline }: ProtocoloPublicoProps) {
    const titulo = solicitacao.protocol_number ?? 'Acompanhamento de solicitação';

    return (
        <ThemeProvider>
            <Head title={`Acompanhamento ${titulo}`} />
            <div className="flex min-h-screen flex-col bg-gray-50 dark:bg-gray-900">
                <AccessibilityBar />

                <header className="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div className="mx-auto flex w-full max-w-3xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                        <Logo
                            markClassName="size-8"
                            textClassName="text-lg font-semibold tracking-tight text-gray-800 dark:text-white/90"
                            subtitle="Acompanhamento de protocolo"
                        />
                        <ThemeToggleButton />
                    </div>
                </header>

                <main id="conteudo" className="flex-1">
                    <div className="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 sm:py-12">
                        <div className="mb-6 flex flex-col gap-2">
                            <p className="text-theme-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                Protocolo
                            </p>
                            <div className="flex flex-wrap items-center gap-3">
                                <h1 className="text-2xl font-semibold tracking-tight text-gray-800 dark:text-white/90">
                                    {titulo}
                                </h1>
                                <Badge size="sm" color={statusColor(solicitacao.status.value)}>
                                    {solicitacao.status.public_label}
                                </Badge>
                            </div>
                            {solicitacao.protocoled_at && (
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Recebida em {formatDateTime(solicitacao.protocoled_at)}.
                                </p>
                            )}
                        </div>

                        <div className="flex flex-col gap-4 md:gap-6">
                            <Card>
                                <CardHeader
                                    title="Andamento do processo"
                                    description="Acompanhe as etapas concluídas e a situação atual da solicitação."
                                />
                                <CardContent>
                                    <div className="flex flex-col gap-6">
                                        <PublicTimeline timeline={timeline} />

                                        {timeline.prazo_estimado && (
                                            <div className="flex items-start gap-3 rounded-xl border border-blue-light-100 bg-blue-light-50 p-4 dark:border-blue-light-500/20 dark:bg-blue-light-500/10">
                                                <span className="mt-0.5 text-blue-light-500">
                                                    <InfoIcon className="size-5 fill-current" />
                                                </span>
                                                <div>
                                                    <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                                                        Prazo estimado: {timeline.prazo_estimado.dias} dias
                                                    </p>
                                                    <p className="mt-0.5 text-theme-xs text-gray-500 dark:text-gray-400">
                                                        {timeline.prazo_estimado.ressalva}
                                                    </p>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            <p className="text-center text-theme-xs text-gray-400 dark:text-gray-500">
                                Esta página de acompanhamento é pública e mostra apenas a situação do processo. Para ver
                                todos os detalhes e documentos, acesse o portal com a sua conta.
                            </p>
                        </div>
                    </div>
                </main>

                <footer id="rodape" className="border-t border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <p className="mx-auto w-full max-w-3xl px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">
                        © {new Date().getFullYear()} Simplifica Salvador — Sistema de Licenciamento Eletrônico · SEDUR
                    </p>
                </footer>
            </div>
        </ThemeProvider>
    );
}
