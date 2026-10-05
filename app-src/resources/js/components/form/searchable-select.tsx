import type { ComponentProps } from 'react';
import OptionSelect, { type OptionSelectOption } from './option-select';

type SearchableSelectProps = Omit<ComponentProps<typeof OptionSelect>, 'searchable'>;

export type { OptionSelectOption };

/**
 * Lista com busca. Use quando as opções não cabem num select nativo
 * (usuários, empresas, setores).
 */
export default function SearchableSelect(props: SearchableSelectProps) {
    return <OptionSelect searchable {...props} />;
}
