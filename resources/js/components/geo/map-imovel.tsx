/// <reference types="vite/client" />
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import type { GeoJsonObject } from 'geojson';
import { useEffect } from 'react';
import { GeoJSON, MapContainer, Marker, Popup, TileLayer, useMap, useMapEvent } from 'react-leaflet';

// Pitfall 10: sem isto o caminho dos assets do marcador quebra no bundle Vite
// e o pin some em produção. Os PNGs importados viram URLs resolvidas pelo Vite.
L.Icon.Default.mergeOptions({ iconUrl, iconRetinaUrl, shadowUrl });

export interface MapLayer {
    id: string | number;
    type: string;
    geojson: GeoJsonObject;
}

export interface MapImovelProps {
    /** Latitude do ponto (padrão Leaflet [lat,lng] — converter para [lng,lat] ao chamar o backend). */
    lat: number;
    lng: number;
    zoom?: number;
    /** Permite arrastar/clicar para ajustar a localização (HU-037). */
    draggable?: boolean;
    /** Camadas a sobrepor como overlay GeoJSON (HU-036) — reutilizável nas Fases 7/8. */
    camadas?: MapLayer[];
    /** Disparado ao arrastar o marcador ou clicar no mapa, com a nova coordenada. */
    onMove?: (latlng: { lat: number; lng: number }) => void;
}

/**
 * Recentraliza o mapa quando o ponto muda (o `center` do MapContainer só vale na
 * montagem). Mantém o marcador visível após geocodificar ou ajustar.
 */
function Recenter({ lat, lng }: { lat: number; lng: number }) {
    const map = useMap();

    useEffect(() => {
        map.setView([lat, lng], map.getZoom());
    }, [map, lat, lng]);

    return null;
}

/** Clique no mapa reposiciona o ponto (posicionamento manual — HU-037). */
function ClickToMove({ onMove }: { onMove: (latlng: { lat: number; lng: number }) => void }) {
    useMapEvent('click', (event) => onMove({ lat: event.latlng.lat, lng: event.latlng.lng }));

    return null;
}

/**
 * Mapa Leaflet reutilizável do imóvel (HU-030): tiles OSM, marcador arrastável
 * (ajuste HU-037), overlay de camadas GeoJSON (HU-036) e popup. Construído para
 * reuso nas Fases 7 e 8. Deve ser montado apenas no cliente (ver `MapaSection`),
 * pois o Leaflet acessa `window` na importação (Pitfall 9 — SSR do Inertia v3).
 */
export function MapImovel({ lat, lng, zoom = 17, draggable = true, camadas, onMove }: MapImovelProps) {
    return (
        <MapContainer
            center={[lat, lng]}
            zoom={zoom}
            scrollWheelZoom
            className="relative z-0 w-full overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800"
            style={{ height: 420 }}
        >
            <TileLayer
                attribution='&copy; OpenStreetMap (ODbL)'
                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            />
            <Recenter lat={lat} lng={lng} />
            {draggable && onMove && <ClickToMove onMove={onMove} />}
            <Marker
                draggable={draggable}
                position={[lat, lng]}
                eventHandlers={{
                    dragend: (event) => {
                        const marker = event.target as L.Marker;
                        const position = marker.getLatLng();
                        onMove?.({ lat: position.lat, lng: position.lng });
                    },
                }}
            >
                <Popup>Localização do imóvel</Popup>
            </Marker>
            {camadas?.map((camada) => (
                <GeoJSON key={camada.id} data={camada.geojson} />
            ))}
        </MapContainer>
    );
}
