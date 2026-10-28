import { Component, type ReactNode } from 'react';

import Alert from '@/components/ui/alert';

interface MapaProtegidoProps {
    children: ReactNode;
}

interface MapaProtegidoEstado {
    falhou: boolean;
}

/**
 * Isola falhas do mapa (geometria inválida, Leaflet): o restante da página
 * continua visível e o lugar do mapa mostra um aviso.
 */
export class MapaProtegido extends Component<MapaProtegidoProps, MapaProtegidoEstado> {
    state: MapaProtegidoEstado = { falhou: false };

    static getDerivedStateFromError(): MapaProtegidoEstado {
        return { falhou: true };
    }

    render() {
        if (this.state.falhou) {
            return (
                <Alert
                    variant="warning"
                    title="Mapa indisponível"
                    message="Não foi possível desenhar o polígono deste processo. As demais informações da ficha continuam disponíveis."
                />
            );
        }

        return this.props.children;
    }
}
