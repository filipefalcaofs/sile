import {
    alternarOpcao,
    enunciadoSemOpcoes,
    numeroDaOpcao,
    type PerguntaPlanilha,
    type RespostaPlanilha,
} from '@/lib/respostas-planilha';

interface Props {
    pergunta: PerguntaPlanilha;
    valor: RespostaPlanilha | undefined;
    onChange: (valor: RespostaPlanilha) => void;
}

const CLASSE_INPUT =
    'size-4 border-gray-300 bg-white scheme-light accent-brand-500 dark:border-gray-400 dark:bg-gray-900 dark:scheme-dark';
const CLASSE_ROTULO = 'inline-flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300';

/**
 * Pergunta da planilha de tratamento, desenhada pelo tipo: Sim/Não, uma opção
 * entre várias ou várias opções marcadas.
 */
export default function PerguntaPlanilhaCampo({ pergunta, valor, onChange }: Props) {
    const nome = `pergunta-${pergunta.numero}`;
    const enunciado = enunciadoSemOpcoes(pergunta.texto, pergunta.tipo === 'sim_nao' ? [] : pergunta.opcoes);

    return (
        <div className="flex flex-col gap-2">
            <p className="text-sm whitespace-pre-line text-gray-700 dark:text-gray-300">
                {pergunta.numero}. {enunciado}
            </p>

            {pergunta.tipo === 'sim_nao' && (
                <div className="flex gap-4">
                    {[
                        { rotulo: 'Sim', resposta: true },
                        { rotulo: 'Não', resposta: false },
                    ].map((alternativa) => (
                        <label key={alternativa.rotulo} className={CLASSE_ROTULO}>
                            <input
                                type="radio"
                                name={nome}
                                className={CLASSE_INPUT}
                                checked={valor === alternativa.resposta}
                                onChange={() => onChange(alternativa.resposta)}
                            />
                            {alternativa.rotulo}
                        </label>
                    ))}
                </div>
            )}

            {pergunta.tipo === 'opcao_unica' && (
                <div className="flex flex-col gap-2">
                    {pergunta.opcoes.map((opcao) => (
                        <label key={opcao.rotulo} className={CLASSE_ROTULO}>
                            <input
                                type="radio"
                                name={nome}
                                className={`${CLASSE_INPUT} mt-0.5 shrink-0`}
                                checked={valor === numeroDaOpcao(opcao.rotulo)}
                                onChange={() => onChange(numeroDaOpcao(opcao.rotulo))}
                            />
                            <span>
                                {opcao.rotulo}. {opcao.texto}
                            </span>
                        </label>
                    ))}
                </div>
            )}

            {pergunta.tipo === 'multipla' && (
                <div className="flex flex-col gap-2">
                    {pergunta.opcoes.map((opcao) => {
                        const numero = numeroDaOpcao(opcao.rotulo);
                        const marcadas = Array.isArray(valor) ? valor : [];

                        return (
                            <label key={opcao.rotulo} className={CLASSE_ROTULO}>
                                <input
                                    type="checkbox"
                                    name={nome}
                                    className={`${CLASSE_INPUT} mt-0.5 shrink-0`}
                                    checked={marcadas.includes(numero)}
                                    onChange={() => onChange(alternarOpcao(marcadas, numero))}
                                />
                                <span>
                                    {opcao.rotulo}. {opcao.texto}
                                </span>
                            </label>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
