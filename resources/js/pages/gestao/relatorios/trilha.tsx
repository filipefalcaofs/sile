import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { FileIcon, SearchIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';
import GestaoLayout from '@/layouts/gestao-layout';

/**
 * Trilha de auditoria por processo (prestação de contas): busca por número de
 * protocolo e consolida as fontes reais de histórico (transições dos dois
 * eixos + auditoria + decisão) ordenadas por data. Protocolo inexistente →
 * estado honesto de "não encontrado" — nunca uma trilha inventada. A impressão
 * em PDF é servida pelo backend (DomPDF) e auditada.
 */
interface TrilhaEvento {
    data: string | null;
    eixo: 'status' | 'analise' | 'auditoria' | 'decisao';
    eixo_label: string;
    descricao: string;
    usuario: string | null;
}

interface Trilha {
    processo: {
        id: number;
        protocolo: string | null;
        status: string;
        status_label: string;
        empresa: string | null;
        protocolado_em: string | null;
    };
    eventos: TrilhaEvento[];
}

interface TrilhaProps {
    trilha: Trilha | null;
    protocolo: string | null;
}

const dateTimeFormat = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

/** Formata a data/hora ISO8601 no padrão brasileiro; null/inválida → travessão. */
function formatarDataHora(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const data = new Date(iso);

    return Number.isNaN(data.getTime()) ? '—' : dateTimeFormat.format(data);
}

/** Cor do badge por eixo da trilha. */
function eixoColor(eixo: TrilhaEvento['eixo']): 'primary' | 'info' | 'light' | 'success' {
    switch (eixo) {
        case 'status':
            return 'primary';
        case 'analise':
            return 'info';
        case 'decisao':
            return 'success';
        default:
            return 'light';
    }
}

export default function TrilhaProcesso({ trilha, protocolo }: TrilhaProps) {
    const [termo, setTermo] = useState(protocolo ?? '');

    function pesquisar(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const limpo = termo.trim();

        if (limpo === '') {
            return;
        }

        router.get('/gestao/relatorios/trilha', { protocolo: limpo }, { preserveState: true, preserveScroll: true });
    }

    return (
        <>
            <Head title="Trilha por processo" />
            <PageHeader
                title="Trilha de auditoria por processo"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }, { label: 'Relatórios' }, { label: 'Trilha por processo' }]}
            />

            <div className="space-y-4 md:space-y-6">
                <Card>
                    <CardHeader
                        title="Buscar processo"
                        description="Informe o número de protocolo para consolidar toda a trilha registrada: mudanças de situação, análise técnica, auditoria e decisão."
                    />
                    <CardContent>
                        <form onSubmit={pesquisar}>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="filtro-protocolo">Número do protocolo</Label>
                                    <Input
                                        id="filtro-protocolo"
                                        type="text"
                                        value={termo}
                                        onChange={(e) => setTermo(e.target.value)}
                                        placeholder="Ex.: VIA-2026-000123"
                                    />
                                </div>
                            </div>

                            <div className="mt-4 flex flex-wrap items-center gap-3">
                                <Button type="submit" size="sm" startIcon={<SearchIcon className="size-5" />}>
                                    Pesquisar
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {trilha === null && protocolo !== null && (
                    <Card>
                        <CardContent>
                            <EmptyState
                                title="Processo não encontrado"
                                description={`Nenhum processo registrado para o protocolo ${protocolo}. Confira o número e tente novamente.`}
                            />
                        </CardContent>
                    </Card>
                )}

                {trilha !== null && (
                    <Card>
                        <CardHeader
                            title={`Processo ${trilha.processo.protocolo ?? ''}`}
                            description={`Situação atual: ${trilha.processo.status_label}${trilha.processo.empresa ? ` · ${trilha.processo.empresa}` : ''}`}
                            actions={
                                <a
                                    href={`/gestao/relatorios/trilha/imprimir?protocolo=${encodeURIComponent(trilha.processo.protocolo ?? '')}`}
                                    className="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-4 py-3 text-sm text-gray-700 shadow-theme-xs ring-1 ring-gray-300 ring-inset transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
                                >
                                    <FileIcon className="size-5" />
                                    Imprimir PDF
                                </a>
                            }
                        />
                        <CardContent>
                            {trilha.eventos.length === 0 ? (
                                <EmptyState
                                    title="Sem eventos registrados"
                                    description="O processo existe, mas ainda não há eventos de histórico registrados."
                                />
                            ) : (
                                <ol className="flex flex-col">
                                    {trilha.eventos.map((evento, indice) => (
                                        <li
                                            key={`${evento.data}-${indice}`}
                                            className="flex flex-col gap-1 border-b border-gray-100 py-3 last:border-b-0 dark:border-white/[0.05] sm:flex-row sm:items-center sm:gap-4"
                                        >
                                            <span className="w-32 shrink-0 text-theme-xs whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                {formatarDataHora(evento.data)}
                                            </span>
                                            <span className="shrink-0">
                                                <Badge variant="light" color={eixoColor(evento.eixo)} size="sm">
                                                    {evento.eixo_label}
                                                </Badge>
                                            </span>
                                            <span className="flex-1 text-theme-sm text-gray-800 dark:text-white/90">
                                                {evento.descricao}
                                            </span>
                                            <span className="shrink-0 text-theme-xs text-gray-500 dark:text-gray-400">
                                                {evento.usuario ?? '—'}
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

TrilhaProcesso.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
