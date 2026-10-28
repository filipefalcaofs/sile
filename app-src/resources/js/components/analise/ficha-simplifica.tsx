import { useEffect, useState, type ReactNode } from "react";
import { mascararDocumento } from "@/components/analise/processo-filtros";
import Badge from "@/components/ui/badge";
import Button from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";

interface Ficha {
  atualizado_em: string | null;
  finalizada: boolean | null;
  deferido: boolean | null;
  parecer: string | null;
  numero_tvl: number | null;
  condicionante: string | null;
  itens: {
    campo: string;
    deferido: boolean | null;
    em_analise: boolean | null;
  }[];
  atividades: {
    codigo: string | null;
    descricao: string | null;
    deferido: boolean | null;
    em_analise: boolean | null;
    incluida: boolean | null;
    excluida: boolean | null;
    enquadrado: boolean | null;
    enquadramento: string | null;
  }[];
}

export interface DetalheSimplifica {
  numero: string;
  protocolo: string | null;
  criado_em: string | null;
  situacao: string;
  finalizado: boolean;
  solicitante: { nome: string | null; documento: string | null };
  localizacao: { latitude: number | null; longitude: number | null } | null;
  parecer: {
    deferido: boolean | null;
    texto: string | null;
    tipo: string | null;
    atualizado_em: string | null;
    valido_ate: string | null;
  } | null;
  fichas: Ficha[];
  tvls: {
    numero: number | null;
    tipo: string | null;
    deferida: boolean | null;
    ativa: boolean | null;
    emissao: string | null;
    validade: string | null;
    situacao: string | null;
    razao_social: string | null;
    inscricao_imobiliaria: string | null;
    endereco: {
      logradouro: string | null;
      numero: string | null;
      bairro: string | null;
      cep: string | null;
    } | null;
    escritorio_virtual: { tipo: string; tvl_sede: number | null } | null;
  }[];
  historico: {
    em: string | null;
    titulo: string | null;
    mensagem: string | null;
    status: string | null;
    por: string | null;
  }[];
}

type Estado =
  | { fase: "carregando" }
  | { fase: "pronto"; processo: DetalheSimplifica }
  | { fase: "erro"; mensagem: string };

const ROTULO_ITEM: Record<string, string> = {
  razao_social: "Razão social",
  espaco: "Espaço",
  porte: "Porte",
  cep: "CEP",
  bairro: "Bairro",
  renovacao: "Renovação",
};

const ROTULO_TVL: Record<string, string> = {
  provisoria: "Provisória",
  definitiva: "Definitiva",
  preoperacional: "Pré-operacional",
  desconhecido: "Tipo não informado",
};

const ROTULO_SITUACAO: Record<string, string> = {
  vigente: "Vigente",
  a_vencer: "A vencer",
  vencida: "Vencida",
};

function data(iso: string | null): string {
  if (!iso) {
    return "—";
  }

  const valor = new Date(iso);

  return Number.isNaN(valor.getTime())
    ? iso
    : valor.toLocaleDateString("pt-BR");
}

function dataHora(iso: string | null): string {
  if (!iso) {
    return "—";
  }

  const valor = new Date(iso);

  return Number.isNaN(valor.getTime())
    ? iso
    : valor.toLocaleString("pt-BR", { dateStyle: "short", timeStyle: "short" });
}

function Resultado({
  deferido,
  emAnalise = false,
}: {
  deferido: boolean | null;
  emAnalise?: boolean | null;
}) {
  if (emAnalise) {
    return (
      <Badge color="warning" size="sm">
        Em análise
      </Badge>
    );
  }

  if (deferido === null) {
    return <span className="text-gray-400">—</span>;
  }

  return (
    <Badge color={deferido ? "success" : "error"} size="sm">
      {deferido ? "Deferido" : "Indeferido"}
    </Badge>
  );
}

function Secao({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <section className="mt-6">
      <h4 className="mb-2 text-sm font-semibold text-gray-800 dark:text-white/90">
        {titulo}
      </h4>
      {children}
    </section>
  );
}

function Campo({ rotulo, children }: { rotulo: string; children: ReactNode }) {
  return (
    <div>
      <dt className="text-theme-xs text-gray-500 dark:text-gray-400">
        {rotulo}
      </dt>
      <dd className="text-theme-sm text-gray-800 dark:text-white/90">
        {children ?? "—"}
      </dd>
    </div>
  );
}

function endereco(
  valor: DetalheSimplifica["tvls"][number]["endereco"],
): string {
  if (!valor) {
    return "—";
  }

  const partes = [
    valor.logradouro,
    valor.numero,
    valor.bairro,
    valor.cep,
  ].filter((parte) => parte && parte !== "");

  return partes.length > 0 ? partes.join(", ") : "—";
}

/**
 * Ficha completa de um processo do Simplifica, lida na hora da API do legado:
 * capa, parecer, fichas de análise (checklist e atividades), TVLs e histórico.
 */
