import React from 'react';
import { useTranslation } from 'react-i18next';
import { useSettings } from '../context/SettingsContext';
import { Moon, Sun } from 'lucide-react';

const SettingsPage: React.FC = () => {
    const { t } = useTranslation();
    const { theme, itemsPerPage, updateSettings } = useSettings();

    const handleThemeChange = (newTheme: 'light' | 'dark') => {
        updateSettings({ theme: newTheme });
    };

    const handleItemsPerPageChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
        updateSettings({ itemsPerPage: parseInt(event.target.value, 10) });
    };

    return (
        <div className="container-fluid py-4">
            <div className="card shadow-sm border-0">
                <div className="card-header bg-white py-3">
                    <h5 className="mb-0 fw-bold">{t('settings_title')}</h5>
                </div>
                <div className="card-body">
                    {/* Appearance Section */}
                    <div className="mb-5">
                        <h6 className="text-uppercase text-muted fw-bold mb-3" style={{ fontSize: '0.8rem' }}>
                            {t('appearance_section')}
                        </h6>
                        
                        <div className="row g-3">
                            <div className="col-md-6 col-lg-4">
                                <label className="form-label d-block mb-2">{t('theme_label')}</label>
                                <div className="btn-group w-100" role="group">
                                    <button
                                        type="button"
                                        className={`btn ${theme === 'light' ? 'btn-primary' : 'btn-outline-primary'}`}
                                        onClick={() => handleThemeChange('light')}
                                    >
                                        <Sun size={18} className="me-2" />
                                        {t('theme_light')}
                                    </button>
                                    <button
                                        type="button"
                                        className={`btn ${theme === 'dark' ? 'btn-primary' : 'btn-outline-primary'}`}
                                        onClick={() => handleThemeChange('dark')}
                                    >
                                        <Moon size={18} className="me-2" />
                                        {t('theme_dark')}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr className="my-4 text-muted opacity-25" />

                    {/* Search Preferences Section */}
                    <div className="mb-3">
                        <h6 className="text-uppercase text-muted fw-bold mb-3" style={{ fontSize: '0.8rem' }}>
                            {t('search_section')}
                        </h6>
                        
                        <div className="row g-3">
                            <div className="col-md-6 col-lg-4">
                                <label htmlFor="itemsPerPage" className="form-label">
                                    {t('items_per_page')}
                                </label>
                                <select
                                    id="itemsPerPage"
                                    className="form-select"
                                    value={itemsPerPage}
                                    onChange={handleItemsPerPageChange}
                                >
                                    <option value={3}>3</option>
                                    <option value={6}>6</option>
                                    <option value={9}>9</option>
                                    <option value={12}>12</option>
                                    <option value={24}>24</option>
                                    <option value={48}>48</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default SettingsPage;