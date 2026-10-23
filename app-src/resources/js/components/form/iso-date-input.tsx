import { DayPicker } from '@daypicker/react';
import { ptBR } from '@daypicker/react/locale';
import '@daypicker/react/style.css';
import { useEffect, useLayoutEffect, useRef, useState, type InputHTMLAttributes } from 'react';
import { createPortal } from 'react-dom';
import { CalendarIcon } from '@/components/icons';
import { brDateToIso, isoToBrDate } from '@/lib/masks';
import MaskedInput from './masked-input';

interface IsoDateInputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'onChange' | 'value' | 'defaultValue' | 'type'> {
    value?: string;
    defaultValue?: string;
    onChange?: (iso: string) => void;
    error?: boolean;
    hint?: string;
}

const LARGURA_PAINEL = 304;
const ALTURA_PAINEL = 380;

function isoParaData(iso: string): Date | undefined {
    const match = iso.match(/^(\d{4})-(\d{2})-(\d{2})$/);

    return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : undefined;
}

function dataParaIso(data: Date): string {
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    const dia = String(data.getDate()).padStart(2, '0');

    return `${data.getFullYear()}-${mes}-${dia}`;
}

export default function IsoDateInput({
    value,
    defaultValue,
    onChange,
    name,
    className = '',
    ...rest
}: IsoDateInputProps) {
    const controlled = value !== undefined;
    const [isoValue, setIsoValue] = useState(defaultValue ?? '');
    const [brValue, setBrValue] = useState(() => isoToBrDate(defaultValue ?? ''));
    const [aberto, setAberto] = useState(false);
    const [posicao, setPosicao] = useState<{ top: number; left: number } | null>(null);
    const campoRef = useRef<HTMLDivElement>(null);
    const botaoRef = useRef<HTMLButtonElement>(null);
    const painelRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (controlled) {
            setBrValue(isoToBrDate(value ?? ''));
        }
    }, [controlled, value]);

    function handleChange(masked: string) {
        setBrValue(masked);
        const iso = brDateToIso(masked) ?? '';
        if (!controlled) {
            setIsoValue(iso);
        }
        onChange?.(iso);
    }

    function escolher(iso: string) {
        handleChange(isoToBrDate(iso));
        setAberto(false);
        botaoRef.current?.focus();
    }

    useLayoutEffect(() => {
        if (!aberto) {
            return;
        }

        function medir() {
            const rect = campoRef.current?.querySelector('input')?.getBoundingClientRect();

            if (!rect) {
                return;
            }

            const margem = 8;
            const left = Math.max(margem, Math.min(rect.left, window.innerWidth - margem - LARGURA_PAINEL));
            const espacoAbaixo = window.innerHeight - rect.bottom - margem;
            const abrirParaCima = espacoAbaixo < ALTURA_PAINEL && rect.top - margem > espacoAbaixo;
            const top = abrirParaCima ? Math.max(margem, rect.top - ALTURA_PAINEL - 4) : rect.bottom + 4;

            setPosicao({ top, left });
        }

        medir();
        window.addEventListener('resize', medir);
        window.addEventListener('scroll', medir, true);

        return () => {
            window.removeEventListener('resize', medir);
            window.removeEventListener('scroll', medir, true);
        };
    }, [aberto]);

    useEffect(() => {
        if (!aberto) {
            return;
        }

        function aoPointer(evento: PointerEvent) {
            const alvo = evento.target as Node;

            if (campoRef.current?.contains(alvo) || painelRef.current?.contains(alvo)) {
                return;
            }

            setAberto(false);
        }

        function aoTecla(evento: KeyboardEvent) {
            if (evento.key === 'Escape') {
                evento.preventDefault();
                setAberto(false);
                botaoRef.current?.focus();
            }
        }

        document.addEventListener('pointerdown', aoPointer);
        document.addEventListener('keydown', aoTecla);

        return () => {
            document.removeEventListener('pointerdown', aoPointer);
            document.removeEventListener('keydown', aoTecla);
        };
    }, [aberto]);

    const selecionada = isoParaData(controlled ? (value ?? '') : isoValue);
    const hoje = new Date();

    const painel =
        aberto && posicao
            ? createPortal(
                  <div
                      ref={painelRef}
                      role="dialog"
                      aria-label="Escolher data"
                      style={{ top: posicao.top, left: posicao.left, width: LARGURA_PAINEL }}
                      className="seletor-data fixed z-99999 rounded-xl border border-gray-200 bg-white p-3 text-gray-800 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900 dark:text-white/90"
                  >
                      <DayPicker
                          mode="single"
                          locale={ptBR}
                          autoFocus
                          captionLayout="dropdown"
                          startMonth={new Date(hoje.getFullYear() - 100, 0)}
                          endMonth={new Date(hoje.getFullYear() + 10, 11)}
                          defaultMonth={selecionada ?? hoje}
                          selected={selecionada}
                          onSelect={(data) => escolher(data ? dataParaIso(data) : '')}
                      />
                      <div className="mt-2 flex justify-between border-t border-gray-100 pt-2 dark:border-gray-800">
                          <button
                              type="button"
                              onClick={() => escolher('')}
                              className="rounded-lg px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5"
                          >
                              Limpar
                          </button>
                          <button
                              type="button"
                              onClick={() => escolher(dataParaIso(hoje))}
                              className="rounded-lg px-3 py-1.5 text-sm font-medium text-brand-500 hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-500/10"
                          >
                              Hoje
                          </button>
                      </div>
                  </div>,
                  document.body,
              )
            : null;

    return (
        <div ref={campoRef} className="relative">
            {name !== undefined && <input type="hidden" name={name} value={brDateToIso(brValue) ?? ''} />}
            <MaskedInput
                {...rest}
                className={`pr-11 ${className}`}
                mask="date"
                value={brValue}
                onChange={handleChange}
                placeholder={rest.placeholder ?? 'dd/mm/aaaa'}
                autoComplete="off"
            />
            <button
                ref={botaoRef}
                type="button"
                disabled={rest.disabled}
                aria-label="Abrir calendário"
                aria-haspopup="dialog"
                aria-expanded={aberto}
                onClick={() => setAberto((atual) => !atual)}
                className="absolute top-0 right-0 flex h-11 w-11 items-center justify-center rounded-r-lg text-gray-500 hover:text-brand-500 disabled:cursor-not-allowed disabled:opacity-40 dark:text-gray-400 dark:hover:text-brand-400"
            >
                <CalendarIcon />
            </button>
            {painel}
        </div>
    );
}
