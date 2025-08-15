/**
 * Component Library - Production Ready UI Components
 * 
 * A comprehensive collection of reusable, accessible, and performant
 * React components for the Bubble of Talents recruitment platform.
 * 
 * @package ComponentLibrary
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

// Core UI Components (existing lowercase components)
export { Button } from './ui/button';
export { Input } from './ui/input';
export { Card, CardHeader, CardContent } from './ui/card';
export { Badge } from './ui/badge';
export { Toast } from './ui/toast';
export { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from './ui/tooltip';
export { Checkbox } from './ui/checkbox';
export { Slider } from './ui/slider';
export { Tabs, TabsContent, TabsList, TabsTrigger } from './ui/tabs';
export { Calendar } from './ui/calendar';
export { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from './ui/select';
export { Sidebar, SidebarContent, SidebarProvider, SidebarTrigger } from './ui/sidebar';
export { LanguageSwitcher } from './ui/language-switcher';

// Layout Components
export { Header } from './layout/Header';
export { Footer } from './layout/Footer';

// Optimized Media Components  
export { OptimizedImage, OptimizedAvatar, OptimizedImageGallery } from './OptimizedImage';

// Business Components
export { BlogCard } from './BlogCard';

// Dashboard Components
export { ApplicationsTable } from './dashboard/ApplicationsTable';
export { EmailHistoryViewer } from './dashboard/EmailHistoryViewer';

// Hooks (only existing ones)
export { useToast } from '../hooks/use-toast';

// Utilities
export { cn } from '../lib/utils';

/**
 * Component Library Version
 */
export const VERSION = '1.0.0';

/**
 * Design System Configuration
 */
export const DESIGN_SYSTEM = {
    name: 'Bubble of Talents Design System',
    version: VERSION,
    description: 'Production-ready component library for recruitment platform',
    repository: 'https://github.com/bubble-talents/frontend',
    documentation: 'https://bubble-talents.github.io/design-system',
    support: {
        accessibility: 'WCAG 2.1 AA',
        browsers: ['Chrome 90+', 'Firefox 88+', 'Safari 14+', 'Edge 90+'],
        frameworks: ['React 18+', 'TypeScript 4.5+'],
        testing: ['Jest', 'React Testing Library', 'Playwright']
    }
};

/**
 * Existing Component Categories
 */
export const COMPONENT_CATEGORIES = {
    'Core UI': [
        'Button', 'Input', 'Card', 'Badge', 'Toast', 'Tooltip', 'Checkbox',
        'Slider', 'Tabs', 'Calendar', 'Popover', 'Select', 'Sidebar'
    ],
    'Layout': [
        'Header', 'Footer', 'LanguageSwitcher'
    ],
    'Media': [
        'OptimizedImage', 'OptimizedAvatar', 'OptimizedImageGallery'
    ],
    'Business Logic': [
        'BlogCard', 'CVConfirmationModal', 'ApplicationsTable', 'EmailHistoryViewer'
    ],
    'Admin': [
        'AdminPanel', 'CandidateManager', 'JobManager', 'ApplicationManager'
    ]
};

/**
 * Component Usage Guidelines
 */
export const USAGE_GUIDELINES = {
    accessibility: {
        description: 'All components follow WCAG 2.1 AA guidelines',
        requirements: [
            'Proper ARIA labels and roles',
            'Keyboard navigation support',
            'Screen reader compatibility',
            'Color contrast compliance',
            'Focus management'
        ]
    },
    performance: {
        description: 'Components are optimized for production performance',
        features: [
            'Lazy loading support',
            'Code splitting ready',
            'Memoization where appropriate',
            'Bundle size optimization',
            'Tree shaking compatible'
        ]
    },
    theming: {
        description: 'Consistent theming across all components',
        features: [
            'Tailwind CSS based',
            'CSS custom properties',
            'Dark/light mode support',
            'Responsive design',
            'Customizable variants'
        ]
    },
    testing: {
        description: 'All components are thoroughly tested',
        coverage: [
            'Unit tests with Jest',
            'Integration tests with React Testing Library',
            'E2E tests with Playwright',
            'Visual regression tests',
            'Accessibility tests'
        ]
    }
};

/**
 * Development Guidelines
 */
export const DEVELOPMENT_GUIDELINES = {
    naming: {
        components: 'PascalCase for component names',
        props: 'camelCase for prop names',
        files: 'kebab-case for component files (following existing pattern)',
        exports: 'Named exports preferred'
    },
    structure: {
        directory: 'Components organized by category',
        files: ['component.tsx', 'component.test.tsx', 'component.stories.tsx'],
        documentation: 'JSDoc comments required for all public APIs'
    },
    patterns: {
        composition: 'Favor composition over inheritance',
        props: 'Use discriminated unions for variant props',
        forwarding: 'Forward refs for DOM components',
        polymorphism: 'Support polymorphic components where appropriate'
    }
};

export default {
    VERSION,
    DESIGN_SYSTEM,
    COMPONENT_CATEGORIES,
    USAGE_GUIDELINES,
    DEVELOPMENT_GUIDELINES
};
