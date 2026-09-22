interface SkeletonProps {
    className?: string;
}

interface SkeletonTextProps {
    lines?: number;
    className?: string;
}

/**
 * Bloco de carregamento com pulso — usar enquanto deferred props ou
 * visitas Inertia estão em andamento.
 */
export function Skeleton({ className = '' }: SkeletonProps) {
    return <div className={`animate-pulse rounded-md bg-gray-100 dark:bg-gray-800 ${className}`} aria-hidden="true" />;
}

export function SkeletonText({ lines = 3, className = '' }: SkeletonTextProps) {
    return (
        <div className={`flex flex-col gap-2.5 ${className}`} aria-hidden="true">
            {Array.from({ length: lines }, (_, index) => (
                <Skeleton key={index} className={`h-4 ${index === lines - 1 ? 'w-2/3' : 'w-full'}`} />
            ))}
        </div>
    );
}
