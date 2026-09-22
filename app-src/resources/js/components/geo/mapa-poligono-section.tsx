import { lazy, Suspense, useEffect, useState } from 'react';
import type { MapPoligonoProps } from '@/components/geo/map-poligono';

// Mesmo pitfall do MapaSection: o Leaflet acessa `window` na importação e
// quebra o SSR do Inertia v3. O import dinâmico só resolve no cliente e a
// guarda `mounted` impede render no servidor.
const MapPoligono = lazy(() =>
    import('@/components/geo/map-poligono').then((module) => ({ default: module.MapPoligono })),
);

function MapaSkeleton({ altura }: { altura: number }) {
    return (
        <div
            className="animate-pulse rounded-xl bg-gray-100 dark:bg-white/[0.03]"
            style={{ height: altura }}
        />
    );
}

/**
 * Monta o mapa do polígono apenas no cliente (SSR-safe), com skeleton da
 * mesma altura enquanto monta — sem salto de layout.
 */
export function MapaPoligonoSection(props: MapPoligonoProps) {
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        setMounted(true);
    }, []);

    if (!mounted) {
        return <MapaSkeleton altura={props.altura ?? 360} />;
    }

    return (
        <Suspense fallback={<MapaSkeleton altura={props.altura ?? 360} />}>
            <MapPoligono {...props} />
        </Suspense>
    );
}
