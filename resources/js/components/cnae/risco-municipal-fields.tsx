import Label from '@/components/form/label';
import Select from '@/components/form/select';

interface NivelOption {
    value: string;
    label: string;
}

interface RiscoMunicipalFieldsProps {
    niveisMunicipais: NivelOption[];
    riscoMunicipal: string;
    onRiscoMunicipalChange: (value: string) => void;
    exigeRt: string;
    onExigeRtChange: (value: string) => void;
    exigeRtSeAlto: string;
    onExigeRtSeAltoChange: (value: string) => void;
    exigeFatorMultiplicador: string;
    onExigeFatorMultiplicadorChange: (value: string) => void;
    exigeDetalhamentoMultiplicador: string;
    onExigeDetalhamentoMultiplicadorChange: (value: string) => void;
    errors: Partial<
        Record<
            | 'risco_municipal'
            | 'exige_rt'
            | 'exige_rt_se_alto'
            | 'exige_fator_multiplicador'
            | 'exige_detalhamento_multiplicador',
            string
        >
    >;
}

const SIM_NAO_OPTIONS = [
    { value: '1', label: 'Sim' },
    { value: '0', label: 'Não' },
];

/**
 * Seção "Classificação de risco" da ficha do CNAE: grau de risco municipal
 * (Decreto 32.636/2020) e as flags de RT/fator multiplicador (HU-047
 * RN-010). Compartilhada entre criar e editar — os mesmos 5 campos.
 */
export default function RiscoMunicipalFields({
    niveisMunicipais,
    riscoMunicipal,
    onRiscoMunicipalChange,
    exigeRt,
    onExigeRtChange,
    exigeRtSeAlto,
    onExigeRtSeAltoChange,
    exigeFatorMultiplicador,
    onExigeFatorMultiplicadorChange,
    exigeDetalhamentoMultiplicador,
    onExigeDetalhamentoMultiplicadorChange,
    errors,
}: RiscoMunicipalFieldsProps) {
    return (
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <Label htmlFor="risco-municipal" required>
                    Grau de risco
                </Label>
                <Select
                    id="risco-municipal"
                    name="risco_municipal"
                    value={riscoMunicipal}
                    onChange={onRiscoMunicipalChange}
                    options={niveisMunicipais}
                />
                {errors.risco_municipal && (
                    <p className="mt-1.5 text-theme-xs text-error-500">{errors.risco_municipal}</p>
                )}
            </div>
            <div>
                <Label htmlFor="exige-rt" required>
                    Exige responsável técnico?
                </Label>
                <Select id="exige-rt" name="exige_rt" value={exigeRt} onChange={onExigeRtChange} options={SIM_NAO_OPTIONS} />
                {errors.exige_rt && <p className="mt-1.5 text-theme-xs text-error-500">{errors.exige_rt}</p>}
            </div>
            <div>
                <Label htmlFor="exige-rt-se-alto" required>
                    Exige RT apenas se alto risco?
                </Label>
                <Select
                    id="exige-rt-se-alto"
                    name="exige_rt_se_alto"
                    value={exigeRtSeAlto}
                    onChange={onExigeRtSeAltoChange}
                    options={SIM_NAO_OPTIONS}
                />
                <p className="mt-1.5 text-theme-xs text-gray-400 dark:text-gray-500">
                    O Responsável Técnico será exigido apenas quando o estabelecimento for classificado como Alto Risco.
                </p>
                {errors.exige_rt_se_alto && (
                    <p className="mt-1.5 text-theme-xs text-error-500">{errors.exige_rt_se_alto}</p>
                )}
            </div>
            <div>
                <Label htmlFor="exige-fator-multiplicador" required>
                    Possui fator multiplicador?
                </Label>
                <Select
                    id="exige-fator-multiplicador"
                    name="exige_fator_multiplicador"
                    value={exigeFatorMultiplicador}
                    onChange={onExigeFatorMultiplicadorChange}
                    options={SIM_NAO_OPTIONS}
                />
                {errors.exige_fator_multiplicador && (
                    <p className="mt-1.5 text-theme-xs text-error-500">{errors.exige_fator_multiplicador}</p>
                )}
            </div>
            <div>
                <Label htmlFor="exige-detalhamento-multiplicador" required>
                    Exige detalhamento do multiplicador?
                </Label>
                <Select
                    id="exige-detalhamento-multiplicador"
                    name="exige_detalhamento_multiplicador"
                    value={exigeDetalhamentoMultiplicador}
                    onChange={onExigeDetalhamentoMultiplicadorChange}
                    options={SIM_NAO_OPTIONS}
                />
                {errors.exige_detalhamento_multiplicador && (
                    <p className="mt-1.5 text-theme-xs text-error-500">{errors.exige_detalhamento_multiplicador}</p>
                )}
            </div>
        </div>
    );
}
