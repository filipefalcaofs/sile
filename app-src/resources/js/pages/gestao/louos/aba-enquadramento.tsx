import { router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';
import Label from '@/components/form/label';
import type { SharedProps } from '@/types';

interface Pergunta {
    numero: number;
    texto: string;
    referencia: string | null;
}

interface Pendencia {
    regra: number | null;
    motivo: string;
}

export interface EnquadramentoData {
    versao: string | null;
    contagens: {
        cnaes: number;
        enquadramentos: number;
        perguntas: number;
        regras: number;
        bindings: number;
    };
    perguntas: Pergunta[];
    rascunho: {
        id: number;
        versao: string;
        arquivo: string | null;
        autor_id: number | null;
        pendencias: Pendencia[];
    } | null;
}

/**
 * Aba Enquadramento do hub Motor de regras — a planilha de tratamento vigente
 * (perguntas, enquadramento, risco e TLL), migrada da tela "Planilha de
 * regras". Quem permite ou proíbe na zona continua sendo o Quadro 10; na via,
 * o Quadro 11A.
 */
export default function AbaEnquadramento({ enquadramento }: { enquadramento: EnquadramentoData }) {
    const { versao, contagens, perguntas, rascunho } = enquadramento;
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-louos');
    const form = useForm<{ arquivo: File | null }>({ arquivo: null });

    function importar(event: FormEvent) {
        event.preventDefault();
        form.post('/gestao/louos/enquadramento/importar', { forceFormData: true, preserveScroll: true });
    }

    return (
        <div className="flex flex-col gap-6">
            {canMaintain && (
                <Card>
                    <CardHeader
                        title="Importar planilha"
                        description="O arquivo .xlsx neste layout abre um rascunho. O mesmo conteúdo não cria outra versão. Quem importou pode publicar."
                    />
                    <CardContent>
                        <form onSubmit={importar} className="flex flex-col gap-4">
                            <div>
                                <Label htmlFor="planilha-arquivo" required>
                                    Planilha de regras
                                </Label>
                                <input
                                    id="planilha-arquivo"
                                    type="file"
                                    accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                    onChange={(event) => form.setData('arquivo', event.target.files?.[0] ?? null)}
                                    className="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-800 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1 file:text-xs file:text-gray-700 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                />
                                {form.errors.arquivo && <p className="mt-1 text-theme-xs text-error-500">{form.errors.arquivo}</p>}
                            </div>
                            <button
                                type="submit"
                                disabled={form.processing || form.data.arquivo === null}
                                className="w-fit rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
                            >
                                Importar
                            </button>
                        </form>
                    </CardContent>
                </Card>
            )}

            {rascunho !== null && (
                <Card>
                    <CardHeader
                        title={`Rascunho ${rascunho.versao}`}
                        description={rascunho.arquivo ?? 'Rascunho aguardando publicação.'}
                    />
                    <CardContent>
                        <div className="flex flex-col gap-4">
                            {rascunho.pendencias.length > 0 && (
                                <ul className="flex flex-col gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    {rascunho.pendencias.map((pendencia) => (
                                        <li key={`${pendencia.regra}-${pendencia.motivo}`}>
                                            {pendencia.regra !== null ? `Regra ${pendencia.regra}. ` : ''}
                                            {pendencia.motivo}
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {canMaintain && (
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.put(
                                            `/gestao/louos/enquadramento/versoes/${rascunho.id}/publicar`,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                    className="w-fit rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-800 hover:bg-gray-50 dark:border-gray-700 dark:text-white/90 dark:hover:bg-white/5"
                                >
                                    Publicar rascunho
                                </button>
                            )}
                        </div>
                    </CardContent>
                </Card>
            )}

            {versao === null ? (
                <EmptyState
                    title="Planilha sem versão vigente"
                    description="Não há versão publicada da planilha de tratamento. O enquadramento fica pendente até a carga versionada."
                />
            ) : (
                <>
            <Card>
                <CardHeader
                    title="Versão vigente"
                    description="Fonte do enquadramento de uso. Não substitui os Quadros 10 e 11A."
                />
                <CardContent>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge color="success">{versao}</Badge>
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            {contagens.cnaes} CNAEs · {contagens.enquadramentos} enquadramentos · {contagens.regras}{' '}
                            regras
                        </span>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader title="Perguntas" description="Perguntas que escolhem o ramo de cada CNAE." />
                <CardContent>
                    {perguntas.length === 0 ? (
                        <p className="text-sm text-gray-500 dark:text-gray-400">Nenhuma pergunta nesta versão.</p>
                    ) : (
                        <ol className="flex flex-col gap-3">
                            {perguntas.map((pergunta) => (
                                <li key={pergunta.numero} className="text-sm text-gray-800 dark:text-white/90">
                                    <span className="font-medium">P{pergunta.numero}.</span> {pergunta.texto}
                                    {pergunta.referencia && (
                                        <span className="mt-1 block text-gray-500 dark:text-gray-400">
                                            {pergunta.referencia}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ol>
                    )}
                </CardContent>
            </Card>
                </>
            )}
        </div>
    );
}
