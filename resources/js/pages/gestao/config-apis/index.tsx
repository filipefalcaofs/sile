import FieldError from '@/components/form/field-error';
import { Form, Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import Switch from '@/components/form/switch';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface Opcao {
    value: string;
    label: string;
}

interface CampoApi {
    id: string;
    rotulo: string;
    tipo: 'url' | 'texto' | 'senha' | 'interruptor' | 'escolha';
    opcoes: Opcao[];
    ajuda: string | null;
    tem_valor: boolean;
    valor: string | boolean | null;
}

interface TesteApi {
    url: string;
    erro: string;
}

interface AbaApi {
    id: string;
    nome: string;
    descricao: string;
    teste: TesteApi | null;
    campos: CampoApi[];
}

interface Props {
    aba: string;
    abas: AbaApi[];
}

function Interruptor({ name, label, checked }: { name: string; label: string; checked: boolean }) {
    const [ligado, setLigado] = useState(checked);

    return (
        <>
            <input type="hidden" name={name} value={ligado ? '1' : '0'} />
            <Switch label={label} defaultChecked={checked} onChange={setLigado} />
        </>
    );
}

function Escolha({ name, id, value, options }: { name: string; id: string; value: string; options: Opcao[] }) {
    const [atual, setAtual] = useState(value);

    return <Select id={id} name={name} value={atual} onChange={setAtual} options={options} />;
}

export default function ConfigApis({ aba, abas }: Props) {
    const { errors } = usePage<SharedProps>().props;
    const inicial = abas.some((item) => item.id === aba) ? aba : (abas[0]?.id ?? '');
    const [ativa, setAtiva] = useState(inicial);
    const selecionada = abas.find((item) => item.id === ativa) ?? abas[0];
    const erroTeste = selecionada?.teste ? errors[selecionada.teste.erro] : undefined;

    return (
        <>
            <Head title="APIs" />
            <PageHeader title="APIs" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <div className="flex flex-col gap-4 md:gap-6">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Endereços e credenciais das integrações em uso. A alteração vale na hora, sem novo deploy. Senha e
                    token gravados não são exibidos.
                </p>

                <div className="border-b border-gray-200 dark:border-gray-800">
                    <nav role="tablist" aria-label="APIs" className="-mb-px flex flex-wrap gap-1">
                        {abas.map((item) => {
                            const selected = item.id === selecionada?.id;

                            return (
                                <button
                                    key={item.id}
                                    type="button"
                                    role="tab"
                                    id={`tab-${item.id}`}
                                    aria-selected={selected}
                                    aria-controls={`panel-${item.id}`}
                                    onClick={() => setAtiva(item.id)}
                                    className={`border-b-2 px-4 py-2.5 text-sm font-medium transition-colors ${
                                        selected
                                            ? 'border-brand-500 text-brand-600 dark:border-brand-400 dark:text-brand-400'
                                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-700 dark:hover:text-gray-200'
                                    }`}
                                >
                                    {item.nome}
                                </button>
                            );
                        })}
                    </nav>
                </div>

                {selecionada && (
                    <section role="tabpanel" id={`panel-${selecionada.id}`} aria-labelledby={`tab-${selecionada.id}`}>
                        <Card>
                            <CardHeader title={selecionada.nome} description={selecionada.descricao} />
                            <CardContent className="space-y-5">
                                <Form
                                    key={`${selecionada.id}-${selecionada.campos.map((campo) => String(campo.valor)).join('|')}`}
                                    action={`/gestao/config-apis/${selecionada.id}`}
                                    method="put"
                                    className="space-y-5"
                                >
                                    {({ errors: formErrors, processing }) => (
                                        <>
                                            {selecionada.campos.map((campo) => {
                                                const name = `campos[${campo.id}]`;
                                                const erro = formErrors[`campos.${campo.id}`];

                                                if (campo.tipo === 'interruptor') {
                                                    return (
                                                        <div key={campo.id}>
                                                            <Interruptor name={name} label={campo.rotulo} checked={campo.valor === true} />
                                                            {erro && <FieldError>{erro}</FieldError>}
                                                        </div>
                                                    );
                                                }

                                                if (campo.tipo === 'escolha') {
                                                    return (
                                                        <div key={campo.id}>
                                                            <Label htmlFor={`${selecionada.id}-${campo.id}`}>{campo.rotulo}</Label>
                                                            <Escolha
                                                                id={`${selecionada.id}-${campo.id}`}
                                                                name={name}
                                                                value={typeof campo.valor === 'string' ? campo.valor : ''}
                                                                options={campo.opcoes}
                                                            />
                                                            {erro && <FieldError>{erro}</FieldError>}
                                                        </div>
                                                    );
                                                }

                                                return (
                                                    <div key={campo.id} className="min-w-0">
                                                        <Label htmlFor={`${selecionada.id}-${campo.id}`}>{campo.rotulo}</Label>
                                                        <Input
                                                            id={`${selecionada.id}-${campo.id}`}
                                                            name={name}
                                                            type={campo.tipo === 'senha' ? 'password' : campo.tipo === 'url' ? 'url' : 'text'}
                                                            autoComplete={campo.tipo === 'senha' ? 'new-password' : 'off'}
                                                            defaultValue={typeof campo.valor === 'string' ? campo.valor : ''}
                                                            placeholder={campo.tipo === 'senha' ? '••••••' : undefined}
                                                            error={!!erro}
                                                            hint={erro ?? campo.ajuda ?? undefined}
                                                        />
                                                        {campo.tipo === 'senha' && (
                                                            <p className="mt-1.5 text-theme-xs text-gray-400 dark:text-gray-500">
                                                                {campo.tem_valor
                                                                    ? 'Já existe um valor gravado. Deixe em branco para mantê-lo.'
                                                                    : 'Nenhum valor gravado. O campo nunca reexibe o que foi salvo.'}
                                                            </p>
                                                        )}
                                                    </div>
                                                );
                                            })}

                                            <Button size="sm" type="submit" disabled={processing}>
                                                {processing ? 'Salvando…' : 'Salvar'}
                                            </Button>
                                        </>
                                    )}
                                </Form>

                                {selecionada.teste && (
                                    <div className="space-y-3 border-t border-gray-100 pt-5 dark:border-gray-800">
                                        <div className="flex flex-wrap gap-2">
                                            <Badge color="light">Teste de conexão</Badge>
                                        </div>
                                        <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                                            Chama a URL ativa de verdade. Falha aparece como falha.
                                        </p>
                                        <Form action={selecionada.teste.url} method="post">
                                            {({ processing }) => (
                                                <div className="space-y-3">
                                                    {erroTeste && (
                                                        <FieldError>{erroTeste}</FieldError>
                                                    )}
                                                    <Button size="sm" type="submit" variant="outline" disabled={processing}>
                                                        {processing ? 'Testando…' : 'Testar conexão'}
                                                    </Button>
                                                </div>
                                            )}
                                        </Form>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </section>
                )}
            </div>
        </>
    );
}

ConfigApis.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
