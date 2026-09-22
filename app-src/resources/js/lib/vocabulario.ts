export interface VocabularioItem {
    value: string;
    label: string;
}

export interface VocabularioCatalog {
    risco_municipal: VocabularioItem[];
    risco_sanitario: VocabularioItem[];
    analysis_category: VocabularioItem[];
    resultado_viabilidade: VocabularioItem[];
    quadro10_permissao: VocabularioItem[];
}

export function rotulo(
    itens: VocabularioItem[] | undefined,
    valor: string | null | undefined,
    fallback?: string,
): string {
    if (valor === null || valor === undefined || valor === '') {
        return fallback ?? '—';
    }

    return itens?.find((item) => item.value === valor)?.label ?? fallback ?? valor;
}
