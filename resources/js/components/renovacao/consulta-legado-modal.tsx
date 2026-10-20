import { useHttp } from '@inertiajs/react';
import { useState } from 'react';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import LegadoItemCard, { type LegadoItem } from '@/components/renovacao/legado-item';
import Alert from '@/components/ui/alert';
import Button from '@/components/ui/button';
import { Modal } from '@/components/ui/modal';

export interface EmpresaVinculada {
    id: number;
    nome: string | null;
    cnpj: string | null;
}

interface RespostaConsulta {
    itens: LegadoItem[];
    indisponivel: boolean;
}

const MENSAGEM_PADRAO = 'Não foi possível consultar a viabilidade agora. Tente novamente em instantes.';

function mensagemDe(data: unknown): string | null {
    if (typeof data === 'string') {
        try {
            data = JSON.parse(data);
        } catch {
            return null;
        }
    }

    if (!data || typeof data !== 'object') {
        return null;
    }

    const corpo = data as { message?: unknown; errors?: Record<string, unknown> };
    const primeiroErro = corpo.errors ? Object.values(corpo.errors).flat()[0] : null;

    if (typeof primeiroErro === 'string') {
        return primeiroErro;
    }

    return typeof corpo.message === 'string' ? corpo.message : null;
}

export default function ConsultaLegadoModal({
    isOpen,
    onClose,
    empresas,
}: {
    isOpen: boolean;
    onClose: () => void;
    empresas: EmpresaVinculada[];
}) {
    const form = useHttp<{ empresa_id: string; numero_viabilidade: string }, RespostaConsulta>({
        empresa_id: empresas.length === 1 ? String(empresas[0].id) : '',
        numero_viabilidade: '',
    });
    const [resposta, setResposta] = useState<RespostaConsulta | null>(null);
    const [erro, setErro] = useState<string | null>(null);

    function consultar() {
        setErro(null);
        setResposta(null);
        // as falhas já são tratadas nos callbacks; o catch evita rejeição não tratada no console
        void Promise.resolve(form.post('/portal/renovacao/legado/consultar', {
            onSuccess: (dados: unknown) => setResposta(dados as RespostaConsulta),
            onError: (erros: Record<string, string>) => setErro(Object.values(erros)[0] ?? MENSAGEM_PADRAO),
            onHttpException: (response) => setErro(mensagemDe(response.data) ?? MENSAGEM_PADRAO),
            onNetworkError: () => setErro(MENSAGEM_PADRAO),
        })).catch(() => undefined);
    }

    const podeConsultar = form.data.empresa_id !== '' && form.data.numero_viabilidade.trim() !== '';

    return (
        <Modal isOpen={isOpen} onClose={onClose} className="max-w-2xl p-6 sm:p-8">
            <h2 className="mb-1 pr-12 text-lg font-semibold text-gray-800 dark:text-white/90">
                Renovar viabilidade do sistema anterior
            </h2>
            <p className="mb-5 text-sm text-gray-600 dark:text-gray-400">
                Informe a empresa e o número da viabilidade provisória emitida no sistema anterior.
            </p>
            <form
                className="flex flex-col gap-4"
                onSubmit={(event) => {
                    event.preventDefault();

                    if (podeConsultar && !form.processing) {
                        consultar();
                    }
                }}
            >
                <div>
                    <Label htmlFor="legado-empresa">Empresa</Label>
                    <Select
                        id="legado-empresa"
                        placeholder="Selecione a empresa"
                        value={form.data.empresa_id}
                        options={empresas.map((empresa) => ({
                            value: String(empresa.id),
                            label: [empresa.nome, empresa.cnpj].filter(Boolean).join(' — '),
                        }))}
                        onChange={(valor) => form.setData('empresa_id', valor)}
                    />
                </div>
                <div>
                    <Label htmlFor="legado-numero">Número da viabilidade</Label>
                    <Input
                        id="legado-numero"
                        autoComplete="off"
                        value={form.data.numero_viabilidade}
                        placeholder="Número da viabilidade (7 dígitos) ou do processo (0000000000-00000000/0000)"
                        onChange={(event) => form.setData('numero_viabilidade', event.target.value)}
                    />
                </div>
                <div>
                    <Button type="submit" className="min-h-11" loading={form.processing} disabled={!podeConsultar}>
                        Consultar
                    </Button>
                </div>
            </form>

            <div className="mt-5 flex flex-col gap-4" aria-live="polite" aria-busy={form.processing}>
                {form.processing && <p className="text-sm text-gray-600 dark:text-gray-300">Consultando o sistema anterior...</p>}
                {erro && <Alert variant="error" title="Consulta não concluída" message={erro} />}
                {resposta?.indisponivel && (
                    <Alert
                        variant="warning"
                        title="Sistema anterior indisponível"
                        message="Não foi possível consultar as viabilidades do sistema anterior agora. As demais continuam disponíveis; tente novamente em instantes."
                    />
                )}
                {resposta && !resposta.indisponivel && resposta.itens.length === 0 && (
                    <p className="text-sm text-gray-600 dark:text-gray-300">
                        Nenhuma viabilidade encontrada para esta empresa com esse número.
                    </p>
                )}
                {resposta && resposta.itens.length > 0 && (
                    <ul className="flex flex-col gap-4">
                        {resposta.itens.map((item) => (
                            <li key={`${item.empresa_id}-${item.numero}`}>
                                <LegadoItemCard item={item} />
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </Modal>
    );
}
