import { marcarEntradaConsole } from '@/components/app/console-entrance';
import { TEMPOS } from './gestao-login-scene-state';
import type { FaseLogin } from './gestao-login-scene-state';

export type OpcoesSequencia = {
    destino: string;
    /** `prefers-reduced-motion: reduce` ativo: navega direto, sem o voo da marca. */
    reduzido: boolean;
    aoMudarFase: (fase: FaseLogin) => void;
    navegar: (destino: string) => void;
};

/**
 * Agenda as fases entre o "credencial aceita" e a troca de página. A
 * marca de entrada é gravada junto com a navegação: é ela que faz o
 * console animar a chegada uma vez só.
 *
 * @returns Função que cancela o que ainda não rodou.
 */
export function sequenciaDeEntrada({ destino, reduzido, aoMudarFase, navegar }: OpcoesSequencia): () => void {
    if (reduzido) {
        navegar(destino);

        return () => {};
    }

    const agendados: ReturnType<typeof setTimeout>[] = [];
    const agendar = (acao: () => void, atraso: number) => {
        agendados.push(setTimeout(acao, atraso));
    };

    aoMudarFase('authorized');
    agendar(() => aoMudarFase('preparing'), TEMPOS.preparing);
    agendar(() => aoMudarFase('docking'), TEMPOS.docking);
    agendar(() => {
        marcarEntradaConsole();
        navegar(destino);
    }, TEMPOS.navegacao);

    return () => agendados.forEach(clearTimeout);
}
