import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import GestaoLayout from '@/layouts/gestao-layout';

interface ChamadaRow {
    id: number;
    created_at: string | null;
    direcao: string;
    direcao_rotulo: string;
    integracao: string;
    integracao_rotulo: string;
    method: string;
    url: string;
    http_status: number | null;
    resultado: string;
    duracao_ms: number | null;
    erro: string | null;
}

interface IntegracaoOpcao {
    value: string;
    label: string;
}

interface IntegracoesProps {
    chamadas: {
        data: ChamadaRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filtros: {
        direcao: string | null;
        resultado: string | null;
        integracao: string | null;
    };
    integracoes: IntegracaoOpcao[];
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

function ResultadoBadge({ resultado }: { resultado: string }) {
    const sucesso = resultado === 'sucesso';

    return (
        <Badge size="sm" color={sucesso ? 'success' : 'error'}>
            {sucesso ? 'Sucesso' : 'Erro'}
        </Badge>
    );
}

const columns: ColumnDef<ChamadaRow>[] = [
    {
        id: 'created_at',
        header: 'Quando',
        cellClassName: 'whitespace-nowrap text-gray-800 dark:text-white/90',
        cell: (chamada) => formatarDataHora(chamada.created_at),
    },
    {
        id: 'direcao',
        header: 'Sentido',
        cell: (chamada) => chamada.direcao_rotulo,
    },
    {
        id: 'integracao',
        header: 'Integração',
        cell: (chamada) => chamada.integracao_rotulo,
    },
    {
        id: 'method',
        header: 'Método',
        cell: (chamada) => (
            <span className="font-mono text-xs">
                {chamada.method}
                {chamada.http_status !== null ? ` ${chamada.http_status}` : ''}
            </span>
        ),
    },
    {
        id: 'url',
        header: 'URL',
        cellClassName: 'max-w-xs truncate text-gray-600 dark:text-gray-400',
        cell: (chamada) => (
            <span className="font-mono text-xs" title={chamada.url}>
                {chamada.url}
            </span>
        ),
    },
    {
        id: 'resultado',
        header: 'Resultado',
        cell: (chamada) => <ResultadoBadge resultado={chamada.resultado} />,
    },
    {
        id: 'duracao',
        header: 'Duração',
        cell: (chamada) => (chamada.duracao_ms === null ? '—' : `${chamada.duracao_ms} ms`),
    },
    {
        id: 'ver',
        header: 'Detalhe',
        cell: (chamada) => (
            <Link
                href={`/gestao/auditoria/integracoes/${chamada.id}`}
                className="text-sm font-medium text-brand-500 hover:underline"
            >
                Ver pedido
            </Link>
        ),
    },
];

const DIRECOES = [
    { label: 'Todos os sentidos', value: null },
    { label: 'Entrada', value: 'entrada' },
    { label: 'Saída', value: 'saida' },
];

const RESULTADOS = [
    { label: 'Todos os resultados', value: null },
    { label: 'Sucesso', value: 'sucesso' },
    { label: 'Erro', value: 'erro' },
];

export default function IntegracoesIndex({ chamadas, filtros, integracoes }: IntegracoesProps) {
    function aplicar(proximo: Partial<IntegracoesProps['filtros']>) {
        const params = new URLSearchParams();
        const direcao = 'direcao' in proximo ? proximo.direcao : filtros.direcao;
        const resultado = 'resultado' in proximo ? proximo.resultado : filtros.resultado;
        const integracao = 'integracao' in proximo ? proximo.integracao : filtros.integracao;

        if (direcao) {
            params.set('direcao', direcao);
        }

        if (resultado) {
            params.set('resultado', resultado);
        }

        if (integracao) {
            params.set('integracao', integracao);
        }

        const consulta = params.toString();

        router.get(`/gestao/auditoria/integracoes${consulta ? `?${consulta}` : ''}`, {}, {
            preserveScroll: true,
            replace: true,
        });
    }

    return (
        <>
            <Head title="Log de integrações" />
            <PageHeader
                title="Log de integrações"
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Trilha de auditoria', href: '/gestao/auditoria' },
                ]}
            />

            <Card>
                <CardHeader
                    title="Chamadas"
                    description="Cada chamada HTTP de fora para a nossa API e cada chamada nossa para um serviço externo. Senha, token e JWT ficam gravados como [redigido]. A entrada é a JUCEB consumindo a API municipal. A saída é o Viabiliza consumindo REGIN, GeoServer, SEDUR, CNPJ, gov.br, geocodificação e IA."
                />
                <CardContent>
                    <div className="space-y-5">
                        <div className="flex flex-wrap gap-2">
                            {DIRECOES.map((opcao) => (
                                <Filtro
                                    key={opcao.label}
                                    ativo={filtros.direcao === opcao.value}
                                    onClick={() => aplicar({ direcao: opcao.value })}
                                >
                                    {opcao.label}
                                </Filtro>
                            ))}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {RESULTADOS.map((opcao) => (
                                <Filtro
                                    key={opcao.label}
                                    ativo={filtros.resultado === opcao.value}
                                    onClick={() => aplicar({ resultado: opcao.value })}
                                >
                                    {opcao.label}
                                </Filtro>
                            ))}
                            {integracoes.map((opcao) => (
                                <Filtro
                                    key={opcao.value}
                                    ativo={filtros.integracao === opcao.value}
                                    onClick={() =>
                                        aplicar({
                                            integracao: filtros.integracao === opcao.value ? null : opcao.value,
                                        })
                                    }
                                >
                                    {opcao.label}
                                </Filtro>
                            ))}
                        </div>

                        <DataTable
                            columns={columns}
                            rows={chamadas.data}
                            rowKey={(chamada) => chamada.id}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title="Nenhuma chamada registrada"
                                    description="As chamadas aparecem aqui quando um serviço externo fala com o Viabiliza ou quando o Viabiliza chama um serviço externo."
                                />
                            }
                        />

                        <Pagination
                            links={chamadas.links}
                            meta={{ from: chamadas.from, to: chamadas.to, total: chamadas.total }}
                        />
                    </div>
                </CardContent>
            </Card>
        </>
    );
}

function Filtro({
    ativo,
    onClick,
    children,
}: {
    ativo: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                ativo
                    ? 'bg-brand-500 text-white'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
            }`}
        >
            {children}
        </button>
    );
}

IntegracoesIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
