import { useEffect, useId, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ChevronDownIcon } from '@/components/icons';

export interface MultiOptionSelectOption {
    value: string;
    label: string;
}

interface MultiOptionSelectProps {
    options: MultiOptionSelectOption[];
    value: string[];
    onChange: (value: string[]) => void;
    placeholder?: string;
    id?: string;
    disabled?: boolean;
    className?: string;
    emptyMessage?: string;
    ariaLabel?: string;
    /** Largura mínima do painel, em px. */
    panelMinWidth?: number;
}

interface PosicaoPainel {
    top?: number;
    bottom?: number;
    left: number;
    width: number;
    maxHeight: number;
}

/**
 * Lista de opções com marcação múltipla e busca. Mostra a descrição de cada
 * opção; quem usa guarda só os valores marcados.
 */
export default function MultiOptionSelect({
    options,
    value,
    onChange,
    placeholder = 'Todos',
    id,
    disabled = false,
    className = '',
    emptyMessage = 'Nenhuma opção disponível',
    ariaLabel,
    panelMinWidth = 360,
}: MultiOptionSelectProps) {
    const listaId = useId();
    const gatilhoRef = useRef<HTMLButtonElement>(null);
    const listaRef = useRef<HTMLDivElement>(null);
    const [aberto, setAberto] = useState(false);
    const [busca, setBusca] = useState('');
    const [posicao, setPosicao] = useState<PosicaoPainel | null>(null);

    const marcadas = options.filter((opcao) => value.includes(opcao.value));
    const visiveis = useMemo(() => {
        const termo = busca.trim().toLocaleLowerCase('pt-BR');

        if (termo === '') {
            return options;
        }

        return options.filter((opcao) => opcao.label.toLocaleLowerCase('pt-BR').includes(termo));
    }, [busca, options]);

    function alternar(opcao: MultiOptionSelectOption) {
        onChange(value.includes(opcao.value) ? value.filter((item) => item !== opcao.value) : [...value, opcao.value]);
    }

    useLayoutEffect(() => {
        if (!aberto) {
            return;
        }

        function medir() {
            const gatilho = gatilhoRef.current;

            if (!gatilho) {
                return;
            }

            const rect = gatilho.getBoundingClientRect();
            const margem = 8;
            const largura = Math.min(Math.max(rect.width, panelMinWidth), window.innerWidth - margem * 2);
            let left = rect.left;

            if (left + largura > window.innerWidth - margem) {
                left = Math.max(margem, window.innerWidth - margem - largura);
            }

            const espacoAbaixo = window.innerHeight - rect.bottom - margem;
            const espacoAcima = rect.top - margem;
            const abrirParaCima = espacoAbaixo < 220 && espacoAcima > espacoAbaixo;
            const maxHeight = Math.max(160, Math.min(360, abrirParaCima ? espacoAcima : espacoAbaixo));
            setPosicao(
                abrirParaCima
                    ? { bottom: window.innerHeight - rect.top + 4, left, width: largura, maxHeight }
                    : { top: rect.bottom + 4, left, width: largura, maxHeight },
            );
        }

        medir();
        window.addEventListener('resize', medir);
        window.addEventListener('scroll', medir, true);

        return () => {
            window.removeEventListener('resize', medir);
            window.removeEventListener('scroll', medir, true);
        };
    }, [aberto, panelMinWidth]);

    useEffect(() => {
        if (!aberto) {
            return;
        }

        function aoPointer(evento: PointerEvent) {
            const alvo = evento.target as Node;

            if (gatilhoRef.current?.contains(alvo) || listaRef.current?.contains(alvo)) {
                return;
            }

            setAberto(false);
        }

        function aoTecla(evento: KeyboardEvent) {
            if (evento.key === 'Escape') {
                evento.preventDefault();
                setAberto(false);
                gatilhoRef.current?.focus();
            }
        }

        document.addEventListener('pointerdown', aoPointer);
        document.addEventListener('keydown', aoTecla);

        return () => {
            document.removeEventListener('pointerdown', aoPointer);
            document.removeEventListener('keydown', aoTecla);
        };
    }, [aberto]);

    const resumo =
        marcadas.length === 0
            ? placeholder
            : marcadas.length === 1
              ? marcadas[0].label
              : `${marcadas.length} selecionados`;

    const painel =
        aberto && posicao
            ? createPortal(
                  <div
                      ref={listaRef}
                      id={listaId}
                      role="listbox"
                      aria-multiselectable="true"
                      aria-label={ariaLabel}
                      style={{ top: posicao.top, bottom: posicao.bottom, left: posicao.left, width: posicao.width, maxHeight: posicao.maxHeight }}
                      className="fixed z-99999 flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-theme-lg dark:border-gray-800 dark:bg-gray-900"
                  >
                      <div className="flex items-center gap-2 border-b border-gray-100 p-2 dark:border-gray-800">
                          <input
                              type="search"
                              value={busca}
                              placeholder="Buscar…"
                              autoComplete="off"
                              autoFocus
                              aria-label="Buscar opção"
                              onChange={(evento) => setBusca(evento.target.value)}
                              className="h-10 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                          />
                          {value.length > 0 && (
                              <button
                                  type="button"
                                  onClick={() => onChange([])}
                                  className="shrink-0 rounded-lg px-2 py-2 text-xs font-medium text-brand-600 hover:bg-gray-50 dark:text-brand-400 dark:hover:bg-white/5"
                              >
                                  Limpar
                              </button>
                          )}
                      </div>
                      <div className="flex flex-col gap-0.5 overflow-y-auto overscroll-contain p-1.5">
                          {visiveis.length === 0 ? (
                              <p className="px-3 py-2.5 text-sm text-gray-500 dark:text-gray-400">
                                  {busca.trim() !== '' ? 'Nenhuma opção encontrada' : emptyMessage}
                              </p>
                          ) : (
                              visiveis.map((opcao) => {
                                  const marcada = value.includes(opcao.value);

                                  return (
                                      <button
                                          key={opcao.value}
                                          type="button"
                                          role="option"
                                          aria-selected={marcada}
                                          className={`flex w-full items-start gap-2.5 rounded-lg px-2.5 py-2.5 text-left ${
                                              marcada
                                                  ? 'bg-brand-50 dark:bg-brand-500/10'
                                                  : 'hover:bg-gray-50 dark:hover:bg-white/5'
                                          }`}
                                          onMouseDown={(evento) => evento.preventDefault()}
                                          onClick={() => alternar(opcao)}
                                      >
                                          <span
                                              className={`mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border ${
                                                  marcada
                                                      ? 'border-brand-500 bg-brand-500 text-white'
                                                      : 'border-gray-300 dark:border-gray-600'
                                              }`}
                                              aria-hidden="true"
                                          >
                                              {marcada && <CheckIcon />}
                                          </span>
                                          <span className="block min-w-0 flex-1 text-sm leading-5 break-words whitespace-normal text-gray-800 dark:text-white/90">
                                              {opcao.label}
                                          </span>
                                      </button>
                                  );
                              })
                          )}
                      </div>
                  </div>,
                  document.body,
              )
            : null;

    return (
        <div className={`relative ${className}`}>
            <button
                ref={gatilhoRef}
                type="button"
                id={id}
                disabled={disabled}
                aria-label={ariaLabel}
                aria-haspopup="listbox"
                aria-expanded={aberto}
                aria-controls={listaId}
                title={marcadas.map((opcao) => opcao.label).join(', ') || undefined}
                onClick={() => {
                    setBusca('');
                    setAberto((atual) => !atual);
                }}
                className={`flex min-h-11 w-full items-center rounded-lg border border-gray-300 bg-transparent py-2.5 pr-10 pl-4 text-left text-sm shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:focus:border-brand-800 ${
                    marcadas.length > 0 ? 'text-gray-800 dark:text-white/90' : 'text-gray-400 dark:text-gray-400'
                }`}
            >
                <span className="block min-w-0 flex-1 break-words whitespace-normal">{resumo}</span>
                <ChevronDownIcon
                    className={`pointer-events-none absolute top-1/2 right-3 size-5 -translate-y-1/2 text-gray-500 transition-transform ${
                        aberto ? 'rotate-180' : ''
                    }`}
                    aria-hidden="true"
                />
            </button>
            {painel}
        </div>
    );
}

function CheckIcon() {
    return (
        <svg width="12" height="12" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path
                d="M3.5 8.25 6.5 11.25 12.5 4.75"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
