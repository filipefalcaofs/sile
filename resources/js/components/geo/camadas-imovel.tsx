import { textoZona, type ZonaCamada } from './camadas-imovel-zona';

export interface CamadaPoligonal {
    regra: string;
    titulo?: string;
    camada?: string;
    status: 'dentro' | 'fora' | 'indisponivel' | string;
    elemento?: string | null;
    incide?: boolean | null;
}

export interface CamadasImovel {
    identificado_em?: string;
    logradouro_tributario?: { cod_log: string; nome: string | null } | null;
    logradouros_proximos?: { cod_log: string | null; nome: string | null; distancia_m: number }[];
    bairro?: { status: string; nome: string | null } | null;
    zona?: ZonaCamada | null;
    via?: string | null;
    camadas?: CamadaPoligonal[];
}

function textoDimensao(dimensao?: { status: string; nome: string | null } | null): string {
    if (!dimensao) {
        return '—';
    }

    if (dimensao.status === 'identificado' && dimensao.nome) {
        return dimensao.nome;
    }

    return dimensao.status === 'indisponivel' ? 'Camada indisponível' : 'Não identificado';
}

function textoCamada(camada: CamadaPoligonal): string {
    if (camada.status === 'indisponivel') {
        return 'Camada indisponível';
    }

    if (camada.status === 'fora') {
        return 'Fora da camada';
    }

    const elemento = camada.elemento ?? 'Dentro da camada';

    return camada.incide ? `${elemento} — regra incide` : elemento;
}

/**
 * Camadas geográficas do imóvel: cada camada e o elemento identificado nela
 * (logradouro, bairro, zona, trecho da borda marítima, loteamento...).
 */
export default function CamadasImovelLista({
    camadas,
    somentePoligonais = false,
}: {
    camadas: CamadasImovel;
    /** Oculta logradouro, bairro, zona e via — a ficha já os mostra na localização. */
    somentePoligonais?: boolean;
}) {
    const identificacao: { camada: string; elemento: string; destaque?: boolean }[] = somentePoligonais
        ? []
        : [
              {
                  camada: 'Logradouro da inscrição (SEFAZ)',
                  elemento: camadas.logradouro_tributario
                      ? `${camadas.logradouro_tributario.cod_log} — ${camadas.logradouro_tributario.nome ?? ''}`.trim()
                      : '—',
              },
              { camada: 'Bairro', elemento: textoDimensao(camadas.bairro) },
              { camada: 'Zona', elemento: textoZona(camadas.zona) },
              { camada: 'Via', elemento: camadas.via?.trim() ? camadas.via : '—' },
          ];
    const linhas: { camada: string; elemento: string; destaque?: boolean }[] = [
        ...identificacao,
        ...(camadas.camadas ?? []).map((camada) => ({
            camada: camada.titulo ?? camada.regra,
            elemento: textoCamada(camada),
            destaque: camada.incide === true,
        })),
    ];

    return (
        <dl className="divide-y divide-gray-100 dark:divide-gray-800">
            {linhas.map((linha) => (
                <div key={linha.camada} className="grid gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt className="text-sm text-gray-500 dark:text-gray-400">{linha.camada}</dt>
                    <dd
                        className={`text-sm sm:col-span-2 ${
                            linha.destaque
                                ? 'font-medium text-warning-600 dark:text-warning-500'
                                : 'text-gray-800 dark:text-white/90'
                        }`}
                    >
                        {linha.elemento}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
