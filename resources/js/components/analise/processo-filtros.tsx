import { type FormEvent, useState } from 'react';
import Input from '@/components/form/input';
import IsoDateInput from '@/components/form/iso-date-input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import Button from '@/components/ui/button';
import { applyMask, MASK_PATTERNS } from '@/lib/masks';

interface SelectOption {
    value: string;
    label: string;
}

export interface ProcessoFiltrosValores {
    analysis_status: string;
    servico: string;
    protocolo: string;
    bap: string;
    data_de: string;
    data_ate: string;
    nome: string;
    cnpj: string;
    bairro: string;
    categoria: string;
    analista: string;
    cnae: string;
    complemento: string;
}

interface ProcessoFiltrosProps {
    valores: Partial<ProcessoFiltrosValores>;
    servicoOptions: SelectOption[];
    analysisStatusOptions: SelectOption[];
    categoriaOptions: SelectOption[];
    analistaOptions?: SelectOption[];
    onAplicar: (valores: ProcessoFiltrosValores) => void;
    onLimpar: () => void;
}

export const FILTROS_VAZIOS: ProcessoFiltrosValores = {
    analysis_status: '',
    servico: '',
    protocolo: '',
    bap: '',
    data_de: '',
    data_ate: '',
    nome: '',
    cnpj: '',
    bairro: '',
    categoria: '',
    analista: '',
    cnae: '',
    complemento: '',
};

/** CPF (até 11 dígitos) ou CNPJ no mesmo campo: a máscara segue a quantidade de dígitos. */
export function mascararDocumento(valor: string): string {
    const digitos = valor.replace(/\D/g, '').slice(0, 14);

    return applyMask(digitos, digitos.length <= 11 ? MASK_PATTERNS.cpf : MASK_PATTERNS.cnpj);
}

/**
 * Filtros de pesquisa das caixas (setor e analista). Server-driven: o pai
 * decide a rota da visita; aqui só se coleta e envia os valores.
 */
export default function ProcessoFiltros({
    valores,
    servicoOptions,
    analysisStatusOptions,
    categoriaOptions,
    analistaOptions = [],
    onAplicar,
    onLimpar,
}: ProcessoFiltrosProps) {
    const [form, setForm] = useState<ProcessoFiltrosValores>({ ...FILTROS_VAZIOS, ...valores });

    function definir(chave: keyof ProcessoFiltrosValores, valor: string) {
        setForm((anterior) => ({ ...anterior, [chave]: valor }));
    }

    function aplicar(evento: FormEvent) {
        evento.preventDefault();
        onAplicar(form);
    }

    function limpar() {
        setForm(FILTROS_VAZIOS);
        onLimpar();
    }

    return (
        <form onSubmit={aplicar} className="space-y-4">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <div>
                    <Label htmlFor="filtro-servico">Serviço</Label>
                    <Select id="filtro-servico" value={form.servico} onChange={(v) => definir('servico', v)} placeholder="Todos" options={servicoOptions} />
                </div>
                <div>
                    <Label htmlFor="filtro-data-de">Data início</Label>
                    <IsoDateInput id="filtro-data-de" value={form.data_de} onChange={(valor) => definir('data_de', valor)} />
                </div>
                <div>
                    <Label htmlFor="filtro-data-ate">Data fim</Label>
                    <IsoDateInput id="filtro-data-ate" value={form.data_ate} onChange={(valor) => definir('data_ate', valor)} />
                </div>
                <div>
                    <Label htmlFor="filtro-analysis-status">Status tramitação</Label>
                    <Select id="filtro-analysis-status" value={form.analysis_status} onChange={(v) => definir('analysis_status', v)} placeholder="Todos" options={analysisStatusOptions} />
                </div>
                <div>
                    <Label htmlFor="filtro-protocolo">Número do processo</Label>
                    <Input id="filtro-protocolo" value={form.protocolo} onChange={(e) => definir('protocolo', e.target.value)} />
                </div>
                <div>
                    <Label htmlFor="filtro-bap">BAP</Label>
                    <Input id="filtro-bap" value={form.bap} onChange={(e) => definir('bap', e.target.value)} />
                </div>
                <div>
                    <Label htmlFor="filtro-nome">Empresa / requerente</Label>
                    <Input id="filtro-nome" value={form.nome} onChange={(e) => definir('nome', e.target.value)} />
                </div>
                <div>
                    <Label htmlFor="filtro-cnpj">CNPJ / CPF</Label>
                    <Input
                        id="filtro-cnpj"
                        inputMode="numeric"
                        value={mascararDocumento(form.cnpj)}
                        onChange={(e) => definir('cnpj', mascararDocumento(e.target.value))}
                    />
                </div>
                <div>
                    <Label htmlFor="filtro-bairro">Bairro</Label>
                    <Input id="filtro-bairro" value={form.bairro} onChange={(e) => definir('bairro', e.target.value)} />
                </div>
                <div>
                    <Label htmlFor="filtro-categoria">Categoria</Label>
                    <Select id="filtro-categoria" value={form.categoria} onChange={(v) => definir('categoria', v)} placeholder="Todas" options={categoriaOptions} />
                </div>
                <div>
                    <Label htmlFor="filtro-analista">Analista</Label>
                    <Select id="filtro-analista" value={form.analista} onChange={(v) => definir('analista', v)} placeholder="Todos" options={analistaOptions} />
                </div>
                <div>
                    <Label htmlFor="filtro-cnae">CNAE</Label>
                    <Input id="filtro-cnae" value={form.cnae} onChange={(e) => definir('cnae', e.target.value)} placeholder="Código ou descrição" />
                </div>
                <div>
                    <Label htmlFor="filtro-complemento">Complemento</Label>
                    <Input id="filtro-complemento" value={form.complemento} onChange={(e) => definir('complemento', e.target.value)} />
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <Button type="submit" variant="primary">Pesquisar</Button>
                <Button type="button" variant="outline" onClick={limpar}>Limpar</Button>
            </div>
        </form>
    );
}
