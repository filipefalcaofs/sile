import { Head, Link, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';
import PortalLayout from '@/layouts/portal-layout';

interface TvlItem {
    id: number;
    elegivel: boolean;
    motivo: string | null;
    numero: string | null;
    validade: string | null;
    tipo: string | null;
    empresa: string | null;
    endereco: string | null;
}

interface ConsultaCnpj {
    cnpj: string;
    razao_social: string;
    nome_fantasia: string | null;
    telefone: string | null;
    endereco: string | null;
    viabilidades: { id: number; numero: string | null; elegivel: boolean; motivo: string | null }[];
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (!match) {
        return value;
    }

    return `${match[3]}/${match[2]}/${match[1]}`;
}

function formatCnpj(raw: string): string {
    const value = raw.replace(/\D/g, '').slice(0, 14);
    const parts = [value.slice(0, 2), value.slice(2, 5), value.slice(5, 8), value.slice(8, 12), value.slice(12, 14)];
    let result = parts[0];
    if (parts[1]) {
        result += `.${parts[1]}`;
    }
    if (parts[2]) {
        result += `.${parts[2]}`;
    }
    if (parts[3]) {
        result += `/${parts[3]}`;
    }
    if (parts[4]) {
        result += `-${parts[4]}`;
    }

    return result;
}

function mensagemHttp(data: unknown): string | null {
    if (typeof data === 'string') {
        try {
            data = JSON.parse(data);
        } catch {
            return null;
        }
    }
    if (data && typeof data === 'object' && typeof (data as { message?: unknown }).message === 'string') {
        return (data as { message: string }).message;
    }

    return null;
}

export default function RenovacaoIndex({ tvls }: { tvls: TvlItem[] }) {
    const consulta = useHttp<{ cnpj: string }, ConsultaCnpj>({ cnpj: '' });
    const [cnpj, setCnpj] = useState('');
    const [resultado, setResultado] = useState<ConsultaCnpj | null>(null);
    const [erro, setErro] = useState<string | null>(null);
    const [consultou, setConsultou] = useState(false);

    function buscarCnpj() {
        setConsultou(true);
        setErro(null);
        setResultado(null);
        consulta.setData('cnpj', cnpj.replace(/\D/g, ''));
        consulta.post('/portal/renovacao/consultar-cnpj', {
            onSuccess: (resposta: unknown) => setResultado(resposta as ConsultaCnpj),
            onError: (payload: { message?: string }) => {
                setResultado(null);
                setErro(payload?.message ?? 'Não foi possível consultar o CNPJ na SEDUR.');
            },
            onHttpException: (response) => {
                setResultado(null);
                setErro(mensagemHttp(response.data) ?? 'Não foi possível consultar o CNPJ na SEDUR.');
            },
        });
    }

    return (
        <PortalLayout>
            <Head title="Renovação de Viabilidade" />
            <PageHeader title="Renovação de Viabilidade" breadcrumbs={[{ label: 'Meu painel', href: '/portal/painel' }]} />

            <p className="mb-6 max-w-2xl text-sm text-gray-600 dark:text-gray-400">
                Informe o CNPJ da empresa. O sistema consulta a base da SEDUR e localiza a viabilidade provisória deste portal. Viabilidade do SIGS continua sendo renovada no SIGS.
            </p>

            <Card className="mb-6">
                <CardHeader title="CNPJ da empresa" description="A consulta valida os dados cadastrais na SEDUR." />
                <CardContent className="flex flex-col gap-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div className="w-full sm:max-w-xs">
                            <Label htmlFor="cnpj-renovacao">CNPJ</Label>
                            <Input
                                id="cnpj-renovacao"
                                inputMode="numeric"
                                autoComplete="off"
                                value={cnpj}
                                placeholder="00.000.000/0000-00"
                                maxLength={18}
                                onChange={(event) => setCnpj(formatCnpj(event.target.value))}
                            />
                        </div>
                        <Button className="min-h-11" onClick={buscarCnpj} loading={consulta.processing} disabled={cnpj.trim() === ''}>
                            Consultar
                        </Button>
                    </div>
                    {erro && <Alert variant="error" title="Consulta não concluída" message={erro} />}
                    {resultado && (
                        <dl className="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                            <div>
                                <dt className="text-gray-500">Razão social</dt>
                                <dd className="font-medium text-gray-800 dark:text-white/90">{resultado.razao_social || '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-gray-500">Nome fantasia</dt>
                                <dd className="font-medium text-gray-800 dark:text-white/90">{resultado.nome_fantasia || '—'}</dd>
                            </div>
                            <div className="md:col-span-2">
                                <dt className="text-gray-500">Endereço na SEDUR</dt>
                                <dd className="font-medium text-gray-800 dark:text-white/90">{resultado.endereco || '—'}</dd>
                            </div>
                            {resultado.viabilidades.length === 0 ? (
                                <p className="md:col-span-2 text-gray-600 dark:text-gray-300">
                                    Não há viabilidade provisória deste portal para esse CNPJ.
                                </p>
                            ) : (
                                <ul className="md:col-span-2 flex flex-col gap-2">
                                    {resultado.viabilidades.map((item) => (
                                        <li key={item.id} className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <span>Viabilidade {item.numero}</span>
                                            {item.elegivel ? (
                                                <Link
                                                    href={`/portal/renovacao/${item.id}`}
                                                    className="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white"
                                                >
                                                    Renovar
                                                </Link>
                                            ) : (
                                                <span className="text-gray-600 dark:text-gray-300">{item.motivo}</span>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </dl>
                    )}
                </CardContent>
            </Card>

            {!consultou && tvls.length === 0 ? (
                <Card>
                    <CardContent>
                        <EmptyState
                            title="Nenhuma viabilidade para renovar"
                            description="Quando uma viabilidade provisória emitida por este portal estiver deferida, ela aparece aqui. Viabilidade do SIGS continua sendo renovada no SIGS."
                        />
                    </CardContent>
                </Card>
            ) : !consultou ? (
                <ul className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {tvls.map((tvl) => (
                        <li key={tvl.id}>
                            <Card>
                                <CardContent className="flex h-full flex-col gap-4">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p className="text-sm text-gray-500 dark:text-gray-400">TVL {tvl.numero}</p>
                                            <h2 className="text-base font-semibold text-gray-800 dark:text-white/90">{tvl.empresa}</h2>
                                        </div>
                                        <Badge color={tvl.elegivel ? 'success' : 'warning'} variant="light">
                                            {tvl.tipo ?? 'TVL'}
                                        </Badge>
                                    </div>
                                    <dl className="grid grid-cols-1 gap-2 text-sm">
                                        <div>
                                            <dt className="text-gray-500 dark:text-gray-400">Endereço</dt>
                                            <dd className="text-gray-800 dark:text-white/90">{tvl.endereco ?? '—'}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-gray-500 dark:text-gray-400">Validade</dt>
                                            <dd className="text-gray-800 dark:text-white/90">{formatDate(tvl.validade)}</dd>
                                        </div>
                                    </dl>
                                    {tvl.elegivel ? (
                                        <Link
                                            href={`/portal/renovacao/${tvl.id}`}
                                            className="mt-auto inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-brand-500 px-5 py-3.5 text-sm font-medium text-white transition hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
                                        >
                                            Renovar
                                        </Link>
                                    ) : (
                                        <p className="mt-auto text-sm text-gray-600 dark:text-gray-300">{tvl.motivo}</p>
                                    )}
                                </CardContent>
                            </Card>
                        </li>
                    ))}
                </ul>
            )}
        </PortalLayout>
    );
}
