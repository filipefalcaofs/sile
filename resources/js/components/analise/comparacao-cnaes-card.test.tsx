// @vitest-environment happy-dom
import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, describe, expect, it, vi } from "vitest";
import ComparacaoCnaesCard, {
  type ComparacaoCnaes,
} from "./comparacao-cnaes-card";

vi.mock("@inertiajs/react", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@inertiajs/react")>()),
  router: { reload: vi.fn() },
  useHttp: () => ({ processing: false, transform: vi.fn(), patch: vi.fn() }),
}));

const comparacao: ComparacaoCnaes = {
  numero_viabilidade: "VIA-2026-77",
  sem_viabilidade: false,
  incluidas: [{ codigo: "5611-2/01", descricao: "Restaurantes" }],
  excluidas: [{ codigo: "4712-1/00", descricao: "Minimercados" }],
  mantidas: [{ codigo: "4711-3/02", descricao: "Supermercados" }],
  invalidas: [
    { codigo: "5611-9/99", codigo_digitos: "5611999", descricao: null },
  ],
};

describe("ComparacaoCnaesCard", () => {
  let root: Root;
  let host: HTMLDivElement;

  (
    globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }
  ).IS_REACT_ACT_ENVIRONMENT = true;

  afterEach(() => {
    act(() => {
      root.unmount();
    });
    host.remove();
  });

  function montar(podeCorrigir: boolean, dados: ComparacaoCnaes = comparacao) {
    host = document.createElement("div");
    document.body.appendChild(host);
    root = createRoot(host);
    act(() => {
      root.render(
        <ComparacaoCnaesCard
          processoId={7}
          comparacao={dados}
          podeCorrigir={podeCorrigir}
        />,
      );
    });
  }

  it("mostra só os códigos inválidos; incluídas, existentes e excluídas ficam nos quadros da ficha", () => {
    montar(false);

    expect(host.textContent).toContain("5611-9/99");
    expect(host.textContent).not.toContain("Supermercados");
    expect(host.textContent).not.toContain("Minimercados");
  });

  it("só oferece a correção a quem pode alterar a ficha", () => {
    montar(false);
    expect(host.textContent).not.toContain("Corrigir código");

    act(() => {
      root.unmount();
    });
    host.remove();
    montar(true);
    expect(host.textContent).toContain("Corrigir código");
  });

  it("sem código inválido não mostra nada", () => {
    montar(false, { ...comparacao, invalidas: [] });

    expect(host.textContent).toBe("");
  });
});
