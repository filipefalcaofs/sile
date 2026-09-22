import { Form, Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Switch from '@/components/form/switch';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface InscricaoImobiliariaConfig {
    em_producao: boolean;
    url_homologacao: string;
    url_producao: string;
    url_ativa: string;
}

interface Props {
    config: InscricaoImobiliariaConfig;
}

export default function ConfigInscricaoImobiliaria({ config }: Props) {
    const { flash } = usePage<SharedProps>().props;
    const [emProducao, setEmProducao] = useState(config.em_producao);

    return (
        <>
            <Head title="API de inscrição imobiliária" />
            <PageHeader title="API de inscrição imobiliária" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <div className="space-y-6">
                <Card>
                    <CardHeader
                        title="Ambiente e endereços"
                        description="O interruptor escolhe qual URL o sistema usa. As duas URLs continuam editáveis."
                    />
                    <CardContent className="space-y-5">
                        <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                            URL ativa agora:{' '}
                            <span className="font-mono text-gray-800 dark:text-white/90">{config.url_ativa}</span>
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Badge color={config.em_producao ? 'warning' : 'light'}>
                                {config.em_producao ? 'Produção' : 'Homologação'}
                            </Badge>
                        </div>

                        {flash.status && (
                            <p className="text-theme-sm text-gray-700 dark:text-gray-200">{flash.status}</p>
                        )}

                        <Form action="/gestao/config-inscricao-imobiliaria" method="put" className="space-y-5">
                            {({ errors, processing }) => (
                                <>
                                    <input type="hidden" name="em_producao" value={emProducao ? '1' : '0'} />

                                    <div>
                                        <Switch
                                            label={emProducao ? 'Usar produção' : 'Usar homologação'}
                                            defaultChecked={config.em_producao}
                                            onChange={setEmProducao}
                                        />
                                        {errors.em_producao && (
                                            <p className="mt-1.5 text-theme-xs text-error-500">{errors.em_producao}</p>
                                        )}
                                    </div>

                                    <div>
                                        <Label htmlFor="url_homologacao">URL de homologação</Label>
                                        <Input
                                            id="url_homologacao"
                                            name="url_homologacao"
                                            type="url"
                                            defaultValue={config.url_homologacao}
                                            error={!!errors.url_homologacao}
                                            hint={errors.url_homologacao}
                                        />
                                    </div>

                                    <div>
                                        <Label htmlFor="url_producao">URL de produção</Label>
                                        <Input
                                            id="url_producao"
                                            name="url_producao"
                                            type="url"
                                            defaultValue={config.url_producao}
                                            error={!!errors.url_producao}
                                            hint={errors.url_producao}
                                        />
                                    </div>

                                    <Button size="sm" type="submit" disabled={processing}>
                                        {processing ? 'Salvando…' : 'Salvar configuração'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        title="Teste de conexão"
                        description="Consulta de verdade a URL ativa. Falha aparece como falha — sem resultado simulado."
                    />
                    <CardContent>
                        <Form action="/gestao/config-inscricao-imobiliaria/testar" method="post">
                            {({ errors, processing }) => (
                                <div className="space-y-3">
                                    {errors.inscricao_connection && (
                                        <p className="text-theme-sm text-error-500">{errors.inscricao_connection}</p>
                                    )}
                                    <Button size="sm" type="submit" variant="outline" disabled={processing}>
                                        {processing ? 'Testando…' : 'Testar conexão na URL ativa'}
                                    </Button>
                                </div>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ConfigInscricaoImobiliaria.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
