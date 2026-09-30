import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Checkbox from '@/components/form/checkbox';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { StepTransition } from '@/components/motion';
import Alert from '@/components/ui/alert';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import PortalLayout from '@/layouts/portal-layout';

interface Origem {
    id: number;
    numero: string | null;
    empresa: string | null;
    cnpj: string | null;
    endereco: string;
    atividades: string[];
    validade: string | null;
    tipo: string | null;
}

interface Declaracao {
    id: number;
    body: string;
    required: boolean;
}

interface Contato {
    email: string | null;
    phone: string | null;
}

const passos = ['Confirmar TVL', 'Contato', 'Declarações', 'Revisar'];

export default function RenovacaoWizard({
    origem,
    declaracoes,
    contato,
}: {
    origem: Origem;
    declaracoes: Declaracao[];
    contato: Contato;
}) {
    const [passo, setPasso] = useState(0);
    const form = useForm({
        email: contato.email ?? '',
        phone: contato.phone ?? '',
        mobile: '',
        declaration_ids: [] as number[],
    });

    const obrigatorias = declaracoes.filter((item) => item.required);
    const todasMarcadas = obrigatorias.every((item) => form.data.declaration_ids.includes(item.id));

    function alternarDeclaracao(id: number, marcado: boolean) {
        const atual = form.data.declaration_ids;

        form.setData(
            'declaration_ids',
            marcado ? [...atual, id] : atual.filter((item) => item !== id),
        );
    }

    function avancar() {
        setPasso((atual) => Math.min(atual + 1, passos.length - 1));
    }

    function protocolar() {
        form.post(`/portal/renovacao/${origem.id}`);
    }

    return (
        <PortalLayout>
            <Head title="Renovação de Viabilidade" />
            <PageHeader
                title="Renovação de Viabilidade"
                breadcrumbs={[
                    { label: 'Meu painel', href: '/portal/painel' },
                    { label: 'Renovação de Viabilidade', href: '/portal/renovacao' },
                ]}
            />

            <ol className="mb-6 flex flex-col gap-2 sm:flex-row sm:gap-4" aria-label="Etapas da renovação">
                {passos.map((nome, indice) => (
                    <li
                        key={nome}
                        className={`flex min-h-11 items-center gap-2 text-sm ${indice === passo ? 'font-semibold text-brand-500' : 'text-gray-500 dark:text-gray-400'}`}
                        aria-current={indice === passo ? 'step' : undefined}
                    >
                        <span className="inline-flex size-7 items-center justify-center rounded-full bg-gray-100 text-xs dark:bg-gray-800">
                            {indice + 1}
                        </span>
                        {nome}
                    </li>
                ))}
            </ol>

            <Card>
                <CardHeader title={passos[passo]} description={origem.numero ? `TVL ${origem.numero}` : undefined} />
                <CardContent className="flex flex-col gap-6">
                    <StepTransition stepKey={String(passo)}>
                    {passo === 0 && (
                        <div className="flex flex-col gap-4 text-sm">
                            <p className="text-gray-600 dark:text-gray-300">
                                Confira se é esta a viabilidade que você quer renovar. Endereço e atividades não podem ser alterados na renovação.
                            </p>
                            <dl className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div>
                                    <dt className="text-gray-500">Empresa</dt>
                                    <dd className="font-medium text-gray-800 dark:text-white/90">{origem.empresa}</dd>
                                    <dd className="text-gray-500">{origem.cnpj}</dd>
                                </div>
                                <div>
                                    <dt className="text-gray-500">Validade atual</dt>
                                    <dd className="font-medium text-gray-800 dark:text-white/90">{origem.validade ?? '—'} ({origem.tipo ?? 'provisória'})</dd>
                                </div>
                                <div className="md:col-span-2">
                                    <dt className="text-gray-500">Endereço</dt>
                                    <dd className="font-medium text-gray-800 dark:text-white/90">{origem.endereco || '—'}</dd>
                                </div>
                                <div className="md:col-span-2">
                                    <dt className="text-gray-500">Atividades</dt>
                                    <dd>
                                        {origem.atividades.length === 0 ? (
                                            <span className="text-gray-500">Nenhuma atividade registrada.</span>
                                        ) : (
                                            <ul className="mt-1 list-disc pl-5 text-gray-800 dark:text-white/90">
                                                {origem.atividades.map((atividade) => (
                                                    <li key={atividade}>{atividade}</li>
                                                ))}
                                            </ul>
                                        )}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    )}

                    {passo === 1 && (
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <Label htmlFor="email">E-mail</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    autoComplete="email"
                                    value={form.data.email}
                                    error={Boolean(form.errors.email)}
                                    hint={form.errors.email}
                                    onChange={(event) => form.setData('email', event.target.value)}
                                />
                            </div>
                            <div>
                                <Label htmlFor="phone">Telefone</Label>
                                <Input
                                    id="phone"
                                    type="tel"
                                    inputMode="tel"
                                    autoComplete="tel"
                                    required
                                    value={form.data.phone}
                                    error={Boolean(form.errors.phone)}
                                    hint={form.errors.phone}
                                    onChange={(event) => form.setData('phone', event.target.value)}
                                />
                            </div>
                            <div>
                                <Label htmlFor="mobile">Celular</Label>
                                <Input
                                    id="mobile"
                                    type="tel"
                                    inputMode="tel"
                                    autoComplete="tel"
                                    value={form.data.mobile}
                                    onChange={(event) => form.setData('mobile', event.target.value)}
                                />
                            </div>
                        </div>
                    )}

                    {passo === 2 && (
                        <fieldset className="flex flex-col gap-3">
                            <legend className="mb-2 text-sm text-gray-600 dark:text-gray-300">
                                Marque todas as declarações para continuar.
                            </legend>
                            {declaracoes.map((declaracao) => (
                                <div key={declaracao.id} className="min-h-11 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                    <Checkbox
                                        id={`declaracao-${declaracao.id}`}
                                        label={declaracao.body}
                                        checked={form.data.declaration_ids.includes(declaracao.id)}
                                        onChange={(marcado) => alternarDeclaracao(declaracao.id, marcado)}
                                    />
                                </div>
                            ))}
                        </fieldset>
                    )}

                    {passo === 3 && (
                        <div className="flex flex-col gap-3 text-sm text-gray-700 dark:text-gray-300">
                            <p>
                                <span className="text-gray-500">TVL </span>
                                {origem.numero} — {origem.empresa}
                            </p>
                            <p>
                                <span className="text-gray-500">Contato </span>
                                {form.data.email || 'sem e-mail'} · {form.data.phone}
                            </p>
                            <p>{form.data.declaration_ids.length} declaração(ões) marcada(s).</p>
                            {form.errors.declaration_ids && <Alert variant="error" title="Não foi possível protocolar" message={form.errors.declaration_ids} />}
                        </div>
                    )}
                    </StepTransition>

                    <div className="sticky bottom-0 -mx-1 flex flex-col-reverse gap-3 bg-white py-2 sm:flex-row sm:justify-between dark:bg-gray-900">
                        {passo > 0 ? (
                            <Button variant="outline" className="min-h-11" onClick={() => setPasso((atual) => atual - 1)}>
                                Voltar
                            </Button>
                        ) : (
                            <span />
                        )}
                        {passo < 3 ? (
                            <Button
                                className="min-h-11"
                                onClick={avancar}
                                disabled={(passo === 1 && form.data.phone.trim() === '') || (passo === 2 && !todasMarcadas)}
                            >
                                Continuar
                            </Button>
                        ) : (
                            <Button className="min-h-11" onClick={protocolar} loading={form.processing} disabled={!todasMarcadas}>
                                Protocolar renovação
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>
        </PortalLayout>
    );
}
