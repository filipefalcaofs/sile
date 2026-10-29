import { Head, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { SearchIcon } from '@/components/icons';
import ItemRenovacaoCard, { type ItemRenovacao } from '@/components/renovacao/item-renovacao';
import Alert from '@/components/ui/alert';
import Button from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';
import PortalLayout from '@/layouts/portal-layout';

interface RenovacaoProps {
    busca: string | null;
    itens: ItemRenovacao[];
    indisponivel: boolean;
    termo_invalido: boolean;
}

export default function RenovacaoIndex({ busca, itens, indisponivel, termo_invalido: termoInvalido }: RenovacaoProps) {
    const [termo, setTermo] = useState(busca ?? '');
    const [buscando, setBuscando] = useState(false);

    function buscar(event: FormEvent) {
        event.preventDefault();
        router.get(
            '/portal/renovacao',
            { busca: termo.trim() },
            { preserveState: true, onStart: () => setBuscando(true), onFinish: () => setBuscando(false) },
        );
    }

    return (
        <PortalLayout>
            <Head title="Renovação de Viabilidade" />
            <PageHeader title="Renovação de Viabilidade" breadcrumbs={[{ label: 'Meu painel', href: '/portal/painel' }]} />

            <p className="mb-6 max-w-2xl text-sm text-gray-600 dark:text-gray-400">
                Informe o CNPJ da empresa ou o número da viabilidade provisória. A busca procura nas viabilidades do
                Viabiliza e nas do sistema anterior (Simplifica). Viabilidade do SIGS continua sendo renovada no SIGS.
            </p>

            <Card className="mb-6">
                <CardContent>
                    <form onSubmit={buscar} role="search">
                        <Label htmlFor="busca-renovacao">CNPJ ou número da viabilidade</Label>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start">
                            <div className="w-full sm:max-w-md">
                                <Input
                                    id="busca-renovacao"
                                    name="busca"
                                    autoComplete="off"
                                    value={termo}
                                    onChange={(event) => setTermo(event.target.value)}
                                    aria-describedby="busca-renovacao-dica"
                                />
                            </div>
                            <Button type="submit" className="min-h-11" loading={buscando} disabled={termo.trim() === ''}>
                                <SearchIcon className="size-4" aria-hidden="true" />
                                Buscar
                            </Button>
                        </div>
                        <p id="busca-renovacao-dica" className="max-w-2xl pt-1.5 text-xs text-gray-500">
                            Exemplos: CNPJ 11.222.333/0001-81 · viabilidade 2104040
                        </p>
                    </form>
                </CardContent>
            </Card>

            <div className="mb-6 flex flex-col gap-3" aria-live="polite">
                {termoInvalido && (
                    <Alert
                        variant="warning"
                        title="Número não reconhecido"
                        message="Informe o CNPJ da empresa (14 dígitos) ou o número da viabilidade."
                    />
                )}
                {indisponivel && (
                    <Alert
                        variant="warning"
                        title="Sistema anterior indisponível"
                        message="Não foi possível consultar as viabilidades do sistema anterior agora. As do Viabiliza continuam disponíveis; tente novamente em instantes."
                    />
                )}
            </div>

            {busca && !termoInvalido && itens.length === 0 ? (
                <Card>
                    <CardContent>
                        <EmptyState
                            title="Nenhuma viabilidade provisória encontrada"
                            description="Confira o CNPJ ou o número. Só viabilidade provisória deferida pode ser renovada."
                        />
                    </CardContent>
                </Card>
            ) : (
                <ul className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {itens.map((item) => (
                        <li
                            key={`${item.origem}-${item.id ?? item.numero}`}
                            className={item.elegivel && item.url ? undefined : 'md:col-span-2'}
                        >
                            {item.elegivel && item.url ? (
                                <ItemRenovacaoCard item={item} />
                            ) : (
                                <Alert
                                    variant="warning"
                                    title={`A viabilidade ${item.numero}${item.empresa ? ` (${item.empresa})` : ''} não pode ser renovada`}
                                    message={item.motivo ?? 'Procure a SEDUR para mais informações.'}
                                />
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </PortalLayout>
    );
}
