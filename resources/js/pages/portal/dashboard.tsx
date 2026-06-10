import { Head } from '@inertiajs/react';
import PortalLayout from '@/layouts/portal-layout';

export default function Dashboard() {
    return (
        <PortalLayout>
            <Head title="Meu painel" />
            <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                Meu painel
            </h2>
            <p className="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
                Bem-vindo(a) ao SILE.
            </p>
        </PortalLayout>
    );
}
