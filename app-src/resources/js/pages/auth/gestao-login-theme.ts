export const gestaoLoginDisplayFont = "font-['Archivo',system-ui,sans-serif]";
export const gestaoLoginMonoFont = "font-[ui-monospace,'SF_Mono',Menlo,monospace]";

export const gestaoLoginBrandForeground = 'text-[oklch(96%_0.008_250)]';

export const gestaoLoginBrandStageClasses = [
    'relative hidden min-h-0 shrink-0 flex-col overflow-hidden',
    gestaoLoginBrandForeground,
    'bg-[oklch(12.5%_0.03_255)]',
    'px-[clamp(28px,5vw,96px)] py-[clamp(28px,3.5vw,56px)]',
    'min-[1101px]:flex min-[1101px]:h-full',
    'min-[1101px]:w-[calc(100%-clamp(440px,34vw,580px))]',
    'transition-[width,padding] duration-[1.05s] ease-[cubic-bezier(0.7,0,0.2,1)]',
    // Docking: o palco vira a sidebar do console (290px, padding 32/20).
    'data-[docking=true]:min-[1101px]:w-[290px] data-[docking=true]:px-5 data-[docking=true]:py-8',
].join(' ');

/** Camada da foto: separada do palco para receber blur, brilho e escala animados. */
export const gestaoLoginStagePhotoClasses = [
    'pointer-events-none absolute -inset-[30px] z-0',
    "bg-[url('/images/salvador-hero.jpg')] bg-cover bg-center",
    'will-change-[filter,transform]',
].join(' ');

/** Os dois gradientes navy do palco, acima da foto. */
export const gestaoLoginStageGradientClasses = [
    'pointer-events-none absolute inset-0 z-0',
    'bg-[linear-gradient(180deg,oklch(12.5%_0.03_255_/_0.55)_0%,oklch(12.5%_0.03_255_/_0.2)_38%,oklch(12.5%_0.032_255_/_0.86)_100%),linear-gradient(100deg,oklch(12.5%_0.032_255_/_0.9)_0%,oklch(12.5%_0.03_255_/_0.42)_60%,oklch(12.5%_0.03_255_/_0.18)_100%)]',
].join(' ');

/** Véu do console: a cidade fica levemente visível sob a sidebar. */
export const gestaoLoginStageVeilClasses = [
    'pointer-events-none absolute inset-0 z-0 bg-[#0c111d] opacity-0',
    'transition-opacity duration-1000 ease-linear delay-[0.25s]',
    'data-[docking=true]:opacity-92',
].join(' ');

export const gestaoLoginShellClasses = [
    'relative flex h-dvh overflow-hidden overscroll-none bg-gray-50',
    "font-[-apple-system,BlinkMacSystemFont,'SF_Pro_Text','Segoe_UI',system-ui,sans-serif]",
    'text-gray-900 antialiased',
    'dark:bg-[oklch(15%_0.034_255)] dark:text-[oklch(96%_0.008_250)]',
].join(' ');

export const gestaoLoginPanelClasses = [
    'flex h-full min-h-0 min-w-0 flex-1 flex-col overflow-hidden bg-white',
    'px-[clamp(22px,4vw,72px)] py-[clamp(20px,3.5vw,56px)]',
    'min-[1101px]:border-l min-[1101px]:border-gray-200',
    'dark:bg-[oklch(19%_0.038_255)] min-[1101px]:dark:border-white/8',
    'transition-[background-color,border-color] duration-600 ease-linear delay-300',
    'data-[docking=true]:border-transparent data-[docking=true]:bg-[#f9fafb]',
    'data-[docking=true]:dark:border-transparent data-[docking=true]:dark:bg-[#f9fafb]',
].join(' ');

export const gestaoLoginLabelClasses = [
    gestaoLoginMonoFont,
    'block text-[10.5px] leading-none font-medium tracking-[0.09em] uppercase',
    'text-gray-500 dark:text-[oklch(67%_0.025_250)]',
].join(' ');

export const gestaoLoginInputClasses = [
    'w-full rounded-xl border border-gray-200 bg-white p-4 text-[15px] font-medium text-gray-900 max-[740px]:p-3.5 max-[740px]:text-sm',
    'transition-all duration-150 placeholder:text-gray-400',
    'hover:border-gray-300 focus:border-brand-500 focus:bg-white',
    'focus:shadow-[0_0_0_4px_oklch(64%_0.155_250_/_0.18)] focus:ring-0 focus:outline-none',
    'aria-invalid:border-error-500',
    'dark:border-white/8 dark:bg-[oklch(14%_0.03_255)] dark:text-[oklch(96%_0.008_250)]',
    'dark:placeholder:text-[oklch(50%_0.02_250)] dark:hover:border-white/14',
    'dark:focus:border-[oklch(64%_0.155_250)] dark:focus:bg-[oklch(13%_0.03_255)]',
    'dark:focus:shadow-[0_0_0_4px_oklch(64%_0.155_250_/_0.18)]',
    'dark:aria-invalid:border-[oklch(64%_0.19_25)]',
].join(' ');

export const gestaoLoginMutedClasses = 'text-sm text-gray-500 dark:text-[oklch(67%_0.025_250)]';

export const gestaoLoginFaintClasses = 'text-gray-400 dark:text-[oklch(50%_0.02_250)]';

export const gestaoLoginLinkClasses =
    'text-[12.5px] text-gray-500 underline underline-offset-[3px] hover:text-gray-900 dark:text-[oklch(67%_0.025_250)] dark:hover:text-[oklch(96%_0.008_250)]';

export const gestaoLoginDividerClasses = 'border-gray-200 dark:border-white/8';

export const gestaoLoginSubmitClasses = [
    'group mt-5 inline-flex w-full cursor-pointer items-center justify-center gap-2.5 rounded-xl max-[740px]:mt-4',
    'bg-brand-500 p-[17px] text-[15px] leading-none font-bold tracking-[0.01em] text-white max-[740px]:p-4 max-[740px]:text-sm',
    'transition-all duration-150 hover:bg-brand-600',
    'focus-visible:shadow-[0_0_0_4px_oklch(64%_0.155_250_/_0.3)] focus-visible:outline-none',
    'active:translate-y-px active:shadow-none disabled:pointer-events-none disabled:opacity-75',
    'dark:bg-[oklch(64%_0.155_250)] dark:text-[oklch(13%_0.03_255)] dark:hover:bg-[oklch(64%_0.155_250)]',
    'dark:hover:brightness-108 dark:hover:shadow-[0_8px_28px_oklch(64%_0.155_250_/_0.28)]',
].join(' ');

export const gestaoLoginThemeToggleClasses = [
    'relative flex size-10 items-center justify-center rounded-full border border-gray-200 bg-white',
    'text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700',
    'dark:border-white/8 dark:bg-[oklch(14%_0.03_255)] dark:text-[oklch(67%_0.025_250)]',
    'dark:hover:bg-white/5 dark:hover:text-[oklch(96%_0.008_250)]',
].join(' ');
