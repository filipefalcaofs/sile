interface ChartDataTableProps {
    caption: string;
    rotuloCabecalho: string;
    valorCabecalho: string;
    linhas: { rotulo: string; valor: string | number }[];
}

/**
 * Equivalente textual de um gráfico: a tabela fica disponível para leitor
 * de tela e o desenho (canvas) pode ser ocultado da árvore acessível.
 */
export default function ChartDataTable({ caption, rotuloCabecalho, valorCabecalho, linhas }: ChartDataTableProps) {
    return (
        <table className="sr-only">
            <caption>{caption}</caption>
            <thead>
                <tr>
                    <th scope="col">{rotuloCabecalho}</th>
                    <th scope="col">{valorCabecalho}</th>
                </tr>
            </thead>
            <tbody>
                {linhas.map((linha, indice) => (
                    <tr key={`${linha.rotulo}-${indice}`}>
                        <td>{linha.rotulo}</td>
                        <td>{linha.valor}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
