export type AreaEmbarqueRequerente = 'sim' | 'nao';

export type AreaEmbarqueExigida = 'sim' | 'nao' | 'na';

export interface VereditoEstacionamento {
    vagasRequeridas: number | null | undefined;
    vagasExigidas: number | null | undefined;
    embarqueRequerente: AreaEmbarqueRequerente | null | undefined;
    embarqueExigida: AreaEmbarqueExigida | null | undefined;
}

interface ParkingComEmbarque {
    area_embarque_requerente?: AreaEmbarqueRequerente | null;
}

/**
 * Copia o Sim/Não declarado na recepção para o lado do requerente,
 * sem sobrescrever o que o analista já gravou na ficha.
 */
export function comAreaEmbarqueInformada<T extends ParkingComEmbarque>(
    parking: T,
    declarado: string | null | undefined,
): T {
    if (Object.prototype.hasOwnProperty.call(parking, 'area_embarque_requerente')) {
        return parking;
    }

    const valor = areaEmbarqueDoTexto(declarado);

    if (valor === null) {
        return parking;
    }

    return { ...parking, area_embarque_requerente: valor };
}

/**
 * Veredito do bloco de estacionamento. Vagas seguem a comparação numérica.
 * A área de embarque só entra quando a norma foi informada: Sim exige Sim
 * do requerente; Não e Não se aplica não reprovam.
 */
export function vereditoEstacionamento(entrada: VereditoEstacionamento): boolean | null {
    const vagas = vereditoVagas(entrada.vagasRequeridas, entrada.vagasExigidas);
    const embarque = vereditoEmbarque(entrada.embarqueRequerente, entrada.embarqueExigida);

    if (vagas === false || embarque === false) {
        return false;
    }

    if (vagas === null) {
        return null;
    }

    return embarque === null ? vagas : vagas && embarque;
}

function vereditoVagas(
    requeridas: number | null | undefined,
    exigidas: number | null | undefined,
): boolean | null {
    if (typeof requeridas !== 'number' || typeof exigidas !== 'number') {
        return null;
    }

    return requeridas >= exigidas;
}

function vereditoEmbarque(
    requerente: AreaEmbarqueRequerente | null | undefined,
    exigida: AreaEmbarqueExigida | null | undefined,
): boolean | null {
    if (exigida === 'na' || exigida === 'nao') {
        return true;
    }

    if (exigida !== 'sim') {
        return null;
    }

    if (requerente !== 'sim' && requerente !== 'nao') {
        return null;
    }

    return requerente === 'sim';
}

function areaEmbarqueDoTexto(valor: string | null | undefined): AreaEmbarqueRequerente | null {
    const normalizado = valor?.trim().toLocaleLowerCase('pt-BR');

    if (normalizado === 'sim') {
        return 'sim';
    }

    if (normalizado === 'não' || normalizado === 'nao') {
        return 'nao';
    }

    return null;
}
