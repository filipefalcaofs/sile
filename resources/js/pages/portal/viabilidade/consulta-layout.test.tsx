// @vitest-environment happy-dom
import { isValidElement } from 'react';
import { describe, expect, it } from 'vitest';
import PortalLayout from '@/layouts/portal-layout';
import Consulta from './consulta';

/**
 * Inertia v3 chama `Component.layout(props)` com as props da página (objeto
 * simples, não o elemento). Elemento válido → render function; componente →
 * layout persistente.
 */
function resolverComoInertia(props: unknown): unknown {
    const layout = (Consulta as unknown as { layout: (valor: unknown) => unknown }).layout;
    const resultado = layout(props);

    return isValidElement(resultado) ? 'elemento' : resultado;
}

describe('Consulta.layout', () => {
    it('usa o PortalLayout para quem está logado', () => {
        expect(resolverComoInertia({ auth: { user: { id: 1, name: 'Ana', email: 'a@b.c' } } })).toBe(PortalLayout);
    });

    it('usa o shell público para o visitante anônimo', () => {
        const layout = resolverComoInertia({ auth: { user: null } });

        expect(typeof layout).toBe('function');
        expect(layout).not.toBe(PortalLayout);
    });
});
