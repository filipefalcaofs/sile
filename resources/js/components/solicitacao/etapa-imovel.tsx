import { useForm, usePage } from '@inertiajs/react';
import type { Polygon } from 'geojson';
import { useMemo, useState } from 'react';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { MapaSection } from '@/components/geo/mapa-section';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import type { SharedProps } from '@/types';

/** Resumo de uma dimensão territorial (TerritoryService::identify — Fase 4). */
interface DimensaoTerritorial {
    status: string;
    nome?: string | null;
    motivo?: string | null;
}

/** Território identificado pelo backend (degrada honesto sem zona/lote oficiais). */
interface TerritorioResumo {
    bairro?: DimensaoTerritorial;
    via?: DimensaoTerritorial;
    zona?: DimensaoTerritorial;
}

interface AreaAlert {
    area_declarada_m2: number;
    area_poligono_m2: number;
    tolerancia_percentual: number;
    divergencia_percentual: number;
}

interface AddressData {
    street: string | null;
    number: string | null;
    complement: string | null;
    neighborhood: string | null;
    zip: string | null;
    reference: string | null;
}

interface Indicators {
    is_virtual_office: boolean;
    is_public_area: boolean;
    has_independent_access: boolean;
}

interface EtapaImovelProps {
    solicitacaoId: number;
    initialPolygon: Polygon | null;
    address: AddressData;
    usedArea: string | number | null;
    indicators: Indicators;
    territorio: TerritorioResumo | null;
    areaAlert: AreaAlert | null;
    onSaved: () => void;
}

/** Centro de Salvador (Praça Municipal) — ponto inicial quando não há imóvel ainda. */
const SALVADOR_CENTER = { lat: -12.9714, lng: -38.5014 };

/** Centroide do anel exterior (média dos vértices, ignorando o ponto de fechamento). */
function centroidOf(polygon: Polygon | null): { lat: number; lng: number } | null {
    const ring = polygon?.coordinates?.[0];

    if (!ring || ring.length < 3) {
        return null;
    }

    const points = ring[0] === ring[ring.length - 1] ? ring.slice(0, -1) : ring;
    const lng = points.reduce((sum, point) => sum + point[0], 0) / points.length;
    const lat = points.reduce((sum, point) => sum + point[1], 0) / points.length;

    return { lat, lng };
}

/**
 * Quadrilátero (polígono de 4 pontos) centrado no ponto, com lado derivado da
 * área declarada (footprint aproximado). É a FONTE enviada ao backend, que faz a
 * geometria real (território, área×polígono, geometry derivada — Fase 4/08-06).
 */
function squareAround(lat: number, lng: number, areaM2: number): Polygon {
    const side = Math.sqrt(Math.max(areaM2, 1));
    const half = side / 2;
    const dLat = half / 110540;
    const dLng = half / (111320 * Math.cos((lat * Math.PI) / 180));

    return {
        type: 'Polygon',
        coordinates: [
            [
                [lng - dLng, lat - dLat],
                [lng - dLng, lat + dLat],
                [lng + dLng, lat + dLat],
                [lng + dLng, lat - dLat],
                [lng - dLng, lat - dLat],
            ],
        ],
    };
}

function TerritorioItem({ rotulo, dimensao }: { rotulo: string; dimensao?: DimensaoTerritorial }) {
    if (!dimensao) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 py-3 last:border-0 dark:border-gray-800">
            <span className="text-sm font-medium text-gray-700 dark:text-gray-300">{rotulo}</span>
            {dimensao.status === 'identificado' ? (
                <span className="text-sm text-gray-800 dark:text-white/90">{dimensao.nome ?? 'Identificado'}</span>
            ) : dimensao.status === 'indisponivel' ? (
                <div className="flex flex-col items-end gap-1 text-right">
                    <Badge size="sm" color="warning">
                        Indisponível
                    </Badge>
                    <span className="max-w-xs text-theme-xs text-gray-500 dark:text-gray-400">
                        {dimensao.motivo ?? 'Base pendente SEDUR.'}
                    </span>
                </div>
            ) : (
                <span className="text-sm text-gray-500 dark:text-gray-400">Não encontrado neste ponto</span>
            )}
        </div>
    );
}

