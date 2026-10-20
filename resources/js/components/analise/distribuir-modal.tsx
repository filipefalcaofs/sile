import { useForm } from '@inertiajs/react';
import FieldError from '@/components/form/field-error';
import Select from '@/components/form/select';
import Button from '@/components/ui/button';
import { Modal } from '@/components/ui/modal';
import { destinatariosDoSetor, escolhaDeSetor, type SetorDoAnalista } from '@/pages/gestao/caixa-setor/setor-destino';

export interface DestinatarioDaDistribuicao {
    id: number;
    name: string;
    setores: SetorDoAnalista[];
}

interface DistribuirModalProps {
    tipo: 'distribuir' | 'redistribuir';
    ids: number[];
    descricao: string;
    destinatarios: DestinatarioDaDistribuicao[];
    /** Quem recebe: "Analista" ou "Vistoriador". */
    papel: 'Analista' | 'Vistoriador';
    distribuiEmQualquerSetor: boolean;
    setoresDosProcessos: (number | null)[];
    onClose: () => void;
    onSuccess: () => void;
}

/** Tramitação (distribuir ou redistribuir) de um ou mais processos a um destinatário do setor. */
export default function DistribuirModal({
    tipo,
    ids,
    descricao,
    destinatarios,
    papel,
    distribuiEmQualquerSetor,
    setoresDosProcessos,
    onClose,
    onSuccess,
}: DistribuirModalProps) {
    const emVistoria = papel === 'Vistoriador';
    const tramitacao = useForm<{ request_ids: number[]; analista_id: string; sector_id: string }>({
        request_ids: ids,
        analista_id: '',
        sector_id: '',
    });

    const elegiveis = destinatariosDoSetor(distribuiEmQualquerSetor, destinatarios, setoresDosProcessos);
    const opcoes = elegiveis.map((pessoa) => ({ value: String(pessoa.id), label: pessoa.name }));
    const destinatario = elegiveis.find((pessoa) => String(pessoa.id) === tramitacao.data.analista_id);
    const escolhaDoSetor = escolhaDeSetor(distribuiEmQualquerSetor, destinatario?.setores ?? [], setoresDosProcessos);

    function confirmar() {
        const rota = tipo === 'redistribuir' ? '/gestao/caixa-setor/redistribuir' : '/gestao/caixa-setor/distribuir';

        tramitacao.post(rota, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                tramitacao.reset();
                onSuccess();
            },
        });
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-w-lg p-6 sm:p-8">
            <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                {tipo === 'redistribuir' ? 'Redistribuir processo' : 'Tramitar processo(s)'}
            </h3>
            <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                {descricao} —{' '}
                {emVistoria
                    ? 'processo em vistoria: selecione o vistoriador responsável pela ficha.'
                    : distribuiEmQualquerSetor
                      ? 'selecione a analista responsável pela análise; o processo passa para o setor dela.'
                      : 'selecione a analista do setor responsável pela análise.'}
            </p>

            <div className="mt-6">
                <label htmlFor="tramitar-analista" className="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300">
                    {papel}
                </label>
                <Select
                    id="tramitar-analista"
                    value={tramitacao.data.analista_id}
                    onChange={(value) => tramitacao.setData({ ...tramitacao.data, analista_id: value, sector_id: '' })}
                    placeholder={
                        emVistoria
                            ? opcoes.length > 0
                                ? 'Selecione o vistoriador'
                                : 'Nenhum vistoriador vinculado ao setor'
                            : opcoes.length > 0
                              ? 'Selecione a analista'
                              : 'Nenhuma analista vinculada ao setor'
                    }
                    options={opcoes}
                    disabled={opcoes.length === 0}
                />
                {tramitacao.errors.analista_id && <FieldError>{tramitacao.errors.analista_id}</FieldError>}
            </div>

            {escolhaDoSetor !== 'nenhuma' && destinatario && (
                <div className="mt-4">
                    <label htmlFor="tramitar-setor" className="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300">
                        Setor que recebe o processo
                    </label>
                    <Select
                        id="tramitar-setor"
                        value={tramitacao.data.sector_id}
                        onChange={(value) => tramitacao.setData('sector_id', value)}
                        placeholder={escolhaDoSetor === 'opcional' ? 'Manter o setor atual do processo' : 'Selecione o setor'}
                        options={destinatario.setores.map((setor) => ({ value: String(setor.id), label: setor.name }))}
                    />
                    {tramitacao.errors.sector_id && <FieldError>{tramitacao.errors.sector_id}</FieldError>}
                </div>
            )}

            <div className="mt-8 flex justify-end gap-3">
                <Button variant="outline" onClick={onClose} disabled={tramitacao.processing}>
                    Cancelar
                </Button>
                <Button
                    variant="primary"
                    onClick={confirmar}
                    loading={tramitacao.processing}
                    disabled={tramitacao.data.analista_id === '' || (escolhaDoSetor === 'obrigatoria' && tramitacao.data.sector_id === '')}
                >
                    Confirmar envio
                </Button>
            </div>
        </Modal>
    );
}
