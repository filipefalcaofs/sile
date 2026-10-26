export const LOGO_PORTAL_SERVICOS_URL = '/img/logo-portal-servicos-sedur.svg';

/**
 * Logo do Portal de Serviços da SEDUR no cabeçalho do portal do cidadão. O
 * SVG oficial é monocromático e entra como máscara: a cor vem do fundo, azul
 * da SEDUR no tema claro e branco no escuro.
 */
export default function LogoPortalServicos({ className = '' }: { className?: string }) {
    return (
        <span
            role="img"
            aria-label="Portal de Serviços da SEDUR"
            className={`block aspect-[205/40] h-8 bg-[#005CA8] dark:bg-white ${className}`}
            style={{
                maskImage: `url(${LOGO_PORTAL_SERVICOS_URL})`,
                WebkitMaskImage: `url(${LOGO_PORTAL_SERVICOS_URL})`,
                maskRepeat: 'no-repeat',
                WebkitMaskRepeat: 'no-repeat',
                maskSize: 'contain',
                WebkitMaskSize: 'contain',
                maskPosition: 'left center',
                WebkitMaskPosition: 'left center',
            }}
        />
    );
}
