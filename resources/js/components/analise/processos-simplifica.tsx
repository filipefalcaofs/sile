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

interface MetaSimplifica {
  total: number;
  pagina: number;
  por_pagina: number;
  paginas: number;
}

interface RespostaSimplifica {
  consultado: boolean;
  processos: ProcessoSimplifica[];
  meta: MetaSimplifica | null;
  erro: string | null;
}

type Estado =
  | { fase: "inativo" }
  | { fase: "carregando" }
  | { fase: "pronto"; processos: ProcessoSimplifica[]; meta: MetaSimplifica | null }
  | { fase: "erro"; mensagem: string };

/** Filtros da tela que o Simplifica entende (os demais dependem de listas do Viabiliza). */
export const CHAVES_CONSULTADAS = [
  "busca",
  "protocolo",
  "bap",
  "produto_tvl",
  "cnpj",
  "inscricao",
  "nome",
  "cnae",
  "complemento",
  "logradouro",
  "bairro",
  "cep",
  "analysis_status",
  "fluxo",
  "data_de",
  "data_ate",
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
 * Processos do Simplifica (sistema anterior) com os mesmos filtros da tela de
 * processos. Consulta só a API do legado, sem trazer nada para a base do
 * Viabiliza; é sempre somente leitura e deixa isso explícito.
 */
export default function ProcessosSimplifica({
  filtros,
  porPagina = 15,
}: {
  filtros: Partial<Record<(typeof CHAVES_CONSULTADAS)[number], string>>;
  porPagina?: number;
}) {
  const [estado, setEstado] = useState<Estado>({ fase: "inativo" });
  const [aberto, setAberto] = useState<string | null>(null);
  const [pagina, setPagina] = useState(1);
  const consulta = new URLSearchParams(
    CHAVES_CONSULTADAS.flatMap((chave) => {
      const valor = (filtros[chave] ?? "").trim();

      return valor === "" ? [] : [[chave, valor] as [string, string]];
    }),
  ).toString();

  useEffect(() => {
    setPagina(1);
  }, [consulta, porPagina]);

  useEffect(() => {
    if (consulta === "") {
      setEstado({ fase: "inativo" });

      return;
    }

    const controle = new AbortController();
    setEstado({ fase: "carregando" });

    fetch(`/gestao/processos/legado?${consulta}&pagina=${pagina}&per_page=${porPagina}`, {
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
          setEstado({ fase: "pronto", processos: dados.processos, meta: dados.meta });
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
  }, [consulta, pagina, porPagina]);

  return (
    <section aria-label="Processos do Simplifica">
      <div className="flex flex-wrap items-center gap-3 px-5 py-4">
        <h2 className="text-base font-semibold text-gray-800 dark:text-white/90">
          Processos do Simplifica (sistema legado)
        </h2>
        <Badge color="warning" size="sm">
          Somente leitura
        </Badge>
      </div>

      {estado.fase === "inativo" && (
        <p className="border-t border-gray-100 px-5 py-4 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
          Informe ao menos um filtro para consultar o Simplifica: texto com 3 ou
          mais caracteres, CNPJ/CPF, inscrição, número do processo ou da
          viabilidade, CEP, situação, fluxo ou período.
        </p>
      )}

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
          <>
            <div className="border-t border-gray-100 dark:border-gray-800">
              <DataTable
                columns={colunasSimplifica(setAberto)}
                rows={estado.processos}
                rowKey={(item) => item.numero}
                density="compact"
              />
            </div>
            {estado.meta && (
              <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-5 py-4 dark:border-gray-800">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                  {estado.meta.total === 1
                    ? "1 processo encontrado"
                    : `${estado.meta.total} processos encontrados`}
                  {" · "}página {estado.meta.pagina} de {Math.max(1, estado.meta.paginas)}
                </p>
                <div className="flex items-center gap-2">
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={estado.meta.pagina <= 1}
                    onClick={() => setPagina((atual) => Math.max(1, atual - 1))}
                  >
                    Anterior
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={estado.meta.pagina >= estado.meta.paginas}
                    onClick={() => setPagina((atual) => atual + 1)}
                  >
                    Próxima
                  </Button>
                </div>
              </div>
            )}
          </>
        ))}
      {aberto !== null && (
        <FichaSimplifica numero={aberto} onClose={() => setAberto(null)} />
      )}
    </section>
  );
}
