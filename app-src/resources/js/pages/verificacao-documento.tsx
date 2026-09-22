import { Head } from '@inertiajs/react';
import AccessibilityBar from '@/components/app/accessibility-bar';
import Logo from '@/components/app/logo';
import { CheckCircleIcon, MoonIcon, SunIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { ThemeProvider, useTheme } from '@/contexts/theme-context';

/**
 * Verificação PÚBLICA de autenticidade de documentos (TVL, indeferimento,
 * comprovante de protocolo) pelo código de verificação — sem login. Mostra SÓ
 * tipo, protocolo e data de referência (payload mínimo, LGPD). Código
 * desconhecido/adulterado → estado inválido honesto, nunca um "válido"
 * presumido.
 */
interface ResultadoVerificacao {
    valido: boolean;
    tipo: string | null;
    protocolo: string | null;
    data_label: string | null;
    data: string | null;
}

interface VerificacaoDocumentoProps {
    codigo: string;
    resultado: ResultadoVerificacao;
}

/** Formata ISO 8601 como 'DD/MM/YYYY HH:MM' sem depender de fuso do navegador. */
function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/);

    if (!match) {
        return value;
    }

    const [, year, month, day, hour, minute] = match;

    return `${day}/${month}/${year} ${hour}:${minute}`;
}

function ThemeToggleButton() {
    const { toggleTheme } = useTheme();

    return (
        <button
            type="button"
            onClick={toggleTheme}
            aria-label="Alternar tema"
            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
        >
            <SunIcon className="hidden dark:block" />
            <MoonIcon className="dark:hidden" />
        </button>
    );
}

export default function VerificacaoDocumento({ codigo, resultado }: VerificacaoDocumentoProps) {
    return (
        <ThemeProvider>
            <Head title="Verificação de autenticidade" />
            <div className="flex min-h-screen flex-col bg-gray-50 dark:bg-gray-900">
                <AccessibilityBar />

                <header className="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div className="mx-auto flex w-full max-w-3xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                        <Logo
                            markClassName="size-8"
                            textClassName="text-lg font-semibold tracking-tight text-gray-800 dark:text-white/90"
                            subtitle="Verificação de documento"
                        />
                        <ThemeToggleButton />
                    </div>
                </header>

                <main id="conteudo" className="flex-1">
                    <div className="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 sm:py-12">
                        <div className="mb-6 flex flex-col gap-2">
                            <p className="text-theme-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                Verificação de autenticidade
                            </p>
                            <div className="flex flex-wrap items-center gap-3">
                                <h1 className="text-2xl font-semibold tracking-tight text-gray-800 dark:text-white/90">
                                    {resultado.valido ? 'Documento autêntico' : 'Documento não encontrado'}
                                </h1>
                                <Badge size="sm" color={resultado.valido ? 'success' : 'error'}>
                                    {resultado.valido ? 'Válido' : 'Inválido'}
                                </Badge>
                            </div>
                        </div>

                        <Card>
                            <CardHeader
                                title={resultado.valido ? (resultado.tipo ?? 'Documento') : 'Código não reconhecido'}
                                description={
                                    resultado.valido
                                        ? 'Este código corresponde a um documento emitido pelo Viabiliza.'
                                        : 'Este código não corresponde a nenhum documento emitido pelo Viabiliza. Confira o código impresso no documento.'
                                }
                            />
                            <CardContent>
                                <dl className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <dt className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                                            Código de verificação
                                        </dt>
                                        <dd className="mt-0.5 text-sm font-medium break-all text-gray-800 dark:text-white/90">
                                            {codigo}
                                        </dd>
                                    </div>
                                    {resultado.valido && resultado.protocolo && (
                                        <div>
                                            <dt className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Protocolo</dt>
                                            <dd className="mt-0.5 text-sm font-medium text-gray-800 dark:text-white/90">
                                                {resultado.protocolo}
                                            </dd>
                                        </div>
                                    )}
                                    {resultado.valido && resultado.data && (
                                        <div>
                                            <dt className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                                                {resultado.data_label ?? 'Data'}
                                            </dt>
                                            <dd className="mt-0.5 text-sm font-medium text-gray-800 dark:text-white/90">
                                                {formatDateTime(resultado.data)}
                                            </dd>
                                        </div>
                                    )}
                                </dl>

                                {resultado.valido && (
                                    <div className="mt-5 flex items-start gap-3 rounded-xl border border-success-200 bg-success-50 p-4 dark:border-success-500/30 dark:bg-success-500/10">
                                        <span className="mt-0.5 text-success-600 dark:text-success-500">
                                            <CheckCircleIcon className="size-5 fill-current" />
                                        </span>
                                        <p className="text-sm text-success-700 dark:text-success-400">
                                            Confira se o tipo do documento, o protocolo e a data acima são exatamente os
                                            impressos no documento que você recebeu. Qualquer divergência indica que o
                                            documento pode não ser autêntico.
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <p className="mt-6 text-center text-theme-xs text-gray-400 dark:text-gray-500">
                            Esta verificação é pública e mostra apenas a autenticidade do documento. Nenhum dado da
                            empresa ou do requerente é exibido.
                        </p>
                    </div>
                </main>

                <footer id="rodape" className="border-t border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <p className="mx-auto w-full max-w-3xl px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">
                        © {new Date().getFullYear()} Viabiliza Salvador — A viabilidade certa, no lugar certo · SEDUR
                    </p>
                </footer>
            </div>
        </ThemeProvider>
    );
}
