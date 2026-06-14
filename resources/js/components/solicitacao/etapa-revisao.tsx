import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import type { SimulacaoData } from '@/components/solicitacao/etapa-simulacao';

interface RevisaoCnae {
    id: number;
    formatted_code: string;
    description: string;
    is_primary: boolean;
}

interface RevisaoSolicitacao {
    id: number;
    service_type: string | null;
    company: { legal_name: string; formatted_cnpj: string } | null;
    used_area_m2: string | number | null;
    address: {
        street: string | null;
        number: string | null;
        neighborhood: string | null;
        reference: string | null;
    };
    cnaes: RevisaoCnae[];
    documentos: Array<{ id: number; original_name: string }>;
}

interface RequisitoFaltante {
    id: number;
    name: string;
}

interface EtapaRevisaoProps {
    solicitacao: RevisaoSolicitacao;
    requisitosFaltantes: RequisitoFaltante[];
    simulation: SimulacaoData | null;
}

function InfoRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex flex-col gap-0.5">
            <span className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">{label}</span>
            <span className="text-sm text-gray-800 dark:text-white/90">{value || '—'}</span>
        </div>
    );
}

/**
 * Etapa de revisão e protocolo (HU-068/141): mostra o resumo, BLOQUEIA o
 * protocolo com a lista quando faltar documento obrigatório (aviso, nunca
 * silencioso — HU-067) e, quando a simulação tende a indeferimento, exige a
 * CIÊNCIA do requerente (proceed_despite) sem impedir o protocolo (direito de
 * petição). Protocolar chama POST solicitacoes.protocolar (08-10).
 */
export default function EtapaRevisao({ solicitacao, requisitosFaltantes, simulation }: EtapaRevisaoProps) {
    const [ciente, setCiente] = useState(false);
    const [processing, setProcessing] = useState(false);

    const docsPendentes = requisitosFaltantes.length > 0;
    const tendenciaDesfavoravel = simulation?.resultado === 'nao_permitido';
    const podeProtocolar = !docsPendentes && (!tendenciaDesfavoravel || ciente);

    const endereco = [solicitacao.address.street, solicitacao.address.number, solicitacao.address.neighborhood]
        .filter(Boolean)
        .join(', ');

    function protocolar() {
        if (!podeProtocolar) {
            return;
        }

        router.post(
            `/portal/solicitacoes/${solicitacao.id}/protocolar`,
            { proceed_despite: tendenciaDesfavoravel && ciente },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <div className="flex flex-col gap-4 md:gap-6">
            <Card>
                <CardHeader title="Resumo da solicitação" description="Confira os dados antes de protocolar." />
                <CardContent>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <InfoRow label="Tipo de serviço" value={solicitacao.service_type} />
                        <InfoRow label="Empresa" value={solicitacao.company?.legal_name} />
                        <InfoRow label="CNPJ" value={solicitacao.company?.formatted_cnpj} />
                        <InfoRow
                            label="Área utilizada"
                            value={solicitacao.used_area_m2 ? `${solicitacao.used_area_m2} m²` : null}
                        />
                        <InfoRow label="Endereço" value={endereco || null} />
                        <InfoRow label="Ponto de referência" value={solicitacao.address.reference} />
                    </div>

                    <div className="mt-6 border-t border-gray-100 pt-5 dark:border-gray-800">
                        <h4 className="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Atividades</h4>
                        {solicitacao.cnaes.length > 0 ? (
                            <div className="flex flex-col gap-2">
                                {solicitacao.cnaes.map((cnae) => (
                                    <div
                                        key={cnae.id}
                                        className="flex items-center justify-between gap-3 rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800"
                                    >
                                        <div className="flex flex-col">
                                            <span className="text-sm font-medium text-gray-800 dark:text-white/90">
                                                {cnae.formatted_code}
                                            </span>
                                            <span className="text-theme-xs text-gray-500 dark:text-gray-400">
                                                {cnae.description}
                                            </span>
                                        </div>
                                        {cnae.is_primary && (
                                            <Badge size="sm" color="primary">
                                                Principal
                                            </Badge>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500 dark:text-gray-400">Nenhuma atividade informada.</p>
                        )}
                    </div>

                    <div className="mt-6 border-t border-gray-100 pt-5 dark:border-gray-800">
                        <h4 className="mb-1 text-sm font-semibold text-gray-800 dark:text-white/90">Documentos</h4>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {solicitacao.documentos.length} anexo(s) enviado(s).
                        </p>
                    </div>
                </CardContent>
            </Card>

            {docsPendentes && (
                <Alert
                    variant="warning"
                    title="Faltam documentos obrigatórios"
                    message={`Para protocolar, anexe na etapa de documentos: ${requisitosFaltantes
                        .map((requisito) => requisito.name)
                        .join('; ')}.`}
                />
            )}

            {tendenciaDesfavoravel && (
                <Card>
                    <CardHeader
                        title="Tendência de indeferimento"
                        description="A simulação indicou tendência de indeferimento. Você pode protocolar mesmo assim (é um direito seu), mas precisa registrar ciência."
                    />
                    <CardContent>
                        <label className="flex items-start gap-3 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                checked={ciente}
                                onChange={(event) => setCiente(event.target.checked)}
                                className="mt-0.5 size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/30 dark:border-gray-700 dark:bg-gray-900"
                            />
                            Estou ciente da tendência de indeferimento e desejo prosseguir com o protocolo.
                        </label>
                    </CardContent>
                </Card>
            )}

            <div className="flex flex-col items-end gap-2">
                <Button size="sm" onClick={protocolar} disabled={!podeProtocolar || processing} loading={processing}>
                    Protocolar solicitação
                </Button>
                {docsPendentes && (
                    <span className="text-theme-xs text-gray-500 dark:text-gray-400">
                        Anexe os documentos obrigatórios para habilitar o protocolo.
                    </span>
                )}
            </div>
        </div>
    );
}
