import { Form, Head, Link } from '@inertiajs/react';
import { LogoMark } from '@/components/app/logo';

const displayFont = "font-['Archivo',system-ui,sans-serif]";
const monoFont = "font-[ui-monospace,'SF_Mono',Menlo,monospace]";
const monoLabel = `${monoFont} block text-[10.5px] leading-none font-medium tracking-[0.09em] text-[oklch(67%_0.025_250)] uppercase`;
const inputClasses =
    'w-full rounded-xl border border-white/8 bg-[oklch(14%_0.03_255)] p-4 text-[15px] font-medium text-[oklch(96%_0.008_250)] transition-all duration-150 placeholder:text-[oklch(50%_0.02_250)] hover:border-white/14 focus:border-[oklch(64%_0.155_250)] focus:bg-[oklch(13%_0.03_255)] focus:shadow-[0_0_0_4px_oklch(64%_0.155_250_/_0.18)] focus:ring-0 focus:outline-none aria-invalid:border-[oklch(64%_0.19_25)]';

interface Props {
    status?: string;
}

export default function GestaoForgotPassword({ status }: Props) {
    return (
        <main className="grid min-h-screen place-items-center bg-[oklch(15%_0.034_255)] px-5 py-10 font-[-apple-system,BlinkMacSystemFont,'SF_Pro_Text','Segoe_UI',system-ui,sans-serif] text-[oklch(96%_0.008_250)] antialiased">
            <Head title="Recuperar senha — Gestão SEDUR">
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
                <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800;900&display=swap" rel="stylesheet" />
            </Head>

            <section className="w-full max-w-[420px] rounded-2xl border border-white/8 bg-[oklch(19%_0.038_255)] p-[clamp(28px,5vw,44px)]">
                <span className="mb-7 flex items-center gap-3.5">
                    <span className="grid size-[44px] place-items-center rounded-xl border border-white/16 bg-[oklch(47%_0.135_252)]">
                        <LogoMark className="size-[22px] text-white" />
                    </span>
                    <span>
                        <span className={`${displayFont} block text-[16px] leading-[1.1] font-extrabold tracking-[0.06em]`}>VIABILIZA</span>
                        <span className={`${monoFont} block text-[10px] leading-normal font-medium tracking-[0.08em] text-[oklch(67%_0.025_250)] uppercase`}>Gestão SEDUR · Salvador</span>
                    </span>
                </span>

                <h1 className={`${displayFont} text-[26px] leading-[1.1] font-extrabold tracking-[-0.02em]`}>Recuperar senha</h1>
                <p className="mt-2.5 text-sm text-[oklch(67%_0.025_250)]">
                    Informe seu e-mail institucional. Se a conta tiver acesso ao console, enviaremos as instruções.
                </p>

                {status && (
                    <p className="mt-6 rounded-xl border border-[oklch(70%_0.14_160_/_0.3)] bg-[oklch(70%_0.14_160_/_0.08)] px-4 py-3 text-sm text-[oklch(80%_0.12_160)]">
                        {status}
                    </p>
                )}

                <Form action="/gestao/forgot-password" method="post">
                    {({ errors, processing }) => (
                        <>
                            <div className="mt-[26px] grid gap-2.5">
                                <label htmlFor="email" className={monoLabel}>E-mail institucional</label>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    inputMode="email"
                                    autoComplete="username"
                                    autoFocus
                                    required
                                    placeholder="nome@salvador.ba.gov.br"
                                    aria-invalid={errors.email ? 'true' : undefined}
                                    className={inputClasses}
                                />
                                {errors.email && <p className="text-[12.5px] text-[oklch(72%_0.16_25)]">{errors.email}</p>}
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="mt-7 inline-flex w-full cursor-pointer items-center justify-center rounded-xl bg-[oklch(64%_0.155_250)] p-[17px] text-[15px] leading-none font-bold tracking-[0.01em] text-[oklch(13%_0.03_255)] transition-all duration-150 hover:brightness-108 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-75"
                            >
                                {processing ? 'Enviando…' : 'Enviar instruções'}
                            </button>
                        </>
                    )}
                </Form>

                <p className="mt-[26px] border-t border-white/8 pt-[22px] text-center text-[13px] text-[oklch(67%_0.025_250)]">
                    Lembrou a senha?{' '}
                    <Link href="/gestao/login" className="font-medium text-[oklch(72%_0.12_250)] underline underline-offset-[3px] hover:text-[oklch(96%_0.008_250)]">
                        Voltar ao login
                    </Link>
                </p>
            </section>
        </main>
    );
}
