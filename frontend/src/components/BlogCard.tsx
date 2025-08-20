import React from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../lib/i18n/LanguageContext';
import {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle,
} from './ui/card';

interface BlogCardProps {
  id: number | string;
  title: string;
  date: string;
  excerpt: string;
  to: string;
  image?: string; // Nueva prop para la imagen
}

export const BlogCard: React.FC<BlogCardProps> = ({ id, title, date, excerpt, to, image }) => {
  const { t } = useLanguage();

  return (
    <Card key={id} className="hover:shadow-lg transition-shadow duration-300">
      <div className="h-48 rounded-t-lg bg-gray-100 overflow-hidden">
        {image ? (
          <img
            src={image}
            alt={title}
            loading="lazy"
            className="w-full h-full object-cover hover:scale-105 transition-transform duration-300"
          />
        ) : (
          <div className="w-full h-full bg-gray-100 flex items-center justify-center">
            <span className="text-gray-400">{t('general.noImage')}</span>
          </div>
        )}
      </div>
      <CardHeader>
        <CardTitle className="mb-2 text-[#2F2F2F]">{title}</CardTitle>
        <CardDescription className="text-sm text-[#2F2F2F]">{date}</CardDescription>
      </CardHeader>
      <CardContent>
        <p className="text-[#2F2F2F]">{excerpt}</p>
      </CardContent>
      <CardFooter>
        <Link to={to} className="text-[#FF4785] hover:underline">
          {t('homepage.readMore')} →
        </Link>
      </CardFooter>
    </Card>
  );
};
