// Simulación de cálculo de score de coincidencia de habilidades
export function simularScoring(habilidadesVacante: string[], habilidadesCandidato: string[]): number {
    if (!habilidadesVacante || habilidadesVacante.length === 0) return 0;
    const coincidencias = habilidadesVacante.filter(hab => habilidadesCandidato.includes(hab));
    return Math.round((coincidencias.length / habilidadesVacante.length) * 100);
}