export default function FichaSimplifica({
  numero,
  onClose,
}: {
  numero: string;
  onClose: () => void;
}) {
  const [estado, setEstado] = useState<Estado>({ fase: "carregando" });

  useEffect(() => {
    const controle = new AbortController();
    setEstado({ fase: "carregando" });

    fetch(
      `/gestao/processos/legado/detalhe?numero=${encodeURIComponent(numero)}`,
      {
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "same-origin",
        signal: controle.signal,
      },
    )
      .then((resposta) =>
        resposta.ok
          ? (resposta.json() as Promise<{
              processo: DetalheSimplifica | null;
              erro: string | null;
            }>)
          : Promise.reject(),
      )
      .then((dados) => {
        if (dados.erro) {
          setEstado({ fase: "erro", mensagem: dados.erro });
        } else if (dados.processo === null) {
          setEstado({
            fase: "erro",
            mensagem: "Processo não encontrado no Simplifica.",
          });
        } else {
          setEstado({ fase: "pronto", processo: dados.processo });
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
  }, [numero]);

  return (
    <Modal isOpen onClose={onClose} className="m-4 max-w-4xl p-6 sm:p-8">
      <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
        Processo {numero} · Simplifica
      </h3>
      <div
        role="note"
        className="mt-3 rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-theme-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200"
      >
        <strong>Dado do Simplifica (sistema legado) — somente leitura.</strong>{" "}
        Esta ficha não faz parte da base do Viabiliza e não pode ser alterada
        aqui.
      </div>

      {estado.fase === "carregando" && (
        <p className="mt-6 text-sm text-gray-500 dark:text-gray-400">
          Consultando o Simplifica…
        </p>
      )}

      {estado.fase === "erro" && (
        <p
          role="alert"
          className="mt-6 text-sm text-error-600 dark:text-error-400"
        >
          {estado.mensagem}
        </p>
      )}

      {estado.fase === "pronto" && <Conteudo processo={estado.processo} />}

      <div className="mt-8 flex justify-end">
        <Button type="button" variant="outline" size="sm" onClick={onClose}>
          Fechar
        </Button>
      </div>
    </Modal>
  );
}

function Conteudo({ processo }: { processo: DetalheSimplifica }) {
  const documento = processo.solicitante.documento;

  return (
    <>
      <dl className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Campo rotulo="Protocolo REGIN">{processo.protocolo}</Campo>
        <Campo rotulo="Protocolado em">{data(processo.criado_em)}</Campo>
        <Campo rotulo="Situação">
          <Badge color={processo.finalizado ? "success" : "info"} size="sm">
            {processo.situacao}
          </Badge>
        </Campo>
        <Campo rotulo="Solicitante">{processo.solicitante.nome}</Campo>
        <Campo rotulo="CPF/CNPJ do solicitante">
          {documento ? mascararDocumento(documento) : null}
        </Campo>
        <Campo rotulo="Ponto no mapa">
          {processo.localizacao
            ? `${processo.localizacao.latitude}, ${processo.localizacao.longitude}`
            : null}
        </Campo>
      </dl>

      {processo.parecer && (
        <Secao titulo="Parecer">
          <div className="flex flex-wrap items-center gap-2">
            <Resultado deferido={processo.parecer.deferido} />
            {processo.parecer.tipo && (
              <Badge color="light" size="sm">
                {ROTULO_TVL[processo.parecer.tipo] ?? processo.parecer.tipo}
              </Badge>
            )}
            <span className="text-theme-xs text-gray-500 dark:text-gray-400">
              {dataHora(processo.parecer.atualizado_em)}
              {processo.parecer.valido_ate
                ? ` · válido até ${data(processo.parecer.valido_ate)}`
                : ""}
            </span>
          </div>
          {processo.parecer.texto && (
            <p className="mt-2 whitespace-pre-line text-theme-sm text-gray-700 dark:text-gray-300">
              {processo.parecer.texto}
            </p>
          )}
        </Secao>
      )}

      <Secao titulo={`Fichas de análise (${processo.fichas.length})`}>
        {processo.fichas.length === 0 ? (
          <p className="text-theme-sm text-gray-500 dark:text-gray-400">
            Sem ficha de análise.
          </p>
        ) : (
          <div className="space-y-4">
            {processo.fichas.map((ficha, indice) => (
              <FichaCard key={indice} ficha={ficha} indice={indice + 1} />
            ))}
          </div>
        )}
      </Secao>

      <Secao titulo={`TVLs (${processo.tvls.length})`}>
        {processo.tvls.length === 0 ? (
          <p className="text-theme-sm text-gray-500 dark:text-gray-400">
            Sem TVL vigente.
          </p>
        ) : (
          <ul className="space-y-3">
            {processo.tvls.map((tvl) => (
              <li
                key={tvl.numero}
                className="rounded-lg border border-gray-200 p-3 dark:border-gray-800"
              >
                <div className="flex flex-wrap items-center gap-2">
                  <span className="font-medium tabular-nums text-gray-800 dark:text-white/90">
                    {tvl.numero}
                  </span>
                  <Badge color="light" size="sm">
                    {ROTULO_TVL[tvl.tipo ?? ""] ?? tvl.tipo ?? "—"}
                  </Badge>
                  {tvl.situacao && (
                    <Badge
                      color={tvl.situacao === "vencida" ? "error" : "success"}
                      size="sm"
                    >
                      {ROTULO_SITUACAO[tvl.situacao] ?? tvl.situacao}
                    </Badge>
                  )}
                  {tvl.escritorio_virtual && (
                    <Badge color="info" size="sm">
                      Escritório virtual ·{" "}
                      {tvl.escritorio_virtual.tipo === "sede"
                        ? "sede"
                        : `abrigado na ${tvl.escritorio_virtual.tvl_sede ?? "—"}`}
                    </Badge>
                  )}
                </div>
                <dl className="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                  <Campo rotulo="Razão social">{tvl.razao_social}</Campo>
                  <Campo rotulo="Inscrição imobiliária">
                    {tvl.inscricao_imobiliaria}
                  </Campo>
                  <Campo rotulo="Emissão">{data(tvl.emissao)}</Campo>
                  <Campo rotulo="Validade">{data(tvl.validade)}</Campo>
                  <Campo rotulo="Endereço">{endereco(tvl.endereco)}</Campo>
                </dl>
              </li>
            ))}
          </ul>
        )}
      </Secao>

      <Secao titulo={`Histórico (${processo.historico.length})`}>
        {processo.historico.length === 0 ? (
          <p className="text-theme-sm text-gray-500 dark:text-gray-400">
            Sem andamentos.
          </p>
        ) : (
          <ol className="space-y-3 border-l border-gray-200 pl-4 dark:border-gray-800">
            {processo.historico.map((item, indice) => (
              <li key={indice}>
                <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                  {item.status ?? item.titulo ?? "Andamento"}
                </p>
                <p className="text-theme-xs text-gray-500 dark:text-gray-400">
                  {dataHora(item.em)}
                  {item.por ? ` · ${item.por}` : ""}
                </p>
                {item.mensagem && (
                  <p className="mt-1 whitespace-pre-line text-theme-sm text-gray-700 dark:text-gray-300">
                    {item.mensagem}
                  </p>
                )}
              </li>
            ))}
          </ol>
        )}
      </Secao>
    </>
  );
}

function FichaCard({ ficha, indice }: { ficha: Ficha; indice: number }) {
  return (
    <div className="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
          Ficha {indice}
        </span>
        <Badge color={ficha.finalizada ? "success" : "warning"} size="sm">
          {ficha.finalizada ? "Finalizada" : "Em elaboração"}
        </Badge>
        <Resultado deferido={ficha.deferido} />
        <span className="text-theme-xs text-gray-500 dark:text-gray-400">
          {dataHora(ficha.atualizado_em)}
          {ficha.numero_tvl ? ` · TVL ${ficha.numero_tvl}` : ""}
        </span>
      </div>

      {ficha.parecer && (
        <p className="mt-2 whitespace-pre-line text-theme-sm text-gray-700 dark:text-gray-300">
          {ficha.parecer}
        </p>
      )}
      {ficha.condicionante && (
        <p className="mt-2 text-theme-sm text-gray-700 dark:text-gray-300">
          <span className="font-medium">Condicionante: </span>
          {ficha.condicionante}
        </p>
      )}

      {ficha.itens.length > 0 && (
        <ul className="mt-3 flex flex-wrap gap-2">
          {ficha.itens.map((item) => (
            <li
              key={item.campo}
              className="flex items-center gap-1.5 text-theme-xs text-gray-600 dark:text-gray-400"
            >
              {ROTULO_ITEM[item.campo] ?? item.campo}
              <Resultado deferido={item.deferido} emAnalise={item.em_analise} />
            </li>
          ))}
        </ul>
      )}

      {ficha.atividades.length > 0 && (
        <div className="mt-3 overflow-x-auto">
          <table className="w-full text-left text-theme-xs">
            <thead className="text-gray-500 dark:text-gray-400">
              <tr>
                <th className="py-1 pr-3 font-medium">CNAE</th>
                <th className="py-1 pr-3 font-medium">Atividade</th>
                <th className="py-1 pr-3 font-medium">Resultado</th>
                <th className="py-1 font-medium">Enquadramento</th>
              </tr>
            </thead>
            <tbody className="text-gray-700 dark:text-gray-300">
              {ficha.atividades.map((atividade, indice) => (
                <tr
                  key={`${atividade.codigo}-${indice}`}
                  className="border-t border-gray-100 dark:border-gray-800"
                >
                  <td className="py-1.5 pr-3 tabular-nums">
                    {atividade.codigo ?? "—"}
                  </td>
                  <td className="py-1.5 pr-3">
                    {atividade.descricao ?? "—"}
                    {atividade.incluida ? " (incluída)" : ""}
                    {atividade.excluida ? " (excluída)" : ""}
                  </td>
                  <td className="py-1.5 pr-3">
                    <Resultado
                      deferido={atividade.deferido}
                      emAnalise={atividade.em_analise}
                    />
                  </td>
                  <td className="py-1.5">
                    {atividade.enquadramento ??
                      (atividade.enquadrado === false ? "Não enquadrado" : "—")}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
