const formato = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 });

/** Área em metros no formato brasileiro. "1250.00" vira "1.250"; "1250.01" vira "1.250,01". */
export function formatarMetros(valor: string | number | null | undefined): string | null {
    if (valor === null || valor === undefined || valor === '') {
        return null;
    }

    const numero = typeof valor === 'number' ? valor : Number(String(valor).trim());

    if (!Number.isFinite(numero)) {
        return null;
    }

    return formato.format(numero);
}

/** Área já com a unidade, ou null quando o valor não existe. */
export function metrosComUnidade(valor: string | number | null | undefined): string | null {
    const texto = formatarMetros(valor);

    return texto === null ? null : `${texto} m²`;
}
