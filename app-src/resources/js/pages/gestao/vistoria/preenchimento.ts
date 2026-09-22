export interface CamposContados {
    ponto_referencia: string;
    logradouro_correto: string;
    tipo_imovel: string;
    acessos: string[];
    atividade_em_funcionamento: string;
    complemento_tipo: string;
    complemento_numero: string;
    complemento_area_m2: string;
    vagas_veiculo_passeio: string;
    vagas_carga_descarga: string;
    patio_carga_descarga: string;
    area_embarque_desembarque: string;
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
    maquinas_motores: string;
    sons_ruidos: string;
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

export interface ContagemSecao {
    id: string;
    rotulo: string;
    preenchidos: number;
    total: number;
    situacao: 'vazia' | 'parcial' | 'completa';
}

export interface ResumoPreenchimento {
    secoes: ContagemSecao[];
    preenchidos: number;
    total: number;
    percentual: number;
    parecerPronto: boolean;
}

export function camposVazios(): CamposContados {
    return {
        ponto_referencia: '',
        logradouro_correto: '',
        tipo_imovel: '',
        acessos: [],
        atividade_em_funcionamento: '',
        complemento_tipo: '',
        complemento_numero: '',
        complemento_area_m2: '',
        vagas_veiculo_passeio: '',
        vagas_carga_descarga: '',
        patio_carga_descarga: '',
        area_embarque_desembarque: '',
        area_terreno_m2: '',
        area_total_construida_m2: '',
        area_ocupada_atividade_m2: '',
        area_carga_descarga_m2: '',
        pavimento_edificacao: '',
        pavimento_ocupado_atividade: '',
        recuo_m: '',
        entorno_residencial_m: '',
        entorno_industrial_m: '',
        entorno_saude_m: '',
        entorno_comercial_m: '',
        entorno_institucional_m: '',
        entorno_especial_m: '',
        entorno_educacional_m: '',
        entorno_misto_m: '',
        entorno_outros_m: '',
        instalacoes_eletricas: '',
        instalacoes_hidrossanitarias: '',
        obras: [],
        alvara_numero: '',
        quantidade_usuarios: '',
        num_salas_alunos: '',
        num_assentos: '',
        unidades_hospedagem: '',
        num_leitos: '',
        equipamentos: [],
        equipamentos_outros: '',
        maquinas_motores: '',
        sons_ruidos: '',
        sons_ruidos_origem: '',
        seg_extintores: false,
        seg_central_gas: false,
        seg_hidrantes: false,
        seg_outros: '',
        observacoes: '',
        parecer: '',
        data_vistoria: '',
        contato_nome: '',
        contato_telefone: '',
    };
}

function textoPreenchido(valor: string): boolean {
    return valor.trim() !== '';
}

function secao(id: string, rotulo: string, itens: boolean[]): ContagemSecao {
    const preenchidos = itens.filter(Boolean).length;
    const total = itens.length;

    return {
        id,
        rotulo,
        preenchidos,
        total,
        situacao: preenchidos === 0 ? 'vazia' : preenchidos === total ? 'completa' : 'parcial',
    };
}

export function calcularPreenchimento(entrada: {
    dados: CamposContados;
    quantidadeAnexos: number;
    poligonoValidado: boolean;
}): ResumoPreenchimento {
    const dados = entrada.dados;
    const parecerPronto = dados.parecer.trim().length >= 3;
    const origemDoRuido = dados.sons_ruidos === '1';

    const secoes = [
        secao('localizacao', 'Localização', [
            textoPreenchido(dados.ponto_referencia),
            textoPreenchido(dados.logradouro_correto),
            entrada.poligonoValidado,
        ]),
        secao('imovel', 'Imóvel', [
            textoPreenchido(dados.tipo_imovel),
            dados.acessos.length > 0,
            textoPreenchido(dados.atividade_em_funcionamento),
            textoPreenchido(dados.complemento_tipo),
            textoPreenchido(dados.complemento_numero),
            textoPreenchido(dados.complemento_area_m2),
            textoPreenchido(dados.vagas_veiculo_passeio),
            textoPreenchido(dados.vagas_carga_descarga),
            textoPreenchido(dados.patio_carga_descarga),
            textoPreenchido(dados.area_embarque_desembarque),
            textoPreenchido(dados.area_terreno_m2),
            textoPreenchido(dados.area_total_construida_m2),
            textoPreenchido(dados.area_ocupada_atividade_m2),
            textoPreenchido(dados.area_carga_descarga_m2),
            textoPreenchido(dados.pavimento_edificacao),
            textoPreenchido(dados.pavimento_ocupado_atividade),
            textoPreenchido(dados.recuo_m),
        ]),
        secao('entorno', 'Entorno', [
            textoPreenchido(dados.entorno_residencial_m),
            textoPreenchido(dados.entorno_industrial_m),
            textoPreenchido(dados.entorno_saude_m),
            textoPreenchido(dados.entorno_comercial_m),
            textoPreenchido(dados.entorno_institucional_m),
            textoPreenchido(dados.entorno_especial_m),
            textoPreenchido(dados.entorno_educacional_m),
            textoPreenchido(dados.entorno_misto_m),
            textoPreenchido(dados.entorno_outros_m),
            textoPreenchido(dados.instalacoes_eletricas),
            textoPreenchido(dados.instalacoes_hidrossanitarias),
            dados.obras.length > 0,
            textoPreenchido(dados.alvara_numero),
            textoPreenchido(dados.quantidade_usuarios),
            textoPreenchido(dados.num_salas_alunos),
            textoPreenchido(dados.num_assentos),
            textoPreenchido(dados.unidades_hospedagem),
            textoPreenchido(dados.num_leitos),
        ]),
        secao('condicoes', 'Condições', [
            dados.equipamentos.length > 0,
            textoPreenchido(dados.equipamentos_outros),
            textoPreenchido(dados.maquinas_motores),
            textoPreenchido(dados.sons_ruidos),
            ...(origemDoRuido ? [textoPreenchido(dados.sons_ruidos_origem)] : []),
            dados.seg_extintores || dados.seg_central_gas || dados.seg_hidrantes || textoPreenchido(dados.seg_outros),
        ]),
        secao('documentacao', 'Documentação', [entrada.quantidadeAnexos > 0, textoPreenchido(dados.observacoes)]),
        secao('conclusao', 'Conclusão', [
            parecerPronto,
            textoPreenchido(dados.data_vistoria),
            textoPreenchido(dados.contato_nome),
            textoPreenchido(dados.contato_telefone),
        ]),
    ];

    const preenchidos = secoes.reduce((soma, item) => soma + item.preenchidos, 0);
    const total = secoes.reduce((soma, item) => soma + item.total, 0);

    return {
        secoes,
        preenchidos,
        total,
        percentual: total === 0 ? 0 : Math.round((preenchidos / total) * 100),
        parecerPronto,
    };
}
