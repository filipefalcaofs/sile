import { Head } from '@inertiajs/react';
import GestaoLayout from '@/layouts/gestao-layout';

export default function Dashboard() {
    return (
        <GestaoLayout>
            <Head title="Painel de gestão" />
            <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                Painel de gestão
            </h2>
        </GestaoLayout>
    );
}
