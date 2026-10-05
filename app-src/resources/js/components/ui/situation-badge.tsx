import Badge from '@/components/ui/badge';

interface SituationBadgeProps {
    active: boolean;
    /** Concordância: "Ativa/Inativa" quando o substantivo é feminino (zona, via, camada, conta). */
    gender?: 'm' | 'f';
}

/** Situação cadastral Ativo/Inativo, compartilhada pelas listagens. */
export default function SituationBadge({ active, gender = 'm' }: SituationBadgeProps) {
    const rotulo = active
        ? gender === 'f'
            ? 'Ativa'
            : 'Ativo'
        : gender === 'f'
          ? 'Inativa'
          : 'Inativo';

    return (
        <Badge size="sm" color={active ? 'success' : 'warning'}>
            {rotulo}
        </Badge>
    );
}
