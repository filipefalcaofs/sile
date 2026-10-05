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
    erro?: string | null;
    processing?: boolean;
    onConfirm: () => void;
    onCancel?: () => void;
    confirmLabel?: string;
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
    erro,
    processing = false,
    onConfirm,
    onCancel,
    confirmLabel = 'Concluir análise',
}: ConclusaoAnaliseFormProps) {
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
                    <Label htmlFor={parecerId}>{parecerLabel}</Label>
                    <Textarea
                        id={parecerId}
                        rows={4}
                        value={parecer}
                        onChange={(evento) => onParecer(evento.target.value)}
                        placeholder="Parecer da conclusão, se houver"
                    />
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
