import { useEffect, useId, useLayoutEffect, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent as ReactKeyboardEvent } from 'react';
import { createPortal } from 'react-dom';
import { ChevronDownIcon } from '@/components/icons';

export interface OptionSelectOption {
    value: string;
    label: string;
    description?: string;
    meta?: string;
}

interface OptionSelectProps {
    options: OptionSelectOption[];
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    id?: string;
    name?: string;
    disabled?: boolean;
    className?: string;
    emptyMessage?: string;
    error?: boolean;
    hint?: string;
    /** Exibe um campo de busca dentro da lista. Ligado por padrão. */
    searchable?: boolean;
}

interface PosicaoPainel {
    top: number;
    left: number;
    width: number;
    maxHeight: number;
}

/**
 * Lista de opções de formulário com o texto inteiro visível.
 * O select nativo corta o rótulo na largura do campo.
 */
export default function OptionSelect({
    options,
    value,
    onChange,
    placeholder = 'Selecione uma opção',
    id,
    name,
    disabled = false,
    className = '',
    emptyMessage = 'Nenhuma opção disponível',
    error = false,
    hint,
    searchable = true,
}: OptionSelectProps) {
    const listaId = useId();
    const hintId = useId();
    const gatilhoRef = useRef<HTMLButtonElement>(null);
    const listaRef = useRef<HTMLDivElement>(null);
    const ativoRef = useRef(0);
    const [aberto, setAberto] = useState(false);
    const [ativo, setAtivo] = useState(0);
    const [busca, setBusca] = useState('');
    const [posicao, setPosicao] = useState<PosicaoPainel | null>(null);

    const selecionada = options.find((opcao) => opcao.value === value);
    const visiveis = useMemo(() => {
        const termo = busca.trim().toLocaleLowerCase('pt-BR');

        if (!searchable || termo === '') {
            return options;
        }

        return options.filter((opcao) =>
            `${opcao.label} ${opcao.description ?? ''} ${opcao.meta ?? ''}`.toLocaleLowerCase('pt-BR').includes(termo),
        );
    }, [busca, options, searchable]);
    ativoRef.current = ativo;

    function escolher(opcao: OptionSelectOption | undefined) {
        if (!opcao) {
            return;
        }

        onChange(opcao.value);
        setAberto(false);
        gatilhoRef.current?.focus();
    }

    function abrir() {
        if (disabled) {
            return;
        }

        const indice = options.findIndex((opcao) => opcao.value === value);
        setBusca('');
        setAtivo(indice >= 0 ? indice : 0);
        setAberto(true);
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
            const largura = Math.min(Math.max(rect.width, 576), window.innerWidth - margem * 2);
            let left = rect.left;

            if (left + largura > window.innerWidth - margem) {
                left = Math.max(margem, window.innerWidth - margem - largura);
            }

            const espacoAbaixo = window.innerHeight - rect.bottom - margem;
            const espacoAcima = rect.top - margem;
            const abrirParaCima = espacoAbaixo < 220 && espacoAcima > espacoAbaixo;
            const maxHeight = Math.max(160, Math.min(360, abrirParaCima ? espacoAcima : espacoAbaixo));
            const top = abrirParaCima ? Math.max(margem, rect.top - maxHeight - 4) : rect.bottom + 4;

            setPosicao({ top, left, width: largura, maxHeight });
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

                return;
            }

            if (visiveis.length === 0) {
                return;
            }

            if (evento.key === 'ArrowDown') {
                evento.preventDefault();
                setAtivo((indice) => Math.min(visiveis.length - 1, indice + 1));
            } else if (evento.key === 'ArrowUp') {
                evento.preventDefault();
                setAtivo((indice) => Math.max(0, indice - 1));
            } else if (evento.key === 'Home') {
                evento.preventDefault();
                setAtivo(0);
            } else if (evento.key === 'End') {
                evento.preventDefault();
                setAtivo(visiveis.length - 1);
            } else if (evento.key === 'Enter') {
                evento.preventDefault();
                const opcao = visiveis[ativoRef.current];

                if (opcao) {
                    onChange(opcao.value);
                    setAberto(false);
                    gatilhoRef.current?.focus();
                }
            }
        }

        document.addEventListener('pointerdown', aoPointer);
        document.addEventListener('keydown', aoTecla);

        return () => {
            document.removeEventListener('pointerdown', aoPointer);
            document.removeEventListener('keydown', aoTecla);
        };
    }, [aberto, onChange, visiveis]);

    useEffect(() => {
        if (!aberto) {
            return;
        }

        const item = listaRef.current?.querySelector('[data-active="true"]');

        if (item instanceof HTMLElement && typeof item.scrollIntoView === 'function') {
            item.scrollIntoView({ block: 'nearest' });
        }
    }, [aberto, ativo]);

    function aoTeclaGatilho(evento: ReactKeyboardEvent<HTMLButtonElement>) {
        if (aberto || disabled) {
            return;
        }

        if (evento.key === 'ArrowDown' || evento.key === 'Enter' || evento.key === ' ') {
            evento.preventDefault();
            abrir();
        }
    }

    const painel =
        aberto && posicao
            ? createPortal(
                  <div
                      ref={listaRef}
                      id={listaId}
                      role="listbox"
                      aria-labelledby={id}
                      style={{
                          top: posicao.top,
                          left: posicao.left,
                          width: posicao.width,
                          maxHeight: posicao.maxHeight,
                      }}
                      className="fixed z-99999 flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-theme-lg dark:border-gray-800 dark:bg-gray-900"
                  >
                      {searchable && (
                          <div className="border-b border-gray-100 p-2 dark:border-gray-800">
                              <label htmlFor={`${listaId}-busca`} className="sr-only">
                                  Buscar opção
                              </label>
                              <input
                                  id={`${listaId}-busca`}
                                  type="search"
                                  value={busca}
                                  placeholder="Buscar…"
                                  autoComplete="off"
                                  autoFocus
                                  onChange={(evento) => {
                                      setBusca(evento.target.value);
                                      setAtivo(0);
                                  }}
                                  className="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                              />
                          </div>
                      )}
                      <div className="flex flex-col gap-0.5 overflow-y-auto overscroll-contain p-1.5">
                          {visiveis.length === 0 ? (
                              <p className="px-3 py-2.5 text-sm text-gray-500 dark:text-gray-400">
                                  {busca.trim() !== '' ? 'Nenhuma opção encontrada' : emptyMessage}
                              </p>
                          ) : (
                              visiveis.map((opcao, indice) => {
                                  const marcada = opcao.value === value;
                                  const destacada = indice === ativo;

                                  return (
                                      <button
                                          key={opcao.value}
                                          type="button"
                                          role="option"
                                          id={`${listaId}-opcao-${indice}`}
                                          aria-selected={marcada}
                                          data-active={destacada ? 'true' : 'false'}
                                          className={`flex w-full items-start gap-2.5 rounded-lg px-2.5 py-2.5 text-left ${
                                              marcada
                                                  ? 'bg-brand-50 dark:bg-brand-500/10'
                                                  : destacada
                                                    ? 'bg-gray-50 dark:bg-white/5'
                                                    : 'hover:bg-gray-50 dark:hover:bg-white/5'
                                          }`}
                                          onMouseDown={(evento) => evento.preventDefault()}
                                          onMouseEnter={() => setAtivo(indice)}
                                          onClick={() => escolher(opcao)}
                                      >
                                          <span
                                              className={`mt-0.5 flex size-4 shrink-0 items-center justify-center ${
                                                  marcada ? 'text-brand-600 dark:text-brand-400' : 'text-transparent'
                                              }`}
                                              aria-hidden="true"
                                          >
                                              <CheckIcon />
                                          </span>
                                          <span className="min-w-0 flex-1">
                                              <span className="block whitespace-normal break-words text-sm leading-5 text-gray-800 dark:text-white/90">
                                                  {opcao.label}
                                              </span>
                                              {opcao.description && (
                                                  <span className="mt-0.5 block whitespace-normal break-words text-sm leading-5 text-gray-600 dark:text-gray-300">
                                                      {opcao.description}
                                                  </span>
                                              )}
                                              {opcao.meta && (
                                                  <span className="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                                      {opcao.meta}
                                                  </span>
                                              )}
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
            {name && <input type="hidden" name={name} value={value} />}
            <button
                ref={gatilhoRef}
                type="button"
                id={id}
                disabled={disabled}
                aria-haspopup="listbox"
                aria-expanded={aberto}
                aria-controls={listaId}
                aria-invalid={error || undefined}
                aria-describedby={hint ? hintId : undefined}
                aria-activedescendant={aberto ? `${listaId}-opcao-${ativo}` : undefined}
                title={selecionada?.label}
                onClick={() => (aberto ? setAberto(false) : abrir())}
                onKeyDown={aoTeclaGatilho}
                className={`flex min-h-11 w-full items-center rounded-lg border bg-transparent py-2.5 pr-10 pl-4 text-left text-sm shadow-theme-xs focus:ring-3 focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-50 dark:bg-gray-900 ${
                    error
                        ? 'border-error-500 focus:border-error-300 focus:ring-error-500/20 dark:border-error-500'
                        : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:focus:border-brand-800'
                } ${selecionada ? 'text-gray-800 dark:text-white/90' : 'text-gray-400 dark:text-gray-400'}`}
            >
                <span className="block min-w-0 flex-1 whitespace-normal break-words">
                    {selecionada?.label ?? placeholder}
                </span>
                <ChevronDownIcon
                    className={`pointer-events-none absolute top-1/2 right-3 size-5 -translate-y-1/2 text-gray-500 transition-transform ${
                        aberto ? 'rotate-180' : ''
                    }`}
                    aria-hidden="true"
                />
            </button>
            {hint && (
                <p id={hintId} role={error ? 'alert' : undefined} className={`pt-1.5 text-xs ${error ? 'text-error-500' : 'text-gray-500'}`}>
                    {hint}
                </p>
            )}
            {painel}
        </div>
    );
}

function CheckIcon() {
    return (
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path
                d="M3.5 8.25 6.5 11.25 12.5 4.75"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
