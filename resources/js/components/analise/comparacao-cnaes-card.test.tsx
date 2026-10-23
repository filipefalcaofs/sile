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

  it("lista incluídas, excluídas, já existentes e inválidas com código e descrição", () => {
    montar(false);

    expect(host.textContent).toContain("VIA-2026-77");
    expect(host.textContent).toContain("5611-2/01");
    expect(host.textContent).toContain("Minimercados");
    expect(host.textContent).toContain("Supermercados");
    expect(host.textContent).toContain("5611-9/99");
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

  it("avisa quando o CNPJ não tem viabilidade vinculada", () => {
    montar(false, {
      ...comparacao,
      sem_viabilidade: true,
      numero_viabilidade: null,
      invalidas: [],
    });

    expect(host.textContent).toContain(
      "todas as atividades são tratadas como incluídas",
    );
  });
});
