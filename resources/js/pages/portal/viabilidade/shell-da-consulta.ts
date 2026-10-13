/**
 * A consulta é pública, mas quem está logado continua dentro do portal
 * (sidebar e serviços à mão) em vez de cair no shell público com "Entrar".
 */
export function shellDaConsulta(user: { id: number } | null | undefined): 'portal' | 'publico' {
    return user ? 'portal' : 'publico';
}
