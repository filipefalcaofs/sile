import {
  useCallback,
  useEffect,
  useLayoutEffect,
  useRef,
  useState,
} from "react";
import type { ReactNode } from "react";
import { createPortal } from "react-dom";
import { AlertIcon } from "@/components/icons";

interface PopoverAvisoProps {
  /** Rótulo acessível do botão (aria-label e tooltip). */
  label: string;
  /** Título do painel. */
  titulo: string;
  children: ReactNode;
}

const LARGURA_PAINEL = 320;
const MARGEM = 8;

/**
 * Ícone de aviso que abre um painel com texto completo. O painel vai para o
 * body com posição fixa, então o scroll horizontal da tabela não o corta e a
 * altura da linha não muda.
 */
export default function PopoverAviso({
  label,
  titulo,
  children,
}: PopoverAvisoProps) {
  const [aberto, setAberto] = useState(false);
  const [posicao, setPosicao] = useState<{ top: number; left: number } | null>(
    null,
  );
  const botaoRef = useRef<HTMLButtonElement>(null);
  const painelRef = useRef<HTMLDivElement>(null);

  const fechar = useCallback(() => setAberto(false), []);

  useLayoutEffect(() => {
    if (!aberto || !botaoRef.current) {
      return;
    }

    const botao = botaoRef.current.getBoundingClientRect();
    const altura = painelRef.current?.offsetHeight ?? 0;
    const left = Math.min(
      Math.max(MARGEM, botao.left),
      window.innerWidth - LARGURA_PAINEL - MARGEM,
    );
    const abaixo = botao.bottom + MARGEM;
    const top =
      abaixo + altura > window.innerHeight - MARGEM
        ? Math.max(MARGEM, botao.top - altura - MARGEM)
        : abaixo;

    setPosicao({ top, left });
  }, [aberto]);

  useEffect(() => {
    if (!aberto) {
      return;
    }

    const aoClicar = (evento: MouseEvent) => {
      const alvo = evento.target as Node;
      if (
        !painelRef.current?.contains(alvo) &&
        !botaoRef.current?.contains(alvo)
      ) {
        fechar();
      }
    };
    const aoTeclar = (evento: KeyboardEvent) => {
      if (evento.key === "Escape") {
        fechar();
        botaoRef.current?.focus();
      }
    };

    document.addEventListener("mousedown", aoClicar);
    document.addEventListener("keydown", aoTeclar);
    window.addEventListener("scroll", fechar, true);
    window.addEventListener("resize", fechar);

    return () => {
      document.removeEventListener("mousedown", aoClicar);
      document.removeEventListener("keydown", aoTeclar);
      window.removeEventListener("scroll", fechar, true);
      window.removeEventListener("resize", fechar);
    };
  }, [aberto, fechar]);

  return (
    <>
      <button
        ref={botaoRef}
        type="button"
        aria-label={label}
        aria-expanded={aberto}
        title={label}
        onClick={() => {
          setPosicao(null);
          setAberto((atual) => !atual);
        }}
        className="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-warning-50 text-warning-600 ring-1 ring-inset ring-warning-200 transition hover:bg-warning-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-warning-500 dark:bg-warning-500/15 dark:text-orange-400 dark:ring-warning-500/30 dark:hover:bg-warning-500/25"
      >
        <AlertIcon className="size-3.5 fill-current" />
      </button>
      {aberto &&
        createPortal(
          <div
            ref={painelRef}
            role="dialog"
            aria-label={titulo}
            style={{
              top: posicao?.top ?? 0,
              left: posicao?.left ?? 0,
              width: LARGURA_PAINEL,
              visibility: posicao ? "visible" : "hidden",
            }}
            className="fixed z-50 max-w-[calc(100vw-1rem)] rounded-xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark"
          >
            <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
              {titulo}
            </p>
            <p className="mt-1 text-theme-sm leading-relaxed break-words text-gray-600 dark:text-gray-300">
              {children}
            </p>
          </div>,
          document.body,
        )}
    </>
  );
}
