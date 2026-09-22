import { Head, Link, router } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import GestaoLayout from '@/layouts/gestao-layout';

interface ManualProps {
    quadro: string;
    urlModeloCsv: string;
    urlRascunho: string;
}

const QUADROS = [
    { id: 'quadro10', label: 'Quadro 10' },
    { id: 'quadro11a', label: 'Quadro 11A' },
] as const;

const LINK_OUTLINE =
    'inline-flex items-center justify-center gap-2 rounded-lg bg-white px-4 py-3 text-sm text-gray-700 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 focus:outline-hidden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]';

function ManualTable({
    caption,
    headers,
    rows,
}: {
    caption: string;
    headers: string[];
    rows: ReactNode[][];
}) {
    return (
        <div className="overflow-x-auto">
            <table className="min-w-full text-left text-theme-sm">
                <caption className="sr-only">{caption}</caption>
                <thead>
                    <tr className="border-b border-gray-200 text-theme-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        {headers.map((header) => (
                            <th key={header} scope="col" className="px-3 py-2 font-medium">
                                {header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => (
                        <tr
                            key={index}
                            className="border-b border-gray-100 text-gray-700 dark:border-gray-800 dark:text-gray-300"
                        >
                            {row.map((cell, cellIndex) => (
                                <td key={cellIndex} className="px-3 py-2 align-top">
                                    {cell}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function CodeBlock({ children }: { children: string }) {
    return (
        <pre className="overflow-x-auto rounded-lg bg-gray-50 p-3 text-theme-xs text-gray-700 dark:bg-white/[0.03] dark:text-gray-300">
            <code>{children}</code>
        </pre>
    );
}

export default function LouosManual({ quadro, urlModeloCsv, urlRascunho }: ManualProps) {
    useEffect(() => {
        document.getElementById(`secao-${quadro}`)?.scrollIntoView({ block: 'start' });
    }, [quadro]);

    return (
        <>
            <Head title="Manual de CSV dos Quadros da LOUOS" />
            <PageHeader
                title="Manual de CSV dos Quadros"
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Quadros da LOUOS', href: '/gestao/louos' },
                ]}
                actions={
                    <div className="flex flex-wrap items-center gap-2">
                        <a href={urlModeloCsv} download className={LINK_OUTLINE}>
                            Baixar modelo CSV
                        </a>
                        <Link href={urlRascunho} className={LINK_OUTLINE}>
                            Ir ao rascunho
                        </Link>
                    </div>
                }
            />

            <div className="space-y-6">
                <Card>
                    <CardContent>
                        <p className="text-theme-sm text-gray-700 dark:text-gray-300">
                            O sistema não lê o PDF da lei. O PDF é a fonte jurídica; o arquivo importado é um CSV em
                            formato longo (uma linha por combinação). Cabeçalho errado rejeita o arquivo inteiro.
                        </p>
                        <nav aria-label="Quadros do manual" className="mt-4 flex flex-wrap gap-2">
                            {QUADROS.map((item) => (
                                <Link
                                    key={item.id}
                                    href={`/gestao/louos/manual?quadro=${item.id}`}
                                    aria-current={quadro === item.id ? 'page' : undefined}
                                    className={
                                        quadro === item.id
                                            ? 'inline-flex rounded-lg bg-brand-500 px-4 py-2 text-sm text-white focus:outline-hidden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500'
                                            : LINK_OUTLINE
                                    }
                                >
                                    {item.label}
                                </Link>
                            ))}
                        </nav>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader title="O que cada Quadro precisa" />
                    <CardContent className="space-y-4">
                        <ManualTable
                            caption="Fontes e cabeçalho de cada Quadro"
                            headers={['Quadro', 'O que o motor usa', 'Fontes', 'Cabeçalho']}
                            rows={[
                                [
                                    '10',
                                    'Zona × uso → S / N / S(c)',
                                    'Matriz do Quadro 10 da Lei nº 9.148/2016',
                                    <code key="h10">zona,grupo_uso,subgrupo,permissao,condicionante_ref,base_legal</code>,
                                ],
                                [
                                    '11A',
                                    'Via × uso → Sim / Não / R',
                                    'Matriz do Quadro 11A',
                                    <code key="h11">classe_via,grupo_uso,condicoes,base_legal</code>,
                                ],
                            ]}
                        />
                        <p className="text-theme-sm text-gray-700 dark:text-gray-300">
                            O enquadramento de uso (grupo, subgrupo, código LOUOS) vem da planilha de
                            tratamento, no grupo Regras — não destes Quadros.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader title="Fluxo na tela" />
                    <CardContent>
                        <ol className="list-decimal space-y-2 pl-5 text-theme-sm text-gray-700 dark:text-gray-300">
                            <li>Abra o rascunho do Quadro e informe o identificador da nova versão.</li>
                            <li>Baixe o modelo CSV desta página ou do rascunho.</li>
                            <li>Monte o arquivo no formato longo (seções abaixo).</li>
                            <li>
                                Importe com <strong>substituir</strong> marcado quando for a carga completa da lei nova.
                            </li>
                            <li>Leia o relatório (lidos, importados, atualizados, rejeitados).</li>
                            <li>Outro usuário publica — o autor do rascunho não publica.</li>
                        </ol>
                        <p className="mt-4 text-theme-sm text-gray-600 dark:text-gray-400">
                            Arquivo CSV ou TXT, no máximo 5 MB, UTF-8, separador vírgula. O Excel em português costuma
                            gravar ponto e vírgula — o importador recusa o cabeçalho. Números com ponto decimal
                            (350.01), não vírgula.
                        </p>
                    </CardContent>
                </Card>

                <section id="secao-quadro10" className="scroll-mt-24" aria-label="Quadro 10 — permissão por zona">
                    <Card>
                        <CardHeader title="Quadro 10 — permissão por zona" />
                        <CardContent className="space-y-4">
                            <p className="text-theme-sm text-gray-700 dark:text-gray-300">
                                Cada célula da matriz (zona × uso) vira uma linha. Não cole a matriz larga do PDF.
                            </p>
                            <CodeBlock>
                                {`zona,grupo_uso,subgrupo,permissao,condicionante_ref,base_legal
ZPR 1,nR1,nR1-01,S,,Lei nº 9.148/2016 — Quadro 10
ZEIS 1,nR1,nR1-08,S(c),Quadro 12,Lei nº 9.148/2016 — Quadro 10
ZIT,nR2,nR2-01,N,,Lei nº 9.148/2016 — Quadro 10
ZPR 1,R1,,S,,Lei nº 9.148/2016 — Quadro 10`}
                            </CodeBlock>
                            <ManualTable
                                caption="Sinais de permissão aceitos no Quadro 10"
                                headers={['No PDF', 'No CSV', 'Gravado']}
                                rows={[
                                    ['S', 'S, Sim, permitido', 'permitido'],
                                    ['N', 'N, Não, nao, proibido', 'proibido'],
                                    ['S(c), S(a), S(b)', 'S(c), S(C), permitido_condicionado', 'permitido_condicionado'],
                                ]}
                            />
                            <p className="text-theme-sm text-gray-700 dark:text-gray-300">
                                Sigla da zona igual à da lei (espaço e hífen importam): ZPR 1, ZCMe 1/01, ZCMe - CA,
                                ZCMu 1 - IPITANGA. Conferência: zonas × usos = total de linhas (hoje 21 × 63 = 1.323).
                            </p>
                        </CardContent>
                    </Card>
                </section>

                <section id="secao-quadro11a" className="scroll-mt-24" aria-label="Quadro 11A — condições pela via">
                    <Card>
                        <CardHeader title="Quadro 11A — condições pela via" />
                        <CardContent className="space-y-4">
                            <p className="text-theme-sm text-gray-700 dark:text-gray-300">
                                Mesma lógica de formato longo. Classes atuais: VP, VL, VC II, VC I, VA II, VA I, VE.
                            </p>
                            <CodeBlock>
                                {`classe_via,grupo_uso,condicoes,base_legal
VL,nR1-01,Sim,Lei nº 9.148/2016 — Quadro 11A
VE,nR3-08,Não,Lei nº 9.148/2016 — Quadro 11A
VA I,ID2-05,Objeto de análise particularizada pela CNLU,Lei nº 9.148/2016 — Quadro 11A`}
                            </CodeBlock>
                            <p className="text-theme-sm text-gray-700 dark:text-gray-300">
                                Várias condições na mesma célula: separe com ponto e vírgula. Na carga vigente, R da
                                matriz foi gravado como “Objeto de análise particularizada pela CNLU”. Conferência: 7
                                vias × usos = total (hoje 525). Sem a classe de via no território, o motor degrada para
                                análise técnica — o CSV mesmo assim precisa estar correto.
                            </p>
                        </CardContent>
                    </Card>
                </section>

                <Card>
                    <CardHeader title="Checklist e o que não fazer" />
                    <CardContent className="space-y-4">
                        <ul className="list-disc space-y-2 pl-5 text-theme-sm text-gray-700 dark:text-gray-300">
                            <li>Quadro certo na tela — arquivo de um Quadro no rascunho de outro quebra o cabeçalho.</li>
                            <li>Primeira linha = cabeçalho exato do modelo baixado.</li>
                            <li>Vírgula, UTF-8, até 5 MB. Amostra de 5 a 10 linhas confrontada com o PDF.</li>
                            <li>Rejeitados vazios, ou cada motivo corrigido num segundo arquivo.</li>
                            <li>Publicação por usuário diferente do autor.</li>
                        </ul>
                        <p className="text-theme-sm text-gray-700 dark:text-gray-300">
                            Não anexe o PDF esperando os CSVs. Não use a planilha 20.08.26 para os Quadros 10 e 11A.
                            Não publique com rejeições sem ler.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <a href={urlModeloCsv} download className={LINK_OUTLINE}>
                                Baixar modelo deste Quadro
                            </a>
                            <Button size="sm" onClick={() => router.visit(urlRascunho)}>
                                Abrir rascunho para importar
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

LouosManual.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
