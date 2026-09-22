import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface CamadaVersao {
    id: number;
    version: string;
    status: string;
    status_label: string;
    source: string;
    valid_from: string | null;
    valid_to: string | null;
    feature_count: number;
}

interface CamadaGrupo {
    tipo: string;
    label: string;
    fonte_bloqueada: boolean;
    vigente: CamadaVersao | null;
    versoes: CamadaVersao[];
}

interface CamadasIndexProps {
    grupos: CamadaGrupo[];
    tipos: { value: string; label: string }[];
    uploadMaxMb: number;
}

const inputArquivo =
    'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs file:mr-4 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-brand-600 hover:file:bg-brand-100 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:file:bg-brand-500/15 dark:file:text-brand-400';

function StatusBadge({ status, label }: { status: string; label: string }) {
    const color = status === 'vigente' ? 'success' : status === 'pendente_fonte' ? 'warning' : 'light';

    return (
        <Badge size="sm" color={color}>
            {label}
        </Badge>
    );
}

function ImportarModal({ tipos, uploadMaxMb, onClose }: { tipos: CamadasIndexProps['tipos']; uploadMaxMb: number; onClose: () => void }) {
    const form = useForm<{
        tipo: string;
        versao: string;
        origem: string;
        arquivo: File | null;
    }>({
        tipo: '',
        versao: '',
        origem: '',
        arquivo: null,
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        form.post('/gestao/territorio/camadas', {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[600px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Importar camada geográfica</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Envie um GeoJSON (FeatureCollection em SRID 4326) da fonte oficial. A importação cria uma nova versão vigente: a versão
                anterior é fechada e preservada no histórico, nunca apagada. Reimportar uma versão já existente substitui as feições dela,
                sem duplicar. Tamanho máximo: {uploadMaxMb} MB. Disponível apenas no banco PostGIS (produção/homologação).
            </p>

            <form onSubmit={handleSubmit} className="mt-6">
                <div className="flex flex-col gap-5">
                    <div>
                        <Label htmlFor="import-tipo">Tipo da camada</Label>
                        <Select
                            id="import-tipo"
                            name="tipo"
                            options={tipos}
                            placeholder="Selecione o tipo"
                            value={form.data.tipo}
                            onChange={(value) => form.setData('tipo', value)}
                        />
                        {form.errors.tipo && <p className="mt-1 text-theme-xs text-error-500">{form.errors.tipo}</p>}
                    </div>
                    <div>
                        <Label htmlFor="import-versao">Versão</Label>
                        <Input
                            id="import-versao"
                            type="text"
                            name="versao"
                            required
                            placeholder="geosalvador-2026-09"
                            value={form.data.versao}
                            onChange={(e) => form.setData('versao', e.target.value)}
                            error={!!form.errors.versao}
                            hint={form.errors.versao}
                        />
                    </div>
                    <div>
                        <Label htmlFor="import-origem">Origem (opcional)</Label>
                        <Input
                            id="import-origem"
                            type="text"
                            name="origem"
                            placeholder="Ex.: GeoSalvador — ArcGIS REST Bairros"
                            value={form.data.origem}
                            onChange={(e) => form.setData('origem', e.target.value)}
                            error={!!form.errors.origem}
                            hint={form.errors.origem}
                        />
                    </div>
                    <div>
                        <Label htmlFor="import-arquivo">Arquivo GeoJSON</Label>
                        <input
                            id="import-arquivo"
                            type="file"
                            accept=".geojson,.json,application/geo+json,application/json"
                            onChange={(e) => form.setData('arquivo', e.target.files?.[0] ?? null)}
                            className={inputArquivo}
                        />
                        {form.errors.arquivo && <p className="mt-1 text-theme-xs text-error-500">{form.errors.arquivo}</p>}
                    </div>
                    <div className="flex items-center justify-end gap-3">
                        <Button size="sm" variant="outline" onClick={onClose} disabled={form.processing} type="button">
                            Cancelar
                        </Button>
                        <Button size="sm" type="submit" disabled={form.processing}>
                            {form.processing ? 'Importando...' : 'Importar camada'}
                        </Button>
                    </div>
                </div>
            </form>
        </Modal>
    );
}

function GrupoCard({ grupo }: { grupo: CamadaGrupo }) {
    const columns: ColumnDef<CamadaVersao>[] = [
        {
            id: 'version',
            header: 'Versão',
            cellClassName: 'font-medium text-gray-800 dark:text-white/90 whitespace-nowrap',
            cell: (versao) => versao.version,
        },
        {
            id: 'status',
            header: 'Situação',
            cell: (versao) => <StatusBadge status={versao.status} label={versao.status_label} />,
        },
        {
            id: 'feature_count',
            header: 'Feições',
            cellClassName: 'whitespace-nowrap',
            cell: (versao) => versao.feature_count,
        },
        {
            id: 'source',
            header: 'Origem',
            cell: (versao) => versao.source,
        },
        {
            id: 'valid_from',
            header: 'Vigente de',
            cellClassName: 'whitespace-nowrap',
            cell: (versao) => versao.valid_from ?? '—',
        },
        {
            id: 'valid_to',
            header: 'Vigente até',
            cellClassName: 'whitespace-nowrap',
            cell: (versao) => versao.valid_to ?? '—',
        },
    ];

    return (
        <Card>
            <CardHeader
                title={grupo.label}
                description={
                    grupo.vigente
                        ? `Vigente: ${grupo.vigente.version} — ${grupo.vigente.feature_count} feições, desde ${grupo.vigente.valid_from ?? '—'}.`
                        : grupo.fonte_bloqueada
                          ? 'Sem fonte vetorial pública confirmada — pendente da base oficial SEDUR/SEFAZ.'
                          : 'Nenhuma versão importada.'
                }
                actions={
                    <div className="flex items-center gap-2">
                        {grupo.fonte_bloqueada && (
                            <Badge size="sm" color="warning">
                                Pendente de fonte
                            </Badge>
                        )}
                        {grupo.vigente ? (
                            <StatusBadge status={grupo.vigente.status} label={grupo.vigente.status_label} />
                        ) : (
                            !grupo.fonte_bloqueada && (
                                <Badge size="sm" color="light">
                                    Sem versão vigente
                                </Badge>
                            )
                        )}
                    </div>
                }
            />
            <CardContent>
                <DataTable
                    columns={columns}
                    rows={grupo.versoes}
                    rowKey={(versao) => versao.id}
                    density="compact"
                    emptyState={
                        <EmptyState
                            title="Nenhuma versão importada"
                            description="Use o botão Importar camada para carregar o GeoJSON oficial deste tipo."
                        />
                    }
                />
            </CardContent>
        </Card>
    );
}

export default function CamadasIndex({ grupos, tipos, uploadMaxMb }: CamadasIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-territorio');

    const [showImport, setShowImport] = useState(false);

    return (
        <>
            <Head title="Camadas geográficas" />
            <PageHeader title="Camadas geográficas" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <div className="mb-6 flex items-center justify-between gap-4">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Versões oficiais das camadas usadas na validação territorial. A importação abre uma nova versão vigente e fecha a
                    anterior — o histórico é preservado para reprodução de decisões.
                </p>
                {canMaintain && (
                    <Button size="sm" onClick={() => setShowImport(true)}>
                        Importar camada
                    </Button>
                )}
            </div>

            <div className="flex flex-col gap-6">
                {grupos.map((grupo) => (
                    <GrupoCard key={grupo.tipo} grupo={grupo} />
                ))}
            </div>

            {canMaintain && showImport && <ImportarModal tipos={tipos} uploadMaxMb={uploadMaxMb} onClose={() => setShowImport(false)} />}
        </>
    );
}

CamadasIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
