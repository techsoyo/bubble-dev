import React, { memo, useCallback, useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useLanguage } from '../lib/i18n/LanguageContext';
import { translateJobData } from '../lib/googleTranslation';

interface JobCardProps {
    id: string;
    title: string;
    description: string;
    location: string;
    type: string;
    category: string;
    /** Fecha en que se publicó la oferta; opcional y no utilizada directamente en la tarjeta. */
    datePosted?: string;
}

// Usar React.memo para evitar renderizaciones innecesarias
const JobCard = memo(function JobCard({
    id,
    title,
    description,
    location,
    type,
    category,
    // datePosted es aceptado pero no se usa
}: JobCardProps) {
    const navigate = useNavigate();
    const { t, language } = useLanguage();

    // Estados para las traducciones
    const [translatedData, setTranslatedData] = useState({
        title,
        description,
        category,
        type
    });
    const [isTranslating, setIsTranslating] = useState(false);

    // Efecto para traducir cuando cambia el idioma
    useEffect(() => {
        const translateData = async () => {
            if (language === 'en') {
                setIsTranslating(true);
                try {
                    const translated = await translateJobData({
                        title,
                        description,
                        category,
                        type
                    }, 'en');
                    setTranslatedData({
                        title: translated.title || title,
                        description: translated.description,
                        category: translated.category,
                        type: translated.type
                    });
                } catch (error) {
                    console.error('Error translating job data:', error);
                    // En caso de error, usar datos originales
                    setTranslatedData({ title, description, category, type });
                } finally {
                    setIsTranslating(false);
                }
            } else {
                // Si es español, usar datos originales
                setTranslatedData({ title, description, category, type });
            }
        };

        translateData();
    }, [language, title, description, category, type]);

    // Usar useCallback para memoizar funciones
    const handleCardClick = useCallback(() => {
        navigate(`/jobs/${id}`);
    }, [navigate, id]);

    const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            navigate(`/jobs/${id}`);
        }
    }, [navigate, id]);

    // Truncar descripción para evitar textos muy largos
    const truncatedDescription = translatedData.description.length > 150
        ? `${translatedData.description.substring(0, 150)}...`
        : translatedData.description;

    return (
        <div
            onClick={handleCardClick}
            onKeyDown={handleKeyDown}
            tabIndex={0}
            role="button"
            aria-label={`${t('jobs.jobDetails')}: ${translatedData.title}`}
            className="grid grid-cols-[1fr_auto] grid-rows-[auto_auto_auto] gap-y-4 p-6 rounded-3xl text-[#444447] bg-gradient-to-br from-[#e0e4e5] to-[#f2f6f9] shadow-[inset_-2px_2px_white,_-20px_20px_40px_rgba(0,0,0,0.25)] font-sans cursor-pointer hover:shadow-[inset_-2px_2px_white,_-25px_25px_45px_rgba(0,0,0,0.3)] transition-shadow duration-300 focus:outline-none focus:ring-2 focus:ring-[#FF4785] focus:ring-offset-2"
            style={{ minWidth: '280px', width: '100%', minHeight: '320px', maxHeight: 'none' }}
        >
            {/* Title */}
            <h3 className="text-xl uppercase font-medium self-end col-start-1 row-start-1 break-words">
                {isTranslating ? (
                    <span className="opacity-70 animate-pulse">{title}</span>
                ) : (
                    translatedData.title
                )}
            </h3>

            {/* Icon */}
            <div className="text-3xl col-start-2 row-start-1 text-transparent bg-clip-text bg-gradient-to-r from-pink-500 to-purple-500" aria-hidden="true">
                <i className="fas fa-briefcase"></i>
            </div>

            {/* Content */}
            <div className="col-span-2 row-start-2">
                <p className="mb-2 text-sm" aria-label={`${t('jobs.about')}: ${truncatedDescription}`}>
                    {isTranslating ? (
                        <span className="opacity-70 animate-pulse">{description.substring(0, 150)}...</span>
                    ) : (
                        truncatedDescription
                    )}
                </p>
                <p className="text-sm" aria-label={`${t('jobs.location')} ${t('general.and')} ${t('jobs.type')}: ${location}, ${isTranslating ? type : translatedData.type}`}>
                    {location} • {isTranslating ? (
                        <span className="opacity-70 animate-pulse">{type}</span>
                    ) : (
                        translatedData.type
                    )}
                </p>
                <span className="inline-block mt-2 px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-700" aria-label={`${t('jobs.category')}: ${isTranslating ? category : translatedData.category}`}>
                    {isTranslating ? (
                        <span className="opacity-70 animate-pulse">{category}</span>
                    ) : (
                        translatedData.category
                    )}
                </span>
            </div>

            {/* Decorative bar - usando div en lugar de hr */}
            <div className="col-span-2 h-1 bg-gradient-to-r from-pink-500 to-purple-500 row-start-3 rounded-full" aria-hidden="true"></div>

            {/* Ver detalles */}
            <span className="col-span-2 text-right text-xs text-blue-600 mt-2 font-medium" aria-hidden="true">
                {t('dashboard.view')} →
            </span>
        </div>
    );
});

export default JobCard;
