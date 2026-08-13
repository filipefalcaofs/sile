import { describe, expect, it } from 'vitest';
import {
    readClosedGroups,
    resolveActiveGroup,
    STORAGE_KEY,
    toggleClosed,
    withGroupOpen,
    writeClosedGroups,
} from './sidebar-collapse';

function createMemoryStorage(initial: Record<string, string> = {}): Storage {
    const map = new Map<string, string>(Object.entries(initial));
    const storage: Storage = {
        get length() {
            return map.size;
        },
        clear: () => map.clear(),
        getItem: (key) => (map.has(key) ? (map.get(key) as string) : null),
        key: (index) => Array.from(map.keys())[index] ?? null,
        removeItem: (key) => {
            map.delete(key);
        },
        setItem: (key, value) => {
            map.set(key, value);
        },
    };

    return storage;
}

const groups = [
    { label: 'Análise técnica', items: [{ href: '/gestao/processos/fila' }, { href: '/gestao/processos' }] },
    { label: 'Regras do licenciamento', items: [{ href: '/gestao/cnaes' }, { href: '/gestao/risco' }] },
];

describe('resolveActiveGroup', () => {
    it('retorna o label da categoria que contém o item ativo', () => {
        expect(resolveActiveGroup(groups, '/gestao/cnaes')).toBe('Regras do licenciamento');
    });

    it('retorna null quando nenhum item corresponde ao href ativo', () => {
        expect(resolveActiveGroup(groups, '/gestao/inexistente')).toBeNull();
    });

    it('retorna null quando não há href ativo', () => {
        expect(resolveActiveGroup(groups, null)).toBeNull();
    });
});

describe('readClosedGroups / writeClosedGroups', () => {
    it('faz round-trip da lista de categorias fechadas no storage', () => {
        const storage = createMemoryStorage();
        writeClosedGroups(['Administração', 'Auditoria e compliance'], storage);
        expect(readClosedGroups(storage)).toEqual(['Administração', 'Auditoria e compliance']);
    });

    it('retorna lista vazia quando não há nada salvo', () => {
        expect(readClosedGroups(createMemoryStorage())).toEqual([]);
    });

    it('retorna lista vazia quando o conteúdo salvo é inválido', () => {
        const storage = createMemoryStorage({ [STORAGE_KEY]: 'isto-não-é-json' });
        expect(readClosedGroups(storage)).toEqual([]);
    });

    it('ignora valores não-string dentro do array salvo', () => {
        const storage = createMemoryStorage({ [STORAGE_KEY]: JSON.stringify(['Administração', 42, null]) });
        expect(readClosedGroups(storage)).toEqual(['Administração']);
    });

    it('retorna lista vazia e não lança quando o storage está indisponível', () => {
        expect(readClosedGroups(null)).toEqual([]);
        expect(() => writeClosedGroups(['Administração'], null)).not.toThrow();
    });
});

describe('toggleClosed', () => {
    it('adiciona a categoria quando ela está aberta', () => {
        expect(toggleClosed([], 'Administração')).toEqual(['Administração']);
    });

    it('remove a categoria quando ela já está fechada', () => {
        expect(toggleClosed(['Administração', 'Início'], 'Administração')).toEqual(['Início']);
    });

    it('não muta o array original', () => {
        const original = ['Início'];
        toggleClosed(original, 'Administração');
        expect(original).toEqual(['Início']);
    });
});

describe('withGroupOpen', () => {
    it('remove a categoria informada da lista de fechadas', () => {
        expect(withGroupOpen(['Início', 'Administração'], 'Administração')).toEqual(['Início']);
    });

    it('é no-op quando o label é null', () => {
        expect(withGroupOpen(['Início'], null)).toEqual(['Início']);
    });

    it('não muta o array original', () => {
        const original = ['Início', 'Administração'];
        withGroupOpen(original, 'Administração');
        expect(original).toEqual(['Início', 'Administração']);
    });
});
