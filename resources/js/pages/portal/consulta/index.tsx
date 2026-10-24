import { Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { ArrowRightIcon, SearchIcon } from '@/components/icons';
import { statusColor, type Timeline, TimelinePublica } from '@/components/processo/timeline-publica';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import PortalLayout from '@/layouts/portal-layout';
import { formatDateTime } from '@/lib/datas';

interface Processo {
    protocol_number: string | null;
    numero_viabilidade: string | null;
    bap: string | null;
    servico: string | null;
    empresa: { razao_social: string; cnpj: string | null } | null;
    protocolado_em: string | null;
    status: { value: string; public_label: string };
    convite: { aberto: boolean; prazo: string | null };
    pode_responder: boolean;
    solicitacao_id: number | null;
}

interface ConsultaProps {
    numero: string | null;
    encontrado: boolean;
    processo: Processo | null;
    timeline: Timeline | null;
}

function Dado({ rotulo, valor }: { rotulo: string; valor: string | null | undefined }) {
    return (
        <div>
            <dt className="text-theme-xs text-gray-500 dark:text-gray-400">{rotulo}</dt>
            <dd className="text-sm font-medium text-gray-800 dark:text-white/90">{valor || '—'}</dd>
        </div>
    );
}

export default function ConsultaProcesso({ numero, encontrado, processo, timeline }: ConsultaProps) {
    const [termo, setTermo] = useState(numero ?? '');
    const [buscando, setBuscando] = useState(false);

    function consultar(event: FormEvent) {
        event.preventDefault();
        router.get(
            '/portal/consulta',
            { numero: termo.trim() },
            { preserveState: true, onStart: () => setBuscando(true), onFinish: () => setBuscando(false) },
        );
    }

    return (
        <PortalLayout>
            <Head title="Consultar processo" />
            <PageHeader title="Consultar processo" breadcrumbs={[{ label: 'Meu painel', href: '/portal/painel' }]} />

            <p className="mb-6 max-w-2xl text-sm text-gray-600 dark:text-gray-400">
                Veja a situação e a tramitação de um processo de viabilidade. Os processos começam na JUCEB e chegam ao
                Viabiliza pelo REGIN.
            </p>

            <Card className="mb-6">
                <CardContent>
                    <form onSubmit={consultar} className="flex flex-col gap-3 sm:flex-row sm:items-end" role="search">
                        <div className="w-full sm:max-w-md">
                            <Label htmlFor="numero-processo">Número do processo, da viabilidade ou do BAP</Label>
                            <Input
                                id="numero-processo"
                                name="numero"
                                autoComplete="off"
                                value={termo}
                                onChange={(event) => setTermo(event.target.value)}
                                hint="Exemplos: processo 2026.000123 · viabilidade 2104040 · BAP BAP2600012345"
                            />
                        </div>
                        <Button type="submit" className="min-h-11" loading={buscando} disabled={termo.trim() === ''}>
                            <SearchIcon className="size-4" aria-hidden="true" />
                            Consultar
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <div aria-live="polite">
                {numero && !encontrado && (
                    <Alert
                        variant="warning"
                        title="Processo não encontrado"
                        message="Confira o número. A consulta aceita o número do processo, o da viabilidade (TVL) ou o do BAP."
                    />
                )}

                {processo && timeline && (
                    <div className="flex flex-col gap-4 md:gap-6">
                        <Card>
                            <CardContent className="flex flex-col gap-5">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="flex flex-col gap-1">
                                        <p className="text-theme-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                            Processo
                                        </p>
                                        <div className="flex flex-wrap items-center gap-3">
                                            <h2 className="text-xl font-semibold text-gray-800 dark:text-white/90">
                                                {processo.protocol_number}
                                            </h2>
                                            <Badge size="sm" color={statusColor(processo.status.value)}>
                                                {processo.status.public_label}
                                            </Badge>
                                        </div>
                                    </div>
                                    {processo.pode_responder && processo.solicitacao_id !== null && (
                                        <Link
                                            href={`/portal/solicitacoes/${processo.solicitacao_id}/pendencias`}
                                            className="inline-flex min-h-11 items-center gap-1.5 rounded-lg bg-warning-500 px-4 text-theme-sm font-medium text-white transition hover:bg-warning-600"
                                        >
                                            Responder convite
                                            <ArrowRightIcon className="size-4" aria-hidden="true" />
                                        </Link>
                                    )}
                                </div>

                                <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Dado
                                        rotulo="Empresa"
                                        valor={
                                            processo.empresa
                                                ? [processo.empresa.razao_social, processo.empresa.cnpj].filter(Boolean).join(' · ')
                                                : null
                                        }
                                    />
                                    <Dado rotulo="Serviço" valor={processo.servico} />
                                    <Dado rotulo="Viabilidade" valor={processo.numero_viabilidade} />
                                    <Dado rotulo="BAP" valor={processo.bap} />
                                    <Dado rotulo="Protocolado em" valor={processo.protocolado_em ? formatDateTime(processo.protocolado_em) : null} />
                                </dl>

                                {processo.convite.aberto && (
                                    <Alert
                                        variant="warning"
                                        title="Convite aguardando resposta"
                                        message={
                                            processo.convite.prazo
                                                ? `A SEDUR aguarda a resposta do requerente até ${formatDateTime(processo.convite.prazo)}.`
                                                : 'A SEDUR aguarda a resposta do requerente.'
                                        }
                                    />
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader title="Tramitação" description="Etapas concluídas e situação atual do processo." />
                            <CardContent>
                                <TimelinePublica timeline={timeline} />
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </PortalLayout>
    );
}
