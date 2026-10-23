import { router, useHttp } from "@inertiajs/react";
import { useState } from "react";
import FieldError from "@/components/form/field-error";
import Input from "@/components/form/input";
import Badge from "@/components/ui/badge";
import Button from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";

export interface CnaeComparado {
  codigo: string;
  descricao: string | null;
}

export interface CnaeInvalido extends CnaeComparado {
  codigo_digitos: string;
}

export interface ComparacaoCnaes {
  numero_viabilidade: string | null;
  sem_viabilidade: boolean;
  incluidas: CnaeComparado[];
  excluidas: CnaeComparado[];
  mantidas: CnaeComparado[];
  invalidas: CnaeInvalido[];
}

interface ComparacaoCnaesCardProps {
  processoId: number;
  comparacao: ComparacaoCnaes;
  /** Só quem pode alterar a ficha corrige o código inválido. */
  podeCorrigir: boolean;
}

/**
 * Alteração de atividade da JUCEB comparada com a viabilidade vinculada ao CNPJ na
 * SEFAZ: o que entra, o que sai, o que já existia e os códigos fora da lista
 * oficial, que o analista corrige aqui.
 */
export default function ComparacaoCnaesCard({
  processoId,
  comparacao,
  podeCorrigir,
}: ComparacaoCnaesCardProps) {
  return (
    <Card>
      <CardHeader
        title="Atividades × viabilidade da SEFAZ"
        description={
          comparacao.sem_viabilidade
            ? "O CNPJ não tem viabilidade vinculada: todas as atividades são tratadas como incluídas."
            : `Viabilidade vinculada: ${comparacao.numero_viabilidade ?? "—"}. A viabilidade nova mantém esse número.`
        }
      />
      <CardContent>
        <div className="flex flex-col gap-5">
          {comparacao.invalidas.length > 0 && (
            <section aria-label="Códigos inválidos">
              <Titulo cor="error">
                Código inválido ou divergente da lista oficial
              </Titulo>
              <ul className="mt-2 flex flex-col gap-3">
                {comparacao.invalidas.map((cnae) => (
                  <CorrigirCodigo
                    key={cnae.codigo_digitos}
                    processoId={processoId}
                    cnae={cnae}
                    podeCorrigir={podeCorrigir}
                  />
                ))}
              </ul>
            </section>
          )}
          <Grupo
            titulo="Incluídas"
            cor="success"
            itens={comparacao.incluidas}
            vazio="Nenhuma atividade incluída."
          />
          <Grupo
            titulo="Excluídas"
            cor="warning"
            itens={comparacao.excluidas}
            vazio="Nenhuma atividade excluída."
          />
          <Grupo
            titulo="Já existiam na viabilidade"
            cor="light"
            itens={comparacao.mantidas}
            vazio="Nenhuma."
          />
        </div>
      </CardContent>
    </Card>
  );
}

function Titulo({
  cor,
  children,
}: {
  cor: "success" | "warning" | "light" | "error";
  children: string;
}) {
  return (
    <Badge size="sm" color={cor}>
      {children}
    </Badge>
  );
}

function Grupo({
  titulo,
  cor,
  itens,
  vazio,
}: {
  titulo: string;
  cor: "success" | "warning" | "light";
  itens: CnaeComparado[];
  vazio: string;
}) {
  return (
    <section aria-label={titulo}>
      <Titulo cor={cor}>{titulo}</Titulo>
      {itens.length === 0 ? (
        <p className="mt-2 text-theme-xs text-gray-500 dark:text-gray-400">
          {vazio}
        </p>
      ) : (
        <ul className="mt-2 flex flex-col gap-1.5">
          {itens.map((cnae) => (
            <li
              key={cnae.codigo}
              className="text-theme-sm text-gray-700 dark:text-gray-300"
            >
              <span className="font-medium tabular-nums">{cnae.codigo}</span>
              {cnae.descricao ? (
                <span className="text-gray-500 dark:text-gray-400">
                  {" "}
                  — {cnae.descricao}
                </span>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

function CorrigirCodigo({
  processoId,
  cnae,
  podeCorrigir,
}: {
  processoId: number;
  cnae: CnaeInvalido;
  podeCorrigir: boolean;
}) {
  const [novo, setNovo] = useState("");
  const [erro, setErro] = useState<string | null>(null);
  const http = useHttp<
    { codigo_atual: string; codigo_novo: string },
    { status: string }
  >({ codigo_atual: cnae.codigo_digitos, codigo_novo: "" });

  function corrigir() {
    setErro(null);
    http.transform(() => ({
      codigo_atual: cnae.codigo_digitos,
      codigo_novo: novo,
    }));
    http.patch(`/gestao/processos/${processoId}/cnae-invalido`, {
      onSuccess: () => router.reload(),
      onError: (erros) => {
        const primeiro = Object.values(erros)[0];
        setErro(
          Array.isArray(primeiro)
            ? primeiro[0]
            : (primeiro ?? "Não foi possível corrigir o código."),
        );
      },
      onHttpException: () => {
        setErro("Não foi possível corrigir o código.");

        return false;
      },
    });
  }

  return (
    <li className="rounded-lg border border-error-200 p-3 dark:border-error-500/30">
      <p className="text-theme-sm font-medium tabular-nums text-gray-800 dark:text-white/90">
        {cnae.codigo}
      </p>
      {podeCorrigir ? (
        <div className="mt-2 flex flex-wrap items-start gap-2">
          <div className="w-44">
            <Input
              aria-label={`Código correto no lugar de ${cnae.codigo}`}
              placeholder="0000-0/00"
              value={novo}
              onChange={(evento) => setNovo(evento.target.value)}
            />
          </div>
          <Button
            size="sm"
            onClick={corrigir}
            disabled={http.processing || novo.trim() === ""}
          >
            Corrigir código
          </Button>
        </div>
      ) : null}
      <FieldError message={erro ?? undefined} />
    </li>
  );
}
