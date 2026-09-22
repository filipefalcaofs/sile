import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';
import GestaoLayout from '@/layouts/gestao-layout';

interface Pergunta {
    numero: number;
    texto: string;
    referencia: string | null;
}

interface TratamentoIndexProps {
    versao: string | null;
    contagens: {
        cnaes: number;
        enquadramentos: number;
        perguntas: number;
        regras: number;
        bindings: number;
    };
    perguntas: Pergunta[];
}

export default function TratamentoIndex({ versao, contagens, perguntas }: TratamentoIndexProps) {
    return (
        <>
            <Head title="Planilha de regras" />
            <PageHeader
                title="Planilha de regras"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }, { label: 'Regras' }]}
            />

            <p className="mb-6 text-sm text-gray-500 dark:text-gray-400">
                Ramos de tratamento vigentes (perguntas, enquadramento, risco e TLL). Quem permite ou proíbe na zona
                continua sendo o Quadro 10; na via, o Quadro 11A.
            </p>

            {versao === null ? (
                <EmptyState
                    title="Planilha sem versão vigente"
                    description="Não há versão publicada da planilha de tratamento. O enquadramento fica pendente até a carga versionada."
                />
            ) : (
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader
                            title="Versão vigente"
                            description="Fonte do enquadramento de uso. Não substitui os Quadros 10 e 11A."
                        />
                        <CardContent>
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge color="success">{versao}</Badge>
                                <span className="text-sm text-gray-500 dark:text-gray-400">
                                    {contagens.cnaes} CNAEs · {contagens.enquadramentos} enquadramentos · {contagens.regras} regras
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader title="Perguntas" description="Perguntas que escolhem o ramo de cada CNAE." />
                        <CardContent>
                            {perguntas.length === 0 ? (
                                <p className="text-sm text-gray-500 dark:text-gray-400">Nenhuma pergunta nesta versão.</p>
                            ) : (
                                <ol className="flex flex-col gap-3">
                                    {perguntas.map((pergunta) => (
                                        <li key={pergunta.numero} className="text-sm text-gray-800 dark:text-white/90">
                                            <span className="font-medium">P{pergunta.numero}.</span> {pergunta.texto}
                                            {pergunta.referencia && (
                                                <span className="mt-1 block text-gray-500 dark:text-gray-400">{pergunta.referencia}</span>
                                            )}
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </CardContent>
                    </Card>
                </div>
            )}
        </>
    );
}

TratamentoIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
