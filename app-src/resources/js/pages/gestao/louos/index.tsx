import { Head, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';
import AbaEnquadramento, { type EnquadramentoData } from './aba-enquadramento';
import AbaQuadros, { type AbaQuadrosProps } from './aba-quadros';
import AbaTerritoriais, { type TerritoriaisData } from './aba-territoriais';
import type { QuadroItem } from './quadro-fields';

type Aba = 'quadros' | 'enquadramento' | 'territoriais';

interface LouosIndexProps {
    aba: Aba;
    quadros: AbaQuadrosProps['quadros'];
    quadroSelecionado: string;
    itens: {
        data: QuadroItem[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    } | null;
    versoes: AbaQuadrosProps['versoes'];
    filtros: {
        quadro: string;
        search: string;
        per_page: number;
    };
    perPageOptions: number[];
    urlRascunho: string;
    urlManual: string;
    enquadramento: EnquadramentoData | null;
    territoriais: TerritoriaisData | null;
}

const ABAS: { id: Aba; label: string; permission: string }[] = [
    { id: 'quadros', label: 'Quadros LOUOS', permission: 'consultar-louos' },
    { id: 'enquadramento', label: 'Enquadramento', permission: 'consultar-cnaes' },
    { id: 'territoriais', label: 'Regras territoriais', permission: 'consultar-louos' },
];

/**
 * Hub "Motor de regras" (Regras → Motor de regras): concentra tudo o que o
 * motor lê como dado versionado — Quadros LOUOS (10/11A), a planilha de
 * enquadramento e as regras territoriais (RT-01 a RT-05). Cada aba é
 * server-driven (param `aba`) e re-checa a permissão do seu domínio.
 */
export default function LouosIndex({
    aba,
    quadros,
    quadroSelecionado,
    itens,
    versoes,
    filtros,
    perPageOptions,
    urlRascunho,
    urlManual,
    enquadramento,
    territoriais,
}: LouosIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-louos');

    const abasVisiveis = ABAS.filter((item) => auth.permissions.includes(item.permission));
    const abaAtiva = abasVisiveis.some((item) => item.id === aba) ? aba : (abasVisiveis[0]?.id ?? 'quadros');

    function trocarAba(nova: Aba) {
        if (nova === abaAtiva) {
            return;
        }

        router.get('/gestao/louos', { aba: nova }, { preserveState: false });
    }

    return (
        <>
            <Head title="Motor de regras" />
            <PageHeader title="Motor de regras" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <div className="mb-6 border-b border-gray-200 dark:border-gray-800">
                <nav className="-mb-px flex gap-6" aria-label="Abas do motor de regras">
                    {abasVisiveis.map((item) => (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => trocarAba(item.id)}
                            aria-current={item.id === abaAtiva ? 'page' : undefined}
                            className={`border-b-2 px-1 pb-3 text-sm font-medium transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500/40 ${
                                item.id === abaAtiva
                                    ? 'border-brand-500 text-brand-600 dark:text-brand-400'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                            }`}
                        >
                            {item.label}
                        </button>
                    ))}
                </nav>
            </div>

            {abaAtiva === 'quadros' && itens !== null && (
                <AbaQuadros
                    quadros={quadros}
                    quadroSelecionado={quadroSelecionado}
                    itens={itens}
                    versoes={versoes}
                    filtros={filtros}
                    perPageOptions={perPageOptions}
                    urlRascunho={urlRascunho}
                    urlManual={urlManual}
                    canMaintain={canMaintain}
                />
            )}

            {abaAtiva === 'enquadramento' && enquadramento !== null && (
                <AbaEnquadramento enquadramento={enquadramento} filtros={filtros} perPageOptions={perPageOptions} />
            )}

            {abaAtiva === 'territoriais' && territoriais !== null && (
                <AbaTerritoriais territoriais={territoriais} canMaintain={canMaintain} />
            )}
        </>
    );
}

LouosIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
