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
 * Códigos da alteração da JUCEB fora da lista oficial de CNAEs, que o analista
 * corrige aqui. Incluídas, existentes e excluídas aparecem nos quadros do
 * "Enquadramento por atividade" da ficha.
 */
export default function ComparacaoCnaesCard({
  processoId,
  comparacao,
  podeCorrigir,
}: ComparacaoCnaesCardProps) {
  if (comparacao.invalidas.length === 0) {
    return null;
  }

  return (
    <Card>
      <CardHeader
        title="Atividades com código inválido"
        description="Códigos que não constam da lista oficial de CNAEs. Corrija para seguir a análise."
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
        </div>
      </CardContent>
    </Card>
  );
}

function Titulo({
  cor,
  children,
}: {
  cor: "error";
  children: string;
}) {
  return (
    <Badge size="sm" color={cor}>
      {children}
    </Badge>
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
