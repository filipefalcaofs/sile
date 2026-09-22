/**
 * Estado de colapso das categorias da sidebar (console de Gestão).
 *
 * O acordeão é "livre com memória": várias categorias podem ficar abertas
 * ao mesmo tempo e a preferência é guardada em localStorage. Persistimos a
 * lista de categorias FECHADAS (não as abertas): assim o padrão é tudo
 * aberto e categorias novas adicionadas no futuro nascem abertas, sem
 * precisar migrar o valor salvo.
 */
export const STORAGE_KEY = 'sile.sidebar.gestao.closedGroups';

interface MenuGroupLike {
    label: string;
    items: ReadonlyArray<{ href: string }>;
}

/**
 * Label da categoria que contém o item atualmente ativo, ou `null`.
 * Usado para abrir a categoria da página atual ao navegar.
 */
export function resolveActiveGroup(groups: ReadonlyArray<MenuGroupLike>, activeHref: string | null): string | null {
    if (!activeHref) {
        return null;
    }

    for (const group of groups) {
        if (group.items.some((item) => item.href === activeHref)) {
            return group.label;
        }
    }

    return null;
}

function resolveStorage(storage?: Storage | null): Storage | null {
    if (storage !== undefined) {
        return storage;
    }

    if (typeof window === 'undefined') {
        return null;
    }

    try {
        return window.localStorage;
    } catch {
        return null;
    }
}

/** Lê a lista de categorias fechadas; tolera storage ausente ou conteúdo inválido. */
export function readClosedGroups(storage?: Storage | null): string[] {
    const target = resolveStorage(storage);
    if (!target) {
        return [];
    }

    try {
        const raw = target.getItem(STORAGE_KEY);
        if (!raw) {
            return [];
        }

        const parsed: unknown = JSON.parse(raw);
        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed.filter((value): value is string => typeof value === 'string');
    } catch {
        return [];
    }
}

/** Grava a lista de categorias fechadas; no-op silencioso se o storage não estiver disponível. */
export function writeClosedGroups(labels: ReadonlyArray<string>, storage?: Storage | null): void {
    const target = resolveStorage(storage);
    if (!target) {
        return;
    }

    try {
        target.setItem(STORAGE_KEY, JSON.stringify(labels));
    } catch {
        // Storage cheio ou indisponível: a preferência não persiste, mas a navegação segue funcionando.
    }
}

/** Alterna uma categoria entre aberta/fechada, sem mutar o array recebido. */
export function toggleClosed(closed: ReadonlyArray<string>, label: string): string[] {
    return closed.includes(label) ? closed.filter((item) => item !== label) : [...closed, label];
}

/** Garante que uma categoria esteja aberta (removida da lista de fechadas), sem mutar o array recebido. */
export function withGroupOpen(closed: ReadonlyArray<string>, label: string | null): string[] {
    if (label === null) {
        return [...closed];
    }

    return closed.filter((item) => item !== label);
}
