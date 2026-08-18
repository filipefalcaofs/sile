import { Head, useForm, useHttp, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { SearchIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface ProcessoPreview {
    id: number;
    protocolo: string | null;
    servico: string | null;
    status: string;
    requerente: string | null;
    ja_em_analise: boolean;
    situacao_analise: string | null;
}

interface EnviarParaAnaliseProps {
    featureEnabled: boolean;
    mensagens: {
        naoEncontrado: string;
        confirmacao: string;
    };
}

interface PesquisaResposta {
    encontrado: boolean;
    processo?: ProcessoPreview;
    mensagem?: string;
}

/**
 * Tela T06 — Enviar processo de TVL para análise (relatório de teste SEDUR).
 * Pesquisa o processo por protocolo; não encontrado → Alert do design system
 * (contraste AA) + Fechar; encontrado → cartão de preview (protocolo, serviço,
 * status atual, requerente — PII reduzida) + confirmação em modal acessível
 * antes de enviar. QUALQUER status pode ser enviado (OPEN-F-2); o envio é
 * idempotente (aviso "já está em análise" sem duplicar). Respeita o toggle
 * features.enviar_tvl_analise (desligada, degrada de forma comunicada).
 */
export default function EnviarParaAnalise({ featureEnabled, mensagens }: EnviarParaAnaliseProps) {
    const { flash } = usePage<SharedProps>().props;

    const [protocolo, setProtocolo] = useState('');
    const [processo, setProcesso] = useState<ProcessoPreview | null>(null);
    const [naoEncontrado, setNaoEncontrado] = useState(false);
    const [erroBusca, setErroBusca] = useState<string | null>(null);
    const [confirmOpen, setConfirmOpen] = useState(false);

    const busca = useHttp<{ protocolo: string }, PesquisaResposta>({ protocolo: '' });
    const envio = useForm<{ protocolo: string }>({ protocolo: '' });

    const podePesquisar = featureEnabled && protocolo.trim().length > 0 && !busca.processing;

    function limpar() {
        setProcesso(null);
        setNaoEncontrado(false);
        setErroBusca(null);
    }

    function pesquisar(event: React.FormEvent) {
        event.preventDefault();

        if (!podePesquisar) {
            return;
        }

        limpar();
        busca.transform(() => ({ protocolo: protocolo.trim() }));
        busca.post('/gestao/processos/enviar-para-analise/pesquisar', {
            onSuccess: (response) => {
                if (response.encontrado && response.processo) {
                    setProcesso(response.processo);
                    setNaoEncontrado(false);
                } else {
                    setProcesso(null);
                    setNaoEncontrado(true);
                }
            },
            onHttpException: (response) => {
                setProcesso(null);
                // Não encontrado é resposta de domínio (404) — mensagem parametrizada.
                if (response.status === 404) {
                    setNaoEncontrado(true);
                } else {
                    setErroBusca('Não foi possível concluir a pesquisa. Tente novamente em instantes.');
                }

                return false;
            },
        });
    }

    function confirmarEnvio() {
        if (!processo) {
            return;
        }

        envio.transform(() => ({ protocolo: processo.protocolo ?? '' }));
        envio.post('/gestao/processos/enviar-para-analise/enviar', {
            preserveScroll: true,
            onSuccess: () => {
                setConfirmOpen(false);
                setProtocolo('');
                limpar();
            },
            onFinish: () => {
                setConfirmOpen(false);
            },
        });
    }

    return (
        <>
            <Head title="Enviar processo para análise" />
            <PageHeader
                title="Enviar processo para análise"
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Processos', href: '/gestao/processos' },
                ]}
            />

            {flash.status && (
                <div className="mb-6">
                    <Alert variant="success" title="Pronto" message={flash.status} />
                </div>
            )}

            {flash.error && (
                <div className="mb-6">
                    <Alert variant="error" title="Não foi possível concluir" message={flash.error} />
                </div>
            )}

            {!featureEnabled ? (
                <Alert
                    variant="warning"
                    title="Envio para análise desativado"
                    message="A tela de envio manual de processos para a análise técnica está desativada nos parâmetros do sistema. Ative o parâmetro correspondente para usá-la."
                />
            ) : (
                <div className="flex flex-col gap-4 md:gap-6">
                    <Card>
                        <CardHeader
                            title="Localizar o processo"
                            description="Informe o número do protocolo do processo de TVL. Qualquer situação pode ser enviada para a análise técnica."
                        />
                        <CardContent>
                            <form onSubmit={pesquisar} className="flex flex-col gap-4 sm:flex-row sm:items-end">
                                <div className="flex-1">
                                    <Label htmlFor="protocolo">Número do protocolo</Label>
                                    <div className="relative">
                                        <span className="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-gray-400 dark:text-gray-500">
                                            <SearchIcon className="size-5" />
                                        </span>
                                        <input
                                            id="protocolo"
                                            type="search"
                                            name="protocolo"
                                            value={protocolo}
                                            onChange={(event) => setProtocolo(event.target.value)}
                                            placeholder="Ex.: VIA-2026-000123"
                                            autoComplete="off"
                                            className="h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                                        />
                                    </div>
                                </div>
                                <Button type="submit" disabled={!podePesquisar} loading={busca.processing}>
                                    Pesquisar
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    {erroBusca && (
                        <Alert variant="error" title="Pesquisa indisponível" message={erroBusca} />
                    )}

                    {naoEncontrado && (
                        <div className="flex flex-col gap-3">
                            <Alert variant="info" title="Processo não encontrado" message={mensagens.naoEncontrado} />
                            <div>
                                <Button variant="outline" onClick={limpar}>
                                    Fechar
                                </Button>
                            </div>
                        </div>
                    )}

                    {processo && (
                        <Card>
                            <CardHeader
                                title="Confira o processo"
                                description="Revise os dados antes de enviar para a análise técnica."
                            />
                            <CardContent>
                                <dl className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <dt className="text-theme-xs text-gray-500 dark:text-gray-400">Protocolo</dt>
                                        <dd className="text-sm font-medium text-gray-800 dark:text-white/90">
                                            {processo.protocolo ?? '—'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-theme-xs text-gray-500 dark:text-gray-400">Serviço</dt>
                                        <dd className="text-sm font-medium text-gray-800 dark:text-white/90">
                                            {processo.servico ?? '—'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-theme-xs text-gray-500 dark:text-gray-400">Situação atual</dt>
                                        <dd className="text-sm font-medium text-gray-800 dark:text-white/90">
                                            {processo.status}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-theme-xs text-gray-500 dark:text-gray-400">Requerente</dt>
                                        <dd className="text-sm font-medium text-gray-800 dark:text-white/90">
                                            {processo.requerente ?? '—'}
                                        </dd>
                                    </div>
                                </dl>

                                {processo.ja_em_analise && (
                                    <div className="mt-5">
                                        <Alert
                                            variant="warning"
                                            title="Este processo já está em análise"
                                            message={`Situação da análise: ${processo.situacao_analise ?? 'em análise'}. Reenviar não duplica a tramitação.`}
                                        />
                                    </div>
                                )}

                                <div className="mt-6 flex items-center justify-end gap-3">
                                    <Button variant="outline" onClick={limpar} disabled={envio.processing}>
                                        Cancelar
                                    </Button>
                                    <Button onClick={() => setConfirmOpen(true)} disabled={envio.processing}>
                                        Enviar para análise
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            )}

            <ConfirmDialog
                isOpen={confirmOpen}
                onClose={() => setConfirmOpen(false)}
                onConfirm={confirmarEnvio}
                title="Enviar para análise"
                description={
                    <span>
                        {mensagens.confirmacao}
                        {processo?.protocolo ? (
                            <>
                                {' '}
                                <span className="font-medium text-gray-700 dark:text-gray-300">
                                    Protocolo {processo.protocolo}.
                                </span>
                            </>
                        ) : null}
                    </span>
                }
                confirmLabel="Confirmar envio"
                cancelLabel="Cancelar"
                variant="info"
                processing={envio.processing}
            />
        </>
    );
}

EnviarParaAnalise.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
