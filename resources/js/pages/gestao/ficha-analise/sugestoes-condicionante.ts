export interface TextoDaBiblioteca {
    id: number;
    category: string;
    content: string;
}

function normalizar(texto: string): string {
    return texto.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase().trim();
}

/**
 * Textos da biblioteca que combinam com o que o analista digita na
 * condicionante (SEDUR 20/10/2026): a partir de 3 letras, todas as palavras
 * digitadas precisam aparecer, sem diferenciar acento nem maiúscula. As
 * condicionantes da biblioteca vêm primeiro e o que já foi adicionado não
 * se repete.
 */
export function sugerirCondicionantes(
    textos: TextoDaBiblioteca[],
    termo: string,
    jaAdicionadas: string[],
    limite = 5,
): { id: number; content: string }[] {
    const busca = normalizar(termo);

    if (busca.replace(/\s/g, '').length < 3) {
        return [];
    }

    const palavras = busca.split(/\s+/).filter(Boolean);
    const adicionadas = new Set(jaAdicionadas.map((texto) => texto.trim()));
    const combinam = textos.filter((texto) => {
        if (adicionadas.has(texto.content.trim())) {
            return false;
        }

        const conteudo = normalizar(texto.content);

        return palavras.every((palavra) => conteudo.includes(palavra));
    });

    const condicionantes = combinam.filter((texto) => texto.category === 'condicionante');
    const demais = combinam.filter((texto) => texto.category !== 'condicionante');

    return [...condicionantes, ...demais].slice(0, limite).map(({ id, content }) => ({ id, content }));
}
