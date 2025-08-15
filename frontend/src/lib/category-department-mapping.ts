/**
 * Mapeo de categorías de habilidades a departamentos
 */

export interface DepartmentMapping {
  [department: string]: string[];
}

export const categoryDepartmentMapping: DepartmentMapping = {
  'Tecnología': [
    'javascript', 'typescript', 'react', 'node.js', 'python', 'java', 'c#', 'php',
    'angular', 'vue', 'sql', 'mongodb', 'postgresql', 'mysql', 'docker', 'kubernetes',
    'aws', 'azure', 'git', 'linux', 'windows server', 'html', 'css', 'bootstrap',
    'tailwind', 'sass', 'webpack', 'vite', 'jest', 'cypress', 'selenium'
  ],
  'Marketing': [
    'seo', 'sem', 'google ads', 'facebook ads', 'content marketing', 'email marketing',
    'social media', 'analytics', 'copywriting', 'branding', 'photoshop', 'illustrator',
    'canva', 'hubspot', 'mailchimp', 'google analytics', 'social media management'
  ],
  'Diseño': [
    'photoshop', 'illustrator', 'figma', 'sketch', 'indesign', 'after effects',
    'premiere pro', 'ux design', 'ui design', 'web design', 'graphic design',
    'prototyping', 'wireframing', 'adobe creative suite', 'canva', 'blender'
  ],
  'Ventas': [
    'sales', 'crm', 'salesforce', 'hubspot', 'lead generation', 'cold calling',
    'negotiation', 'customer service', 'account management', 'business development',
    'prospecting', 'closing', 'pipeline management'
  ],
  'Recursos Humanos': [
    'recruiting', 'hiring', 'onboarding', 'performance management', 'compensation',
    'benefits', 'hr policies', 'employee relations', 'training', 'development',
    'workforce planning', 'talent acquisition', 'hr analytics'
  ],
  'Finanzas': [
    'accounting', 'bookkeeping', 'financial analysis', 'budgeting', 'forecasting',
    'excel', 'quickbooks', 'sap', 'financial modeling', 'auditing', 'tax preparation',
    'accounts payable', 'accounts receivable', 'financial reporting'
  ],
  'Operaciones': [
    'project management', 'process improvement', 'quality assurance', 'supply chain',
    'logistics', 'inventory management', 'lean', 'six sigma', 'agile', 'scrum',
    'kanban', 'jira', 'asana', 'trello'
  ],
  'Atención al Cliente': [
    'customer service', 'customer support', 'helpdesk', 'zendesk', 'freshdesk',
    'ticketing systems', 'live chat', 'phone support', 'email support',
    'customer satisfaction', 'complaint resolution'
  ]
};

/**
 * Sugiere departamentos basados en las habilidades proporcionadas
 */
export function suggestDepartmentFromSkills(skills: string[]): string[] {
  const skillsLower = skills.map(skill => skill.toLowerCase());
  const departmentScores: { [department: string]: number } = {};

  // Calcular puntuación para cada departamento
  Object.entries(categoryDepartmentMapping).forEach(([department, departmentSkills]) => {
    const matchingSkills = skillsLower.filter(skill =>
      departmentSkills.some(depSkill =>
        skill.includes(depSkill.toLowerCase()) || depSkill.toLowerCase().includes(skill)
      )
    );

    if (matchingSkills.length > 0) {
      departmentScores[department] = matchingSkills.length;
    }
  });

  // Ordenar departamentos por puntuación
  return Object.entries(departmentScores)
    .sort(([, a], [, b]) => b - a)
    .map(([department]) => department);
}

/**
 * Obtiene las habilidades relacionadas con un departamento
 */
export function getSkillsForDepartment(department: string): string[] {
  return categoryDepartmentMapping[department] || [];
}

/**
 * Verifica si una habilidad pertenece a un departamento específico
 */
export function isSkillInDepartment(skill: string, department: string): boolean {
  const departmentSkills = categoryDepartmentMapping[department] || [];
  const skillLower = skill.toLowerCase();

  return departmentSkills.some(depSkill =>
    skillLower.includes(depSkill.toLowerCase()) || depSkill.toLowerCase().includes(skillLower)
  );
}