/**
 * Etapa de imóvel + área (HU-062/063): reusa o mapa Leaflet da Fase 4
 * (MapaSection/MapImovel) para posicionar o imóvel e demarcar o polígono de 4
 * pontos (derivado da área declarada). Envia ao PUT solicitacoes.imovel (08-06),
 * que identifica o território (degrada honesto sem zona) e alerta a divergência
 * de área (RN-004, não bloqueia).
 */
export default function EtapaImovel({
    solicitacaoId,
    initialPolygon,
    address,
    usedArea,
    indicators,
    territorio,
    areaAlert,
    onSaved,
}: EtapaImovelProps) {
    const initialCenter = centroidOf(initialPolygon) ?? SALVADOR_CENTER;
    const [center, setCenter] = useState(initialCenter);

    // O polígono é derivado (não é campo do form): erros de validação dele vêm
    // do bag compartilhado (ex.: property_polygon_geojson.coordinates.0).
    const { errors: sharedErrors } = usePage<SharedProps>().props;
    const polygonError = Object.entries(sharedErrors as Record<string, string>).find(([key]) =>
        key.startsWith('property_polygon_geojson'),
    )?.[1];

    const { data, setData, put, transform, processing, errors } = useForm({
        used_area_m2: usedArea !== null ? String(usedArea) : '',
        address_street: address.street ?? '',
        address_number: address.number ?? '',
        address_complement: address.complement ?? '',
        address_neighborhood: address.neighborhood ?? '',
        address_zip: address.zip ?? '',
        address_reference: address.reference ?? '',
        is_virtual_office: indicators.is_virtual_office,
        is_public_area: indicators.is_public_area,
        has_independent_access: indicators.has_independent_access,
    });

    const area = Number(data.used_area_m2);
    const hasArea = data.used_area_m2.trim() !== '' && area > 0;

    const polygon = useMemo(
        () => (hasArea ? squareAround(center.lat, center.lng, area) : null),
        [center.lat, center.lng, area, hasArea],
    );

    const camadas = polygon ? [{ id: 'imovel', type: 'imovel', geojson: polygon }] : undefined;

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (!polygon) {
            return;
        }

        // O polígono derivado (área + ponto) é a FONTE enviada ao backend; não é
        // campo de formulário, então entra via transform no momento do envio.
        transform((current) => ({ ...current, property_polygon_geojson: polygon }));

        put(`/portal/solicitacoes/${solicitacaoId}/imovel`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: onSaved,
        });
    }

    return (
        <form onSubmit={submit} className="flex flex-col gap-4 md:gap-6">
            <Card>
                <CardHeader
                    title="Localização do imóvel"
                    description="Posicione o imóvel no mapa (arraste o marcador ou clique). O quadrilátero do imóvel é demarcado a partir da área informada abaixo."
                />
                <CardContent>
                    <MapaSection lat={center.lat} lng={center.lng} draggable onMove={setCenter} camadas={camadas} />
                    <p className="mt-3 text-theme-xs text-gray-500 dark:text-gray-400">
                        Mapa &copy; OpenStreetMap, dados sob licença ODbL.
                    </p>
                </CardContent>
            </Card>

            {territorio && (
                <Card>
                    <CardHeader
                        title="Território identificado"
                        description="Bairro e via vêm das camadas oficiais. A zona urbanística fica indisponível enquanto a base estiver pendente SEDUR — sem isso, a viabilidade locacional permanece pendente (nunca permitida/não permitida sem zona)."
                    />
                    <CardContent>
                        <div className="flex flex-col">
                            <TerritorioItem rotulo="Bairro" dimensao={territorio.bairro} />
                            <TerritorioItem rotulo="Via mais próxima" dimensao={territorio.via} />
                            <TerritorioItem rotulo="Zona urbanística" dimensao={territorio.zona} />
                        </div>
                    </CardContent>
                </Card>
            )}

            {areaAlert && (
                <Alert
                    variant="warning"
                    title="Divergência entre a área declarada e o polígono"
                    message={`A área declarada (${areaAlert.area_declarada_m2} m²) excede a do polígono demarcado (${areaAlert.area_poligono_m2} m²) em ${areaAlert.divergencia_percentual}%. Isso não impede o prosseguimento; o analista verá o registro.`}
                />
            )}

            <Card>
                <CardHeader title="Área e endereço" description="Informe a área utilizada e os dados do endereço do imóvel." />
                <CardContent>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div>
                            <Label htmlFor="used_area_m2" required>
                                Área utilizada (m²)
                            </Label>
                            <Input
                                id="used_area_m2"
                                type="number"
                                name="used_area_m2"
                                min={1}
                                value={data.used_area_m2}
                                onChange={(event) => setData('used_area_m2', event.target.value)}
                                placeholder="Ex.: 120"
                                error={!!errors.used_area_m2 || !!polygonError}
                                hint={errors.used_area_m2 ?? polygonError ?? 'Define o tamanho do quadrilátero do imóvel no mapa.'}
                            />
                        </div>
                        <div>
                            <Label htmlFor="address_reference" required>
                                Ponto de referência
                            </Label>
                            <Input
                                id="address_reference"
                                type="text"
                                name="address_reference"
                                value={data.address_reference}
                                onChange={(event) => setData('address_reference', event.target.value)}
                                placeholder="Ex.: em frente à praça"
                                error={!!errors.address_reference}
                                hint={errors.address_reference}
                            />
                        </div>
                        <div className="sm:col-span-2">
                            <Label htmlFor="address_street">Logradouro</Label>
                            <Input
                                id="address_street"
                                type="text"
                                name="address_street"
                                value={data.address_street}
                                onChange={(event) => setData('address_street', event.target.value)}
                                error={!!errors.address_street}
                                hint={errors.address_street}
                            />
                        </div>
                        <div>
                            <Label htmlFor="address_number">Número</Label>
                            <Input
                                id="address_number"
                                type="text"
                                name="address_number"
                                value={data.address_number}
                                onChange={(event) => setData('address_number', event.target.value)}
                                error={!!errors.address_number}
                                hint={errors.address_number}
                            />
                        </div>
                        <div>
                            <Label htmlFor="address_complement">Complemento</Label>
                            <Input
                                id="address_complement"
                                type="text"
                                name="address_complement"
                                value={data.address_complement}
                                onChange={(event) => setData('address_complement', event.target.value)}
                                placeholder="Ex.: sala 2, edifício comercial"
                                error={!!errors.address_complement}
                                hint={errors.address_complement}
                            />
                        </div>
                        <div>
                            <Label htmlFor="address_neighborhood">Bairro</Label>
                            <Input
                                id="address_neighborhood"
                                type="text"
                                name="address_neighborhood"
                                value={data.address_neighborhood}
                                onChange={(event) => setData('address_neighborhood', event.target.value)}
                                error={!!errors.address_neighborhood}
                                hint={errors.address_neighborhood}
                            />
                        </div>
                        <div>
                            <Label htmlFor="address_zip">CEP</Label>
                            <Input
                                id="address_zip"
                                type="text"
                                name="address_zip"
                                value={data.address_zip}
                                onChange={(event) => setData('address_zip', event.target.value)}
                                placeholder="40000000"
                                error={!!errors.address_zip}
                                hint={errors.address_zip}
                            />
                        </div>
                    </div>

                    <fieldset className="mt-6 border-t border-gray-100 pt-5 dark:border-gray-800">
                        <legend className="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">
                            Características do imóvel
                        </legend>
                        <div className="flex flex-col gap-3">
                            <label className="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    checked={data.is_virtual_office}
                                    onChange={(event) => setData('is_virtual_office', event.target.checked)}
                                    className="size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/30 dark:border-gray-700 dark:bg-gray-900"
                                />
                                Escritório virtual
                            </label>
                            <label className="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    checked={data.is_public_area}
                                    onChange={(event) => setData('is_public_area', event.target.checked)}
                                    className="size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/30 dark:border-gray-700 dark:bg-gray-900"
                                />
                                Em área pública (exige termo de concessão de uso)
                            </label>
                            <label className="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    checked={data.has_independent_access}
                                    onChange={(event) => setData('has_independent_access', event.target.checked)}
                                    className="size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/30 dark:border-gray-700 dark:bg-gray-900"
                                />
                                Possui acesso independente
                            </label>
                        </div>
                    </fieldset>

                    <div className="mt-6 flex justify-end">
                        <Button type="submit" size="sm" disabled={processing || !hasArea} loading={processing}>
                            Salvar imóvel e continuar
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </form>
    );
}
