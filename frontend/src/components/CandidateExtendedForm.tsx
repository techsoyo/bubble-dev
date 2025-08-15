// src/components/CandidateExtendedForm.tsx
import React, { useState } from 'react';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Textarea } from './ui/textarea';
import { Select } from './ui/select';

interface CandidateExtendedFormProps {
  mode: 'short' | 'extended';
  onSubmit: (formData: Record<string, string>) => void;
  initialEmail?: string;
}

export default function CandidateExtendedForm({ mode, onSubmit, initialEmail = '' }: CandidateExtendedFormProps) {
  const [formData, setFormData] = useState<Record<string, string>>({
    email: initialEmail,
    username: '',
    password: '',
    confirmPassword: '',
    first_name: '',
    last_name: '',
    phone: '',
    location: '',
    education_level: '',
    experience_years: '',
    skills: '',
    languages: '',
    linkedin_url: '',
    portfolio_url: '',
    notes: '',
  });

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onSubmit(formData);
  };

  return (
    <form className="space-y-4" onSubmit={handleSubmit}>
      {/* Siempre visibles */}
      <Input name="email" type="email" value={formData.email} placeholder="Email" onChange={handleChange} required />
      <Input name="username" type="text" value={formData.username} placeholder="Nombre de usuario" onChange={handleChange} required />
      <Input name="password" type="password" value={formData.password} placeholder="Contraseña" onChange={handleChange} required />
      <Input name="confirmPassword" type="password" value={formData.confirmPassword} placeholder="Confirmar contraseña" onChange={handleChange} required />

      {/* Campos extendidos solo si mode = extended */}
      {mode === 'extended' && (
        <>
          <Input name="first_name" type="text" value={formData.first_name} placeholder="Nombre" onChange={handleChange} />
          <Input name="last_name" type="text" value={formData.last_name} placeholder="Apellidos" onChange={handleChange} />
          <Input name="phone" type="text" value={formData.phone} placeholder="Teléfono" onChange={handleChange} />
          <Input name="location" type="text" value={formData.location} placeholder="Ubicación" onChange={handleChange} />
          <Input name="education_level" type="text" value={formData.education_level} placeholder="Nivel de estudios" onChange={handleChange} />
          <Input name="experience_years" type="number" value={formData.experience_years} placeholder="Años de experiencia" onChange={handleChange} />
          <Textarea name="skills" value={formData.skills} placeholder="Habilidades" onChange={handleChange} />
          <Textarea name="languages" value={formData.languages} placeholder="Idiomas" onChange={handleChange} />
          <Input name="linkedin_url" type="url" value={formData.linkedin_url} placeholder="LinkedIn" onChange={handleChange} />
          <Input name="portfolio_url" type="url" value={formData.portfolio_url} placeholder="Portfolio" onChange={handleChange} />
          <Textarea name="notes" value={formData.notes} placeholder="Notas adicionales" onChange={handleChange} />
        </>
      )}

      <Button type="submit" className="bg-[#FF4785] text-white hover:bg-[#2f2f2f]">
        {mode === 'extended' ? 'Enviar registro completo' : 'Registrar'}
      </Button>
    </form>
  );
}
