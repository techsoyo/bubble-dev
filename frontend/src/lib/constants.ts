// src/lib/constants.ts

// Estados de aplicación para el sistema de gestión de candidatos
export const APPLICATION_STATUS = [
  'Received',
  'Review',
  'Interview',
  'Hired',
  'Rejected'
] as const;

export type ApplicationStatusType = typeof APPLICATION_STATUS[number];

// Tipos de empleo
export const EMPLOYMENT_TYPES = [
  'Full-time',
  'Part-time',
  'Contract',
  'Freelance',
  'Internship'
] as const;

export type EmploymentType = typeof EMPLOYMENT_TYPES[number];

// Categorías de trabajo
export const JOB_CATEGORIES = [
  'Technology',
  'Marketing',
  'Sales',
  'Human Resources',
  'Finance',
  'Operations',
  'Design',
  'Customer Service'
] as const;

export type JobCategory = typeof JOB_CATEGORIES[number];
