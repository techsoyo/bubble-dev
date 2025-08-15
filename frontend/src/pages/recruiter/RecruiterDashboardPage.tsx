// src/pages/recruiter/RecruiterDashboardPage.tsx
import React, { useEffect, useState } from 'react';
import RecruiterDashboard from '../../components/dashboard/RecruiterDashboard';

const RecruiterDashboardPage: React.FC = () => {
  const [recruiterId, setRecruiterId] = useState<string>('');

  useEffect(() => {
    // Producción: obtener el usuario autenticado desde la API/backend
    fetch('/api/me')
      .then(res => res.json())
      .then(user => {
        if (user && user.id) {
          setRecruiterId(user.id);
        } else {
          setRecruiterId('');
        }
      })
      .catch(error => {
        console.error('Error al obtener usuario autenticado:', error);
        setRecruiterId('');
      });
  }, []);

  if (!recruiterId) {
    return <div className="p-6">Cargando información del reclutador...</div>;
  }

  return (
    <div>
      <RecruiterDashboard recruiterId={recruiterId} />
    </div>
  );
};

export default RecruiterDashboardPage;
