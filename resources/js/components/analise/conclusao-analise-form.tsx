import { useState } from 'react';

import FieldError from '@/components/form/field-error';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import Textarea from '@/components/form/textarea';
import IsoDateInput from '@/components/form/iso-date-input';
import Button from '@/components/ui/button';

export interface ConclusaoOpcao {
    value: string;
    label: string;
}

/** Tipo do deferimento (SEDUR 20/10/2026): o valor é o tipo de validade gravado na decisão. */
export const TIPO_DEFERIMENTO_OPCOES: ConclusaoOpcao[] = [
    { value: 'definitiva', label: 'Definitivo' },
    { value: 'provisoria', label: 'Provisório' },
    { value: 'pre_operacional', label: 'Pré-operacional' },
];

export const VALIDADE_OPCOES: ConclusaoOpcao[] = [
    { value: '6', label: '6 meses' },
    { value: '12', label: '1 ano' },
    { value: '24', label: '2 anos' },
    { value: 'personalizado', label: 'Personalizado' },
];

const TIPOS_COM_VALIDADE = ['provisoria', 'pre_operacional'];

interface ConclusaoAnaliseFormProps {
    resultadoId: string;
    resultado: string;
    onResultado: (valor: string) => void;
    opcoes: ConclusaoOpcao[];
    parecerId?: string;
    parecer?: string;
    onParecer?: (valor: string) => void;
    parecerLabel?: string;
    /** Parecer registrado na ficha de análise: é o texto que o botão "Importar parecer" coloca no campo. */
    parecerDaFicha?: string | null;
    erro?: string | null;
    /** Resultado contradiz a ficha: mostra o aviso e o botão para confirmar mesmo assim. */
    divergencia?: string | null;
    onConfirmarDivergencia?: () => void;
    processing?: boolean;
    onConfirm: () => void;
    onCancel?: () => void;
    confirmLabel?: string;
    /** Sem onTipo (ex.: renovação, que tem diálogo próprio), Tipo e Validade não aparecem. */
    tipo?: string;
    onTipo?: (valor: string) => void;
    validadeMeses?: string;
    onValidadeMeses?: (valor: string) => void;
    /** Data da validade (AAAA-MM-DD), pedida quando a validade é "Personalizado". */
    validadeData?: string;
    onValidadeData?: (valor: string) => void;
    erroValidadeData?: string | null;
    erroTipo?: string | null;
    erroValidade?: string | null;
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
    divergencia = null,
    onConfirmarDivergencia,
    processing = false,
    onConfirm,
    onCancel,
    confirmLabel = 'Concluir análise',
    tipo = '',
    onTipo,
    validadeMeses = '',
    onValidadeMeses,
    validadeData = '',
    onValidadeData,
    erroValidadeData = null,
    erroTipo = null,
    erroValidade = null,
}: ConclusaoAnaliseFormProps) {
    const pedeTipo = onTipo !== undefined && resultado === 'deferido';
    const pedeValidade = pedeTipo && TIPOS_COM_VALIDADE.includes(tipo);
    const validadePersonalizada = pedeValidade && validadeMeses === 'personalizado' && onValidadeData !== undefined;
    const faltaCampo =
        (pedeTipo && tipo === '') ||
        (pedeValidade && validadeMeses === '') ||
        (validadePersonalizada && validadeData === '');

    const [importado, setImportado] = useState(false);
    const textoDaFicha = (parecerDaFicha ?? '').trim();

    function importarParecer() {
        onParecer?.(textoDaFicha);
        setImportado(true);
        window.setTimeout(() => setImportado(false), 2500);
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
            {pedeTipo && (
                <div>
                    <Label htmlFor="tipo-deferimento" required>
                        Tipo
                    </Label>
                    <Select
                        id="tipo-deferimento"
                        value={tipo}
                        onChange={(valor) => onTipo?.(valor)}
                        placeholder="Selecione o tipo"
                        options={TIPO_DEFERIMENTO_OPCOES}
                    />
                    {erroTipo && <FieldError>{erroTipo}</FieldError>}
                </div>
            )}
            {pedeValidade && (
                <div>
                    <Label htmlFor="validade-deferimento" required>
                        Data da Validade
                    </Label>
                    <Select
                        id="validade-deferimento"
                        value={validadeMeses}
                        onChange={(valor) => onValidadeMeses?.(valor)}
                        placeholder="Selecione a validade"
                        options={onValidadeData ? VALIDADE_OPCOES : VALIDADE_OPCOES.filter((opcao) => opcao.value !== 'personalizado')}
                    />
                    {erroValidade && <FieldError>{erroValidade}</FieldError>}
                </div>
            )}
            {validadePersonalizada && (
                <div>
                    <Label htmlFor="validade-data-deferimento" required>
                        Data
                    </Label>
                    <IsoDateInput
                        id="validade-data-deferimento"
                        value={validadeData}
                        onChange={(iso) => onValidadeData?.(iso)}
                        placeholder="dd/mm/aaaa"
                    />
                    {erroValidadeData && <FieldError>{erroValidadeData}</FieldError>}
                </div>
            )}
            {onParecer && (
                <div>
                    <div className="flex items-center justify-between gap-2">
                        <Label htmlFor={parecerId}>{parecerLabel}</Label>
                        <Button
                            type="button"
                            variant="ghost"
                            size="xs"
                            onClick={importarParecer}
                            disabled={textoDaFicha === ''}
                        >
                            {importado ? 'Importado!' : 'Importar parecer'}
                        </Button>
                    </div>
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
            {divergencia && (
                <div
                    role="alert"
                    className="rounded-lg border border-warning-300 bg-warning-50 p-3 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300"
                >
                    <p>{divergencia}</p>
                    <p className="mt-1 font-medium">
                        Deseja, de fato, concluir como {opcoes.find((opcao) => opcao.value === resultado)?.label ?? 'o resultado escolhido'}?
                    </p>
                </div>
            )}
            <div className="flex items-center justify-end gap-3">
                {onCancel && (
                    <Button type="button" variant="outline" size="sm" onClick={onCancel} disabled={processing}>
                        Cancelar
                    </Button>
                )}
                {divergencia && onConfirmarDivergencia ? (
                    <Button type="button" size="sm" onClick={onConfirmarDivergencia} loading={processing}>
                        Confirmar mesmo assim
                    </Button>
                ) : (
                    <Button type="button" size="sm" onClick={onConfirm} disabled={resultado === '' || faltaCampo} loading={processing}>
                        {confirmLabel}
                    </Button>
                )}
            </div>
        </div>
    );
}
