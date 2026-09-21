export const gestaoLoginDisplayFont = "font-['Archivo',system-ui,sans-serif]";
export const gestaoLoginMonoFont = "font-[ui-monospace,'SF_Mono',Menlo,monospace]";

export const gestaoLoginShellClasses = [
    'grid min-h-screen grid-cols-1 bg-gray-50',
    "font-[-apple-system,BlinkMacSystemFont,'SF_Pro_Text','Segoe_UI',system-ui,sans-serif]",
    'text-gray-900 antialiased',
    'dark:bg-[oklch(15%_0.034_255)] dark:text-[oklch(96%_0.008_250)]',
    'min-[1101px]:grid-cols-[minmax(0,1fr)_clamp(440px,34vw,580px)]',
].join(' ');

export const gestaoLoginPanelClasses = [
    'flex min-h-screen flex-col bg-white',
    'px-[clamp(32px,4vw,72px)] py-[clamp(28px,3.5vw,56px)]',
    'min-[1101px]:min-h-0 min-[1101px]:border-l min-[1101px]:border-gray-200',
    'dark:bg-[oklch(19%_0.038_255)] min-[1101px]:dark:border-white/8',
    'max-sm:px-[22px]',
].join(' ');

export const gestaoLoginLabelClasses = [
    gestaoLoginMonoFont,
    'block text-[10.5px] leading-none font-medium tracking-[0.09em] uppercase',
    'text-gray-500 dark:text-[oklch(67%_0.025_250)]',
].join(' ');

export const gestaoLoginInputClasses = [
    'w-full rounded-xl border border-gray-200 bg-white p-4 text-[15px] font-medium text-gray-900',
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
    'group mt-7 inline-flex w-full cursor-pointer items-center justify-center gap-2.5 rounded-xl',
    'bg-brand-500 p-[17px] text-[15px] leading-none font-bold tracking-[0.01em] text-white',
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
