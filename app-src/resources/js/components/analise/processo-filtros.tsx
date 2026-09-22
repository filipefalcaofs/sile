import { type FormEvent, useState } from 'react';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import Button from '@/components/ui/button';

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
}

interface ProcessoFiltrosProps {
    valores: ProcessoFiltrosValores;
    servicoOptions: SelectOption[];
    analysisStatusOptions: SelectOption[];
    categoriaOptions: SelectOption[];
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
};

/**
 * Filtros de pesquisa das caixas (setor e analista). Server-driven: o pai
 * decide a rota da visita; aqui só se coleta e envia os valores.
 */
export default function ProcessoFiltros({
    valores,
    servicoOptions,
    analysisStatusOptions,
    categoriaOptions,
    onAplicar,
    onLimpar,
}: ProcessoFiltrosProps) {
    const [form, setForm] = useState<ProcessoFiltrosValores>(valores);
    const [avancadosAbertos, setAvancadosAbertos] = useState(
        valores.nome !== '' || valores.cnpj !== '' || valores.bairro !== '' || valores.categoria !== '',
    );

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
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <div>
                    <Label htmlFor="filtro-servico">Serviço</Label>
                    <Select id="filtro-servico" value={form.servico} onChange={(v) => definir('servico', v)} placeholder="Todos" options={servicoOptions} />
                </div>
                <div>
                    <Label htmlFor="filtro-data-de">Data início</Label>
                    <Input id="filtro-data-de" type="date" value={form.data_de} onChange={(e) => definir('data_de', e.target.value)} />
                </div>
                <div>
                    <Label htmlFor="filtro-data-ate">Data fim</Label>
                    <Input id="filtro-data-ate" type="date" value={form.data_ate} onChange={(e) => definir('data_ate', e.target.value)} />
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
            </div>

            {avancadosAbertos && (
                <div id="filtros-avancados" className="grid grid-cols-1 gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-gray-800">
                    <div>
                        <Label htmlFor="filtro-nome">Empresa / requerente</Label>
                        <Input id="filtro-nome" value={form.nome} onChange={(e) => definir('nome', e.target.value)} />
                    </div>
                    <div>
                        <Label htmlFor="filtro-cnpj">CNPJ</Label>
                        <Input id="filtro-cnpj" value={form.cnpj} onChange={(e) => definir('cnpj', e.target.value)} />
                    </div>
                    <div>
                        <Label htmlFor="filtro-bairro">Bairro</Label>
                        <Input id="filtro-bairro" value={form.bairro} onChange={(e) => definir('bairro', e.target.value)} />
                    </div>
                    <div>
                        <Label htmlFor="filtro-categoria">Categoria</Label>
                        <Select id="filtro-categoria" value={form.categoria} onChange={(v) => definir('categoria', v)} placeholder="Todas" options={categoriaOptions} />
                    </div>
                </div>
            )}

            <div className="flex flex-wrap items-center gap-3">
                <Button type="submit" variant="primary">Pesquisar</Button>
                <Button type="button" variant="outline" onClick={limpar}>Limpar</Button>
                <button
                    type="button"
                    aria-expanded={avancadosAbertos}
                    aria-controls="filtros-avancados"
                    onClick={() => setAvancadosAbertos((aberto) => !aberto)}
                    className="text-theme-sm font-medium text-brand-600 underline dark:text-brand-400"
                >
                    {avancadosAbertos ? 'Ocultar filtros avançados' : 'Filtros avançados'}
                </button>
            </div>
        </form>
    );
}
