import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import type { SharedProps } from '@/types';
import { formatarData } from './quadro-fields';

export interface TerritorialAddress {
    rotulo: string | null;
    cod_log: string | null;
    logradouro_nome: string | null;
    numero_porta: string | null;
    bairro: string | null;
    [key: string]: string | null;
}

export interface TerritorialRegra {
    id: number;
    codigo: string;
    titulo: string;
    tipo: string;
    tipo_label: string;
    efeito: string;
    efeito_label: string;
    cod_log: string | null;
    logradouro_nome: string | null;
    camada: string | null;
    camada_filtro: string | null;
    zona_alvo: string | null;
    motivo_analise: string | null;
    base_legal: string | null;
    decision_text_id: number | null;
    ativo: boolean;
    cnaes: string[];
    addresses: TerritorialAddress[];
}

interface TerritorialVersao {
    id: number;
    version: string;
    status: string;
    status_label: string;
    valid_from: string | null;
    valid_to: string | null;
    total: number;
    vigente: boolean;
    rascunho: boolean;
    autor_id: number | null;
}

export interface TerritoriaisData {
    versao_vigente: string | null;
    versao_exibida: string | null;
    rascunho: { id: number; version: string; autor: string | null; autor_id: number | null } | null;
    regras: TerritorialRegra[];
    versoes: TerritorialVersao[];
    textos: { id: number; key: string; description: string | null }[];
}

function alvoDaRegra(regra: TerritorialRegra): string {
    if (regra.cod_log) {
        return `Logradouro ${regra.cod_log}${regra.logradouro_nome ? ` — ${regra.logradouro_nome}` : ''}`;
    }

    if (regra.camada) {
        return regra.camada_filtro ? `${regra.camada} (${regra.camada_filtro})` : regra.camada;
    }

    if (regra.zona_alvo) {
        return `Zona ${regra.zona_alvo}`;
    }

    return '—';
}

function formatarCnae(digitos: string): string {
    return digitos.replace(/^(\d{4})(\d)(\d{2})$/, '$1-$2/$3');
}

interface EditState {
    codigo: string;
    version: string;
    titulo: string;
    ativo: boolean;
    cod_log: string;
    logradouro_nome: string;
    camada: string;
    camada_filtro: string;
    zona_alvo: string;
    motivo_analise: string;
    base_legal: string;
    decision_text_id: string;
    cnaes: string;
    addresses: TerritorialAddress[];
}

function estadoInicial(regra: TerritorialRegra, versaoSugerida: string, rascunhoAberto: string | null): EditState {
    return {
        codigo: regra.codigo,
        version: rascunhoAberto ?? versaoSugerida,
        titulo: regra.titulo,
        ativo: regra.ativo,
        cod_log: regra.cod_log ?? '',
        logradouro_nome: regra.logradouro_nome ?? '',
        camada: regra.camada ?? '',
        camada_filtro: regra.camada_filtro ?? '',
        zona_alvo: regra.zona_alvo ?? '',
        motivo_analise: regra.motivo_analise ?? '',
        base_legal: regra.base_legal ?? '',
        decision_text_id: regra.decision_text_id !== null ? String(regra.decision_text_id) : '',
        cnaes: regra.cnaes.join('\n'),
        addresses: regra.addresses.map((endereco) => ({ ...endereco })),
    };
}

