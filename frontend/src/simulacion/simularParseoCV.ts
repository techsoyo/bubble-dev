// Definir categorías funcionales internamente
const FUNC_CATEGORIES = [
    {
        categoria: "Desarrollo",
        subcategorias: ["frontend", "backend", "fullstack", "desarrollo web", "web development", "programación", "desarrollador"]
    },
    {
        categoria: "Diseño",
        subcategorias: ["diseño gráfico", "ux", "ui", "diseño web", "illustrator", "photoshop", "figma"]
    },
    {
        categoria: "Marketing",
        subcategorias: ["seo", "sem", "marketing digital", "redes sociales", "growth", "campañas"]
    },
    {
        categoria: "Finanzas",
        subcategorias: ["contabilidad", "finanzas", "contable", "financiero", "presupuesto", "tesorería"]
    },
    {
        categoria: "Recursos Humanos",
        subcategorias: ["rrhh", "recursos humanos", "selección", "reclutamiento", "gestión", "talento"]
    }
];

// Clasificación automática de categoría funcional
export function simularCategoriaCandidato(skills: string[], jobPosition: string = ''): string {
    let maxCoincidencias = 0;
    let categoriaSeleccionada = 'Sin categoría';
    const puesto = jobPosition.toLowerCase();
    FUNC_CATEGORIES.forEach(cat => {
        let coincidencias = 0;
        cat.subcategorias.forEach(sub => {
            const subLower = sub.toLowerCase();
            if (skills.some(skill => subLower.includes(skill.toLowerCase())) || puesto.includes(subLower)) {
                coincidencias++;
            }
        });
        if (coincidencias > maxCoincidencias) {
            maxCoincidencias = coincidencias;
            categoriaSeleccionada = cat.categoria;
        }
    });
    return categoriaSeleccionada;
}
// Simulación de parseo de CV y extracción de habilidades desde texto o nombre de archivo
export const habilidadesSimuladas = ['react', 'php', 'copywriting', 'seo', 'adobe', 'figma', 'typescript', 'python', 'gestion', 'marketing'];

export function simularParseoCV(nombreArchivo: string): string[] {
    const nombre = nombreArchivo.toLowerCase();
    return habilidadesSimuladas.filter(hab => nombre.includes(hab));
}
