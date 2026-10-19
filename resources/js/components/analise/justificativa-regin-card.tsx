import { router } from '@inertiajs/react';
import { useState } from 'react';
import FieldError from '@/components/form/field-error';
import Label from '@/components/form/label';
import Textarea from '@/components/form/textarea';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';

export interface JustificativaRegin {
    texto: string;
    personalizada: boolean;
}

interface JustificativaReginCardProps {
    processoId: number;
    justificativa: JustificativaRegin;
}

const LIMITE = 10000;

/**
 * Justificativa que o parecer leva à JUCEB. O texto fica gravado no processo: o
 * analista o escreve com o fundamento da decisão e o envio usa exatamente ele.
 */
export default function JustificativaReginCard({ processoId, justificativa }: JustificativaReginCardProps) {
    const [texto, setTexto] = useState(justificativa.texto);
    const [processando, setProcessando] = useState(false);
    const [erro, setErro] = useState<string | null>(null);

    const alterada = texto.trim() !== justificativa.texto.trim();
    const vazia = texto.trim() === '';

    function salvar() {
        setErro(null);
        router.put(
            `/gestao/processos/${processoId}/justificativa-regin`,
            { justificativa: texto },
            {
                preserveScroll: true,
                onStart: () => setProcessando(true),
                onFinish: () => setProcessando(false),
                onError: (erros) => {
                    const mapa = erros as Record<string, string>;
                    setErro(mapa.justificativa ?? 'Não foi possível salvar a justificativa.');
                },
            },
        );
    }

    return (
        <Card>
            <CardHeader
                title="Justificativa enviada à JUCEB"
                actions={
                    <Badge size="sm" color={justificativa.personalizada ? 'success' : 'warning'}>
                        {justificativa.personalizada ? 'Texto do analista' : 'Texto padrão da decisão'}
                    </Badge>
                }
            />
            <CardContent>
                <div className="flex flex-col gap-4">
                    <p className="text-sm text-gray-600 dark:text-gray-400">
                        Este é o texto que segue no campo de justificativa do parecer. Diga o fundamento da decisão na
                        lei; ao salvar, o parecer passa a levar exatamente este texto.
                    </p>
                    <div>
                        <Label htmlFor="justificativa-regin">Justificativa</Label>
                        <Textarea
                            id="justificativa-regin"
                            rows={10}
                            maxLength={LIMITE}
                            value={texto}
                            onChange={(evento) => setTexto(evento.target.value)}
                            error={erro !== null}
                            hint={`${texto.length} de ${LIMITE} caracteres`}
                        />
                    </div>
                    {erro && <FieldError>{erro}</FieldError>}
                    <div className="flex justify-end">
                        <Button
                            type="button"
                            size="sm"
                            onClick={salvar}
                            disabled={vazia || !alterada || processando}
                            loading={processando}
                        >
                            Salvar
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
