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

function linhaDaZona(dados: DadosMapaDecisao): string {
    if (!dados.zona) {
        return 'Zona não identificada no processo.';
    }

    const codigo = dados.zona.codigo;

    if (dados.zona.fonte === 'Ponto da inscrição') {
        return loteDoMapa(dados)
            ? `Zona ${codigo}: zona onde está o ponto da inscrição, porque a maior porção do lote não pôde ser calculada.`
            : `Zona ${codigo}: zona onde está o ponto da inscrição, porque o processo não tem o desenho do lote.`;
    }

    const [maior, ...demais] = dados.porcoes;

    if (!maior) {
        return `Zona ${codigo}: ${dados.zona.fonte ? dados.zona.fonte.toLowerCase() : 'zona gravada no processo'}.`;
    }

    if (demais.length === 0) {
        return `Zona ${codigo}: o lote está inteiro nela (${formatarArea(maior.area_m2)}).`;
    }

    const outras = demais.map((porcao) => `${porcao.zona} ocupa ${numero.format(porcao.percentual)}% (${formatarArea(porcao.area_m2)})`).join('; ');

    return `Zona ${codigo}: maior porção do lote (${numero.format(maior.percentual)}%, ${formatarArea(maior.area_m2)}). ${outras}.`;
}

function linhaDaCentralidade(dados: DadosMapaDecisao): string {
    const porCima = dados.linhas.filter((linha) => linha.passa_sobre_lote);

    if (porCima.length > 0) {
        return porCima
            .map((linha) => `A linha da ${linha.tipo} passa por cima do lote${linha.dentro_m ? `: ${formatarMetros(linha.dentro_m)} dentro dele` : ''}.`)
            .join(' ');
    }

    if (dados.linhas.length === 0) {
        return 'Nenhuma linha de ZCLMu ou ZCLMe no entorno do lote.';
    }

    const medidas = dados.linhas.filter((linha) => typeof linha.distancia_m === 'number');
    const proxima = [...medidas].sort((a, b) => (a.distancia_m ?? 0) - (b.distancia_m ?? 0))[0];

    return proxima
        ? `Nenhuma linha de ZCLMu ou ZCLMe passa por cima do lote. A mais próxima é a da ${proxima.tipo}, a ${formatarMetros(proxima.distancia_m ?? 0)}.`
        : 'Há linha de ZCLMu ou ZCLMe no entorno do lote, sem medida da distância.';
}

function linhaDaVia(dados: DadosMapaDecisao): string | null {
    if (!dados.via) {
        return null;
    }

    const nome = dados.via.nome ? `${dados.via.nome} (CODLOG ${dados.via.cod_log})` : `CODLOG ${dados.via.cod_log}`;
    const classe = dados.via.classe ? `classe ${dados.via.classe}` : 'sem classe viária publicada';
    const foraDoRecorte = dados.vias.some((via) => via.da_decisao) ? '' : ' A via não aparece no recorte do mapa.';

    return `Via: ${nome}, ${classe}.${foraDoRecorte}`;
}

/** Frases que explicam, com as medidas do mapa, a zona e a via gravadas no processo. */
export function explicacaoDaDecisao(dados: DadosMapaDecisao): string[] {
    const via = linhaDaVia(dados);

    return [linhaDaZona(dados), linhaDaCentralidade(dados), ...(via ? [via] : [])];
}
