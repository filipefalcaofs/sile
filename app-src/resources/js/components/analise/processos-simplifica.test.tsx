// @vitest-environment happy-dom
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ProcessosSimplifica from './processos-simplifica';

vi.mock('@/components/ui/modal', () => ({
    Modal: ({ isOpen, children }: { isOpen: boolean; children: React.ReactNode }) =>
        isOpen ? <div role="dialog">{children}</div> : null,
}));

const resposta = (corpo: unknown, ok = true) => Promise.resolve({ ok, json: () => Promise.resolve(corpo) });

describe('ProcessosSimplifica', () => {
    let root: Root;
    let host: HTMLDivElement;

    async function montar(filtros: Record<string, string>) {
        host = document.createElement('div');
        document.body.appendChild(host);
        root = createRoot(host);
        await act(async () => {
            root.render(<ProcessosSimplifica filtros={filtros} />);
        });
    }

    afterEach(() => {
        act(() => root.unmount());
        host.remove();
        vi.unstubAllGlobals();
    });

    it('não consulta nem aparece sem busca', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        await montar({ busca: '' });

        expect(fetchMock).not.toHaveBeenCalled();
        expect(host.textContent).toBe('');
    });

    it('mostra os processos do Simplifica identificados como sistema anterior', async () => {
        const fetchMock = vi.fn(() =>
            resposta({
                consultado: true,
                erro: null,
                processos: [
                    {
                        numero: '0000012345-00067890/2024',
                        criado_em: '2024-03-10T09:00:00-03:00',
                        situacao: 'Em análise',
                        finalizado: false,
                        tvls: [2104040],
                    },
                ],
            }),
        );
        vi.stubGlobal('fetch', fetchMock);

        await montar({ busca: '12345678000195' });

        expect(fetchMock).toHaveBeenCalledWith(
            '/gestao/processos/legado?busca=12345678000195',
            expect.objectContaining({ credentials: 'same-origin' }),
        );
        expect(host.textContent).toContain('Simplifica');
        expect(host.textContent).toContain('sistema legado');
        expect(host.textContent).toContain('Somente leitura');
        expect(host.textContent).toContain('não podem ser alterados');
        expect(host.textContent).toContain('0000012345-00067890/2024');
        expect(host.textContent).toContain('2104040');
    });

    it('avisa quando a API falha, sem esconder a seção', async () => {
        vi.stubGlobal('fetch', vi.fn(() => resposta({}, false)));

        await montar({ busca: '12345678000195' });

        expect(host.textContent).toContain('Não foi possível consultar o Simplifica agora.');
    });

    it('some quando a busca não é chave do Simplifica', async () => {
        vi.stubGlobal('fetch', vi.fn(() => resposta({ consultado: false, processos: [], erro: null })));

        await montar({ busca: 'padaria' });

        expect(host.textContent).toBe('');
    });

    it('abre a ficha completa do processo ao clicar em Ver ficha', async () => {
        const lista = {
            consultado: true,
            erro: null,
            processos: [
                {
                    numero: '5921000030-00055276/2026',
                    protocolo: 'BAP2601568762',
                    criado_em: '2026-03-10T09:00:00-03:00',
                    situacao: 'Deferido',
                    finalizado: true,
                    tvls: [2104040],
                },
            ],
        };
        const detalhe = {
            erro: null,
            processo: {
                ...lista.processos[0],
                solicitante: { nome: 'MARIA DA SILVA', documento: '12345678909' },
                localizacao: { latitude: -12.97, longitude: -38.5 },
                parecer: { deferido: true, texto: 'Deferido conforme a ficha.', tipo: 'provisoria', atualizado_em: null, valido_ate: null },
                fichas: [
                    {
                        atualizado_em: null,
                        finalizada: true,
                        deferido: true,
                        parecer: null,
                        numero_tvl: 2104040,
                        condicionante: 'Horário comercial',
                        itens: [{ campo: 'cep', deferido: true, em_analise: false }],
                        atividades: [
                            {
                                codigo: '4711-3/02',
                                descricao: 'Supermercados',
                                deferido: true,
                                em_analise: false,
                                incluida: false,
                                excluida: false,
                                enquadrado: true,
                                enquadramento: 'Comércio local',
                            },
                        ],
                    },
                ],
                tvls: [],
                historico: [{ em: null, titulo: 'Protocolado', mensagem: 'Processo protocolado.', status: 'Em análise', por: 'Ana Analista' }],
            },
        };
        const fetchMock = vi.fn((url: string) => resposta(url.includes('/detalhe') ? detalhe : lista));
        vi.stubGlobal('fetch', fetchMock);

        await montar({ busca: '12345678000195' });

        const botao = Array.from(host.querySelectorAll('button')).find((b) => b.textContent === 'Ver ficha');
        expect(botao).toBeDefined();

        await act(async () => {
            botao?.click();
        });

        expect(fetchMock).toHaveBeenCalledWith(
            '/gestao/processos/legado/detalhe?numero=5921000030-00055276%2F2026',
            expect.objectContaining({ credentials: 'same-origin' }),
        );

        const modal = document.body.textContent ?? '';
        expect(modal).toContain('somente leitura');
        expect(modal).toContain('Simplifica (sistema legado)');
        expect(modal).toContain('MARIA DA SILVA');
        expect(modal).toContain('123.456.789-09');
        expect(modal).toContain('Deferido conforme a ficha.');
        expect(modal).toContain('Horário comercial');
        expect(modal).toContain('Comércio local');
        expect(modal).toContain('Ana Analista');
    });
});
