import { useState } from 'react';

interface SwitchProps {
    label: string;
    /** Quando informado, o componente é controlado pelo pai. */
    checked?: boolean;
    defaultChecked?: boolean;
    disabled?: boolean;
    onChange?: (checked: boolean) => void;
    color?: 'blue' | 'gray';
}

export default function Switch({
    label,
    checked,
    defaultChecked = false,
    disabled = false,
    onChange,
    color = 'blue',
}: SwitchProps) {
    const [interno, setInterno] = useState(defaultChecked);
    const ligado = checked ?? interno;

    const handleToggle = () => {
        if (disabled) {
            return;
        }

        const proximo = !ligado;

        if (checked === undefined) {
            setInterno(proximo);
        }

        onChange?.(proximo);
    };

    const switchColors =
        color === 'blue'
            ? {
                  background: ligado ? 'bg-brand-500' : 'bg-gray-200 dark:bg-white/10',
                  knob: ligado ? 'translate-x-full bg-white' : 'translate-x-0 bg-white',
              }
            : {
                  background: ligado ? 'bg-gray-800 dark:bg-white/10' : 'bg-gray-200 dark:bg-white/10',
                  knob: ligado ? 'translate-x-full bg-white' : 'translate-x-0 bg-white',
              };

    return (
        <button
            type="button"
            role="switch"
            aria-checked={ligado}
            disabled={disabled}
            onClick={handleToggle}
            className={`flex cursor-pointer items-center gap-3 text-left text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:cursor-not-allowed ${
                disabled ? 'text-gray-400' : 'text-gray-700 dark:text-gray-400'
            }`}
        >
            <div className="relative">
                <div
                    className={`block transition duration-150 ease-linear h-6 w-11 rounded-full ${
                        disabled ? 'bg-gray-100 pointer-events-none dark:bg-gray-800' : switchColors.background
                    }`}
                ></div>
                <div
                    className={`absolute left-0.5 top-0.5 h-5 w-5 rounded-full shadow-theme-sm duration-150 ease-linear transform ${switchColors.knob}`}
                ></div>
            </div>
            {label}
        </button>
    );
}
