import { useHttp } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Abre o PDF real do TVL pelo endpoint que gera o documento e devolve a URL
 * assinada. Sem arquivo, o botão informa a falha em vez de marcar disponibilidade
 * sem saída.
 */
export default function BaixarTvl({ solicitacaoId }: { solicitacaoId: number }) {
    const [erro, setErro] = useState<string | null>(null);
    const [abrindo, setAbrindo] = useState(false);
    const pedido = useHttp<Record<string, never>, { download_url?: string }>({});

    function baixar() {
        setErro(null);
        setAbrindo(true);
        pedido.post(`/gestao/processos/${solicitacaoId}/tvl`, {
            onSuccess: (resposta) => {
                setAbrindo(false);
                const url = resposta?.download_url;

                if (typeof url === 'string' && url !== '') {
                    window.open(url, '_blank', 'noopener');

                    return;
                }

                setErro('A viabilidade locacional não devolveu o arquivo.');
            },
            onHttpException: () => {
                setAbrindo(false);
                setErro('Não foi possível abrir o PDF da viabilidade locacional.');

                return false;
            },
            onNetworkError: () => {
                setAbrindo(false);
                setErro('Não foi possível abrir o PDF da viabilidade locacional. Verifique a conexão e tente novamente.');
            },
        });
    }

    return (
        <span className="inline-flex flex-col items-start gap-1">
            <button
                type="button"
                onClick={baixar}
                disabled={abrindo}
                className="inline-flex h-8 items-center rounded-lg border border-gray-300 bg-white px-2.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
            >
                {abrindo ? 'Abrindo PDF...' : 'Abrir PDF'}
            </button>
            {erro && <span className="text-xs text-error-500">{erro}</span>}
        </span>
    );
}
