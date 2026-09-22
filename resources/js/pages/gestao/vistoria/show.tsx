import { Head, Link, router, useHttp } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';
import type { ChangeEvent, ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import { MapaPoligonoSection } from '@/components/geo/mapa-poligono-section';
import {
    areaPoligonoM2,
    geojsonParaVertices,
    verticesParaGeoJson,
    type GeoJsonPolygon,
    type Vertice,
} from '@/components/geo/poligono';
import Checkbox from '@/components/form/checkbox';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import MaskedInput from '@/components/form/masked-input';
import Select from '@/components/form/select';
import { CheckCircleIcon, EyeIcon, TrashIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import GestaoLayout from '@/layouts/gestao-layout';

interface Ficha {
    id: number;
    viability_request_id: number;
    tipo: string;
    tipo_label: string;
    status: string;
    status_label: string;
    editavel: boolean;
    opened_at: string | null;
    concluded_at: string | null;
    vistoriador: string | null;
    cod_logradouro: string | null;
    logradouro: string | null;
    numero_metrico: string | null;
    bairro: string | null;
    cep: string | null;
    ponto_referencia: string | null;
    zona: string | null;
    via: string | null;
    logradouro_correto: boolean | null;
    polygon_geojson: GeoJsonPolygon | null;
    polygon_validated_at: string | null;
    polygon_area_m2: number | null;
    tipo_imovel: string | null;
    acessos: string[];
    atividade_em_funcionamento: boolean | null;
    complemento_tipo: string | null;
    complemento_numero: string | null;
    complemento_area_m2: number | null;
    area_total_m2: number | null;
    vagas_veiculo_passeio: number | null;
    vagas_carga_descarga: number | null;
    patio_carga_descarga: boolean | null;
    area_embarque_desembarque: boolean | null;
    area_terreno_m2: number | null;
    area_total_construida_m2: number | null;
    area_ocupada_atividade_m2: number | null;
    area_carga_descarga_m2: number | null;
    pavimento_edificacao: string | null;
    pavimento_ocupado_atividade: string | null;
    recuo_m: number | null;
    entorno_residencial_m: number | null;
    entorno_industrial_m: number | null;
    entorno_saude_m: number | null;
    entorno_comercial_m: number | null;
    entorno_institucional_m: number | null;
    entorno_especial_m: number | null;
    entorno_educacional_m: number | null;
    entorno_misto_m: number | null;
    entorno_outros_m: number | null;
    instalacoes_eletricas: string | null;
    instalacoes_hidrossanitarias: string | null;
    obras: string[];
    alvara_numero: string | null;
    quantidade_usuarios: number | null;
    num_salas_alunos: number | null;
    num_assentos: number | null;
    unidades_hospedagem: number | null;
    num_leitos: number | null;
    equipamentos: string[];
    equipamentos_outros: string | null;
    maquinas_motores: boolean | null;
    sons_ruidos: boolean | null;
    sons_ruidos_origem: string | null;
    seg_extintores: boolean | null;
    seg_central_gas: boolean | null;
    seg_hidrantes: boolean | null;
    seg_outros: string | null;
    observacoes: string | null;
    parecer: string | null;
    data_vistoria: string | null;
    contato_nome: string | null;
    contato_telefone: string | null;
}

interface Processo {
    id: number;
    protocol_number: string | null;
    status: string;
    status_label: string;
}

interface Anexo {
    id: number;
    original_name: string;
    mime_type: string;
    size: number;
    created_at: string | null;
    download_url: string;
}

interface Opcoes {
    acessos: Record<string, string>;
    obras: Record<string, string>;
    equipamentos: Record<string, string>;
    satisfacao: Record<string, string>;
}

interface VistoriaShowProps {
    ficha: Ficha;
    processo: Processo;
    tiposImovel: Array<{ value: string; label: string }>;
    opcoes: Opcoes;
    anexos: Anexo[];
}

/** '' = não respondido; '1'/'0' = sim/não (selects e radios Sim/Não). */
type SimNao = '' | '1' | '0';

interface DadosFicha {
    ponto_referencia: string;
    logradouro_correto: SimNao;
    tipo_imovel: string;
    acessos: string[];
    atividade_em_funcionamento: SimNao;
    complemento_tipo: string;
    complemento_numero: string;
    complemento_area_m2: string;
    vagas_veiculo_passeio: string;
    vagas_carga_descarga: string;
    patio_carga_descarga: SimNao;
    area_embarque_desembarque: SimNao;
    area_terreno_m2: string;
    area_total_construida_m2: string;
    area_ocupada_atividade_m2: string;
    area_carga_descarga_m2: string;
    pavimento_edificacao: string;
    pavimento_ocupado_atividade: string;
    recuo_m: string;
    entorno_residencial_m: string;
    entorno_industrial_m: string;
    entorno_saude_m: string;
    entorno_comercial_m: string;
    entorno_institucional_m: string;
    entorno_especial_m: string;
    entorno_educacional_m: string;
    entorno_misto_m: string;
    entorno_outros_m: string;
    instalacoes_eletricas: string;
    instalacoes_hidrossanitarias: string;
    obras: string[];
    alvara_numero: string;
    quantidade_usuarios: string;
    num_salas_alunos: string;
    num_assentos: string;
    unidades_hospedagem: string;
    num_leitos: string;
    equipamentos: string[];
    equipamentos_outros: string;
    maquinas_motores: SimNao;
    sons_ruidos: SimNao;
    sons_ruidos_origem: string;
    seg_extintores: boolean;
    seg_central_gas: boolean;
    seg_hidrantes: boolean;
    seg_outros: string;
    observacoes: string;
    parecer: string;
    data_vistoria: string;
    contato_nome: string;
    contato_telefone: string;
}

function texto(valor: string | null): string {
    return valor ?? '';
}

function numeroTexto(valor: number | null): string {
    return valor === null ? '' : String(valor).replace('.', ',');
}

function simNao(valor: boolean | null): SimNao {
    if (valor === null || valor === undefined) {
        return '';
    }

    return valor ? '1' : '0';
}

/** Payload JSON do rascunho/conclusão — valores escalares ou listas de opções. */
type PayloadFicha = Record<string, string | number | boolean | string[] | null>;

function estadoInicial(ficha: Ficha): DadosFicha {
    return {
        ponto_referencia: texto(ficha.ponto_referencia),
        logradouro_correto: simNao(ficha.logradouro_correto),
        tipo_imovel: texto(ficha.tipo_imovel),
        acessos: ficha.acessos ?? [],
        atividade_em_funcionamento: simNao(ficha.atividade_em_funcionamento),
        complemento_tipo: texto(ficha.complemento_tipo),
        complemento_numero: texto(ficha.complemento_numero),
        complemento_area_m2: numeroTexto(ficha.complemento_area_m2),
        vagas_veiculo_passeio: numeroTexto(ficha.vagas_veiculo_passeio),
        vagas_carga_descarga: numeroTexto(ficha.vagas_carga_descarga),
        patio_carga_descarga: simNao(ficha.patio_carga_descarga),
        area_embarque_desembarque: simNao(ficha.area_embarque_desembarque),
        area_terreno_m2: numeroTexto(ficha.area_terreno_m2),
        area_total_construida_m2: numeroTexto(ficha.area_total_construida_m2),
        area_ocupada_atividade_m2: numeroTexto(ficha.area_ocupada_atividade_m2),
        area_carga_descarga_m2: numeroTexto(ficha.area_carga_descarga_m2),
        pavimento_edificacao: texto(ficha.pavimento_edificacao),
        pavimento_ocupado_atividade: texto(ficha.pavimento_ocupado_atividade),
        recuo_m: numeroTexto(ficha.recuo_m),
        entorno_residencial_m: numeroTexto(ficha.entorno_residencial_m),
        entorno_industrial_m: numeroTexto(ficha.entorno_industrial_m),
        entorno_saude_m: numeroTexto(ficha.entorno_saude_m),
        entorno_comercial_m: numeroTexto(ficha.entorno_comercial_m),
        entorno_institucional_m: numeroTexto(ficha.entorno_institucional_m),
        entorno_especial_m: numeroTexto(ficha.entorno_especial_m),
        entorno_educacional_m: numeroTexto(ficha.entorno_educacional_m),
        entorno_misto_m: numeroTexto(ficha.entorno_misto_m),
        entorno_outros_m: numeroTexto(ficha.entorno_outros_m),
        instalacoes_eletricas: texto(ficha.instalacoes_eletricas),
        instalacoes_hidrossanitarias: texto(ficha.instalacoes_hidrossanitarias),
        obras: ficha.obras ?? [],
        alvara_numero: texto(ficha.alvara_numero),
        quantidade_usuarios: numeroTexto(ficha.quantidade_usuarios),
        num_salas_alunos: numeroTexto(ficha.num_salas_alunos),
        num_assentos: numeroTexto(ficha.num_assentos),
        unidades_hospedagem: numeroTexto(ficha.unidades_hospedagem),
        num_leitos: numeroTexto(ficha.num_leitos),
        equipamentos: ficha.equipamentos ?? [],
        equipamentos_outros: texto(ficha.equipamentos_outros),
        maquinas_motores: simNao(ficha.maquinas_motores),
        sons_ruidos: simNao(ficha.sons_ruidos),
        sons_ruidos_origem: texto(ficha.sons_ruidos_origem),
        seg_extintores: ficha.seg_extintores ?? false,
        seg_central_gas: ficha.seg_central_gas ?? false,
        seg_hidrantes: ficha.seg_hidrantes ?? false,
        seg_outros: texto(ficha.seg_outros),
        observacoes: texto(ficha.observacoes),
        parecer: texto(ficha.parecer),
        data_vistoria: texto(ficha.data_vistoria),
        contato_nome: texto(ficha.contato_nome),
        contato_telefone: texto(ficha.contato_telefone),
    };
}

function numeroOuNulo(valor: string): number | null {
    const limpo = valor.trim().replace(',', '.');

    if (limpo === '') {
        return null;
    }

    const numero = Number(limpo);

    return Number.isFinite(numero) ? numero : null;
}

function inteiroOuNulo(valor: string): number | null {
    const numero = numeroOuNulo(valor);

    return numero === null ? null : Math.trunc(numero);
}

function boolOuNulo(valor: SimNao): boolean | null {
    if (valor === '') {
        return null;
    }

    return valor === '1';
}

function textoOuNulo(valor: string): string | null {
    const limpo = valor.trim();

    return limpo === '' ? null : limpo;
}

function formatarData(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleDateString('pt-BR');
}

function formatarTamanho(bytes: number): string {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024)).toLocaleString('pt-BR')} KB`;
}

const inputReadonly =
    'h-11 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400';

/** Campo somente leitura do snapshot da localização (veio do cadastro). */
function CampoReadonly({ rotulo, valor, className = '' }: { rotulo: string; valor: string | null; className?: string }) {
    return (
        <div className={className}>
            <span className="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">{rotulo}</span>
            <div className={inputReadonly} aria-readonly="true">
                {valor ?? '—'}
            </div>
        </div>
    );
}

/** Radio Sim/Não das perguntas da ficha. */
function RadioSimNao({
    nome,
    valor,
    onChange,
    disabled,
}: {
    nome: string;
    valor: SimNao;
    onChange: (valor: SimNao) => void;
    disabled: boolean;
}) {
    return (
        <div className="flex items-center gap-6" role="radiogroup">
            {(['1', '0'] as const).map((opcao) => (
                <label key={opcao} className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input
                        type="radio"
                        name={nome}
                        value={opcao}
                        checked={valor === opcao}
                        disabled={disabled}
                        onChange={() => onChange(opcao)}
                        className="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700"
                    />
                    {opcao === '1' ? 'Sim' : 'Não'}
                </label>
            ))}
        </div>
    );
}

/** Radio de domínio fechado (ex.: Satisfaz / Não Satisfaz). */
function RadioOpcoes({
    nome,
    valor,
    opcoes,
    onChange,
    disabled,
}: {
    nome: string;
    valor: string;
    opcoes: Record<string, string>;
    onChange: (valor: string) => void;
    disabled: boolean;
}) {
    return (
        <div className="flex flex-col gap-2" role="radiogroup">
            {Object.entries(opcoes).map(([valorOpcao, rotulo]) => (
                <label key={valorOpcao} className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input
                        type="radio"
                        name={nome}
                        value={valorOpcao}
                        checked={valor === valorOpcao}
                        disabled={disabled}
                        onChange={() => onChange(valorOpcao)}
                        className="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700"
                    />
                    {rotulo}
                </label>
            ))}
        </div>
    );
}

function SecaoTitulo({ children }: { children: ReactNode }) {
    return (
        <h4 className="text-xs font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">{children}</h4>
    );
}

export default function VistoriaShow({ ficha: fichaInicial, processo, tiposImovel, opcoes, anexos }: VistoriaShowProps) {
    const [ficha, setFicha] = useState<Ficha>(fichaInicial);
    const [dados, setDados] = useState<DadosFicha>(() => estadoInicial(fichaInicial));
    const [saveState, setSaveState] = useState<'idle' | 'salvo' | 'erro'>('idle');
    const [erroGeral, setErroGeral] = useState<string | null>(null);
    const [showConcluir, setShowConcluir] = useState(false);

    const editavel = ficha.editavel;

    // Polígono: vértices de trabalho (visualização ou redesenho em andamento).
    const [vertices, setVertices] = useState<Vertice[]>(() => geojsonParaVertices(fichaInicial.polygon_geojson));
    const [editandoPoligono, setEditandoPoligono] = useState(false);
    const [erroPoligono, setErroPoligono] = useState<string | null>(null);

    // Anexos.
    const arquivoRef = useRef<HTMLInputElement>(null);
    const [enviandoAnexo, setEnviandoAnexo] = useState(false);
    const [anexoParaRemover, setAnexoParaRemover] = useState<Anexo | null>(null);

    const baseUrl = `/gestao/processos/${processo.id}/vistoria`;

    const rascunho = useHttp<PayloadFicha, { ficha: Ficha; status: string }>({});
    const conclusao = useHttp<PayloadFicha, { ficha: Ficha; status: string }>({});
    const poligonoHttp = useHttp<{ polygon: GeoJsonPolygon | null }, { ficha: Ficha; status: string }>({ polygon: null });

    const areaPrevia = useMemo(() => areaPoligonoM2(vertices), [vertices]);

    const set = <K extends keyof DadosFicha>(campo: K, valor: DadosFicha[K]) => {
        setDados((atual) => ({ ...atual, [campo]: valor }));
        setSaveState('idle');
    };

    const alternarLista = (campo: 'acessos' | 'obras' | 'equipamentos', valor: string, marcado: boolean) => {
        setDados((atual) => ({
            ...atual,
            [campo]: marcado ? [...atual[campo], valor] : atual[campo].filter((item) => item !== valor),
        }));
        setSaveState('idle');
    };

    const construirPayload = (): PayloadFicha => ({
        ponto_referencia: textoOuNulo(dados.ponto_referencia),
        logradouro_correto: boolOuNulo(dados.logradouro_correto),
        tipo_imovel: textoOuNulo(dados.tipo_imovel),
        acessos: dados.acessos,
        atividade_em_funcionamento: boolOuNulo(dados.atividade_em_funcionamento),
        complemento_tipo: textoOuNulo(dados.complemento_tipo),
        complemento_numero: textoOuNulo(dados.complemento_numero),
        complemento_area_m2: numeroOuNulo(dados.complemento_area_m2),
        area_total_m2: numeroOuNulo(dados.complemento_area_m2),
        vagas_veiculo_passeio: inteiroOuNulo(dados.vagas_veiculo_passeio),
        vagas_carga_descarga: inteiroOuNulo(dados.vagas_carga_descarga),
        patio_carga_descarga: boolOuNulo(dados.patio_carga_descarga),
        area_embarque_desembarque: boolOuNulo(dados.area_embarque_desembarque),
        area_terreno_m2: numeroOuNulo(dados.area_terreno_m2),
        area_total_construida_m2: numeroOuNulo(dados.area_total_construida_m2),
        area_ocupada_atividade_m2: numeroOuNulo(dados.area_ocupada_atividade_m2),
        area_carga_descarga_m2: numeroOuNulo(dados.area_carga_descarga_m2),
        pavimento_edificacao: textoOuNulo(dados.pavimento_edificacao),
        pavimento_ocupado_atividade: textoOuNulo(dados.pavimento_ocupado_atividade),
        recuo_m: numeroOuNulo(dados.recuo_m),
        entorno_residencial_m: numeroOuNulo(dados.entorno_residencial_m),
        entorno_industrial_m: numeroOuNulo(dados.entorno_industrial_m),
        entorno_saude_m: numeroOuNulo(dados.entorno_saude_m),
        entorno_comercial_m: numeroOuNulo(dados.entorno_comercial_m),
        entorno_institucional_m: numeroOuNulo(dados.entorno_institucional_m),
        entorno_especial_m: numeroOuNulo(dados.entorno_especial_m),
        entorno_educacional_m: numeroOuNulo(dados.entorno_educacional_m),
        entorno_misto_m: numeroOuNulo(dados.entorno_misto_m),
        entorno_outros_m: numeroOuNulo(dados.entorno_outros_m),
        instalacoes_eletricas: textoOuNulo(dados.instalacoes_eletricas),
        instalacoes_hidrossanitarias: textoOuNulo(dados.instalacoes_hidrossanitarias),
        obras: dados.obras,
        alvara_numero: textoOuNulo(dados.alvara_numero),
        quantidade_usuarios: inteiroOuNulo(dados.quantidade_usuarios),
        num_salas_alunos: inteiroOuNulo(dados.num_salas_alunos),
        num_assentos: inteiroOuNulo(dados.num_assentos),
        unidades_hospedagem: inteiroOuNulo(dados.unidades_hospedagem),
        num_leitos: inteiroOuNulo(dados.num_leitos),
        equipamentos: dados.equipamentos,
        equipamentos_outros: textoOuNulo(dados.equipamentos_outros),
        maquinas_motores: boolOuNulo(dados.maquinas_motores),
        sons_ruidos: boolOuNulo(dados.sons_ruidos),
        sons_ruidos_origem: textoOuNulo(dados.sons_ruidos_origem),
        seg_extintores: dados.seg_extintores,
        seg_central_gas: dados.seg_central_gas,
        seg_hidrantes: dados.seg_hidrantes,
        seg_outros: textoOuNulo(dados.seg_outros),
        observacoes: textoOuNulo(dados.observacoes),
        parecer: textoOuNulo(dados.parecer),
        data_vistoria: textoOuNulo(dados.data_vistoria),
        contato_nome: textoOuNulo(dados.contato_nome),
        contato_telefone: textoOuNulo(dados.contato_telefone),
    });

    const salvarRascunho = () => {
        setErroGeral(null);
        rascunho.transform(() => construirPayload());
        rascunho.patch(baseUrl, {
            onSuccess: (resposta) => {
                setFicha(resposta.ficha);
                setSaveState('salvo');
            },
            onError: () => setSaveState('erro'),
            onHttpException: () => {
                setSaveState('erro');
                setErroGeral('Não foi possível salvar o rascunho. Revise os campos destacados.');

                return false;
            },
        });
    };

    const concluirVistoria = () => {
        setErroGeral(null);
        conclusao.transform(() => construirPayload());
        conclusao.post(`${baseUrl}/concluir`, {
            onSuccess: (resposta) => {
                setFicha(resposta.ficha);
                setShowConcluir(false);
            },
            onHttpException: () => {
                setShowConcluir(false);
                setErroGeral('Não foi possível concluir a vistoria. Revise os campos destacados.');

                return false;
            },
        });
    };

    const iniciarRedesenho = () => {
        setErroPoligono(null);
        setVertices([]);
        setEditandoPoligono(true);
    };

    const cancelarRedesenho = () => {
        setErroPoligono(null);
        setVertices(geojsonParaVertices(ficha.polygon_geojson));
        setEditandoPoligono(false);
    };

    const validarPoligono = () => {
        const geojson = verticesParaGeoJson(vertices);

        if (geojson === null) {
            setErroPoligono('Desenhe ao menos 3 vértices no mapa antes de validar o polígono.');

            return;
        }

        setErroPoligono(null);
        poligonoHttp.transform(() => ({ polygon: geojson }));
        poligonoHttp.post(`${baseUrl}/poligono`, {
            onSuccess: (resposta) => {
                setFicha(resposta.ficha);
                setVertices(geojsonParaVertices(resposta.ficha.polygon_geojson));
                setEditandoPoligono(false);
            },
            onHttpException: () => {
                setErroPoligono('O polígono desenhado foi recusado. Revise o desenho e tente novamente.');

                return false;
            },
        });
    };

    const enviarAnexo = (event: ChangeEvent<HTMLInputElement>) => {
        const arquivo = event.target.files?.[0];

        if (!arquivo) {
            return;
        }

        setEnviandoAnexo(true);
        router.post(
            `${baseUrl}/anexos`,
            { file: arquivo },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setEnviandoAnexo(false);

                    if (arquivoRef.current) {
                        arquivoRef.current.value = '';
                    }
                },
            },
        );
    };

    const removerAnexo = () => {
        if (!anexoParaRemover) {
            return;
        }

        router.delete(`${baseUrl}/anexos/${anexoParaRemover.id}`, {
            preserveScroll: true,
            onFinish: () => setAnexoParaRemover(null),
        });
    };

    const erroParecer = (conclusao.errors as Record<string, string>).parecer ?? null;
    const areaTotalCalculada = numeroOuNulo(dados.complemento_area_m2);

    return (
        <>
            <Head title={`Ficha de vistoria — ${processo.protocol_number ?? 'processo'}`} />

            <PageHeader
                title="Ficha de vistoria"
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Processos', href: '/gestao/processos' },
                    { label: processo.protocol_number ?? `Processo #${processo.id}`, href: `/gestao/processos/${processo.id}/ficha` },
                ]}
                actions={
                    <Badge size="sm" color={ficha.status === 'concluida' ? 'success' : 'info'}>
                        {ficha.status_label}
                    </Badge>
                }
            />

            {/* Identificação da ficha — gravada na abertura, nunca digitada. */}
            <Card className="mb-6">
                <CardContent className="flex flex-wrap items-center gap-x-8 gap-y-3 border-t-0">
                    <div>
                        <span className="block text-xs tracking-wide text-gray-400 uppercase">Tipo</span>
                        <strong className="text-sm font-semibold text-gray-800 dark:text-white/90">{ficha.tipo_label}</strong>
                    </div>
                    <div>
                        <span className="block text-xs tracking-wide text-gray-400 uppercase">Abertura</span>
                        <strong className="text-sm font-semibold text-gray-800 tabular-nums dark:text-white/90">
                            {formatarData(ficha.opened_at)}
                        </strong>
                    </div>
                    <div>
                        <span className="block text-xs tracking-wide text-gray-400 uppercase">Vistoriador</span>
                        <strong className="text-sm font-semibold text-gray-800 dark:text-white/90">
                            {ficha.vistoriador ?? '—'}
                        </strong>
                    </div>
                    {ficha.concluded_at && (
                        <div>
                            <span className="block text-xs tracking-wide text-gray-400 uppercase">Conclusão</span>
                            <strong className="text-sm font-semibold text-gray-800 tabular-nums dark:text-white/90">
                                {formatarData(ficha.concluded_at)}
                            </strong>
                        </div>
                    )}
                </CardContent>
            </Card>

            <div className="flex flex-col gap-6">
                {/* 1 — Localização e polígono */}
                <Card>
                    <CardHeader
                        title="1. Localização e polígono"
                        description="Endereço vindo do cadastro do processo; o polígono pode ser redesenhado e validado."
                    />
                    <CardContent>
                        <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                            <div>
                                <SecaoTitulo>Localização</SecaoTitulo>
                                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <CampoReadonly rotulo="CodLog" valor={ficha.cod_logradouro} />
                                    <CampoReadonly rotulo="Logradouro" valor={ficha.logradouro} />
                                    <CampoReadonly rotulo="Nº Métrico" valor={ficha.numero_metrico} />
                                    <CampoReadonly rotulo="Bairro" valor={ficha.bairro} />
                                    <CampoReadonly rotulo="CEP" valor={ficha.cep} />
                                    <div>
                                        <Label htmlFor="ponto_referencia">Ponto de Referência</Label>
                                        <Input
                                            id="ponto_referencia"
                                            value={dados.ponto_referencia}
                                            disabled={!editavel}
                                            onChange={(e) => set('ponto_referencia', e.target.value)}
                                        />
                                    </div>
                                    <CampoReadonly rotulo="Zona" valor={ficha.zona} />
                                    <CampoReadonly rotulo="Via" valor={ficha.via} />
                                    <div className="sm:col-span-2">
                                        <Label htmlFor="logradouro_correto">O logradouro está correto?</Label>
                                        <Select
                                            id="logradouro_correto"
                                            value={dados.logradouro_correto}
                                            disabled={!editavel}
                                            onChange={(valor) => set('logradouro_correto', valor as SimNao)}
                                            placeholder="Selecione"
                                            options={[
                                                { value: '1', label: 'Sim' },
                                                { value: '0', label: 'Não' },
                                            ]}
                                        />
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <SecaoTitulo>Polígono</SecaoTitulo>
                                    {ficha.polygon_validated_at && !editandoPoligono ? (
                                        <Badge size="sm" color="success" startIcon={<CheckCircleIcon className="size-3.5 fill-current" />}>
                                            Polígono validado
                                        </Badge>
                                    ) : (
                                        <Badge size="sm" color="warning">
                                            {editandoPoligono ? 'Redesenhando' : 'Pendente de validação'}
                                        </Badge>
                                    )}
                                </div>

                                <div className="mt-4">
                                    <MapaPoligonoSection
                                        vertices={vertices}
                                        editando={editandoPoligono}
                                        onChange={editavel ? setVertices : undefined}
                                    />
                                </div>

                                {erroPoligono && <p className="mt-2 text-sm text-error-500">{erroPoligono}</p>}
                                {editandoPoligono && (
                                    <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        Clique no mapa para adicionar vértices; arraste os marcadores para ajustar.
                                    </p>
                                )}

                                <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                                    <span className="text-sm text-gray-500 dark:text-gray-400">
                                        {areaPrevia !== null ? (
                                            <>
                                                Área do polígono{' '}
                                                <strong className="font-semibold text-gray-800 tabular-nums dark:text-white/90">
                                                    {areaPrevia.toLocaleString('pt-BR', { minimumFractionDigits: 2 })} m²
                                                </strong>{' '}
                                                · {vertices.length} vértices
                                            </>
                                        ) : (
                                            <>{vertices.length} vértices</>
                                        )}
                                        {ficha.polygon_area_m2 !== null && !editandoPoligono && (
                                            <span className="ml-2 text-xs">
                                                (área validada:{' '}
                                                {ficha.polygon_area_m2.toLocaleString('pt-BR', { minimumFractionDigits: 2 })} m²)
                                            </span>
                                        )}
                                    </span>
                                    {editavel && (
                                        <div className="flex flex-wrap items-center gap-2">
                                            {editandoPoligono ? (
                                                <>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => setVertices((atual) => atual.slice(0, -1))}
                                                        disabled={vertices.length === 0}
                                                    >
                                                        Remover último ponto
                                                    </Button>
                                                    <Button size="sm" variant="outline" onClick={cancelarRedesenho}>
                                                        Cancelar
                                                    </Button>
                                                    <Button size="sm" onClick={validarPoligono} loading={poligonoHttp.processing}>
                                                        Validar polígono
                                                    </Button>
                                                </>
                                            ) : (
                                                <Button size="sm" variant="outline" onClick={iniciarRedesenho}>
                                                    Redesenhar polígono
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* 2 — Imóvel */}
                <Card>
                    <CardHeader title="2. Imóvel" description="Dados, vagas e características do imóvel vistoriado." />
                    <CardContent className="flex flex-col gap-6">
                        <div>
                            <SecaoTitulo>Dados do imóvel</SecaoTitulo>
                            <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <Label htmlFor="tipo_imovel">Tipo de Imóvel</Label>
                                    <Select
                                        id="tipo_imovel"
                                        value={dados.tipo_imovel}
                                        disabled={!editavel}
                                        onChange={(valor) => set('tipo_imovel', valor)}
                                        placeholder="Selecione"
                                        options={tiposImovel}
                                    />
                                </div>
                                <fieldset>
                                    <legend className="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-400">Acesso</legend>
                                    <div className="grid grid-cols-2 gap-2">
                                        {Object.entries(opcoes.acessos).map(([valor, rotulo]) => (
                                            <Checkbox
                                                key={valor}
                                                label={rotulo}
                                                checked={dados.acessos.includes(valor)}
                                                disabled={!editavel}
                                                onChange={(marcado) => alternarLista('acessos', valor, marcado)}
                                            />
                                        ))}
                                    </div>
                                </fieldset>
                                <fieldset>
                                    <legend className="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Atividade está em funcionamento?
                                    </legend>
                                    <RadioSimNao
                                        nome="atividade_em_funcionamento"
                                        valor={dados.atividade_em_funcionamento}
                                        disabled={!editavel}
                                        onChange={(valor) => set('atividade_em_funcionamento', valor)}
                                    />
                                </fieldset>
                            </div>
                        </div>

                        <div className="border-t border-gray-100 pt-6 dark:border-gray-800">
                            <SecaoTitulo>Complemento</SecaoTitulo>
                            <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <Label htmlFor="complemento_tipo">Tipo</Label>
                                    <Input
                                        id="complemento_tipo"
                                        value={dados.complemento_tipo}
                                        disabled={!editavel}
                                        placeholder="Ex.: Lote, Sala, Loja"
                                        onChange={(e) => set('complemento_tipo', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="complemento_numero">Número</Label>
                                    <Input
                                        id="complemento_numero"
                                        value={dados.complemento_numero}
                                        disabled={!editavel}
                                        onChange={(e) => set('complemento_numero', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="complemento_area_m2">Área (m²)</Label>
                                    <Input
                                        id="complemento_area_m2"
                                        value={dados.complemento_area_m2}
                                        disabled={!editavel}
                                        inputMode="decimal"
                                        onChange={(e) => set('complemento_area_m2', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <span className="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Área Total
                                    </span>
                                    <div className="flex h-11 items-center justify-between rounded-lg border border-brand-200 bg-brand-50 px-4 dark:border-brand-500/30 dark:bg-brand-500/10">
                                        <strong className="text-sm font-semibold text-brand-700 tabular-nums dark:text-brand-300">
                                            {areaTotalCalculada !== null
                                                ? areaTotalCalculada.toLocaleString('pt-BR', { minimumFractionDigits: 2 })
                                                : '—'}
                                        </strong>
                                        <span className="text-xs text-brand-500">calculado</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="border-t border-gray-100 pt-6 dark:border-gray-800">
                            <SecaoTitulo>Vagas vistoria</SecaoTitulo>
                            <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <Label htmlFor="vagas_veiculo_passeio">Veículo de passeio</Label>
                                    <Input
                                        id="vagas_veiculo_passeio"
                                        value={dados.vagas_veiculo_passeio}
                                        disabled={!editavel}
                                        inputMode="numeric"
                                        onChange={(e) => set('vagas_veiculo_passeio', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="vagas_carga_descarga">Carga e Descarga</Label>
                                    <Input
                                        id="vagas_carga_descarga"
                                        value={dados.vagas_carga_descarga}
                                        disabled={!editavel}
                                        inputMode="numeric"
                                        onChange={(e) => set('vagas_carga_descarga', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="patio_carga_descarga">Pátio de Carga e Descarga?</Label>
                                    <Select
                                        id="patio_carga_descarga"
                                        value={dados.patio_carga_descarga}
                                        disabled={!editavel}
                                        onChange={(valor) => set('patio_carga_descarga', valor as SimNao)}
                                        placeholder="Selecione"
                                        options={[
                                            { value: '1', label: 'Sim' },
                                            { value: '0', label: 'Não' },
                                        ]}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="area_embarque_desembarque">
                                        Área para Embarque e Desembarque de Passageiros?
                                    </Label>
                                    <Select
                                        id="area_embarque_desembarque"
                                        value={dados.area_embarque_desembarque}
                                        disabled={!editavel}
                                        onChange={(valor) => set('area_embarque_desembarque', valor as SimNao)}
                                        placeholder="Selecione"
                                        options={[
                                            { value: '1', label: 'Sim' },
                                            { value: '0', label: 'Não' },
                                        ]}
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="border-t border-gray-100 pt-6 dark:border-gray-800">
                            <SecaoTitulo>Característica do imóvel</SecaoTitulo>
                            <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {(
                                    [
                                        ['area_terreno_m2', 'Área do terreno (m²)'],
                                        ['area_total_construida_m2', 'Área total construída (m²)'],
                                        ['area_ocupada_atividade_m2', 'Área ocupada / Atividade (m²)'],
                                        ['area_carga_descarga_m2', 'Área Carga / Descarga (m²)'],
                                        ['pavimento_edificacao', 'Pavimento da Edificação'],
                                        ['pavimento_ocupado_atividade', 'Pavimento ocupados c/ atividade'],
                                        ['recuo_m', 'Recuo (m)'],
                                    ] as const
                                ).map(([campo, rotulo]) => (
                                    <div key={campo}>
                                        <Label htmlFor={campo}>{rotulo}</Label>
                                        <Input
                                            id={campo}
                                            value={dados[campo]}
                                            disabled={!editavel}
                                            onChange={(e) => set(campo, e.target.value)}
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* 3 — Entorno e infraestrutura */}
                <Card>
                    <CardHeader title="3. Entorno e infraestrutura" description="Distâncias, instalações e usuários." />
                    <CardContent className="flex flex-col gap-6">
                        <div>
                            <SecaoTitulo>Entorno (distância em metro linear)</SecaoTitulo>
                            <div className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                                {(
                                    [
                                        ['entorno_residencial_m', 'Residencial (m)'],
                                        ['entorno_industrial_m', 'Industrial (m)'],
                                        ['entorno_saude_m', 'Saúde (m)'],
                                        ['entorno_comercial_m', 'Comercial (m)'],
                                        ['entorno_institucional_m', 'Institucional (m)'],
                                        ['entorno_especial_m', 'Especial (m)'],
                                        ['entorno_educacional_m', 'Educacional (m)'],
                                        ['entorno_misto_m', 'Misto (m)'],
                                        ['entorno_outros_m', 'Outros (m)'],
                                    ] as const
                                ).map(([campo, rotulo]) => (
                                    <div key={campo}>
                                        <Label htmlFor={campo}>{rotulo}</Label>
                                        <Input
                                            id={campo}
                                            value={dados[campo]}
                                            disabled={!editavel}
                                            inputMode="decimal"
                                            onChange={(e) => set(campo, e.target.value)}
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="border-t border-gray-100 pt-6 dark:border-gray-800">
                            <SecaoTitulo>Infra-estrutura</SecaoTitulo>
                            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                                <div className="flex flex-col gap-5 rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                                    <fieldset>
                                        <legend className="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-400">
                                            Instalações Elétricas?
                                        </legend>
                                        <RadioOpcoes
                                            nome="instalacoes_eletricas"
                                            valor={dados.instalacoes_eletricas}
                                            opcoes={opcoes.satisfacao}
                                            disabled={!editavel}
                                            onChange={(valor) => set('instalacoes_eletricas', valor)}
                                        />
                                    </fieldset>
                                    <fieldset>
                                        <legend className="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-400">
                                            Instalações Hidrossanitárias?
                                        </legend>
                                        <RadioOpcoes
                                            nome="instalacoes_hidrossanitarias"
                                            valor={dados.instalacoes_hidrossanitarias}
                                            opcoes={opcoes.satisfacao}
                                            disabled={!editavel}
                                            onChange={(valor) => set('instalacoes_hidrossanitarias', valor)}
                                        />
                                    </fieldset>
                                </div>

                                <div className="flex flex-col gap-5 rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                                    <fieldset>
                                        <legend className="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-400">
                                            Existência de Obras?
                                        </legend>
                                        <div className="grid grid-cols-2 gap-2">
                                            {Object.entries(opcoes.obras).map(([valor, rotulo]) => (
                                                <Checkbox
                                                    key={valor}
                                                    label={rotulo}
                                                    checked={dados.obras.includes(valor)}
                                                    disabled={!editavel}
                                                    onChange={(marcado) => alternarLista('obras', valor, marcado)}
                                                />
                                            ))}
                                        </div>
                                    </fieldset>
                                    <div>
                                        <Label htmlFor="alvara_numero">Alvará Nº</Label>
                                        <Input
                                            id="alvara_numero"
                                            value={dados.alvara_numero}
                                            disabled={!editavel}
                                            onChange={(e) => set('alvara_numero', e.target.value)}
                                        />
                                    </div>
                                </div>

                                <div className="rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                                    <span className="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Quantidade de Usuários
                                    </span>
                                    <div className="grid grid-cols-2 gap-3">
                                        {(
                                            [
                                                ['quantidade_usuarios', 'Qtd. de usuários'],
                                                ['num_salas_alunos', 'Nº de Salas/Alunos'],
                                                ['num_assentos', 'Nº de Assentos'],
                                                ['unidades_hospedagem', 'Unidades Hospedagem'],
                                                ['num_leitos', 'Nº de Leitos'],
                                            ] as const
                                        ).map(([campo, rotulo]) => (
                                            <div key={campo}>
                                                <Label htmlFor={campo}>{rotulo}</Label>
                                                <Input
                                                    id={campo}
                                                    value={dados[campo]}
                                                    disabled={!editavel}
                                                    inputMode="numeric"
                                                    onChange={(e) => set(campo, e.target.value)}
                                                />
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* 4 — Condições e segurança */}
                <Card>
                    <CardHeader title="4. Condições e segurança" description="Controle ambiental e equipamentos de segurança." />
                    <CardContent className="flex flex-col gap-6">
                        <div>
                            <SecaoTitulo>Controle ambiental</SecaoTitulo>
                            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                                <fieldset className="rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                                    <legend className="px-1 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                        Equipamentos
                                    </legend>
                                    <div className="grid grid-cols-2 gap-2">
                                        {Object.entries(opcoes.equipamentos).map(([valor, rotulo]) => (
                                            <Checkbox
                                                key={valor}
                                                label={rotulo}
                                                checked={dados.equipamentos.includes(valor)}
                                                disabled={!editavel}
                                                onChange={(marcado) => alternarLista('equipamentos', valor, marcado)}
                                            />
                                        ))}
                                    </div>
                                    <div className="mt-3">
                                        <Label htmlFor="equipamentos_outros">Outros</Label>
                                        <Input
                                            id="equipamentos_outros"
                                            value={dados.equipamentos_outros}
                                            disabled={!editavel}
                                            onChange={(e) => set('equipamentos_outros', e.target.value)}
                                        />
                                    </div>
                                </fieldset>

                                <fieldset className="rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                                    <legend className="px-1 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                        Máquina / Motores
                                    </legend>
                                    <RadioSimNao
                                        nome="maquinas_motores"
                                        valor={dados.maquinas_motores}
                                        disabled={!editavel}
                                        onChange={(valor) => set('maquinas_motores', valor)}
                                    />
                                </fieldset>

                                <fieldset className="rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                                    <legend className="px-1 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                        Sons / Ruídos
                                    </legend>
                                    <RadioSimNao
                                        nome="sons_ruidos"
                                        valor={dados.sons_ruidos}
                                        disabled={!editavel}
                                        onChange={(valor) => set('sons_ruidos', valor)}
                                    />
                                    <div className="mt-3">
                                        <Label htmlFor="sons_ruidos_origem">Origem</Label>
                                        <Input
                                            id="sons_ruidos_origem"
                                            value={dados.sons_ruidos_origem}
                                            disabled={!editavel || dados.sons_ruidos !== '1'}
                                            onChange={(e) => set('sons_ruidos_origem', e.target.value)}
                                        />
                                    </div>
                                </fieldset>
                            </div>
                        </div>

                        <div className="border-t border-gray-100 pt-6 dark:border-gray-800">
                            <SecaoTitulo>Equipamentos de segurança</SecaoTitulo>
                            <div className="mt-4 flex flex-wrap items-end gap-x-8 gap-y-4">
                                <Checkbox
                                    label="Extintores"
                                    checked={dados.seg_extintores}
                                    disabled={!editavel}
                                    onChange={(marcado) => set('seg_extintores', marcado)}
                                />
                                <Checkbox
                                    label="Central de Gás"
                                    checked={dados.seg_central_gas}
                                    disabled={!editavel}
                                    onChange={(marcado) => set('seg_central_gas', marcado)}
                                />
                                <Checkbox
                                    label="Hidrantes"
                                    checked={dados.seg_hidrantes}
                                    disabled={!editavel}
                                    onChange={(marcado) => set('seg_hidrantes', marcado)}
                                />
                                <div className="min-w-56 flex-1">
                                    <Label htmlFor="seg_outros">Outros</Label>
                                    <Input
                                        id="seg_outros"
                                        value={dados.seg_outros}
                                        disabled={!editavel}
                                        onChange={(e) => set('seg_outros', e.target.value)}
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* 5 — Documentação */}
                <Card>
                    <CardHeader
                        title="5. Documentação"
                        description="Anexos da vistoria e observações complementares."
                        actions={<Badge size="sm" color="light">{anexos.length} anexos</Badge>}
                    />
                    <CardContent className="flex flex-col gap-6">
                        <div>
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <SecaoTitulo>Anexos</SecaoTitulo>
                                {editavel && (
                                    <>
                                        <input
                                            ref={arquivoRef}
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp,application/pdf"
                                            className="hidden"
                                            onChange={enviarAnexo}
                                        />
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            loading={enviandoAnexo}
                                            onClick={() => arquivoRef.current?.click()}
                                        >
                                            Anexar arquivo
                                        </Button>
                                    </>
                                )}
                            </div>

                            {anexos.length === 0 ? (
                                <p className="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                    Nenhum anexo enviado. Anexe fotos ou documentos da vistoria.
                                </p>
                            ) : (
                                <ul className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    {anexos.map((anexo) => (
                                        <li
                                            key={anexo.id}
                                            className="flex items-center justify-between gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium text-gray-800 dark:text-white/90">
                                                    {anexo.original_name}
                                                </p>
                                                <p className="text-xs text-gray-400">
                                                    {anexo.mime_type.split('/')[1]?.toUpperCase()} · {formatarTamanho(anexo.size)}
                                                </p>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-1">
                                                <a
                                                    href={anexo.download_url}
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-brand-500 dark:hover:bg-white/[0.05]"
                                                    title="Visualizar anexo"
                                                >
                                                    <EyeIcon className="size-4 fill-current" />
                                                </a>
                                                {editavel && (
                                                    <button
                                                        type="button"
                                                        onClick={() => setAnexoParaRemover(anexo)}
                                                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-error-50 hover:text-error-500"
                                                        title="Remover anexo"
                                                    >
                                                        <TrashIcon className="size-4 fill-current" />
                                                    </button>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        <div className="border-t border-gray-100 pt-6 dark:border-gray-800">
                            <SecaoTitulo>Observações</SecaoTitulo>
                            <textarea
                                id="observacoes"
                                rows={4}
                                value={dados.observacoes}
                                disabled={!editavel}
                                placeholder="Registre observações complementares da vistoria"
                                onChange={(e) => set('observacoes', e.target.value)}
                                className="mt-4 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* 6 — Conclusão */}
                <Card className="border-brand-200 dark:border-brand-500/30">
                    <CardHeader
                        title="6. Conclusão da vistoria"
                        description="Etapa final — o parecer é obrigatório para concluir a ficha."
                    />
                    <CardContent className="flex flex-col gap-6">
                        <div>
                            <Label htmlFor="parecer" required>
                                Parecer Vistoria
                            </Label>
                            <textarea
                                id="parecer"
                                rows={7}
                                value={dados.parecer}
                                disabled={!editavel}
                                placeholder="Descreva o que foi constatado no local"
                                onChange={(e) => set('parecer', e.target.value)}
                                className={`w-full rounded-lg border bg-transparent px-4 py-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:ring-3 focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-40 dark:bg-gray-900 dark:text-white/90 ${
                                    erroParecer
                                        ? 'border-error-500 focus:border-error-300 focus:ring-error-500/20'
                                        : 'border-brand-200 focus:border-brand-300 focus:ring-brand-500/20 dark:border-brand-500/30'
                                }`}
                            />
                            {erroParecer && <p className="mt-1.5 text-xs text-error-500">{erroParecer}</p>}
                        </div>

                        <div className="grid grid-cols-1 gap-4 border-t border-gray-100 pt-6 sm:grid-cols-3 dark:border-gray-800">
                            <div>
                                <Label htmlFor="data_vistoria">Data Vistoria</Label>
                                <Input
                                    id="data_vistoria"
                                    type="date"
                                    value={dados.data_vistoria}
                                    disabled={!editavel}
                                    onChange={(e) => set('data_vistoria', e.target.value)}
                                />
                            </div>
                            <div>
                                <Label htmlFor="contato_nome">Nome do Contato</Label>
                                <Input
                                    id="contato_nome"
                                    value={dados.contato_nome}
                                    disabled={!editavel}
                                    placeholder="Quem acompanhou a vistoria"
                                    onChange={(e) => set('contato_nome', e.target.value)}
                                />
                            </div>
                            <div>
                                <Label htmlFor="contato_telefone">Telefone de Contato</Label>
                                <MaskedInput
                                    id="contato_telefone"
                                    mask="phone"
                                    value={dados.contato_telefone}
                                    disabled={!editavel}
                                    onChange={(valor) => set('contato_telefone', valor)}
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Barra de ações */}
                <div className="sticky bottom-4 z-20 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
                    <div className="text-sm text-gray-500 dark:text-gray-400">
                        {saveState === 'salvo' && <span className="text-success-600">Rascunho salvo.</span>}
                        {saveState === 'erro' && <span className="text-error-500">{erroGeral ?? 'Falha ao salvar.'}</span>}
                        {!editavel && ficha.status === 'concluida' && <span>Ficha concluída — somente leitura.</span>}
                        {!editavel && ficha.status !== 'concluida' && (
                            <span>Somente leitura — a ficha é do vistoriador {ficha.vistoriador ?? 'responsável'}.</span>
                        )}
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href={`/gestao/processos/${processo.id}/ficha`}
                            className="inline-flex items-center justify-center rounded-lg px-5 py-3.5 text-sm text-gray-700 ring-1 ring-gray-300 ring-inset transition hover:bg-gray-50 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
                        >
                            {editavel ? 'Cancelar' : 'Voltar'}
                        </Link>
                        {editavel && (
                            <>
                                <Button variant="outline" onClick={salvarRascunho} loading={rascunho.processing}>
                                    Salvar rascunho
                                </Button>
                                <Button onClick={() => setShowConcluir(true)}>Concluir vistoria</Button>
                            </>
                        )}
                    </div>
                </div>
            </div>

            <ConfirmDialog
                isOpen={showConcluir}
                onClose={() => setShowConcluir(false)}
                onConfirm={concluirVistoria}
                title="Concluir a vistoria?"
                description="Após a conclusão, a ficha se torna imutável e o parecer registrado passa a ser a peça oficial da vistoria. Confira o preenchimento antes de confirmar."
                confirmLabel="Concluir vistoria"
                variant="info"
                processing={conclusao.processing}
            />

            <ConfirmDialog
                isOpen={anexoParaRemover !== null}
                onClose={() => setAnexoParaRemover(null)}
                onConfirm={removerAnexo}
                title="Remover anexo?"
                description={
                    anexoParaRemover
                        ? `O arquivo "${anexoParaRemover.original_name}" será removido da ficha de vistoria.`
                        : ''
                }
                confirmLabel="Remover"
                variant="danger"
            />
        </>
    );
}

VistoriaShow.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
