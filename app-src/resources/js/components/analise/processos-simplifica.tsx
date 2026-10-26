import { useEffect, useState } from "react";
import FichaSimplifica from "@/components/analise/ficha-simplifica";
import Badge from "@/components/ui/badge";
import Button from "@/components/ui/button";
import DataTable from "@/components/ui/data-table/data-table";
import type { ColumnDef } from "@/components/ui/data-table/types";

export interface ProcessoSimplifica {
  numero: string;
  protocolo: string | null;
  criado_em: string | null;
  situacao: string;
  finalizado: boolean;
  tvls: number[];
}

interface RespostaSimplifica {
  consultado: boolean;
  processos: ProcessoSimplifica[];
  erro: string | null;
}

type Estado =
  | { fase: "inativo" }
  | { fase: "carregando" }
  | { fase: "pronto"; processos: ProcessoSimplifica[] }
  | { fase: "erro"; mensagem: string };

const CHAVES_CONSULTADAS = [
  "busca",
  "cnpj",
  "inscricao",
  "produto_tvl",
  "protocolo",
] as const;

function formatarData(iso: string | null): string {
  if (!iso) {
    return "—";
  }

  const data = new Date(iso);

  return Number.isNaN(data.getTime()) ? iso : data.toLocaleDateString("pt-BR");
}

function colunasSimplifica(
  onAbrir: (numero: string) => void,
): ColumnDef<ProcessoSimplifica>[] {
  return [
    {
      id: "numero",
      header: "Processo",
      cell: (item) => (
        <span className="font-medium tabular-nums">{item.numero}</span>
      ),
    },
    {
      id: "protocolo",
      header: "Protocolo REGIN",
      cell: (item) => item.protocolo ?? "—",
    },
    {
      id: "criado_em",
      header: "Protocolado em",
      cell: (item) => formatarData(item.criado_em),
    },
    {
      id: "situacao",
      header: "Situação",
      cell: (item) => (
        <Badge color={item.finalizado ? "success" : "info"} size="sm">
          {item.situacao}
        </Badge>
      ),
    },
    {
      id: "tvls",
      header: "TVL",
      cell: (item) =>
        item.tvls.length > 0 ? (
          <span className="tabular-nums">{item.tvls.join(", ")}</span>
        ) : (
          "—"
        ),
    },
    {
      id: "ficha",
      header: "",
      align: "end",
      cell: (item) => (
        <Button
          type="button"
          size="sm"
          variant="outline"
          onClick={() => onAbrir(item.numero)}
        >
          Ver ficha
        </Button>
      ),
    },
  ];
}

/**
 * Processos do Simplifica (sistema anterior) para a busca da tela de processos.
 * Consulta só a API do legado, sem trazer nada para a base do Viabiliza; some
 * quando a busca não é uma chave que o Simplifica entenda.
 */
export default function ProcessosSimplifica({
  filtros,
}: {
  filtros: Partial<Record<(typeof CHAVES_CONSULTADAS)[number], string>>;
}) {
  const [estado, setEstado] = useState<Estado>({ fase: "inativo" });
  const [aberto, setAberto] = useState<string | null>(null);
  const consulta = new URLSearchParams(
    CHAVES_CONSULTADAS.flatMap((chave) => {
      const valor = (filtros[chave] ?? "").trim();

      return valor === "" ? [] : [[chave, valor] as [string, string]];
    }),
  ).toString();

  useEffect(() => {
    if (consulta === "") {
      setEstado({ fase: "inativo" });

      return;
    }

    const controle = new AbortController();
    setEstado({ fase: "carregando" });

    fetch(`/gestao/processos/legado?${consulta}`, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
      signal: controle.signal,
    })
      .then((resposta) =>
        resposta.ok
          ? (resposta.json() as Promise<RespostaSimplifica>)
          : Promise.reject(),
      )
      .then((dados) => {
        if (!dados.consultado) {
          setEstado({ fase: "inativo" });
        } else if (dados.erro) {
          setEstado({ fase: "erro", mensagem: dados.erro });
        } else {
          setEstado({ fase: "pronto", processos: dados.processos });
        }
      })
      .catch(() => {
        if (!controle.signal.aborted) {
          setEstado({
            fase: "erro",
            mensagem: "Não foi possível consultar o Simplifica agora.",
          });
        }
      });

    return () => controle.abort();
  }, [consulta]);

  if (estado.fase === "inativo") {
    return null;
  }

  return (
    <section
      aria-label="Processos do Simplifica"
      className="mt-6 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
    >
      <div className="px-5 py-4">
        <div className="flex flex-wrap items-center gap-3">
          <h2 className="text-base font-semibold text-gray-800 dark:text-white/90">
            Processos do Simplifica (sistema legado)
          </h2>
          <Badge color="warning" size="sm">
            Somente leitura
          </Badge>
        </div>
        <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
          Dados do Simplifica, consultados na hora. Não fazem parte da base do
          Viabiliza e não podem ser alterados aqui.
        </p>
      </div>

      {estado.fase === "carregando" && (
        <p className="border-t border-gray-100 px-5 py-4 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
          Consultando o Simplifica…
        </p>
      )}

      {estado.fase === "erro" && (
        <p
          role="alert"
          className="border-t border-gray-100 px-5 py-4 text-sm text-error-600 dark:border-gray-800 dark:text-error-400"
        >
          {estado.mensagem}
        </p>
      )}

      {estado.fase === "pronto" &&
        (estado.processos.length === 0 ? (
          <p className="border-t border-gray-100 px-5 py-4 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
            Nenhum processo do Simplifica para esta busca.
          </p>
        ) : (
          <div className="border-t border-gray-100 dark:border-gray-800">
            <DataTable
              columns={colunasSimplifica(setAberto)}
              rows={estado.processos}
              rowKey={(item) => item.numero}
              density="compact"
            />
          </div>
        ))}
      {aberto !== null && (
        <FichaSimplifica numero={aberto} onClose={() => setAberto(null)} />
      )}
    </section>
  );
}
