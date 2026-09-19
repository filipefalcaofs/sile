import Badge from '@/components/ui/badge';
import type { ColumnDef } from '@/components/ui/data-table/types';
import { permissaoColor } from './quadro-fields';
import type { Quadro10Item, Quadro11Item, QuadroItem } from './quadro-fields';

export function getColumns(quadro: string): ColumnDef<QuadroItem>[] {
    if (quadro === 'quadro10') {
        return [
            {
                id: 'zona',
                header: 'Zona',
                cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
                cell: (row) => (row as Quadro10Item).zona,
            },
            { id: 'grupo_uso', header: 'Grupo de uso', cell: (row) => (row as Quadro10Item).grupo_uso },
            { id: 'subgrupo', header: 'Subgrupo', cell: (row) => (row as Quadro10Item).subgrupo ?? '—' },
            {
                id: 'permissao',
                header: 'Permissão',
                cellClassName: 'whitespace-nowrap',
                cell: (row) => (
                    <Badge color={permissaoColor((row as Quadro10Item).permissao)} size="sm">
                        {(row as Quadro10Item).permissao_label}
                    </Badge>
                ),
            },
            {
                id: 'condicionante',
                header: 'Condicionante',
                cell: (row) => (row as Quadro10Item).condicionante_ref ?? '—',
            },
            { id: 'base_legal', header: 'Base legal', cell: (row) => (row as Quadro10Item).base_legal ?? '—' },
        ];
    }

    return [
        {
            id: 'classe_via',
            header: 'Classe de via',
            cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
            cell: (row) => (row as Quadro11Item).classe_via,
        },
        { id: 'grupo_uso', header: 'Grupo de uso', cell: (row) => (row as Quadro11Item).grupo_uso ?? '—' },
        {
            id: 'condicoes',
            header: 'Condições',
            cell: (row) => {
                const condicoes = (row as Quadro11Item).condicoes;

                return Array.isArray(condicoes) && condicoes.length > 0 ? condicoes.join('; ') : '—';
            },
        },
        { id: 'base_legal', header: 'Base legal', cell: (row) => (row as Quadro11Item).base_legal ?? '—' },
    ];
}
