export const MASK_PATTERNS = {
    cpf: '999.999.999-99',
    phone: '(99) 99999-9999',
    cnpj: '99.999.999/9999-99',
    cep: '99999-999',
    date: '99/99/9999',
} as const;

export type MaskPattern = keyof typeof MASK_PATTERNS | 'inscricao';

export function applyMask(value: string, pattern: string): string {
    const digits = value.replace(/\D/g, '');
    let result = '';
    let digitIndex = 0;

    for (let i = 0; i < pattern.length && digitIndex < digits.length; i++) {
        if (pattern[i] === '9') {
            result += digits[digitIndex++];
        } else {
            result += pattern[i];
        }
    }

    return result;
}

export function applyDigitsOnly(value: string, maxLength = 20): string {
    return value.replace(/\D/g, '').slice(0, maxLength);
}

/**
 * Máscara do CNPJ alfanumérico (RFB): letras na raiz, DV numérico.
 */
export function applyAlphanumericCnpjMask(raw: string): string {
    const value = raw.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 14);
    const parts = [
        value.slice(0, 2),
        value.slice(2, 5),
        value.slice(5, 8),
        value.slice(8, 12),
        value.slice(12, 14),
    ];

    let result = parts[0];
    if (parts[1]) {
        result += `.${parts[1]}`;
    }
    if (parts[2]) {
        result += `.${parts[2]}`;
    }
    if (parts[3]) {
        result += `/${parts[3]}`;
    }
    if (parts[4]) {
        result += `-${parts[4]}`;
    }

    return result;
}

/** Formata dígitos digitados como reais (os dois últimos são centavos). */
export function applyCurrencyMask(raw: string): string {
    const digits = raw.replace(/\D/g, '').replace(/^0+(?=\d)/, '');

    if (digits === '') {
        return '';
    }

    const padded = digits.padStart(3, '0');
    const inteiro = padded.slice(0, -2);
    const decimal = padded.slice(-2);

    return `${inteiro.replace(/\B(?=(\d{3})+(?!\d))/g, '.')},${decimal}`;
}

/** Converte "1.111,78" no decimal "1111.78". */
export function brlToDecimal(masked: string): string {
    if (masked.trim() === '') {
        return '';
    }

    const digits = masked.replace(/\D/g, '').replace(/^0+(?=\d)/, '');

    if (digits === '') {
        return '';
    }

    const padded = digits.padStart(3, '0');
    const inteiro = padded.slice(0, -2).replace(/^0+(?=\d)/, '') || '0';

    return `${inteiro}.${padded.slice(-2)}`;
}

/** Converte o decimal "1111.78" para "1.111,78". */
export function decimalToBrl(decimal: string): string {
    const limpo = decimal.trim();

    if (limpo === '') {
        return '';
    }

    const [inteiroRaw, fracRaw = ''] = limpo.replace('-', '').split('.');
    const inteiro = inteiroRaw.replace(/\D/g, '') || '0';
    const frac = `${fracRaw.replace(/\D/g, '')}00`.slice(0, 2);

    return applyCurrencyMask(`${inteiro}${frac}`);
}

export function isoToBrDate(iso: string): string {
    const match = iso.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) {
        return '';
    }

    return `${match[3]}/${match[2]}/${match[1]}`;
}

export function brDateToIso(br: string): string | null {
    const match = br.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (!match) {
        return null;
    }

    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3]);

    if (month < 1 || month > 12 || day < 1 || day > 31) {
        return null;
    }

    const date = new Date(year, month - 1, day);
    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return null;
    }

    return `${match[3]}-${match[2]}-${match[1]}`;
}
