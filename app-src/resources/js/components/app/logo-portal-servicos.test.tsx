// @vitest-environment happy-dom
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import LogoPortalServicos, { LOGO_PORTAL_SERVICOS_URL } from './logo-portal-servicos';

describe('LogoPortalServicos', () => {
    it('desenha a logo do Portal de Serviços pela máscara, com nome acessível', () => {
        const html = renderToStaticMarkup(<LogoPortalServicos />);

        expect(html).toContain('role="img"');
        expect(html).toContain('aria-label="Portal de Serviços da SEDUR"');
        expect(html).toContain(`url(${LOGO_PORTAL_SERVICOS_URL})`);
        expect(LOGO_PORTAL_SERVICOS_URL).toBe('/img/logo-portal-servicos-sedur.svg');
    });

    it('usa o azul da SEDUR no tema claro e branco no escuro', () => {
        const html = renderToStaticMarkup(<LogoPortalServicos />);

        expect(html).toContain('bg-[#005CA8]');
        expect(html).toContain('dark:bg-white');
    });
});
