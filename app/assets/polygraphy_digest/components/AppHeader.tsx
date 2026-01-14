import React from 'react';
import { useTranslation } from 'react-i18next';
import { Package } from 'lucide-react';

interface AppHeaderProps {
    title: string;
}

const AppHeader: React.FC<AppHeaderProps> = ({ title }) => {
    const { t, i18n } = useTranslation();

    const changeLanguage = (lng: string) => {
        i18n.changeLanguage(lng);
    };

    const languages = [
        { code: 'cs', label: 'Čeština' },
        { code: 'en', label: 'English' },
        { code: 'ru', label: 'Русский' }
    ];

    // Current language code
    const currentLangCode = i18n.language;

    return (
        <header className="bg-white border-b border-border shadow-sm mb-4 sticky-top" style={{ zIndex: 1000 }}>
            <div className="max-w-7xl mx-auto py-[28px] px-0">
                <div className="d-flex justify-content-between align-items-center">
                    {/* Left: Home Button */}
                    <a href={`/${currentLangCode}`} className="btn btn-outline-secondary btn-sm d-inline-flex align-items-center" style={{ fontFamily: 'var(--bs-body-font-family)' }}>
                        <i className="bi bi-house me-2"></i>
                        {t('nav.back_to_home', 'Zpět na hlavní')}
                    </a>

                    {/* Center: Icon + Title */}
                    <div className="flex items-center">
                        <div className="border rounded p-2 mr-4 flex items-center justify-center w-12 h-12">
                            <Package size={24} className="text-dark" />
                        </div>
                        <h1 className="text-[calc(1.375rem_+_1.5vw)] xl:text-[2.5rem] font-medium leading-[1.2] text-foreground mb-0" style={{ fontFamily: 'var(--bs-body-font-family)' }}>
                            {title}
                        </h1>
                    </div>

                    {/* Right: Language Switcher */}
                    <div className="dropdown">
                        <button 
                            className="btn btn-outline-secondary btn-sm dropdown-toggle text-uppercase" 
                            type="button" 
                            id="languageHeaderDropdown" 
                            data-bs-toggle="dropdown" 
                            aria-expanded="false"
                            style={{ fontFamily: 'var(--bs-body-font-family)' }}
                        >
                            <span className="me-1">{currentLangCode}</span>
                        </button>
                        <ul className="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="languageHeaderDropdown" style={{ minWidth: 'auto' }}>
                            {languages.map((lang) => (
                                <li key={lang.code}>
                                    <button 
                                        className={`dropdown-item ${currentLangCode === lang.code ? 'active' : ''}`} 
                                        onClick={() => changeLanguage(lang.code)}
                                    >
                                        {lang.label}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            </div>
        </header>
    );
};

export default AppHeader;
