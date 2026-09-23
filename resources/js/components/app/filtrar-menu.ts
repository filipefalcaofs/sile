interface GrupoMenu<T extends { name: string }> {
    label: string;
    items: readonly T[];
}

function normalizar(valor: string): string {
    return valor
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLowerCase()
        .trim();
}

/**
 * Filtra os grupos do menu pelo texto digitado.
 * Casa com o nome do item (contém) e, a partir de duas letras, com o
 * começo do nome do grupo — aí a categoria inteira permanece visível.
 */
export function filtrarGruposMenu<T extends { name: string }>(
    groups: readonly GrupoMenu<T>[],
    termo: string,
): GrupoMenu<T>[] {
    const consulta = normalizar(termo);

    if (consulta === '') {
        return groups.map((group) => ({ ...group, items: [...group.items] }));
    }

    return groups
        .map((group) => {
            const grupoCasa = consulta.length >= 2 && normalizar(group.label).startsWith(consulta);

            if (grupoCasa) {
                return { ...group, items: [...group.items] };
            }

            return {
                ...group,
                items: group.items.filter((item) => normalizar(item.name).includes(consulta)),
            };
        })
        .filter((group) => group.items.length > 0);
}
