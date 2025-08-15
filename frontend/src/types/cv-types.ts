// Tipos específicos para CVConfirmationModal
export interface PersonalInfo {
  name?: string;
  email?: string;
  phone?: string;
  location?: string;
}

export interface Skill {
  name?: string;
  level?: string;
}

export interface Experience {
  position?: string;
  company?: string;
  start_date?: string;
  end_date?: string;
  description?: string;
}

export interface Education {
  degree?: string;
  institution?: string;
  start_date?: string;
  end_date?: string;
}

export interface CVData {
  personal_info?: PersonalInfo;
  hard_skills?: (string | Skill)[];
  soft_skills?: string[];
  experience?: Experience[];
  education?: Education[];
  area_of_interest?: string;
  [key: string]: unknown;
}

// Tipos para ChatBot
export interface ChatBotActionData {
  url?: string;
  [key: string]: unknown;
}

export interface ChatBotOption {
  action_data?: ChatBotActionData;
  [key: string]: unknown;
}
