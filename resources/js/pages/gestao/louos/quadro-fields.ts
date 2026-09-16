export interface AlteracaoField {
    key: string;
    label: string;
    kind: 'text' | 'number' | 'select' | 'textarea';
    required?: boolean;
    options?: { value: string; label: string }[];
    placeholder?: string;
    /** Campo de texto que vira lista (uma entrada por linha) no payload. */
    toArray?: boolean;
    /** Ocupa a linha inteira do grid no modal. */
    full?: boolean;
}

export interface Quadro7Item {
    id: number;
    cnae_code: string;
    formatted_code: string;
    grupo: string;
    subgrupo: string | null;
    area_min: number;
    area_max: number | null;
    observacao: string | null;
}

export interface Quadro10Item {
    id: number;
    zona: string;
    grupo_uso: string;
    subgrupo: string | null;
    permissao: string;
    permissao_label: string;
    condicionante_ref: string | null;
    base_legal: string | null;
    observacao: string | null;
}

export interface Quadro11Item {
    id: number;
    classe_via: string;
    grupo_uso: string | null;
    condicoes: string[] | null;
    base_legal: string | null;
    observacao: string | null;
}

export type QuadroItem = Quadro7Item | Quadro10Item | Quadro11Item;

export const PERMISSAO_OPTIONS = [
    { value: 'permitido', label: 'Permitido' },
    { value: 'permitido_condicionado', label: 'Permitido condicionado' },
    { value: 'proibido', label: 'Proibido' },
];

/**
 * Campos editáveis por Quadro numa linha do rascunho. Espelham a chave
 * natural e o esquema da tabela tipada validados no StoreLouosLinhaRequest.
 * A chave `quadro11` foi removida — o backend só expõe `quadro11a`.
 */
export const ALTERACAO_FIELDS: Record<string, AlteracaoField[]> = {
    quadro7: [
        { key: 'cnae_code', label: 'CNAE', kind: 'text', required: true, placeholder: '0000-0/00' },
        { key: 'area_min', label: 'Área mínima (m²)', kind: 'number', required: true, placeholder: '0' },
        { key: 'area_max', label: 'Área máxima (m²)', kind: 'number', placeholder: 'sem limite' },
        { key: 'grupo', label: 'Grupo', kind: 'text', required: true, placeholder: 'ex.: nR3' },
        { key: 'subgrupo', label: 'Subgrupo', kind: 'text', placeholder: 'ex.: nR3-99' },
    ],
    quadro10: [
        { key: 'zona', label: 'Zona', kind: 'text', required: true, placeholder: 'ex.: ZCAL.1' },
        { key: 'grupo_uso', label: 'Grupo de uso', kind: 'text', required: true },
        { key: 'subgrupo', label: 'Subgrupo', kind: 'text' },
        { key: 'permissao', label: 'Permissão', kind: 'select', required: true, options: PERMISSAO_OPTIONS },
        { key: 'condicionante_ref', label: 'Condicionante', kind: 'text' },
        { key: 'base_legal', label: 'Base legal', kind: 'text', full: true },
    ],
    quadro11a: [
        { key: 'classe_via', label: 'Classe de via', kind: 'text', required: true, placeholder: 'ex.: Via arterial' },
        { key: 'grupo_uso', label: 'Grupo de uso', kind: 'text' },
        { key: 'condicoes', label: 'Condições (uma por linha)', kind: 'textarea', toArray: true, full: true },
        { key: 'base_legal', label: 'Base legal', kind: 'text', full: true },
    ],
};

ALTERACAO_FIELDS.quadro11 = ALTERACAO_FIELDS.quadro11a;

export function fieldsFor(quadro: string): AlteracaoField[] {
    return ALTERACAO_FIELDS[quadro] ?? ALTERACAO_FIELDS.quadro7;
}

const OBSERVACAO_FIELD: AlteracaoField = { key: 'observacao', label: 'Observação', kind: 'textarea', full: true };

/**
 * Campos para o CRUD de uma linha do rascunho — inclui o campo `observacao` que
 * existe nas três tabelas tipadas mas não faz parte do modal de publicação (ALTERACAO_FIELDS).
 */
export function linhaFieldsFor(quadro: string): AlteracaoField[] {
    return [...fieldsFor(quadro), OBSERVACAO_FIELD];
}

/**
 * Monta o payload de uma linha a partir dos campos preenchidos: converte
 * números, transforma textareas em listas e descarta campos vazios.
 */
export function buildAlteracaoPayload(
    fields: AlteracaoField[],
    row: Record<string, string>,
): Record<string, unknown> {
    const payload: Record<string, unknown> = {};

    for (const field of fields) {
        const raw = (row[field.key] ?? '').trim();

        if (raw === '') {
            continue;
        }

        if (field.kind === 'number') {
            payload[field.key] = Number(raw);
        } else if (field.toArray) {
            const itens = raw
                .split('\n')
                .map((line) => line.trim())
                .filter((line) => line !== '');

            if (itens.length > 0) {
                payload[field.key] = itens;
            }
        } else {
            payload[field.key] = raw;
        }
    }

    return payload;
}

export function permissaoColor(permissao: string): 'success' | 'warning' | 'error' {
    if (permissao === 'permitido') {
        return 'success';
    }

    if (permissao === 'proibido') {
        return 'error';
    }

    return 'warning';
}

/** Formata a data ISO (YYYY-MM-DD) para dd/mm/aaaa sem deslocamento de fuso. */
export function formatarData(iso: string | null): string | null {
    if (!iso) {
        return null;
    }

    const [ano, mes, dia] = iso.split('-');

    return dia && mes && ano ? `${dia}/${mes}/${ano}` : iso;
}

/** Faixa de área legível: "0 – 350 m²" ou "≥ 200 m²" quando sem limite superior. */
export function faixaArea(min: number, max: number | null): string {
    const fmt = (valor: number) => valor.toLocaleString('pt-BR');

    return max === null ? `≥ ${fmt(min)} m²` : `${fmt(min)} – ${fmt(max)} m²`;
}