function EditarRegraModal({
    regra,
    territoriais,
    onClose,
}: {
    regra: TerritorialRegra;
    territoriais: TerritoriaisData;
    onClose: () => void;
}) {
    const hoje = new Date().toISOString().slice(0, 10);
    const versaoSugerida = `rt-${hoje}`;
    const [form, setForm] = useState<EditState>(() =>
        estadoInicial(regra, versaoSugerida, territoriais.rascunho?.version ?? null),
    );
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const rascunhoAberto = territoriais.rascunho !== null;

    function setCampo<K extends keyof EditState>(campo: K, valor: EditState[K]) {
        setForm((atual) => ({ ...atual, [campo]: valor }));
    }

    function setEndereco(indice: number, campo: keyof TerritorialAddress, valor: string) {
        setForm((atual) => ({
            ...atual,
            addresses: atual.addresses.map((endereco, i) =>
                i === indice ? { ...endereco, [campo]: valor === '' ? null : valor } : endereco,
            ),
        }));
    }

    function salvar() {
        setProcessing(true);
        setErrors({});

        router.post(
            '/gestao/louos/territoriais/rascunho',
            {
                codigo: form.codigo,
                version: form.version,
                titulo: form.titulo,
                ativo: form.ativo,
                cod_log: form.cod_log || null,
                logradouro_nome: form.logradouro_nome || null,
                camada: form.camada || null,
                camada_filtro: form.camada_filtro || null,
                zona_alvo: form.zona_alvo || null,
                motivo_analise: form.motivo_analise || null,
                base_legal: form.base_legal || null,
                decision_text_id: form.decision_text_id !== '' ? Number(form.decision_text_id) : null,
                cnaes: form.cnaes
                    .split('\n')
                    .map((linha) => linha.replace(/\D/g, ''))
                    .filter((linha) => linha !== ''),
                addresses: form.addresses,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onClose(),
                onError: (falhas) => setErrors(falhas as Record<string, string>),
            },
        );
    }

    const inputClass =
        'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    const labelClass = 'mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400';

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-w-3xl p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                Editar {regra.codigo} — {regra.titulo}
            </h4>
            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                A alteração grava no <strong>rascunho</strong> do domínio — a versão vigente não é tocada. A
                publicação exige outro mantenedor (quatro olhos).
            </p>

            <div className="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label className={labelClass} htmlFor="rt-version">
                        Versão do rascunho
                    </label>
                    <input
                        id="rt-version"
                        className={inputClass}
                        value={form.version}
                        disabled={rascunhoAberto}
                        onChange={(event) => setCampo('version', event.target.value)}
                    />
                    {rascunhoAberto && (
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Há um rascunho aberto — a alteração entra nele.
                        </p>
                    )}
                    {errors.version && <p className="mt-1 text-xs text-error-600">{errors.version}</p>}
                </div>
                <div>
                    <label className={labelClass} htmlFor="rt-titulo">
                        Título
                    </label>
                    <input
                        id="rt-titulo"
                        className={inputClass}
                        value={form.titulo}
                        onChange={(event) => setCampo('titulo', event.target.value)}
                    />
                    {errors.titulo && <p className="mt-1 text-xs text-error-600">{errors.titulo}</p>}
                </div>
                <div>
                    <label className={labelClass} htmlFor="rt-codlog">
                        CODLOG (logradouro)
                    </label>
                    <input
                        id="rt-codlog"
                        className={inputClass}
                        value={form.cod_log}
                        onChange={(event) => setCampo('cod_log', event.target.value)}
                    />
                </div>
                <div>
                    <label className={labelClass} htmlFor="rt-logradouro">
                        Nome do logradouro
                    </label>
                    <input
                        id="rt-logradouro"
                        className={inputClass}
                        value={form.logradouro_nome}
                        onChange={(event) => setCampo('logradouro_nome', event.target.value)}
                    />
                </div>
                <div>
                    <label className={labelClass} htmlFor="rt-camada">
                        Camada geográfica (WFS)
                    </label>
                    <input
                        id="rt-camada"
                        className={inputClass}
                        value={form.camada}
                        placeholder="workspace:FeatureType"
                        onChange={(event) => setCampo('camada', event.target.value)}
                    />
                </div>
                <div>
                    <label className={labelClass} htmlFor="rt-filtro">
                        Filtro da camada (CQL)
                    </label>
                    <input
                        id="rt-filtro"
                        className={inputClass}
                        value={form.camada_filtro}
                        placeholder="Ex.: TRECHO IN ('5','6')"
                        onChange={(event) => setCampo('camada_filtro', event.target.value)}
                    />
                </div>
                <div>
                    <label className={labelClass} htmlFor="rt-zona">
                        Zona alvo (observação do Quadro 10)
                    </label>
                    <input
                        id="rt-zona"
                        className={inputClass}
                        value={form.zona_alvo}
                        onChange={(event) => setCampo('zona_alvo', event.target.value)}
                    />
                </div>
                <div>
                    <label className={labelClass} htmlFor="rt-texto">
                        Texto decisório vinculado
                    </label>
                    <select
                        id="rt-texto"
                        className={inputClass}
                        value={form.decision_text_id}
                        onChange={(event) => setCampo('decision_text_id', event.target.value)}
                    >
                        <option value="">Nenhum</option>
                        {territoriais.textos.map((texto) => (
                            <option key={texto.id} value={texto.id}>
                                {texto.key}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            <div className="mt-4">
                <label className={labelClass} htmlFor="rt-motivo">
                    Motivo de análise (texto exato do encaminhamento)
                </label>
                <textarea
                    id="rt-motivo"
                    className={inputClass}
                    rows={2}
                    value={form.motivo_analise}
                    onChange={(event) => setCampo('motivo_analise', event.target.value)}
                />
            </div>

            <div className="mt-4">
                <label className={labelClass} htmlFor="rt-base-legal">
                    Base legal
                </label>
                <input
                    id="rt-base-legal"
                    className={inputClass}
                    value={form.base_legal}
                    onChange={(event) => setCampo('base_legal', event.target.value)}
                />
            </div>

            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label className={labelClass} htmlFor="rt-cnaes">
                        CNAEs vinculados (um por linha, com ou sem máscara)
                    </label>
                    <textarea
                        id="rt-cnaes"
                        className={`${inputClass} font-mono`}
                        rows={8}
                        value={form.cnaes}
                        onChange={(event) => setCampo('cnaes', event.target.value)}
                    />
                    {errors['cnaes'] && <p className="mt-1 text-xs text-error-600">{errors.cnaes}</p>}
                </div>
                <div>
                    <span className={labelClass}>Endereços / localizações da allow-list</span>
                    <div className="flex max-h-64 flex-col gap-2 overflow-y-auto pr-1">
                        {form.addresses.map((endereco, indice) => (
                            <div
                                key={indice}
                                className="grid grid-cols-6 items-center gap-2 rounded-lg border border-gray-200 p-2 dark:border-gray-800"
                            >
                                <input
                                    className={`${inputClass} col-span-3`}
                                    placeholder="Rótulo"
                                    value={endereco.rotulo ?? ''}
                                    onChange={(event) => setEndereco(indice, 'rotulo', event.target.value)}
                                />
                                <input
                                    className={inputClass}
                                    placeholder="CODLOG"
                                    value={endereco.cod_log ?? ''}
                                    onChange={(event) => setEndereco(indice, 'cod_log', event.target.value)}
                                />
                                <input
                                    className={inputClass}
                                    placeholder="Nº porta"
                                    value={endereco.numero_porta ?? ''}
                                    onChange={(event) => setEndereco(indice, 'numero_porta', event.target.value)}
                                />
                                <Button
                                    size="xs"
                                    variant="outline"
                                    onClick={() =>
                                        setForm((atual) => ({
                                            ...atual,
                                            addresses: atual.addresses.filter((_, i) => i !== indice),
                                        }))
                                    }
                                >
                                    Remover
                                </Button>
                                <input
                                    className={`${inputClass} col-span-3`}
                                    placeholder="Logradouro"
                                    value={endereco.logradouro_nome ?? ''}
                                    onChange={(event) => setEndereco(indice, 'logradouro_nome', event.target.value)}
                                />
                                <input
                                    className={`${inputClass} col-span-3`}
                                    placeholder="Bairro"
                                    value={endereco.bairro ?? ''}
                                    onChange={(event) => setEndereco(indice, 'bairro', event.target.value)}
                                />
                            </div>
                        ))}
                    </div>
                    <div className="mt-2">
                        <Button
                            size="xs"
                            variant="outline"
                            onClick={() =>
                                setForm((atual) => ({
                                    ...atual,
                                    addresses: [
                                        ...atual.addresses,
                                        { rotulo: null, cod_log: null, logradouro_nome: null, numero_porta: null, bairro: null },
                                    ],
                                }))
                            }
                        >
                            Adicionar endereço
                        </Button>
                    </div>
                </div>
            </div>

            <div className="mt-4 flex items-center gap-2">
                <input
                    id="rt-ativo"
                    type="checkbox"
                    className="size-4 rounded border-gray-300"
                    checked={form.ativo}
                    onChange={(event) => setCampo('ativo', event.target.checked)}
                />
                <label htmlFor="rt-ativo" className="text-sm text-gray-700 dark:text-gray-300">
                    Regra ativa (regra inativa não é avaliada pelo motor — bloqueio explícito, visível aqui)
                </label>
            </div>

            <div className="mt-6 flex items-center justify-end gap-3">
                <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                    Cancelar
                </Button>
                <Button size="sm" onClick={salvar} loading={processing}>
                    Gravar no rascunho
                </Button>
            </div>
        </Modal>
    );
}

/**
 * Aba Regras territoriais do hub Motor de regras (RT-01 a RT-05 — pacote
 * normativo SEDUR 2026-08-28): consulta da versão vigente e manutenção
 * versionada (rascunho → publicação quatro olhos), mesmo fluxo dos quadros.
 */
export default function AbaTerritoriais({
    territoriais,
    canMaintain,
}: {
    territoriais: TerritoriaisData;
    canMaintain: boolean;
}) {
    const { auth } = usePage<SharedProps>().props;
    const [editando, setEditando] = useState<TerritorialRegra | null>(null);
    const [processing, setProcessing] = useState(false);

    const rascunho = territoriais.rascunho;
    const souAutorDoRascunho = rascunho !== null && auth.user?.id === rascunho.autor_id;

    function publicar(versaoId: number) {
        setProcessing(true);
        router.put(
            `/gestao/louos/territoriais/versoes/${versaoId}/publicar`,
            {},
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    }

    function ativar(versaoId: number) {
        setProcessing(true);
        router.put(
            `/gestao/louos/territoriais/versoes/${versaoId}/ativar`,
            {},
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    }

    function descartar(versaoId: number) {
        setProcessing(true);
        router.delete(`/gestao/louos/territoriais/rascunho/${versaoId}`, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <div className="space-y-6">
            {rascunho && (
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/10">
                    <p className="text-sm text-gray-700 dark:text-gray-200">
                        Rascunho <strong>{rascunho.version}</strong> em edição — autor:{' '}
                        <strong>{rascunho.autor ?? `ID ${rascunho.autor_id}`}</strong>. A listagem abaixo mostra o
                        rascunho; a versão vigente ({territoriais.versao_vigente ?? 'nenhuma'}) segue em uso pelo
                        motor até a publicação.
                    </p>
                    {canMaintain && (
                        <div className="flex items-center gap-2">
                            <Button size="sm" variant="outline" onClick={() => descartar(rascunho.id)} disabled={processing}>
                                Descartar rascunho
                            </Button>
                            {souAutorDoRascunho ? (
                                <span className="text-xs text-gray-500 dark:text-gray-400">
                                    Quatro olhos: outro mantenedor publica.
                                </span>
                            ) : (
                                <Button size="sm" onClick={() => publicar(rascunho.id)} loading={processing}>
                                    Publicar rascunho
                                </Button>
                            )}
                        </div>
                    )}
                </div>
            )}

            <Card>
                <CardHeader
                    title="Regras territoriais"
                    description={
                        territoriais.versao_exibida
                            ? `Versão ${territoriais.versao_exibida}${rascunho ? ' (rascunho em edição)' : ' vigente'} — regras de lugar: logradouro × CNAE, poligonais TAC/ZEM/ZPAM/ABM/ZUE e allow-lists do Quadro 10.`
                            : 'Sem versão vigente publicada.'
                    }
                />
                <CardContent>
                    {territoriais.regras.length === 0 ? (
                        <EmptyState
                            title="Nenhuma regra territorial"
                            description="A carga oficial do pacote normativo SEDUR (seed) registra as regras RT-01 a RT-05."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-theme-sm">
                                <thead>
                                    <tr className="border-b border-gray-200 text-theme-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                        <th className="px-3 py-2 font-medium">Regra</th>
                                        <th className="px-3 py-2 font-medium">Tipo</th>
                                        <th className="px-3 py-2 font-medium">Efeito</th>
                                        <th className="px-3 py-2 font-medium">Alvo</th>
                                        <th className="px-3 py-2 font-medium">Vínculos</th>
                                        <th className="px-3 py-2 font-medium">Situação</th>
                                        {canMaintain && <th className="px-3 py-2 font-medium">Ação</th>}
                                    </tr>
                                </thead>
                                <tbody>
                                    {territoriais.regras.map((regra) => (
                                        <tr
                                            key={regra.id}
                                            className="border-b border-gray-100 align-top last:border-0 dark:border-gray-800"
                                        >
                                            <td className="px-3 py-3">
                                                <div className="font-medium text-gray-800 dark:text-white/90">
                                                    {regra.codigo}
                                                </div>
                                                <div className="text-theme-xs text-gray-500 dark:text-gray-400">
                                                    {regra.titulo}
                                                </div>
                                                {regra.motivo_analise && (
                                                    <div className="mt-1 max-w-md text-theme-xs text-gray-500 dark:text-gray-400">
                                                        Motivo: {regra.motivo_analise}
                                                    </div>
                                                )}
                                                {regra.base_legal && (
                                                    <div className="mt-1 max-w-md text-theme-xs text-gray-400 dark:text-gray-500">
                                                        {regra.base_legal}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-3 py-3 text-gray-700 dark:text-gray-300">
                                                {regra.tipo_label}
                                            </td>
                                            <td className="px-3 py-3 text-gray-700 dark:text-gray-300">
                                                {regra.efeito_label}
                                            </td>
                                            <td className="max-w-xs px-3 py-3 text-gray-700 dark:text-gray-300">
                                                <span className="break-all">{alvoDaRegra(regra)}</span>
                                            </td>
                                            <td className="px-3 py-3 text-gray-700 dark:text-gray-300">
                                                {regra.cnaes.length > 0 && (
                                                    <div className="flex max-w-xs flex-wrap gap-1">
                                                        {regra.cnaes.slice(0, 6).map((cnae) => (
                                                            <Badge key={cnae} color="light" size="sm">
                                                                {formatarCnae(cnae)}
                                                            </Badge>
                                                        ))}
                                                        {regra.cnaes.length > 6 && (
                                                            <span className="text-theme-xs text-gray-400">
                                                                +{regra.cnaes.length - 6}
                                                            </span>
                                                        )}
                                                    </div>
                                                )}
                                                {regra.addresses.length > 0 && (
                                                    <div className="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
                                                        {regra.addresses.length}{' '}
                                                        {regra.addresses.length === 1 ? 'endereço' : 'endereços'} na
                                                        allow-list
                                                    </div>
                                                )}
                                                {regra.cnaes.length === 0 && regra.addresses.length === 0 && '—'}
                                            </td>
                                            <td className="px-3 py-3">
                                                <Badge color={regra.ativo ? 'success' : 'warning'} size="sm">
                                                    {regra.ativo ? 'Ativa' : 'Inativa'}
                                                </Badge>
                                            </td>
                                            {canMaintain && (
                                                <td className="px-3 py-3">
                                                    <Button size="xs" variant="outline" onClick={() => setEditando(regra)}>
                                                        Editar
                                                    </Button>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    title="Histórico de versões"
                    description="Publicar gera nova versão por quatro olhos; qualquer versão publicada pode voltar a ser a vigente. Nenhuma versão é apagada."
                />
                <CardContent>
                    {territoriais.versoes.length === 0 ? (
                        <EmptyState
                            title="Nenhuma versão"
                            description="A carga oficial (seed) publica a primeira versão das regras territoriais."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-theme-sm">
                                <thead>
                                    <tr className="border-b border-gray-200 text-theme-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                        <th className="px-3 py-2 font-medium">Versão</th>
                                        <th className="px-3 py-2 font-medium">Situação</th>
                                        <th className="px-3 py-2 font-medium">Vigência</th>
                                        <th className="px-3 py-2 font-medium">Regras</th>
                                        {canMaintain && <th className="px-3 py-2 font-medium">Ação</th>}
                                    </tr>
                                </thead>
                                <tbody>
                                    {territoriais.versoes.map((versao) => (
                                        <tr
                                            key={versao.id}
                                            className="border-b border-gray-100 last:border-0 dark:border-gray-800"
                                        >
                                            <td className="px-3 py-3 font-medium text-gray-800 dark:text-white/90">
                                                {versao.version}
                                            </td>
                                            <td className="px-3 py-3">
                                                <Badge
                                                    color={versao.vigente ? 'success' : versao.rascunho ? 'warning' : 'light'}
                                                    size="sm"
                                                >
                                                    {versao.status_label}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-3 text-gray-500 dark:text-gray-400">
                                                {formatarData(versao.valid_from) ?? '—'}
                                                {versao.valid_to ? ` até ${formatarData(versao.valid_to)}` : ''}
                                            </td>
                                            <td className="px-3 py-3 text-gray-700 dark:text-gray-300">
                                                {versao.total}
                                            </td>
                                            {canMaintain && (
                                                <td className="px-3 py-3">
                                                    {versao.vigente ? (
                                                        <span className="text-theme-xs text-gray-400">Em uso</span>
                                                    ) : versao.rascunho ? (
                                                        <span className="text-theme-xs text-gray-400">
                                                            Rascunho — publique pelo aviso acima
                                                        </span>
                                                    ) : (
                                                        <Button
                                                            size="xs"
                                                            variant="outline"
                                                            onClick={() => ativar(versao.id)}
                                                            disabled={processing}
                                                        >
                                                            Ativar
                                                        </Button>
                                                    )}
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>

            {canMaintain && editando && (
                <EditarRegraModal regra={editando} territoriais={territoriais} onClose={() => setEditando(null)} />
            )}
        </div>
    );
}
