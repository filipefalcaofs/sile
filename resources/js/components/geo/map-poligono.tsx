/// <reference types="vite/client" />
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import { useEffect } from 'react';
import { MapContainer, Marker, Polygon, TileLayer, useMap, useMapEvent } from 'react-leaflet';
import type { Vertice } from '@/components/geo/poligono';

// Mesmo pitfall do MapImovel: sem isto o caminho dos assets do marcador quebra
// no bundle Vite e o pin some em produção.
L.Icon.Default.mergeOptions({ iconUrl, iconRetinaUrl, shadowUrl });

export interface MapPoligonoProps {
    /** Vértices atuais [lat, lng] — o polígono existente ou o desenho em edição. */
    vertices: Vertice[];
    /** Em edição: clique adiciona vértice e os marcadores são arrastáveis. */
    editando: boolean;
    onChange?: (vertices: Vertice[]) => void;
    altura?: number;
}

/** Enquadra o polígono na montagem e ao entrar/sair do modo de edição. */
function EnquadrarPoligono({ vertices, editando }: { vertices: Vertice[]; editando: boolean }) {
    const map = useMap();

    useEffect(() => {
        if (vertices.length > 0) {
            map.fitBounds(L.latLngBounds(vertices.map(([lat, lng]) => L.latLng(lat, lng))), { padding: [40, 40] });
        }
        // Recentra só na montagem e na troca de modo — nunca a cada clique,
        // para não brigar com o desenho em andamento.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [map, editando]);

    return null;
}

/** Clique no mapa adiciona vértice (só no modo de edição). */
function CliqueParaAdicionar({ onAdd }: { onAdd: (vertice: Vertice) => void }) {
    useMapEvent('click', (event) => onAdd([event.latlng.lat, event.latlng.lng]));

    return null;
}

/** Recalcula o tamanho do Leaflet quando o container muda de largura. */
function InvalidateOnResize() {
    const map = useMap();

    useEffect(() => {
        const container = map.getContainer();
        const observer = new ResizeObserver(() => {
            map.invalidateSize();
        });
        observer.observe(container);

        return () => observer.disconnect();
    }, [map]);

    return null;
}

const CENTRO_SALVADOR: Vertice = [-12.9711, -38.5108];

/**
 * Mapa da ficha de vistoria: exibe o polígono do imóvel e, no modo de edição,
 * permite redesenhá-lo (clique adiciona vértices, marcadores arrastáveis).
 * Montar apenas no cliente (Leaflet acessa `window` na importação — ver
 * `MapaPoligonoSection`).
 */
export function MapPoligono({ vertices, editando, onChange, altura = 360 }: MapPoligonoProps) {
    const centro = vertices.length > 0 ? vertices[0] : CENTRO_SALVADOR;

    return (
        <MapContainer
            center={centro}
            zoom={vertices.length > 0 ? 17 : 13}
            scrollWheelZoom
            className="relative z-0 w-full overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800"
            style={{ height: altura }}
        >
            <TileLayer
                attribution='&copy; OpenStreetMap (ODbL)'
                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            />
            <EnquadrarPoligono vertices={vertices} editando={editando} />
            <InvalidateOnResize />
            {editando && onChange && <CliqueParaAdicionar onAdd={(vertice) => onChange([...vertices, vertice])} />}
            {vertices.length >= 3 && (
                <Polygon
                    positions={vertices}
                    pathOptions={{
                        color: editando ? '#f79009' : '#d63384',
                        fillColor: editando ? '#f79009' : '#e95498',
                        fillOpacity: 0.35,
                    }}
                />
            )}
            {editando &&
                vertices.map((vertice, indice) => (
                    <Marker
                        key={`${indice}-${vertice[0]}-${vertice[1]}`}
                        draggable
                        position={vertice}
                        eventHandlers={{
                            dragend: (event) => {
                                const marcador = event.target as L.Marker;
                                const posicao = marcador.getLatLng();
                                const novos = vertices.map((atual, i) =>
                                    i === indice ? ([posicao.lat, posicao.lng] as Vertice) : atual,
                                );
                                onChange?.(novos);
                            },
                        }}
                    />
                ))}
        </MapContainer>
    );
}
