// src/components/ui/language-switcher.tsx
import React, { memo, useCallback } from 'react';
import { useLanguage } from '../../lib/i18n/LanguageContext';
import { Button } from './button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from './dropdown-menu';
import { Globe } from 'lucide-react';

export const LanguageSwitcher = memo(() => {
  const { language, setLanguage, t } = useLanguage();
  const [isOpen, setIsOpen] = React.useState(false);

  const handleLanguageChange = useCallback((lang: 'es' | 'en') => {
    setLanguage(lang);
    setIsOpen(false);
  }, [setLanguage]);

  return (
    <DropdownMenu open={isOpen} onOpenChange={setIsOpen}>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          className="text-white hover:text-[#FF4785] hover:bg-transparent"
          aria-expanded={isOpen}
          aria-haspopup="menu"
          aria-label={t('general.language')}
        >
          <Globe className="h-5 w-5" />
          <span className="sr-only">{t('general.language')}</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent
        align="end"
        className="bg-[#2f2f2f]/95 backdrop-blur-sm border-gray-600 text-white min-w-[140px]"
      >
        <DropdownMenuItem
          onClick={() => handleLanguageChange('es')}
          className={`text-white hover:bg-[#FF4785]/20 focus:bg-[#FF4785]/20 ${language === 'es' ? 'bg-[#FF4785]/30' : ''}`}
          aria-selected={language === 'es'}
        >
          <span className="mr-3 font-mono text-sm">ES</span>
          {t('general.spanish')}
        </DropdownMenuItem>
        <DropdownMenuItem
          onClick={() => handleLanguageChange('en')}
          className={`text-white hover:bg-[#FF4785]/20 focus:bg-[#FF4785]/20 ${language === 'en' ? 'bg-[#FF4785]/30' : ''}`}
          aria-selected={language === 'en'}
        >
          <span className="mr-3 font-mono text-sm">EN</span>
          {t('general.english')}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
});

LanguageSwitcher.displayName = 'LanguageSwitcher';
