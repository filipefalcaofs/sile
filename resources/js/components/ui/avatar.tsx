type AvatarSize = 'sm' | 'md' | 'lg';

interface AvatarProps {
    name: string;
    size?: AvatarSize;
}

const sizeStyles: Record<AvatarSize, string> = {
    sm: 'h-8 w-8 text-theme-xs',
    md: 'h-10 w-10 text-theme-sm',
    lg: 'h-12 w-12 text-base',
};

const palettes = [
    'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
    'bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/15 dark:text-blue-light-400',
    'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500',
    'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-orange-400',
    'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500',
    'bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-400',
];

function initialsOf(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    const first = parts[0][0];
    const last = parts.length > 1 ? parts[parts.length - 1][0] : '';

    return `${first}${last}`.toUpperCase();
}

function paletteOf(name: string): string {
    let hash = 0;

    for (const char of name) {
        hash = (hash + char.codePointAt(0)!) % palettes.length;
    }

    return palettes[hash];
}

/**
 * Avatar com iniciais e cor determinística pelo nome — para listas de
 * pessoas sem foto (o sistema não armazena imagens de usuários).
 */
export default function Avatar({ name, size = 'md' }: AvatarProps) {
    return (
        <span
            aria-hidden="true"
            className={`inline-flex shrink-0 items-center justify-center rounded-full font-semibold ${sizeStyles[size]} ${paletteOf(name)}`}
        >
            {initialsOf(name)}
        </span>
    );
}
