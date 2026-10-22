import type { Geometry } from 'geojson';

export interface ZonaDoMapa {
    codigo: string;
    da_decisao: boolean;
    geojson: Geometry;
}

export interface PorcaoDoMapa {
    zona: string;
    area_m2: number;
    percentual: number;
    da_decisao: boolean;
    geojson: Geometry;
}

export interface LinhaCentral {
    tipo: 'ZCLMu' | 'ZCLMe';
    nome: string | null;
    geojson: Geometry;
    passa_sobre_lote?: boolean | null;
    dentro_m?: number | null;
    distancia_m?: number | null;
    ligacao?: Geometry | null;
}

export interface ViaDoMapa {
    cod_log: string | null;
    nome: string | null;
    classe: string | null;
    da_decisao: boolean;
    geojson: Geometry;
}

export interface DadosMapaDecisao {
    lote: Geometry | null;
    enderecamento: Geometry | null;
    ponto: { lat: number; lng: number } | null;
    area_lote_m2: number | null;
    zona: { codigo: string; fonte: string | null } | null;
    via: { cod_log: string; nome: string | null; classe: string | null } | null;
    zonas: ZonaDoMapa[];
    porcoes: PorcaoDoMapa[];
    linhas: LinhaCentral[];
    vias: ViaDoMapa[];
    indisponiveis: string[];
}

const numero = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 0 });
const decimal = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

export function formatarMetros(valor: number): string {
    return `${decimal.format(valor)} m`;
}

export function formatarArea(valor: number): string {
    return `${numero.format(valor)} m²`;
}

/** O desenho do mapa só serve quando há lote ou ponto e alguma camada de contexto. */
export function temCamadasDeContexto(dados: DadosMapaDecisao): boolean {
    return (dados.lote ?? dados.enderecamento ?? dados.ponto) !== null && (dados.zonas.length > 0 || dados.linhas.length > 0 || dados.vias.length > 0);
}

/** Lote que o mapa enquadra e mede: o do endereçamento, que decide a maior porção, senão o do requerente. */
export function loteDoMapa(dados: DadosMapaDecisao): Geometry | null {
    return dados.enderecamento ?? dados.lote;
}

/** Porção do lote que vence (maior porção) e as demais, na ordem em que o motor as mede. */
export function papelDaPorcao(porcoes: PorcaoDoMapa[], porcao: PorcaoDoMapa): 'unica' | 'maior' | 'menor' {
    if (porcoes.length <= 1) {
        return 'unica';
    }

    return porcao.da_decisao || porcoes[0] === porcao ? 'maior' : 'menor';
}

export function rotuloDaPorcao(porcoes: PorcaoDoMapa[], porcao: PorcaoDoMapa): string {
    return `${porcao.zona}: ${formatarArea(porcao.area_m2)} (${numero.format(porcao.percentual)}%)`;
}

/** Rótulo da zona do lote conforme a regra que a decidiu. */
export function rotuloDaZonaDoLote(dados: DadosMapaDecisao): string | null {
    if (!dados.zona) {
        return null;
    }

    if (dados.porcoes.length > 1) {
        return `${dados.zona.codigo} · maior porção do lote`;
    }

    return dados.zona.fonte === 'Ponto da inscrição' ? `${dados.zona.codigo} · zona do ponto` : `${dados.zona.codigo} · zona do lote`;
}

export function rotuloDaLinha(linha: LinhaCentral): string | null {
    if (linha.passa_sobre_lote && linha.dentro_m) {
        return `Linha da ${linha.tipo} dentro do lote: ${formatarMetros(linha.dentro_m)}`;
    }

    if (linha.distancia_m !== null && linha.distancia_m !== undefined && linha.ligacao) {
        return `${formatarMetros(linha.distancia_m)} até a linha da ${linha.tipo}`;
    }

    return null;
}
