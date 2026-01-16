import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';

export interface Settings {
    theme: 'light' | 'dark';
    itemsPerPage: number;
}

const DEFAULT_SETTINGS: Settings = {
    theme: 'light',
    itemsPerPage: 12,
};

interface SettingsContextType extends Settings {
    updateSettings: (newSettings: Partial<Settings>) => void;
}

const SettingsContext = createContext<SettingsContextType | undefined>(undefined);

const STORAGE_KEY = 'polygraphy_settings';

export const SettingsProvider: React.FC<{ children: ReactNode }> = ({ children }) => {
    const [settings, setSettings] = useState<Settings>(() => {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            try {
                return { ...DEFAULT_SETTINGS, ...JSON.parse(stored) };
            } catch (e) {
                console.error('Failed to parse settings', e);
            }
        }
        return DEFAULT_SETTINGS;
    });

    const updateSettings = (newSettings: Partial<Settings>) => {
        setSettings(prev => {
            const updated = { ...prev, ...newSettings };
            localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));
            return updated;
        });
    };

    useEffect(() => {
        // Apply theme to the root element for Bootstrap
        document.documentElement.setAttribute('data-bs-theme', settings.theme);

        // Apply theme for Tailwind
        if (settings.theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        
        // Optional: toggle class on body if needed for custom styles
        if (settings.theme === 'dark') {
            document.body.classList.add('theme-dark');
        } else {
            document.body.classList.remove('theme-dark');
        }
    }, [settings.theme]);

    return (
        <SettingsContext.Provider value={{ ...settings, updateSettings }}>
            {children}
        </SettingsContext.Provider>
    );
};

export const useSettings = () => {
    const context = useContext(SettingsContext);
    if (context === undefined) {
        throw new Error('useSettings must be used within a SettingsProvider');
    }
    return context;
};
