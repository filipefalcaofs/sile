import { Head, useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Checkbox from '@/components/form/checkbox';
import Input from '@/components/form/input';
import MaskedInput from '@/components/form/masked-input';
import Label from '@/components/form/label';
import { CalendarIcon, CheckCircleIcon, InfoIcon, MapPinIcon } from '@/components/icons';
import { StepTransition } from '@/components/motion';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { formatarData } from '@/components/renovacao/legado-item';
import PortalLayout from '@/layouts/portal-layout';

interface Origem {
    id: number | null;
    legado?: boolean;
    cnpj_digitos?: string;
    numero: string | number | null;
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

const passos = ['Confirmar viabilidade', 'Contato', 'Declarações', 'Revisar'];

function ItemRevisao({ titulo, onEditar, children }: { titulo: string; onEditar: () => void; children: ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-4 p-4 text-sm">
            <div className="min-w-0">
                <dt className="mb-1 text-theme-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{titulo}</dt>
                <dd className="break-words">{children}</dd>
            </div>
            <button
                type="button"
                onClick={onEditar}
                className="min-h-11 shrink-0 rounded-lg px-3 text-sm font-medium text-brand-500 hover:bg-brand-50 focus-visible:outline-2 focus-visible:outline-brand-500 dark:hover:bg-brand-500/10"
            >
                Editar<span className="sr-only"> {titulo.toLowerCase()}</span>
            </button>
        </div>
    );
}

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
    const contatoCompleto =
        /^\S+@\S+\.\S+$/.test(form.data.email.trim()) && form.data.phone.trim() !== '' && form.data.mobile.trim() !== '';

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
        form.post(
            origem.legado
                ? origem.cnpj_digitos
                    ? `/portal/renovacao/legado/${origem.cnpj_digitos}/${origem.numero}`
                    : `/portal/renovacao/legado/${origem.numero}`
                : `/portal/renovacao/${origem.id}`,
        );
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

            <nav aria-label="Etapas da renovação" className="mb-6">
                <p className="mb-3 text-theme-xs text-gray-500 sm:hidden dark:text-gray-400">
                    Etapa {passo + 1} de {passos.length}
                </p>
                <ol className="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-0">
                    {passos.map((nome, indice) => {
                        const concluido = indice < passo;
                        const atual = indice === passo;

                        return (
                            <li
                                key={nome}
                                className={`flex items-center gap-3 sm:flex-1 ${indice === passos.length - 1 ? 'sm:flex-none' : ''}`}
                                aria-current={atual ? 'step' : undefined}
                            >
                                <span
                                    className={`inline-flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold transition-colors ${
                                        concluido
                                            ? 'bg-success-500 text-white'
                                            : atual
                                              ? 'bg-brand-500 text-white ring-4 ring-brand-500/15'
                                              : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'
                                    }`}
                                >
                                    {concluido ? <CheckCircleIcon className="size-5 fill-current" aria-hidden="true" /> : indice + 1}
                                </span>
                                <span
                                    className={`text-sm ${
                                        atual
                                            ? 'font-semibold text-gray-800 dark:text-white/90'
                                            : concluido
                                              ? 'font-medium text-gray-700 dark:text-gray-300'
                                              : 'text-gray-500 dark:text-gray-400'
                                    }`}
                                >
                                    {nome}
                                </span>
                                {indice < passos.length - 1 && (
                                    <span
                                        aria-hidden="true"
                                        className={`ml-1 hidden h-0.5 flex-1 rounded-full sm:mr-3 sm:block ${concluido ? 'bg-success-500' : 'bg-gray-200 dark:bg-gray-800'}`}
                                    />
                                )}
                            </li>
                        );
                    })}
                </ol>
            </nav>

            <Card>
                <CardHeader title={passos[passo]} description={origem.numero ? `Viabilidade ${origem.numero}` : undefined} />
                <CardContent className="flex flex-col gap-6">
                    <StepTransition stepKey={String(passo)}>
                    {passo === 0 && (
                        <div className="flex flex-col gap-5">
                            <div className="flex items-start gap-3 rounded-xl border border-blue-light-100 bg-blue-light-50 p-4 dark:border-blue-light-500/20 dark:bg-blue-light-500/10">
                                <span className="mt-0.5 text-blue-light-500">
                                    <InfoIcon className="size-5 fill-current" aria-hidden="true" />
                                </span>
                                <p className="text-sm text-gray-700 dark:text-gray-300">
                                    Confira se é esta a viabilidade que você quer renovar. Endereço e atividades não podem ser alterados na renovação.
                                    {origem.legado && ' Esta viabilidade foi emitida no sistema anterior.'}
                                </p>
                            </div>

                            <div className="rounded-xl border border-gray-200 p-5 dark:border-gray-800">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="min-w-0">
                                        <p className="text-theme-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Empresa</p>
                                        <p className="mt-1 break-words text-base font-semibold text-gray-800 dark:text-white/90">
                                            {origem.empresa ?? 'Empresa sem razão social registrada'}
                                        </p>
                                        {origem.cnpj && <p className="text-sm text-gray-500 dark:text-gray-400">{origem.cnpj}</p>}
                                    </div>
                                    <Badge color="warning" size="sm">
                                        {origem.tipo ?? 'Provisória'}
                                    </Badge>
                                </div>

                                <dl className="mt-5 grid grid-cols-1 gap-5 border-t border-gray-100 pt-5 md:grid-cols-2 dark:border-gray-800">
                                    <div className="flex items-start gap-3">
                                        <span className="mt-0.5 text-gray-400">
                                            <CalendarIcon className="size-5" aria-hidden="true" />
                                        </span>
                                        <div>
                                            <dt className="text-theme-xs text-gray-500 dark:text-gray-400">Validade atual</dt>
                                            <dd className="text-sm font-medium text-gray-800 dark:text-white/90">
                                                {origem.legado ? formatarData(origem.validade) : (origem.validade ?? '—')}
                                            </dd>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <span className="mt-0.5 text-gray-400">
                                            <MapPinIcon className="size-5" aria-hidden="true" />
                                        </span>
                                        <div className="min-w-0">
                                            <dt className="text-theme-xs text-gray-500 dark:text-gray-400">Endereço</dt>
                                            <dd className="break-words text-sm font-medium text-gray-800 dark:text-white/90">{origem.endereco || '—'}</dd>
                                        </div>
                                    </div>
                                </dl>

                                <div className="mt-5 border-t border-gray-100 pt-5 dark:border-gray-800">
                                    <p className="text-theme-xs text-gray-500 dark:text-gray-400">Atividades</p>
                                    {origem.atividades.length === 0 ? (
                                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Nenhuma atividade registrada.</p>
                                    ) : (
                                        <ul className="mt-2 flex flex-col gap-2">
                                            {origem.atividades.map((atividade) => (
                                                <li
                                                    key={atividade}
                                                    className="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-800 dark:bg-white/5 dark:text-white/90"
                                                >
                                                    {atividade}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {passo === 1 && (
                        <div className="flex flex-col gap-5">
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                Informe como a SEDUR pode falar com você sobre esta renovação. Todos os campos são obrigatórios.
                            </p>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <Label htmlFor="email" required>E-mail</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    autoComplete="email"
                                    required
                                    value={form.data.email}
                                    error={Boolean(form.errors.email)}
                                    hint={form.errors.email}
                                    onChange={(event) => form.setData('email', event.target.value)}
                                />
                            </div>
                            <div>
                                <Label htmlFor="phone" required>Telefone</Label>
                                <MaskedInput
                                    id="phone"
                                    mask="phone"
                                    autoComplete="tel"
                                    required
                                    value={form.data.phone}
                                    error={Boolean(form.errors.phone)}
                                    hint={form.errors.phone}
                                    onChange={(valor) => form.setData('phone', valor)}
                                />
                            </div>
                            <div>
                                <Label htmlFor="mobile" required>Celular</Label>
                                <MaskedInput
                                    id="mobile"
                                    mask="phone"
                                    autoComplete="tel"
                                    required
                                    value={form.data.mobile}
                                    error={Boolean(form.errors.mobile)}
                                    hint={form.errors.mobile}
                                    onChange={(valor) => form.setData('mobile', valor)}
                                />
                            </div>
                        </div>
                        </div>
                    )}

                    {passo === 2 && (
                        <fieldset className="flex flex-col gap-3">
                            <legend className="mb-1 text-sm text-gray-600 dark:text-gray-300">
                                Leia e marque todas as declarações para continuar.
                            </legend>
                            {obrigatorias.length > 0 && (
                                <p className="text-theme-xs text-gray-500 dark:text-gray-400" aria-live="polite">
                                    {form.data.declaration_ids.length} de {declaracoes.length} marcada(s)
                                </p>
                            )}
                            {declaracoes.map((declaracao) => {
                                const marcada = form.data.declaration_ids.includes(declaracao.id);

                                return (
                                    <div
                                        key={declaracao.id}
                                        className={`min-h-11 rounded-xl border p-4 transition-colors ${
                                            marcada
                                                ? 'border-brand-500 bg-brand-50 dark:border-brand-500/60 dark:bg-brand-500/10'
                                                : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600'
                                        }`}
                                    >
                                        <Checkbox
                                            id={`declaracao-${declaracao.id}`}
                                            label={declaracao.body}
                                            checked={marcada}
                                            onChange={(valor) => alternarDeclaracao(declaracao.id, valor)}
                                        />
                                    </div>
                                );
                            })}
                        </fieldset>
                    )}

                    {passo === 3 && (
                        <div className="flex flex-col gap-4">
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                Confira os dados antes de protocolar. Depois de protocolada, a renovação segue para análise da SEDUR.
                            </p>
                            <dl className="flex flex-col divide-y divide-gray-100 rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                                <ItemRevisao titulo="Viabilidade" onEditar={() => setPasso(0)}>
                                    <p className="font-medium text-gray-800 dark:text-white/90">{origem.numero}</p>
                                    <p className="text-gray-500 dark:text-gray-400">{origem.empresa}</p>
                                </ItemRevisao>
                                <ItemRevisao titulo="Contato" onEditar={() => setPasso(1)}>
                                    <p className="text-gray-800 dark:text-white/90">{form.data.email}</p>
                                    <p className="text-gray-500 dark:text-gray-400">
                                        {form.data.phone} · {form.data.mobile}
                                    </p>
                                </ItemRevisao>
                                <ItemRevisao titulo="Declarações" onEditar={() => setPasso(2)}>
                                    <p className="text-gray-800 dark:text-white/90">
                                        {form.data.declaration_ids.length} de {declaracoes.length} marcada(s)
                                    </p>
                                </ItemRevisao>
                            </dl>
                            {form.errors.declaration_ids && <Alert variant="error" title="Não foi possível protocolar" message={form.errors.declaration_ids} />}
                        </div>
                    )}
                    </StepTransition>

                    <div className="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-between dark:border-gray-800">
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
                                disabled={(passo === 1 && !contatoCompleto) || (passo === 2 && !todasMarcadas)}
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
