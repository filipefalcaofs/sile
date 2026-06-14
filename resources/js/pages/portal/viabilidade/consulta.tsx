import { Head, Link, useHttp } from '@inertiajs/react';
import type { ComponentType, ReactNode, SVGProps } from 'react';
import { useState } from 'react';
import AccessibilityBar from '@/components/app/accessibility-bar';
import Logo from '@/components/app/logo';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { MapaSection } from '@/components/geo/mapa-section';
import { ArrowRightIcon, FileIcon, MapPinIcon, MoonIcon, SearchIcon, SunIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { ResultadoViabilidade } from '@/components/viabilidade/resultado-viabilidade';
import type { ResultadoConsulta } from '@/components/viabilidade/resultado-viabilidade';
import { ThemeProvider, useTheme } from '@/contexts/theme-context';

interface ConsultaProps {
    consultaEnabled: boolean;
}

type TipoEntrada = 'endereco' | 'cnae' | 'inscricao';

/** Corpo POST dos endpoints /portal/viabilidade/* (07-06). */
interface ConsultaPayload {
    endereco?: string;
    inscricao?: string;
    cnae: string;
    area: number | null;
}

const ENTRADAS: Array<{ tipo: TipoEntrada; rotulo: string; icon: ComponentType<SVGProps<SVGSVGElement>> }> = [
    { tipo: 'endereco', rotulo: 'Endereço', icon: MapPinIcon },
    { tipo: 'cnae', rotulo: 'CNAE', icon: SearchIcon },
    { tipo: 'inscricao', rotulo: 'Inscrição imobiliária', icon: FileIcon },
];

/** Extrai a mensagem do backend (bag `{message}` ou erro por campo) — padrão do território (04-07). */
function mensagemDoErro(errors: Record<string, unknown>): string | null {
    const direta = errors?.message;
    if (typeof direta === 'string') {
        return direta;
    }
    const primeiro = Object.values(errors ?? {})[0];
    if (typeof primeiro === 'string') {
        return primeiro;
    }
    if (Array.isArray(primeiro) && typeof primeiro[0] === 'string') {
        return primeiro[0];
    }
    return null;
}

/** Mensagem do backend para erros HTTP (404/503), com fallback por status — padrão do território (04-07). */
function mensagemDaExcecao(response: { status: number; data: unknown }, fallback: string): string {
    let corpo: unknown = response.data;
    if (typeof corpo === 'string') {
        try {
            corpo = JSON.parse(corpo);
        } catch {
            corpo = null;
        }
    }
    if (corpo && typeof corpo === 'object' && typeof (corpo as Record<string, unknown>).message === 'string') {
        return (corpo as Record<string, string>).message;
    }
    return fallback;
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

/**
 * Shell público standalone (padrão home.tsx): cabeçalho próprio com Logo,
 * acessibilidade eMAG e tema — NÃO usa o PortalLayout (autenticado). É um layout
 * persistente do Inertia (Consulta.layout), preservando tema/acessibilidade.
 */
function PublicShell({ children }: { children: ReactNode }) {
    return (
        <ThemeProvider>
            <div className="flex min-h-screen flex-col bg-white dark:bg-gray-900">
                <AccessibilityBar />
                <header
                    id="menu"
                    className="sticky top-0 z-50 border-b border-gray-200/70 bg-white/80 backdrop-blur-md dark:border-gray-800/70 dark:bg-gray-900/80"
                >
                    <div className="mx-auto flex w-full max-w-(--breakpoint-xl) items-center justify-between gap-4 px-4 py-4 sm:px-6">
                        <Link href="/" className="flex items-center">
                            <Logo
                                markClassName="size-8"
                                textClassName="text-lg font-semibold tracking-tight text-gray-800 dark:text-white/90"
                                subtitle="SEDUR — Salvador"
                            />
                        </Link>
                        <div className="flex items-center gap-3">
                            <ThemeToggleButton />
                            <Link
                                href="/portal/login"
                                className="hidden rounded-lg bg-white px-4 py-2.5 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 sm:inline-flex dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03] dark:hover:text-gray-300"
                            >
                                Entrar
                            </Link>
                        </div>
                    </div>
                </header>
                <main id="conteudo" className="flex-1">
                    {children}
                </main>
                <footer id="rodape" className="border-t border-gray-200 dark:border-gray-800">
                    <p className="mx-auto w-full max-w-(--breakpoint-xl) px-4 py-6 text-sm text-gray-500 dark:text-gray-400 sm:px-6">
                        © {new Date().getFullYear()} Simplifica Salvador — Sistema de Licenciamento Eletrônico · SEDUR ·
                        Prefeitura de Salvador
                    </p>
                </footer>
            </div>
        </ThemeProvider>
    );
}

/**
 * Consulta prévia de viabilidade PÚBLICA (HU-054/055/056): 3 entradas honestas
 * (endereço, CNAE ou inscrição), mapa Leaflet reusado da Fase 4 para o ponto e o
 * resultado real dos motores via os endpoints /portal/viabilidade/* (useHttp —
 * nada simulado no front). O veredito locacional é honesto: pendente com motivo
 * quando sem zona, nunca Permitido/Não permitido sem o dado oficial.
 */
export default function Consulta({ consultaEnabled }: ConsultaProps) {
    const [tipo, setTipo] = useState<TipoEntrada>('endereco');
    const [endereco, setEndereco] = useState('');
    const [inscricao, setInscricao] = useState('');
    const [cnae, setCnae] = useState('');
    const [area, setArea] = useState('');
    const [result, setResult] = useState<ResultadoConsulta | null>(null);
    const [erro, setErro] = useState<string | null>(null);

    const consulta = useHttp<ConsultaPayload, ResultadoConsulta>({ cnae: '', area: null });

    const podeConsultar =
        consultaEnabled &&
        cnae.trim().length > 0 &&
        (tipo === 'endereco'
            ? endereco.trim().length >= 3
            : tipo === 'inscricao'
              ? inscricao.trim().length > 0
              : true);

    function consultar(event: React.FormEvent) {
        event.preventDefault();
        if (!podeConsultar || consulta.processing) {
            return;
        }

        setErro(null);
        const areaNumero = area.trim() === '' ? null : Number(area);

        let url: string;
        let payload: ConsultaPayload;
        if (tipo === 'endereco') {
            url = '/portal/viabilidade/endereco';
            payload = { endereco, cnae, area: areaNumero };
        } else if (tipo === 'cnae') {
            url = '/portal/viabilidade/cnae';
            payload = { cnae, area: areaNumero };
        } else {
            url = '/portal/viabilidade/inscricao';
            payload = { inscricao, cnae, area: areaNumero };
        }

        // transform() é síncrono: garante o envio do corpo fresco da aba ativa
        // (o dataRef do useHttp só atualiza no efeito pós-render) — precedente 04-07.
        consulta.transform(() => payload);
        consulta.post(url, {
            onSuccess: (response) => {
                setResult(response);
                setErro(null);
            },
            onError: (errors) => {
                setResult(null);
                setErro(
                    mensagemDoErro(errors) ?? 'Não foi possível concluir a consulta. Revise os dados e tente novamente.',
                );
            },
            onHttpException: (response) => {
                setResult(null);
                if (response.status === 429) {
                    setErro('Muitas consultas em sequência. Aguarde um instante e tente novamente.');
                } else {
                    setErro(
                        mensagemDaExcecao(
                            response,
                            'Não foi possível concluir a consulta. Tente novamente em instantes.',
                        ),
                    );
                }
                return false;
            },
        });
    }

    function selecionarTipo(novo: TipoEntrada) {
        setTipo(novo);
        setErro(null);
    }

    return (
        <>
            <Head title="Consulta de viabilidade" />

            <div className="mx-auto w-full max-w-(--breakpoint-xl) px-4 py-10 sm:px-6">
                <div className="max-w-2xl">
                    <h1 className="text-2xl font-semibold tracking-tight text-gray-800 dark:text-white/90 sm:text-title-sm">
                        Consulta de viabilidade locacional
                    </h1>
                    <p className="mt-3 text-base text-gray-500 dark:text-gray-400">
                        Verifique se uma atividade econômica pode funcionar em um local de Salvador, aplicando as regras
                        da LOUOS e a classificação de risco municipal. Consulte por endereço, CNAE ou inscrição
                        imobiliária — sem necessidade de login.
                    </p>
                    <p className="mt-4 text-sm text-gray-500 dark:text-gray-400">
                        <Link
                            href="/portal/login"
                            className="inline-flex items-center gap-1 font-medium text-brand-500 underline-offset-4 hover:underline dark:text-brand-400"
                        >
                            Entre para salvar o histórico das suas consultas
                            <ArrowRightIcon className="size-4" aria-hidden="true" />
                        </Link>
                    </p>
                </div>

                {!consultaEnabled && (
                    <div className="mt-6">
                        <Alert
                            variant="warning"
                            title="Consulta temporariamente desativada"
                            message="A consulta de viabilidade está indisponível no momento. Tente novamente mais tarde."
                        />
                    </div>
                )}

                <div className="mt-8 grid gap-4 md:gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-1">
                        <form onSubmit={consultar}>
                            <Card>
                                <CardHeader title="Nova consulta" description="Escolha como deseja consultar a viabilidade." />
                                <CardContent>
                                    <div
                                        role="tablist"
                                        aria-label="Tipo de consulta"
                                        className="mb-5 grid grid-cols-3 gap-1 rounded-lg bg-gray-100 p-1 dark:bg-white/[0.03]"
                                    >
                                        {ENTRADAS.map((entrada) => {
                                            const ativo = tipo === entrada.tipo;
                                            return (
                                                <button
                                                    key={entrada.tipo}
                                                    type="button"
                                                    role="tab"
                                                    aria-selected={ativo}
                                                    onClick={() => selecionarTipo(entrada.tipo)}
                                                    className={`flex flex-col items-center justify-center gap-1 rounded-md px-2 py-2 text-theme-xs font-medium transition ${
                                                        ativo
                                                            ? 'bg-white text-brand-500 shadow-theme-xs dark:bg-gray-800 dark:text-brand-400'
                                                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                                                    }`}
                                                >
                                                    <entrada.icon className="size-5" aria-hidden="true" />
                                                    {entrada.rotulo}
                                                </button>
                                            );
                                        })}
                                    </div>

                                    <div className="flex flex-col gap-4">
                                        {tipo === 'endereco' && (
                                            <div>
                                                <Label htmlFor="endereco" required>
                                                    Endereço
                                                </Label>
                                                <Input
                                                    id="endereco"
                                                    type="text"
                                                    name="endereco"
                                                    value={endereco}
                                                    onChange={(event) => setEndereco(event.target.value)}
                                                    placeholder="Ex.: Praça da Sé, Salvador"
                                                    disabled={!consultaEnabled}
                                                />
                                            </div>
                                        )}

                                        {tipo === 'cnae' && (
                                            <Alert
                                                variant="info"
                                                title="Consulta por CNAE"
                                                message="A consulta por CNAE não avalia o local: o veredito locacional depende do endereço. Para a viabilidade do ponto, consulte por endereço."
                                            />
                                        )}

                                        {tipo === 'inscricao' && (
                                            <>
                                                <Alert
                                                    variant="info"
                                                    title="Inscrição imobiliária"
                                                    message="A resolução por inscrição pode estar indisponível (base de lotes pendente SEDUR). Se ocorrer, a resposta indicará e sugerimos a consulta por endereço."
                                                />
                                                <div>
                                                    <Label htmlFor="inscricao" required>
                                                        Inscrição imobiliária
                                                    </Label>
                                                    <Input
                                                        id="inscricao"
                                                        type="text"
                                                        name="inscricao"
                                                        value={inscricao}
                                                        onChange={(event) => setInscricao(event.target.value)}
                                                        placeholder="Número da inscrição"
                                                        disabled={!consultaEnabled}
                                                    />
                                                </div>
                                            </>
                                        )}

                                        <div>
                                            <Label htmlFor="cnae" required>
                                                CNAE da atividade
                                            </Label>
                                            <Input
                                                id="cnae"
                                                type="text"
                                                name="cnae"
                                                value={cnae}
                                                onChange={(event) => setCnae(event.target.value)}
                                                placeholder="Ex.: 4712-1/00"
                                                maxLength={14}
                                                disabled={!consultaEnabled}
                                            />
                                        </div>

                                        <div>
                                            <Label htmlFor="area">Área da atividade (m²)</Label>
                                            <Input
                                                id="area"
                                                type="number"
                                                name="area"
                                                min={0}
                                                value={area}
                                                onChange={(event) => setArea(event.target.value)}
                                                placeholder="Opcional"
                                                disabled={!consultaEnabled}
                                                hint="Usada no enquadramento por faixa de área (Quadro 7)."
                                            />
                                        </div>

                                        <Button
                                            type="submit"
                                            variant="primary"
                                            size="sm"
                                            disabled={!podeConsultar}
                                            loading={consulta.processing}
                                        >
                                            Consultar viabilidade
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </form>
                    </div>

                    <div className="flex flex-col gap-4 md:gap-6 lg:col-span-2">
                        {erro && <Alert variant="error" title="Consulta de viabilidade" message={erro} />}

                        {!erro && !result && (
                            <Card>
                                <CardContent className="border-t-0">
                                    <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Preencha os dados e clique em <span className="font-medium">Consultar viabilidade</span>{' '}
                                        para ver o resultado.
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        {result && (
                            <>
                                {result.geocode && (
                                    <Card>
                                        <CardHeader
                                            title="Localização da consulta"
                                            description={result.geocode.display_name}
                                        />
                                        <CardContent>
                                            <MapaSection
                                                lat={result.geocode.latitude}
                                                lng={result.geocode.longitude}
                                                draggable={false}
                                            />
                                            <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                                Mapa &copy; OpenStreetMap, dados sob licença ODbL.
                                            </p>
                                        </CardContent>
                                    </Card>
                                )}

                                <ResultadoViabilidade result={result} />
                            </>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

Consulta.layout = (page: ReactNode) => <PublicShell>{page}</PublicShell>;
