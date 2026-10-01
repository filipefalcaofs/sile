import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import GestaoLayout from '@/layouts/gestao-layout';

interface ChamadaDetalhe {
    id: number;
    created_at: string | null;
    direcao_rotulo: string;
    integracao_rotulo: string;
    method: string;
    url: string;
    http_status: number | null;
    resultado: string;
    duracao_ms: number | null;
    erro: string | null;
    truncado: boolean;
    correlacao: string | null;
    ip: string | null;
    agente: string | null;
    cabecalhos_pedido: Record<string, string> | null;
    corpo_pedido: string | null;
    cabecalhos_resposta: Record<string, string> | null;
    corpo_resposta: string | null;
}

function formatarDataHora(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const data = new Date(iso);

    if (Number.isNaN(data.getTime())) {
        return iso;
    }

    return data.toLocaleString('pt-BR');
}

function formatarCorpo(corpo: string | null): string {
    if (!corpo) {
        return 'Sem corpo';
    }

    try {
        return JSON.stringify(JSON.parse(corpo), null, 2);
    } catch {
        return corpo;
    }
}

function Bloco({ titulo, texto }: { titulo: string; texto: string }) {
    return (
        <div className="space-y-2">
            <h3 className="text-sm font-medium text-gray-800 dark:text-white/90">{titulo}</h3>
            <pre className="max-h-96 overflow-auto rounded-lg bg-gray-950 p-4 font-mono text-xs whitespace-pre-wrap break-all text-gray-100">
                {texto}
            </pre>
        </div>
    );
}

export default function IntegracaoShow({ chamada }: { chamada: ChamadaDetalhe }) {
    const sucesso = chamada.resultado === 'sucesso';

    return (
        <>
            <Head title={`Integração ${chamada.id}`} />
            <PageHeader
                title={`${chamada.integracao_rotulo} · ${chamada.method}`}
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Log de integrações', href: '/gestao/auditoria/integracoes' },
                ]}
            />

            <Card>
                <CardHeader
                    title="Chamada"
                    description={chamada.url}
                    actions={
                        <Link
                            href="/gestao/auditoria/integracoes"
                            className="text-sm font-medium text-brand-500 hover:underline"
                        >
                            Voltar à lista
                        </Link>
                    }
                />
                <CardContent>
                    <dl className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Item termo="Quando" valor={formatarDataHora(chamada.created_at)} />
                        <Item termo="Sentido" valor={chamada.direcao_rotulo} />
                        <Item termo="HTTP" valor={chamada.http_status === null ? 'sem resposta' : String(chamada.http_status)} />
                        <Item
                            termo="Resultado"
                            valor={
                                <Badge size="sm" color={sucesso ? 'success' : 'error'}>
                                    {sucesso ? 'Sucesso' : 'Erro'}
                                </Badge>
                            }
                        />
                        <Item termo="Duração" valor={chamada.duracao_ms === null ? '—' : `${chamada.duracao_ms} ms`} />
                        <Item termo="IP" valor={chamada.ip ?? '—'} />
                        <Item termo="Correlação" valor={chamada.correlacao ?? '—'} />
                    </dl>

                    {chamada.erro && (
                        <p className="mb-6 text-sm text-error-600 dark:text-error-400">{chamada.erro}</p>
                    )}

                    {chamada.truncado && (
                        <p className="mb-6 text-sm text-gray-600 dark:text-gray-400">
                            O corpo passou do limite técnico e foi cortado. O início gravado é o que chegou.
                        </p>
                    )}

                    <div className="space-y-6">
                        <Bloco
                            titulo="Cabeçalhos do pedido"
                            texto={
                                chamada.cabecalhos_pedido
                                    ? JSON.stringify(chamada.cabecalhos_pedido, null, 2)
                                    : 'Sem cabeçalhos'
                            }
                        />
                        <Bloco titulo="Corpo do pedido" texto={formatarCorpo(chamada.corpo_pedido)} />
                        <Bloco
                            titulo="Cabeçalhos da resposta"
                            texto={
                                chamada.cabecalhos_resposta
                                    ? JSON.stringify(chamada.cabecalhos_resposta, null, 2)
                                    : 'Sem cabeçalhos'
                            }
                        />
                        <Bloco titulo="Corpo da resposta" texto={formatarCorpo(chamada.corpo_resposta)} />
                    </div>
                </CardContent>
            </Card>
        </>
    );
}

function Item({ termo, valor }: { termo: string; valor: ReactNode }) {
    return (
        <div>
            <dt className="text-xs text-gray-500 dark:text-gray-400">{termo}</dt>
            <dd className="mt-1 text-sm text-gray-800 dark:text-white/90">{valor}</dd>
        </div>
    );
}

IntegracaoShow.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
