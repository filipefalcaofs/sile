import AppLayout from '@/layouts/gestao-layout';
import { Head } from '@inertiajs/react';

interface Props {
    quadro: string;
    quadroLabel: string;
    draft: {
        id: number;
        version: string;
        autor: { id: number; name: string };
    } | null;
    itens: unknown | null;
    diff: { novas: number; alteradas: number; excluidas: number } | null;
    canPublish: boolean;
}

export default function LouosRascunho({ quadro, quadroLabel, draft }: Props) {
    return (
        <AppLayout>
            <Head title={`Rascunho — ${quadroLabel}`} />
            <div className="p-4">
                <h1 className="text-xl font-semibold">{quadroLabel}</h1>
                {draft ? (
                    <p>Rascunho em edição: {draft.version}</p>
                ) : (
                    <p>Nenhum rascunho aberto para {quadro}.</p>
                )}
            </div>
        </AppLayout>
    );
}
