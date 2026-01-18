import React from 'react';
import { useTranslation } from 'react-i18next';
import { Globe } from 'lucide-react';

const LanguageSwitcher: React.FC = () => {
    const { i18n } = useTranslation();

    const languages = [
        { code: 'cs', label: 'Čeština' },
        { code: 'en', label: 'English' },
        { code: 'ru', label: 'Русский' }
    ];

    const changeLanguage = (lng: string) => {
        i18n.changeLanguage(lng);
    };

    const currentLang = languages.find(l => l.code === i18n.language) || languages[0];

    return (
        <div className="dropdown">
            <button 
                className="btn btn-light btn-sm rounded-pill border shadow-sm px-3 dropdown-toggle d-flex align-items-center gap-2" 
                type="button" 
                id="languageDropdown" 
                data-bs-toggle="dropdown" 
                aria-expanded="false"
            >
                <Globe size={14} />
                {currentLang.label}
            </button>
            <ul className="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="languageDropdown">
                {languages.map((lang) => (
                    <li key={lang.code}>
                        <button 
                            className={`dropdown-item ${i18n.language === lang.code ? 'active' : ''}`} 
                            onClick={() => changeLanguage(lang.code)}
                        >
                            {lang.label}
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
};

export default LanguageSwitcher;
