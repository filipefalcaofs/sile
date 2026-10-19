import { useState } from 'react';

import FieldError from '@/components/form/field-error';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import Textarea from '@/components/form/textarea';
import Button from '@/components/ui/button';

export interface ConclusaoOpcao {
    value: string;
    label: string;
}

interface ConclusaoAnaliseFormProps {
    resultadoId: string;
    resultado: string;
    onResultado: (valor: string) => void;
    opcoes: ConclusaoOpcao[];
    parecerId?: string;
    parecer?: string;
    onParecer?: (valor: string) => void;
    parecerLabel?: string;
    /** Parecer registrado na ficha de análise: é o texto que o botão "Copiar parecer" leva para a área de transferência. */
    parecerDaFicha?: string | null;
    erro?: string | null;
    processing?: boolean;
    onConfirm: () => void;
    onCancel?: () => void;
    confirmLabel?: string;
}

export async function copiarTexto(texto: string): Promise<void> {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(texto);

        return;
    }

    const campo = document.createElement('textarea');
    campo.value = texto;
    campo.setAttribute('readonly', '');
    campo.style.position = 'fixed';
    campo.style.left = '-9999px';
    document.body.appendChild(campo);
    campo.select();
    const copiou = document.execCommand('copy');
    document.body.removeChild(campo);

    if (!copiou) {
        throw new Error('copia');
    }
}

/** Resultado e parecer da conclusão de análise, nas três telas que decidem o processo. */
export default function ConclusaoAnaliseForm({
    resultadoId,
    resultado,
    onResultado,
    opcoes,
    parecerId = 'parecer-conclusao',
    parecer = '',
    onParecer,
    parecerLabel = 'Parecer',
    parecerDaFicha = null,
    erro,
    processing = false,
    onConfirm,
    onCancel,
    confirmLabel = 'Concluir análise',
}: ConclusaoAnaliseFormProps) {
    const [copiado, setCopiado] = useState(false);
    const [erroCopia, setErroCopia] = useState(false);
    const textoParaCopiar = (parecerDaFicha ?? '').trim() !== '' ? (parecerDaFicha ?? '') : parecer;

    async function copiarParecer() {
        setErroCopia(false);

        try {
            await copiarTexto(textoParaCopiar);
            setCopiado(true);
            window.setTimeout(() => setCopiado(false), 2500);
        } catch {
            setCopiado(false);
            setErroCopia(true);
        }
    }

    return (
        <div className="flex flex-col gap-4">
            <div>
                <Label htmlFor={resultadoId} required>
                    Resultado
                </Label>
                <Select
                    id={resultadoId}
                    value={resultado}
                    onChange={onResultado}
                    placeholder="Selecione o resultado"
                    options={opcoes}
                />
            </div>
            {onParecer && (
                <div>
                    <div className="flex items-center justify-between gap-2">
                        <Label htmlFor={parecerId}>{parecerLabel}</Label>
                        <Button
                            type="button"
                            variant="ghost"
                            size="xs"
                            onClick={copiarParecer}
                            disabled={textoParaCopiar.trim() === ''}
                        >
                            {copiado ? 'Copiado!' : 'Copiar parecer'}
                        </Button>
                    </div>
                    <Textarea
                        id={parecerId}
                        rows={4}
                        value={parecer}
                        onChange={(evento) => onParecer(evento.target.value)}
                        placeholder="Parecer da conclusão, se houver"
                    />
                    {erroCopia && <FieldError>Não foi possível copiar. Selecione o texto e copie manualmente.</FieldError>}
                </div>
            )}
            {erro && <FieldError>{erro}</FieldError>}
            <div className="flex items-center justify-end gap-3">
                {onCancel && (
                    <Button type="button" variant="outline" size="sm" onClick={onCancel} disabled={processing}>
                        Cancelar
                    </Button>
                )}
                <Button type="button" size="sm" onClick={onConfirm} disabled={resultado === ''} loading={processing}>
                    {confirmLabel}
                </Button>
            </div>
        </div>
    );
}
