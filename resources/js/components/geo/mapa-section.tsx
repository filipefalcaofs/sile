import { lazy, Suspense, useEffect, useState } from 'react';
import type { MapImovelProps } from '@/components/geo/map-imovel';

// Pitfall 9: o Leaflet acessa `window` na importação e quebra o SSR do Inertia
// v3. O import dinâmico (lazy) só é resolvido no cliente, e a guarda `mounted`
// garante que nada do mapa renderize no servidor nem na primeira passada.
const MapImovel = lazy(() =>
    import('@/components/geo/map-imovel').then((module) => ({ default: module.MapImovel })),
);

function MapaSkeleton() {
    return <div className="h-[420px] animate-pulse rounded-2xl bg-gray-100 dark:bg-white/[0.03]" />;
}

/**
 * Monta o mapa Leaflet apenas no cliente (SSR-safe). Exibe um skeleton com a
 * mesma altura do mapa enquanto monta, evitando salto de layout.
 */
export function MapaSection(props: MapImovelProps) {
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        setMounted(true);
    }, []);

    if (!mounted) {
        return <MapaSkeleton />;
    }

    return (
        <Suspense fallback={<MapaSkeleton />}>
            <MapImovel {...props} />
        </Suspense>
    );
}
